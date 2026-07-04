<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedDataSources extends Command
{
    protected $signature = 'db:seed-sources';
    protected $description = 'Inserts all data source records into the data_sources table.';

    public function handle(): int
    {
        $sources = [
            [
                'key'         => 'unfallatlas',
                'name'        => 'Unfallatlas Germany',
                'url'         => 'https://www.opengeodata.nrw.de/produkte/transport_verkehr/unfallatlas/',
                'license'     => 'dl-de/by-2-0',
                'license_url' => 'https://www.govdata.de/dl-de/by-2-0',
                'publisher'   => 'Statistische Ämter des Bundes und der Länder',
                'description' => 'Point data for road accidents with personal injury across Germany, 2016–2024, with year/month/hour/category/participant flags.',
            ],
            [
                'key'         => 'gv_isys',
                'name'        => 'GV-ISys Region Directory',
                'url'         => 'https://www.destatis.de/DE/Themen/Laender-Regionen/Regionales/Gemeindeverzeichnis/',
                'license'     => 'dl-de/by-2-0',
                'license_url' => 'https://www.govdata.de/dl-de/by-2-0',
                'publisher'   => 'Statistisches Bundesamt (Destatis)',
                'description' => 'Official AGS region key hierarchy for all German states, districts and municipalities.',
            ],
            [
                'key'         => 'genesis',
                'name'        => 'GENESIS-Online / Regional Statistics',
                'url'         => 'https://www-genesis.destatis.de/datenbank/online',
                'license'     => 'dl-de/by-2-0',
                'license_url' => 'https://www.govdata.de/dl-de/by-2-0',
                'publisher'   => 'Statistisches Bundesamt (Destatis)',
                'description' => 'Population and registered vehicle statistics by district and year, used to compute accident rates per 100,000 inhabitants.',
            ],
            [
                'key'         => 'regionalatlas',
                'name'        => 'Regional Atlas Germany',
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

        $this->info('Seeded ' . count($sources) . ' data source(s) into data_sources.');

        return self::SUCCESS;
    }
}
