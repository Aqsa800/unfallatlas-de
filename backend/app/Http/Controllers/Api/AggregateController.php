<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Aggregates",
 *     description="Pre-computed and on-the-fly aggregate statistics"
 * )
 */
class AggregateController extends Controller
{
    private const SOURCE  = 'Statistisches Bundesamt';
    private const LICENSE = 'dl-de/by-2-0';

    // =========================================================================
    // Shared helpers
    // =========================================================================

    /**
     * Parse a boolean query param (accepts "1", "true", "yes", "on").
     */
    private function boolParam(Request $request, string $key): ?bool
    {
        if (! $request->has($key)) {
            return null;
        }
        return filter_var($request->query($key), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Apply standard accident filters to a DB query builder.
     *
     * @param \Illuminate\Database\Query\Builder $q
     */
    private function applyAccidentFilters($q, Request $request): void
    {
        if ($v = $request->query('state_ags')) {
            $q->whereRaw("district_ags LIKE ?", [$v . '%']);
        }
        if ($v = $request->query('district_ags')) {
            // City-state special case: Berlin (11000) and Hamburg (02000) store accidents
            // under sub-district codes (11001-11012, 02101-02717). Match by state prefix.
            if (str_ends_with($v, '000') && strlen($v) === 5) {
                $q->whereRaw("district_ags LIKE ?", [substr($v, 0, 2) . '%']);
            } else {
                $q->where('district_ags', $v);
            }
        }
        if ($v = $request->query('year')) {
            $q->where('year', (int) $v);
        }
        if ($v = $request->query('year_from')) {
            $q->where('year', '>=', (int) $v);
        }
        if ($v = $request->query('year_to')) {
            $q->where('year', '<=', (int) $v);
        }
        if ($v = $request->query('category')) {
            $q->where('category', (int) $v);
        }
        foreach (['involves_bicycle', 'involves_pedestrian', 'involves_motorcycle', 'involves_car', 'involves_truck'] as $flag) {
            $val = $this->boolParam($request, $flag);
            if ($val !== null) {
                $q->where($flag, $val);
            }
        }
    }

    /**
     * Build a consistent envelope.
     */
    private function envelope(array $data, array $meta = []): array
    {
        return [
            'data'    => $data,
            'meta'    => array_merge(['total' => count($data)], $meta),
            'source'  => self::SOURCE,
            'license' => self::LICENSE,
        ];
    }

    // =========================================================================
    // GET /api/v1/aggregates/accidents
    // =========================================================================

    /**
     * @OA\Get(
     *     path="/api/v1/aggregates/accidents",
     *     operationId="aggregateAccidents",
     *     tags={"Aggregates"},
     *     summary="Accident counts grouped by region and year",
     *     @OA\Parameter(name="level",                in="query", required=false, @OA\Schema(type="string", enum={"state","district","municipality"})),
     *     @OA\Parameter(name="state_ags",            in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="district_ags",         in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="year",                 in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="year_from",            in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="year_to",              in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="category",             in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="involves_bicycle",     in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="involves_pedestrian",  in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="involves_motorcycle",  in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="Aggregated accident counts per region/year")
     * )
     */
    public function accidents(Request $request): JsonResponse
    {
        $level = $request->query('level', 'district');
        $districtAgs = $request->query('district_ags');
        $isCityState = $districtAgs && str_ends_with($districtAgs, '000') && strlen($districtAgs) === 5;

        if ($isCityState) {
            // City-state special case (Berlin 11000, Hamburg 02000):
            // Accidents stored under sub-district codes — group by year only.
            $statePrefix = substr($districtAgs, 0, 2) . '%';
            $regionName  = DB::table('regions')->where('ags', $districtAgs)->value('name') ?? $districtAgs;

            $q = DB::table('accidents')
                ->select([
                    DB::raw("? AS ags"),
                    DB::raw('year'),
                    DB::raw('COUNT(*) AS accident_count'),
                    DB::raw('SUM(CASE WHEN category = 1 THEN 1 ELSE 0 END) AS fatal_count'),
                    DB::raw('SUM(CASE WHEN category = 2 THEN 1 ELSE 0 END) AS serious_count'),
                    DB::raw('SUM(CASE WHEN category = 3 THEN 1 ELSE 0 END) AS slight_count'),
                    DB::raw('SUM(CASE WHEN involves_bicycle    = TRUE THEN 1 ELSE 0 END) AS bicycle_count'),
                    DB::raw('SUM(CASE WHEN involves_pedestrian = TRUE THEN 1 ELSE 0 END) AS pedestrian_count'),
                ])
                ->addBinding($districtAgs, 'select')
                ->whereRaw("district_ags LIKE ?", [$statePrefix]);

            $this->applyAccidentFilters($q, $request);

            $rows = $q->groupBy('year')->orderBy('year')->get();

            $data = $rows->map(function ($row) use ($level, $districtAgs, $regionName) {
                return [
                    'ags'              => $districtAgs,
                    'name'             => $regionName,
                    'level'            => $level,
                    'year'             => (int) $row->year,
                    'accident_count'   => (int) $row->accident_count,
                    'fatal_count'      => (int) $row->fatal_count,
                    'serious_count'    => (int) $row->serious_count,
                    'slight_count'     => (int) $row->slight_count,
                    'bicycle_count'    => (int) $row->bicycle_count,
                    'pedestrian_count' => (int) $row->pedestrian_count,
                ];
            })->values()->toArray();

            return response()->json($this->envelope($data));
        }

        // Determine the AGS column that groups records
        $agsColumn = match ($level) {
            'state'        => DB::raw('SUBSTR(district_ags, 1, 2) AS ags'),
            'municipality' => DB::raw('municipality_ags AS ags'),
            default        => DB::raw('district_ags AS ags'),
        };

        $q = DB::table('accidents')
            ->select([
                $agsColumn,
                DB::raw('year'),
                DB::raw('COUNT(*) AS accident_count'),
                DB::raw('SUM(CASE WHEN category = 1 THEN 1 ELSE 0 END) AS fatal_count'),
                DB::raw('SUM(CASE WHEN category = 2 THEN 1 ELSE 0 END) AS serious_count'),
                DB::raw('SUM(CASE WHEN category = 3 THEN 1 ELSE 0 END) AS slight_count'),
                DB::raw('SUM(CASE WHEN involves_bicycle    = TRUE THEN 1 ELSE 0 END) AS bicycle_count'),
                DB::raw('SUM(CASE WHEN involves_pedestrian = TRUE THEN 1 ELSE 0 END) AS pedestrian_count'),
            ]);

        $this->applyAccidentFilters($q, $request);

        // Derive the groupBy column name (without alias)
        $groupByRaw = match ($level) {
            'state'        => 'SUBSTR(district_ags, 1, 2)',
            'municipality' => 'municipality_ags',
            default        => 'district_ags',
        };

        $rows = $q->groupByRaw("{$groupByRaw}, year")
            ->orderByRaw("{$groupByRaw}, year")
            ->get();

        // Enrich with region names via a separate lookup
        $agsValues = $rows->pluck('ags')->unique()->filter()->values()->toArray();

        $nameMap = [];
        if (! empty($agsValues)) {
            $regions = DB::table('regions')
                ->whereIn('ags', $agsValues)
                ->pluck('name', 'ags');
            $nameMap = $regions->toArray();
        }

        $data = $rows->map(function ($row) use ($nameMap, $level) {
            return [
                'ags'              => $row->ags,
                'name'             => $nameMap[$row->ags] ?? null,
                'level'            => $level,
                'year'             => (int) $row->year,
                'accident_count'   => (int) $row->accident_count,
                'fatal_count'      => (int) $row->fatal_count,
                'serious_count'    => (int) $row->serious_count,
                'slight_count'     => (int) $row->slight_count,
                'bicycle_count'    => (int) $row->bicycle_count,
                'pedestrian_count' => (int) $row->pedestrian_count,
            ];
        })->values()->toArray();

        return response()->json($this->envelope($data));
    }

    // =========================================================================
    // GET /api/v1/aggregates/time-series
    // =========================================================================

    /**
     * @OA\Get(
     *     path="/api/v1/aggregates/time-series",
     *     operationId="timeSeries",
     *     tags={"Aggregates"},
     *     summary="Accident counts over time grouped by year, month, weekday, or hour",
     *     @OA\Parameter(name="state_ags",    in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="district_ags", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="group_by",     in="query", required=false, @OA\Schema(type="string", enum={"year","month","weekday","hour"}, default="year")),
     *     @OA\Response(response=200, description="Time-series data")
     * )
     */
    public function timeSeries(Request $request): JsonResponse
    {
        $groupBy = $request->query('group_by', 'year');
        if (! in_array($groupBy, ['year', 'month', 'weekday', 'hour'], true)) {
            return response()->json([
                'error' => 'group_by must be one of: year, month, weekday, hour',
                'code'  => 422,
            ], 422);
        }

        $q = DB::table('accidents')
            ->select([
                DB::raw("{$groupBy} AS period"),
                DB::raw('COUNT(*) AS accident_count'),
                DB::raw('SUM(CASE WHEN involves_bicycle    = TRUE THEN 1 ELSE 0 END) AS bicycle_count'),
                DB::raw('SUM(CASE WHEN involves_pedestrian = TRUE THEN 1 ELSE 0 END) AS pedestrian_count'),
            ]);

        if ($v = $request->query('state_ags')) {
            $q->whereRaw("district_ags LIKE ?", [$v . '%']);
        }
        if ($v = $request->query('district_ags')) {
            if (str_ends_with($v, '000') && strlen($v) === 5) {
                $q->whereRaw("district_ags LIKE ?", [substr($v, 0, 2) . '%']);
            } else {
                $q->where('district_ags', $v);
            }
        }

        $rows = $q->groupByRaw($groupBy)
            ->orderByRaw($groupBy)
            ->get();

        $data = $rows->map(fn ($row) => [
            'period'           => $row->period !== null ? (int) $row->period : null,
            'accident_count'   => (int) $row->accident_count,
            'bicycle_count'    => (int) $row->bicycle_count,
            'pedestrian_count' => (int) $row->pedestrian_count,
        ])->values()->toArray();

        return response()->json($this->envelope($data, ['group_by' => $groupBy]));
    }

    // =========================================================================
    // GET /api/v1/aggregates/rates
    // =========================================================================

    /**
     * @OA\Get(
     *     path="/api/v1/aggregates/rates",
     *     operationId="rates",
     *     tags={"Aggregates"},
     *     summary="Accident rates per 100,000 population or registered cars",
     *     @OA\Parameter(name="level", in="query", required=false, @OA\Schema(type="string", enum={"state","district"}, default="district")),
     *     @OA\Parameter(name="year",  in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per",   in="query", required=false, @OA\Schema(type="string", enum={"population","registered_cars"}, default="population"), description="Denominator: population (default) or registered_cars"),
     *     @OA\Response(response=200, description="Accident rates per region sorted by rate descending")
     * )
     */
    public function rates(Request $request): JsonResponse
    {
        $level = $request->query('level', 'district');
        if (! in_array($level, ['state', 'district'], true)) {
            return response()->json([
                'error' => 'level must be one of: state, district',
                'code'  => 422,
            ], 422);
        }

        $accidentAgsExpr = $level === 'state'
            ? 'SUBSTR(a.district_ags, 1, 2)'
            : 'a.district_ags';

        $regionAgsField = 'r.ags';

        $yearFilter   = '';
        $regionFilter = '';

        $year = $request->query('year');
        if ($year) {
            $yearFilter = 'AND a.year = ?';
        }

        // Region filter must be set BEFORE SQL string interpolation
        $regionBinding = null;
        if ($stateAgs = $request->query('state_ags')) {
            $regionFilter  = 'AND r.state_ags = ?';
            $regionBinding = $stateAgs;
        } elseif ($districtAgs = $request->query('district_ags')) {
            $regionFilter  = 'AND r.ags = ?';
            $regionBinding = $districtAgs;
        }

        // Choose denominator: population (default) or registered_cars
        $perIndicator = $request->query('per', 'population');
        if (! in_array($perIndicator, ['population', 'registered_cars'], true)) {
            $perIndicator = 'population';
        }
        $perLabel = $perIndicator;

        // Join accidents with regions and indicator_values
        $sql = "
            SELECT
                r.ags,
                r.name,
                r.level,
                COALESCE(pop.value, r.population) AS population,
                COUNT(a.id) AS accident_count,
                CASE
                    WHEN COALESCE(pop.value, r.population) > 0
                    THEN ROUND(COUNT(a.id) * 100000.0 / COALESCE(pop.value, r.population), 2)
                    ELSE NULL
                END AS rate_per_100k
            FROM regions r
            LEFT JOIN (
                SELECT iv.region_id, iv.year, iv.value
                FROM indicator_values iv
                JOIN indicators i ON i.id = iv.indicator_id AND i.code = ?
            ) pop ON pop.region_id = r.id
                  AND pop.year = COALESCE(NULLIF(?, 0), EXTRACT(YEAR FROM CURRENT_DATE)::int)
            LEFT JOIN accidents a
                ON {$accidentAgsExpr} = r.ags
                {$yearFilter}
            WHERE r.level = ?
            {$regionFilter}
            GROUP BY r.ags, r.name, r.level, pop.value, r.population
            ORDER BY rate_per_100k DESC NULLS LAST
        ";

        // Bindings must match SQL ? order:
        // 1. i.code (subquery indicator)
        // 2. pop.year (COALESCE NULLIF)
        // 3. a.year (yearFilter, only if year set)
        // 4. r.level
        // 5. regionFilter (if set)
        $bindings = [];
        $bindings[] = $perIndicator;                // 1. indicator code
        $bindings[] = $year ? (int) $year : 0;      // 2. pop.year (0 = current year)
        if ($year) $bindings[] = (int) $year;       // 3. accident year filter
        $bindings[] = $level;                       // 4. region level
        if ($regionBinding !== null) {
            $bindings[] = $regionBinding;           // 5. state/district filter
        }

        $rows = DB::select($sql, $bindings);

        $data = array_map(fn ($row) => [
            'ags'            => $row->ags,
            'name'           => $row->name,
            'level'          => $row->level,
            'denominator'    => $perLabel,
            'denominator_value' => $row->population !== null ? (int) $row->population : null,
            'accident_count' => (int) $row->accident_count,
            'rate_per_100k'  => $row->rate_per_100k !== null ? (float) $row->rate_per_100k : null,
        ], $rows);

        return response()->json($this->envelope($data));
    }

    // =========================================================================
    // GET /api/v1/aggregates/rankings
    // =========================================================================

    /**
     * @OA\Get(
     *     path="/api/v1/aggregates/rankings",
     *     operationId="rankings",
     *     tags={"Aggregates"},
     *     summary="Top/bottom districts by a chosen metric",
     *     @OA\Parameter(name="year",   in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="metric", in="query", required=false, @OA\Schema(type="string", enum={"fatal","bicycle","pedestrian","total"}, default="total")),
     *     @OA\Parameter(name="limit",  in="query", required=false, @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="order",  in="query", required=false, @OA\Schema(type="string", enum={"desc","asc"}, default="desc")),
     *     @OA\Response(response=200, description="Ranked districts")
     * )
     */
    public function rankings(Request $request): JsonResponse
    {
        $metric  = $request->query('metric', 'total');
        $limit   = max(1, min((int) $request->query('limit', 10), 100));
        $order   = strtolower($request->query('order', 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $metricExpr = match ($metric) {
            'fatal'       => "SUM(CASE WHEN category = 1 THEN 1 ELSE 0 END)",
            'bicycle'     => "SUM(CASE WHEN involves_bicycle = TRUE THEN 1 ELSE 0 END)",
            'pedestrian'  => "SUM(CASE WHEN involves_pedestrian = TRUE THEN 1 ELSE 0 END)",
            default       => "COUNT(*)",
        };

        $q = DB::table('accidents AS a')
            ->join('regions AS r', 'r.ags', '=', 'a.district_ags')
            ->select([
                'a.district_ags AS ags',
                'r.name',
                DB::raw("COUNT(*) AS total_count"),
                DB::raw("SUM(CASE WHEN a.category = 1 THEN 1 ELSE 0 END) AS fatal_count"),
                DB::raw("SUM(CASE WHEN a.involves_bicycle = TRUE THEN 1 ELSE 0 END) AS bicycle_count"),
                DB::raw("SUM(CASE WHEN a.involves_pedestrian = TRUE THEN 1 ELSE 0 END) AS pedestrian_count"),
                DB::raw("{$metricExpr} AS metric_value"),
            ])
            ->where('r.level', 'district');

        if ($year = $request->query('year')) {
            $q->where('a.year', (int) $year);
        }

        $rows = $q->groupBy('a.district_ags', 'r.name')
            ->orderByRaw("metric_value {$order}")
            ->limit($limit)
            ->get();

        $data = $rows->map(fn ($row) => [
            'ags'              => $row->ags,
            'name'             => $row->name,
            'total_count'      => (int) $row->total_count,
            'fatal_count'      => (int) $row->fatal_count,
            'bicycle_count'    => (int) $row->bicycle_count,
            'pedestrian_count' => (int) $row->pedestrian_count,
            'metric'           => $metric,
            'metric_value'     => (int) $row->metric_value,
        ])->values()->toArray();

        return response()->json($this->envelope($data, [
            'metric' => $metric,
            'order'  => strtolower($order),
            'limit'  => $limit,
        ]));
    }

    // =========================================================================
    // GET /api/v1/aggregates/year-over-year
    // =========================================================================

    /**
     * @OA\Get(
     *     path="/api/v1/aggregates/year-over-year",
     *     operationId="yearOverYear",
     *     tags={"Aggregates"},
     *     summary="Year-over-year accident count changes",
     *     @OA\Parameter(name="state_ags",    in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="district_ags", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Year-over-year changes")
     * )
     */
    public function yearOverYear(Request $request): JsonResponse
    {
        $q = DB::table('accidents')
            ->select([
                DB::raw('year'),
                DB::raw('COUNT(*) AS accident_count'),
            ]);

        if ($v = $request->query('state_ags')) {
            $q->whereRaw("district_ags LIKE ?", [$v . '%']);
        }
        if ($v = $request->query('district_ags')) {
            if (str_ends_with($v, '000') && strlen($v) === 5) {
                $q->whereRaw("district_ags LIKE ?", [substr($v, 0, 2) . '%']);
            } else {
                $q->where('district_ags', $v);
            }
        }

        $rows = $q->groupBy('year')
            ->orderBy('year')
            ->get()
            ->toArray();

        $data = [];
        for ($i = 0; $i < count($rows); $i++) {
            $current  = $rows[$i];
            $previous = $i > 0 ? $rows[$i - 1] : null;

            $changeAbs = $previous !== null
                ? (int) $current->accident_count - (int) $previous->accident_count
                : null;

            $changePct = ($previous !== null && (int) $previous->accident_count > 0)
                ? round($changeAbs / (int) $previous->accident_count * 100, 2)
                : null;

            $data[] = [
                'year'           => (int) $current->year,
                'accident_count' => (int) $current->accident_count,
                'change_abs'     => $changeAbs,
                'change_pct'     => $changePct,
            ];
        }

        return response()->json($this->envelope($data));
    }

    // =========================================================================
    // GET /api/v1/aggregates/earliest-year
    // =========================================================================

    /**
     * @OA\Get(
     *     path="/api/v1/aggregates/earliest-year",
     *     operationId="earliestYear",
     *     tags={"Aggregates"},
     *     summary="Earliest year available in the accidents dataset",
     *     @OA\Parameter(name="state_ags", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Earliest year")
     * )
     */
    public function earliestYear(Request $request): JsonResponse
    {
        $q = DB::table('accidents');

        $stateAgs = $request->query('state_ags');
        if ($stateAgs) {
            $q->where('district_ags', 'like', $stateAgs . '%');
        }

        $earliestYear = $q->min('year');

        $payload = [
            'earliest_year' => $earliestYear !== null ? (int) $earliestYear : null,
        ];

        if ($stateAgs) {
            $stateName = DB::table('regions')
                ->where('ags', $stateAgs)
                ->where('level', 'state')
                ->value('name');

            $payload['state_ags']  = $stateAgs;
            $payload['state_name'] = $stateName;
        }

        return response()->json([
            'data'    => $payload,
            'source'  => self::SOURCE,
            'license' => self::LICENSE,
        ]);
    }

    // =========================================================================
    // GET /api/v1/aggregates/zero-cases  (BONUS)
    // =========================================================================

    /**
     * @OA\Get(
     *     path="/api/v1/aggregates/zero-cases",
     *     operationId="zeroCases",
     *     tags={"Aggregates"},
     *     summary="Regions with zero accidents in a given year",
     *     description="Performs a LEFT JOIN of the region reference against accidents for the requested year and returns all regions that recorded no accidents.",
     *     @OA\Parameter(name="year",      in="query", required=true,  @OA\Schema(type="integer")),
     *     @OA\Parameter(name="level",     in="query", required=false, @OA\Schema(type="string", enum={"state","district","municipality"}, default="district")),
     *     @OA\Parameter(name="state_ags", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200,  description="Regions with zero accidents"),
     *     @OA\Response(response=422,  description="Validation error — year is required")
     * )
     */
    public function zeroCases(Request $request): JsonResponse
    {
        $year = $request->query('year');
        if (! $year) {
            return response()->json([
                'error' => 'The year parameter is required.',
                'code'  => 422,
            ], 422);
        }

        $level = $request->query('level', 'district');
        if (! in_array($level, ['state', 'district', 'municipality'], true)) {
            return response()->json([
                'error' => 'level must be one of: state, district, municipality',
                'code'  => 422,
            ], 422);
        }

        // The JOIN column depends on the level we are querying
        $joinCondition = match ($level) {
            'state'        => 'r.ags = SUBSTR(a.district_ags, 1, 2)',
            'municipality' => 'r.ags = a.municipality_ags',
            default        => 'r.ags = a.district_ags',
        };

        $q = DB::table('regions AS r')
            ->select([
                'r.id',
                'r.ags',
                'r.name',
                'r.level',
                'r.state_ags',
                'r.population',
            ])
            ->leftJoin('accidents AS a', function ($join) use ($joinCondition, $year) {
                $join->whereRaw($joinCondition)
                     ->where('a.year', '=', (int) $year);
            })
            ->where('r.level', $level)
            ->whereNull('a.id');

        // Exclude city-states whose accidents are stored under sub-district codes:
        // Berlin (11000) uses 11001-11012, Hamburg (02000) uses 02xxx sub-codes.
        // These always appear as zero in the LEFT JOIN but actually have accidents.
        if ($level === 'district') {
            $cityStateAgs = ['11000', '02000'];
            foreach ($cityStateAgs as $csAgs) {
                $statePrefix = substr($csAgs, 0, 2);
                $hasAccidents = DB::table('accidents')
                    ->whereRaw("district_ags LIKE ?", [$statePrefix . '%'])
                    ->where('year', (int) $year)
                    ->exists();
                if ($hasAccidents) {
                    $q->where('r.ags', '!=', $csAgs);
                }
            }
        }

        if ($stateAgs = $request->query('state_ags')) {
            $q->where('r.state_ags', $stateAgs);
        }

        $rows = $q->orderBy('r.ags')->get();

        $data = $rows->map(fn ($row) => [
            'id'         => $row->id,
            'ags'        => $row->ags,
            'name'       => $row->name,
            'level'      => $row->level,
            'state_ags'  => $row->state_ags,
            'population' => $row->population !== null ? (int) $row->population : null,
        ])->values()->toArray();

        return response()->json($this->envelope($data, ['year' => (int) $year, 'level' => $level]));
    }
}
