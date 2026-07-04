<?php

namespace App\Http\Controllers\Api;

/**
 * @OA\Info(
 *     title="UnfallAtlas DE — German Road Accident API",
 *     version="1.0.0",
 *     description="REST API integrating official German road accident data (Unfallatlas), regional statistics (GENESIS), and region hierarchy (GV-ISys). All data is sourced from Statistisches Bundesamt / Statistische Landesämter under licence dl-de/by-2-0.",
 *     @OA\Contact(email="student@example.com"),
 *     @OA\License(name="dl-de/by-2-0", url="https://www.govdata.de/dl-de/by-2-0")
 * )
 *
 * @OA\Server(url="http://localhost:8002", description="Local development")
 *
 * @OA\Tag(name="Regions", description="State, district, and municipality lookup")
 * @OA\Tag(name="Accidents", description="Individual accident records with full filters")
 * @OA\Tag(name="Aggregates", description="Counts, rates, rankings, time series")
 * @OA\Tag(name="Metadata", description="Data sources, licences, import history")
 * @OA\Tag(name="Statistics", description="Overall platform summary")
 *
 * @OA\Schema(
 *     schema="Region",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="ags", type="string", example="14612"),
 *     @OA\Property(property="name", type="string", example="Dresden"),
 *     @OA\Property(property="level", type="string", enum={"state","district","municipality"}),
 *     @OA\Property(property="state_name", type="string", example="Sachsen"),
 *     @OA\Property(property="state_ags", type="string", example="14"),
 *     @OA\Property(property="district_ags", type="string", example="14612"),
 *     @OA\Property(property="population", type="integer", nullable=true),
 * )
 *
 * @OA\Schema(
 *     schema="Accident",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="year", type="integer", example=2023),
 *     @OA\Property(property="month", type="integer", example=6),
 *     @OA\Property(property="hour", type="integer", nullable=true),
 *     @OA\Property(property="weekday", type="integer", nullable=true, description="1=Mon, 7=Sun"),
 *     @OA\Property(property="category", type="integer", enum={1,2,3}, description="1=Fatal, 2=Serious, 3=Slight"),
 *     @OA\Property(property="involves_bicycle", type="boolean"),
 *     @OA\Property(property="involves_pedestrian", type="boolean"),
 *     @OA\Property(property="involves_car", type="boolean"),
 *     @OA\Property(property="involves_motorcycle", type="boolean"),
 *     @OA\Property(property="involves_truck", type="boolean"),
 *     @OA\Property(property="lon", type="number", format="float", nullable=true),
 *     @OA\Property(property="lat", type="number", format="float", nullable=true),
 *     @OA\Property(property="district_ags", type="string", nullable=true),
 *     @OA\Property(property="source_system", type="string", example="unfallatlas"),
 * )
 *
 * @OA\Schema(
 *     schema="DataSource",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="key", type="string", example="unfallatlas"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="url", type="string"),
 *     @OA\Property(property="license", type="string", example="dl-de/by-2-0"),
 *     @OA\Property(property="license_url", type="string"),
 *     @OA\Property(property="publisher", type="string"),
 *     @OA\Property(property="description", type="string"),
 * )
 *
 * @OA\Schema(
 *     schema="ImportRun",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="status", type="string", enum={"started","completed","failed"}),
 *     @OA\Property(property="records_inserted", type="integer"),
 *     @OA\Property(property="records_updated", type="integer"),
 *     @OA\Property(property="records_skipped", type="integer"),
 *     @OA\Property(property="retrieved_at", type="string", format="date-time"),
 *     @OA\Property(property="source_snapshot", type="string", nullable=true),
 * )
 *
 * @OA\Schema(
 *     schema="AggregateAccident",
 *     @OA\Property(property="ags", type="string"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="level", type="string"),
 *     @OA\Property(property="year", type="integer"),
 *     @OA\Property(property="accident_count", type="integer"),
 *     @OA\Property(property="fatal_count", type="integer"),
 *     @OA\Property(property="serious_count", type="integer"),
 *     @OA\Property(property="slight_count", type="integer"),
 *     @OA\Property(property="bicycle_count", type="integer"),
 *     @OA\Property(property="pedestrian_count", type="integer"),
 * )
 *
 * @OA\Schema(
 *     schema="RateResult",
 *     @OA\Property(property="ags", type="string"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="population", type="integer", nullable=true),
 *     @OA\Property(property="accident_count", type="integer"),
 *     @OA\Property(property="rate_per_100k", type="number", format="float", nullable=true),
 * )
 *
 * @OA\Schema(
 *     schema="ApiEnvelope",
 *     @OA\Property(property="data", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="meta", type="object",
 *         @OA\Property(property="total", type="integer"),
 *         @OA\Property(property="page", type="integer"),
 *     ),
 *     @OA\Property(property="source", type="string", example="Statistisches Bundesamt"),
 *     @OA\Property(property="license", type="string", example="dl-de/by-2-0"),
 * )
 */
class SwaggerInfo {}
