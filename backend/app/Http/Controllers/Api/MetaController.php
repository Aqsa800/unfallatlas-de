<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataSource;
use App\Models\ImportRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Metadata",
 *     description="Data sources and import run history"
 * )
 */
class MetaController extends Controller
{
    private const SOURCE  = 'Statistisches Bundesamt';
    private const LICENSE = 'dl-de/by-2-0';

    // =========================================================================
    // GET /api/v1/metadata/sources
    // =========================================================================

    /**
     * @OA\Get(
     *     path="/api/v1/metadata/sources",
     *     operationId="metadataSources",
     *     tags={"Metadata"},
     *     summary="List all registered data sources with license info",
     *     @OA\Response(response=200, description="Data source list")
     * )
     */
    public function sources(): JsonResponse
    {
        $sources = DataSource::with(['importRuns' => function ($q) {
            $q->orderBy('created_at', 'desc')->limit(1);
        }])->get();

        $data = $sources->map(function ($source) {
            $latestRun = $source->importRuns->first();

            return [
                'id'           => $source->id,
                'key'          => $source->key,
                'name'         => $source->name,
                'url'          => $source->url,
                'license'      => $source->license,
                'license_url'  => $source->license_url,
                'publisher'    => $source->publisher,
                'description'  => $source->description,
                'retrieved_at' => $latestRun?->retrieved_at?->toIso8601String(),
                'latest_run'   => $latestRun ? [
                    'id'               => $latestRun->id,
                    'status'           => $latestRun->status,
                    'records_inserted' => $latestRun->records_inserted,
                    'records_updated'  => $latestRun->records_updated,
                    'records_skipped'  => $latestRun->records_skipped,
                    'retrieved_at'     => $latestRun->retrieved_at?->toIso8601String(),
                    'created_at'       => $latestRun->created_at?->toIso8601String(),
                ] : null,
            ];
        })->values()->toArray();

        return response()->json([
            'data'    => $data,
            'meta'    => ['total' => count($data)],
            'source'  => self::SOURCE,
            'license' => self::LICENSE,
        ]);
    }

    // =========================================================================
    // GET /api/v1/import-runs
    // =========================================================================

    /**
     * @OA\Get(
     *     path="/api/v1/import-runs",
     *     operationId="importRuns",
     *     tags={"Metadata"},
     *     summary="List import run history",
     *     @OA\Parameter(name="source_key", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="status",     in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="limit",      in="query", required=false, @OA\Schema(type="integer", default=20)),
     *     @OA\Response(response=200, description="Import run list")
     * )
     */
    public function importRuns(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->query('limit', 20), 200));

        $query = ImportRun::with('dataSource');

        if ($sourceKey = $request->query('source_key')) {
            $query->whereHas('dataSource', fn ($q) => $q->where('key', $sourceKey));
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $runs = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $data = $runs->map(fn ($run) => [
            'id'               => $run->id,
            'status'           => $run->status,
            'records_inserted' => $run->records_inserted,
            'records_updated'  => $run->records_updated,
            'records_skipped'  => $run->records_skipped,
            'source_url'       => $run->source_url,
            'retrieved_at'     => $run->retrieved_at?->toIso8601String(),
            'notes'            => $run->notes,
            'created_at'       => $run->created_at?->toIso8601String(),
            'updated_at'       => $run->updated_at?->toIso8601String(),
            'data_source'      => $run->dataSource ? [
                'id'        => $run->dataSource->id,
                'key'       => $run->dataSource->key,
                'name'      => $run->dataSource->name,
                'license'   => $run->dataSource->license,
                'publisher' => $run->dataSource->publisher,
            ] : null,
        ])->values()->toArray();

        return response()->json([
            'data'    => $data,
            'meta'    => ['total' => count($data), 'limit' => $limit],
            'source'  => self::SOURCE,
            'license' => self::LICENSE,
        ]);
    }
}
