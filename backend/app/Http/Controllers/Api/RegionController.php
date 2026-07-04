<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegionResource;
use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Regions",
 *     description="German administrative regions (states, districts, municipalities)"
 * )
 */
class RegionController extends Controller
{
    private const SOURCE   = 'Statistisches Bundesamt';
    private const LICENSE  = 'dl-de/by-2-0';
    private const PER_PAGE = 50;

    // -------------------------------------------------------------------------
    // GET /api/v1/regions
    // -------------------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/v1/regions",
     *     operationId="listRegions",
     *     tags={"Regions"},
     *     summary="List administrative regions",
     *     description="Returns a paginated list of regions, optionally filtered by level, state, district or name search.",
     *     @OA\Parameter(
     *         name="level",
     *         in="query",
     *         description="Filter by administrative level: state | district | municipality",
     *         required=false,
     *         @OA\Schema(type="string", enum={"state","district","municipality"})
     *     ),
     *     @OA\Parameter(
     *         name="state_ags",
     *         in="query",
     *         description="2-digit state AGS code",
     *         required=false,
     *         @OA\Schema(type="string", example="09")
     *     ),
     *     @OA\Parameter(
     *         name="district_ags",
     *         in="query",
     *         description="5-digit district AGS code",
     *         required=false,
     *         @OA\Schema(type="string", example="09162")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Case-insensitive name search",
     *         required=false,
     *         @OA\Schema(type="string", example="München")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of regions",
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
        $query = Region::query();

        // --- level filter ---
        $level = $request->query('level');
        if ($level && in_array($level, ['state', 'district', 'municipality'], true)) {
            $query->where('level', $level);
        }

        // --- state_ags filter ---
        $stateAgs = $request->query('state_ags');
        if ($stateAgs) {
            $query->where('state_ags', $stateAgs);
        }

        // --- district_ags filter ---
        $districtAgs = $request->query('district_ags');
        if ($districtAgs) {
            $query->where('district_ags', $districtAgs);
        }

        // --- name search (case-insensitive) ---
        $search = $request->query('search');
        if ($search) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($search) . '%']);
        }

        $query->orderBy('ags');

        $paginated = $query->paginate(self::PER_PAGE);

        return response()->json([
            'data'    => RegionResource::collection($paginated->items()),
            'meta'    => [
                'total'        => $paginated->total(),
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

    // -------------------------------------------------------------------------
    // GET /api/v1/regions/{id}
    // -------------------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/v1/regions/{id}",
     *     operationId="showRegion",
     *     tags={"Regions"},
     *     summary="Get a single region with children count and indicators",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Region detail"),
     *     @OA\Response(response=404, description="Region not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $region = Region::withCount('children')
            ->with(['indicatorValues.indicator'])
            ->find($id);

        if (! $region) {
            return response()->json([
                'error' => 'Region not found.',
                'code'  => 404,
            ], 404);
        }

        return response()->json([
            'data'    => new RegionResource($region),
            'source'  => self::SOURCE,
            'license' => self::LICENSE,
        ]);
    }
}
