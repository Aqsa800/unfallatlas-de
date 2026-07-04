<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $now = now();

        // ── Data Sources ──────────────────────────────────────────────────
        $sources = [
            [
                'key'         => 'unfallatlas',
                'name'        => 'Unfallatlas Deutschland',
                'url'         => 'https://www.opengeodata.nrw.de/produkte/transport_verkehr/unfallatlas/',
                'license'     => 'dl-de/by-2-0',
                'license_url' => 'https://www.govdata.de/dl-de/by-2-0',
                'publisher'   => 'Statistische Ämter des Bundes und der Länder',
                'description' => 'Point data for road accidents with personal injury across Germany, 2016–2024, with year/month/hour/category/participant flags.',
            ],
            [
                'key'         => 'gv_isys',
                'name'        => 'Gemeindeverzeichnis GV-ISys',
                'url'         => 'https://www.destatis.de/DE/Themen/Laender-Regionen/Regionales/Gemeindeverzeichnis/',
                'license'     => 'dl-de/by-2-0',
                'license_url' => 'https://www.govdata.de/dl-de/by-2-0',
                'publisher'   => 'Statistisches Bundesamt (Destatis)',
                'description' => 'Official AGS region key hierarchy for all German states, districts and municipalities.',
            ],
            [
                'key'         => 'genesis',
                'name'        => 'GENESIS-Online / Regionalstatistik',
                'url'         => 'https://www-genesis.destatis.de/datenbank/online',
                'license'     => 'dl-de/by-2-0',
                'license_url' => 'https://www.govdata.de/dl-de/by-2-0',
                'publisher'   => 'Statistisches Bundesamt (Destatis)',
                'description' => 'Population and registered vehicle statistics by district and year, used to compute accident rates per 100,000 inhabitants.',
            ],
            [
                'key'         => 'regionalatlas',
                'name'        => 'Regionalatlas Deutschland',
                'url'         => 'https://regionalatlas.statistikportal.de/',
                'license'     => 'dl-de/by-2-0',
                'license_url' => 'https://www.govdata.de/dl-de/by-2-0',
                'publisher'   => 'Statistische Ämter des Bundes und der Länder',
                'description' => 'Regional indicators (economic, demographic, infrastructure) via REST/JSON API.',
            ],
        ];
        $now = now();
        foreach ($sources as &$row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }
        unset($row);

        DB::table('data_sources')->upsert(
            $sources,
            ['key'],
            ['name', 'url', 'license', 'license_url', 'publisher', 'description', 'updated_at']
        );

        // ── German States (fallback seed — full import via import:gv-isys) ─
        $states = [
            ['ags' => '01', 'name' => 'Schleswig-Holstein'],
            ['ags' => '02', 'name' => 'Hamburg'],
            ['ags' => '03', 'name' => 'Niedersachsen'],
            ['ags' => '04', 'name' => 'Bremen'],
            ['ags' => '05', 'name' => 'Nordrhein-Westfalen'],
            ['ags' => '06', 'name' => 'Hessen'],
            ['ags' => '07', 'name' => 'Rheinland-Pfalz'],
            ['ags' => '08', 'name' => 'Baden-Württemberg'],
            ['ags' => '09', 'name' => 'Bayern'],
            ['ags' => '10', 'name' => 'Saarland'],
            ['ags' => '11', 'name' => 'Berlin'],
            ['ags' => '12', 'name' => 'Brandenburg'],
            ['ags' => '13', 'name' => 'Mecklenburg-Vorpommern'],
            ['ags' => '14', 'name' => 'Sachsen'],
            ['ags' => '15', 'name' => 'Sachsen-Anhalt'],
            ['ags' => '16', 'name' => 'Thüringen'],
        ];
        foreach ($states as $s) {
            DB::table('regions')->upsert([
                'ags'        => $s['ags'],
                'name'       => $s['name'],
                'level'      => 'state',
                'state_name' => $s['name'],
                'state_ags'  => $s['ags'],
                'created_at' => $now,
                'updated_at' => $now,
            ], ['ags'], ['name', 'state_name', 'updated_at']);
        }

        // ── Indicators ────────────────────────────────────────────────────
        $indicators = [
            ['code' => 'population',       'name' => 'Total Population',              'unit' => 'persons',  'source_system' => 'genesis'],
            ['code' => 'registered_cars',  'name' => 'Registered Passenger Cars',     'unit' => 'vehicles', 'source_system' => 'genesis'],
            ['code' => 'area_km2',         'name' => 'Area',                          'unit' => 'km²',      'source_system' => 'gv_isys'],
            ['code' => 'pop_density',      'name' => 'Population Density',            'unit' => 'per km²',  'source_system' => 'genesis'],
        ];
        foreach ($indicators as $ind) {
            DB::table('indicators')->upsert(
                array_merge($ind, ['created_at' => $now, 'updated_at' => $now]),
                ['code'],
                ['name', 'unit', 'source_system', 'updated_at']
            );
        }
    }
}
