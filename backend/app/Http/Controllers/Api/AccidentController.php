<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccidentResource;
use App\Models\Accident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Accidents",
 *     description="Individual road accident records"
 * )
 */
class AccidentController extends Controller
{
    private const SOURCE          = 'Statistisches Bundesamt';
    private const LICENSE         = 'dl-de/by-2-0';
    private const DEFAULT_PER_PAGE = 100;
    private const MAX_PER_PAGE     = 500;

    // -------------------------------------------------------------------------
    // GET /api/v1/accidents
    // -------------------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/v1/accidents",
     *     operationId="listAccidents",
     *     tags={"Accidents"},
     *     summary="List road accidents with filters",
     *     description="Returns a paginated list of individual accident records.",
     *     @OA\Parameter(name="state_ags",             in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="district_ags",           in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="municipality_ags",       in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="year",                   in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="year_from",              in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="year_to",                in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="month",                  in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=12)),
     *     @OA\Parameter(name="weekday",                in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=7)),
     *     @OA\Parameter(name="hour",                   in="query", required=false, @OA\Schema(type="integer", minimum=0, maximum=23)),
     *     @OA\Parameter(name="category",               in="query", required=false, @OA\Schema(type="integer", enum={1,2,3})),
     *     @OA\Parameter(name="involves_bicycle",       in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="involves_pedestrian",    in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="involves_motorcycle",    in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="involves_car",           in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="involves_truck",         in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="light_condition",        in="query", required=false, @OA\Schema(type="integer", enum={0,1,2}, description="0=daylight, 1=dusk/dawn, 2=night")),
     *     @OA\Parameter(name="road_condition",         in="query", required=false, @OA\Schema(type="integer", enum={0,1,2}, description="0=dry, 1=wet, 2=icy — available 2022+")),
     *     @OA\Parameter(name="crash_type",             in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=7, description="UTYP1: 1=single-vehicle, 2=turning, 3=crossing, 4=pedestrian, 5=parking, 6=in-lane, 7=other")),
     *     @OA\Parameter(name="per_page",               in="query", required=false, @OA\Schema(type="integer", default=100, maximum=500)),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated accidents",
     *         @OA\JsonContent(
     *             @OA\Property(property="data",    type="array", @OA\Items(type="object")),
     *             @OA\Property(property="meta",    type="object"),
     *             @OA\Property(property="source",  type="string"),
     *             @OA\Property(property="license", type="string")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Accident::query();

        // --- geographic filters ---
        if ($v = $request->query('state_ags')) {
            // state_ags is stored on the related region; filter via district_ags prefix
            // The first 2 chars of district_ags equal state_ags
            $query->whereRaw("district_ags LIKE ?", [$v . '%']);
        }

        if ($v = $request->query('district_ags')) {
            if (str_ends_with($v, '000') && strlen($v) === 5) {
                $query->whereRaw("district_ags LIKE ?", [substr($v, 0, 2) . '%']);
            } else {
                $query->where('district_ags', $v);
            }
        }

        if ($v = $request->query('municipality_ags')) {
            $query->where('municipality_ags', $v);
        }

        // --- time filters ---
        if ($v = $request->query('year')) {
            $query->where('year', (int) $v);
        }

        if ($v = $request->query('year_from')) {
            $query->where('year', '>=', (int) $v);
        }

        if ($v = $request->query('year_to')) {
            $query->where('year', '<=', (int) $v);
        }

        if ($v = $request->query('month')) {
            $query->where('month', (int) $v);
        }

        if ($v = $request->query('weekday')) {
            $query->where('weekday', (int) $v);
        }

        if ($v = $request->query('hour')) {
            $query->where('hour', (int) $v);
        }

        // --- accident characteristics ---
        if ($v = $request->query('category')) {
            $query->where('category', (int) $v);
        }

        if (($v = $request->query('light_condition')) !== null && $v !== '') {
            $query->where('light_condition', (int) $v);
        }

        if (($v = $request->query('road_condition')) !== null && $v !== '') {
            $query->where('year', '>=', 2022);
            $query->where('road_condition', (int) $v);
        }

        if ($v = $request->query('crash_type')) {
            $query->where('crash_type', (int) $v);
        }

        foreach (['involves_bicycle', 'involves_pedestrian', 'involves_motorcycle', 'involves_car', 'involves_truck'] as $flag) {
            if ($request->has($flag)) {
                $val = filter_var($request->query($flag), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($val !== null) {
                    $query->where($flag, $val);
                }
            }
        }

        // --- pagination ---
        $perPage = min(
            (int) ($request->query('per_page', self::DEFAULT_PER_PAGE)),
            self::MAX_PER_PAGE
        );
        if ($perPage < 1) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        $paginated = $query->orderBy('year')->orderBy('id')->paginate($perPage);

        return response()->json([
            'data'    => AccidentResource::collection($paginated->items()),
            'meta'    => [
                'total'        => $paginated->total(),
                'total_count'  => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'from'         => $paginated->firstItem(),
                'to'           => $paginated->lastItem(),
            ],
            'source'  => self::SOURCE,
            'license' => self::LICENSE,
        ]);
    }
}
