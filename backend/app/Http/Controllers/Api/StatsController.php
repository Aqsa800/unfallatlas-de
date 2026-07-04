<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Stats",
 *     description="Overall dataset summary statistics"
 * )
 */
class StatsController extends Controller
{
    private const SOURCE  = 'Statistisches Bundesamt';
    private const LICENSE = 'dl-de/by-2-0';

    // =========================================================================
    // GET /api/v1/stats/summary
    // =========================================================================

    /**
     * @OA\Get(
     *     path="/api/v1/stats/summary",
     *     operationId="statsSummary",
     *     tags={"Stats"},
     *     summary="Overall dataset summary statistics",
     *     description="Returns aggregate counts and date ranges covering the entire dataset.",
     *     @OA\Response(
     *         response=200,
     *         description="Summary statistics",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_accidents", type="integer"),
     *                 @OA\Property(property="years_covered",   type="integer"),
     *                 @OA\Property(property="earliest_year",   type="integer"),
     *                 @OA\Property(property="latest_year",     type="integer"),
     *                 @OA\Property(property="total_regions",   type="integer"),
     *                 @OA\Property(property="states_count",    type="integer"),
     *                 @OA\Property(property="districts_count", type="integer")
     *             ),
     *             @OA\Property(property="source",  type="string"),
     *             @OA\Property(property="license", type="string")
     *         )
     *     )
     * )
     */
    public function summary(): JsonResponse
    {
        // Accident stats — single pass
        $accidentStats = DB::table('accidents')
            ->selectRaw('COUNT(*) AS total_accidents, MIN(year) AS earliest_year, MAX(year) AS latest_year')
            ->first();

        $totalAccidents = (int) ($accidentStats->total_accidents ?? 0);
        $earliestYear   = $accidentStats->earliest_year ? (int) $accidentStats->earliest_year : null;
        $latestYear     = $accidentStats->latest_year   ? (int) $accidentStats->latest_year   : null;
        $yearsCovered   = ($earliestYear !== null && $latestYear !== null)
            ? ($latestYear - $earliestYear + 1)
            : 0;

        // Region stats
        $regionStats = DB::table('regions')
            ->selectRaw("
                COUNT(*) AS total_regions,
                SUM(CASE WHEN level = 'state'    THEN 1 ELSE 0 END) AS states_count,
                SUM(CASE WHEN level = 'district' THEN 1 ELSE 0 END) AS districts_count
            ")
            ->first();

        $totalRegions    = (int) ($regionStats->total_regions    ?? 0);
        $statesCount     = (int) ($regionStats->states_count     ?? 0);
        $districtsCount  = (int) ($regionStats->districts_count  ?? 0);

        return response()->json([
            'data' => [
                'total_accidents' => $totalAccidents,
                'years_covered'   => $yearsCovered,
                'earliest_year'   => $earliestYear,
                'latest_year'     => $latestYear,
                'total_regions'   => $totalRegions,
                'states_count'    => $statesCount,
                'districts_count' => $districtsCount,
            ],
            'source'  => self::SOURCE,
            'license' => self::LICENSE,
        ]);
    }
}
