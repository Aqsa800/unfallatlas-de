<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\Accident;
use App\Models\DataSource;
use App\Models\ImportRun;
use App\Models\Region;

class ImportUnfallatlas extends Command
{
    protected $signature = 'import:unfallatlas
                            {--year= : Import a specific year (2016–2024). Omit to import all years.}
                            {--force : Re-import even if an import run already exists for the year.}
                            {--fresh : Truncate the accidents table before importing.}';

    protected $description = 'Downloads Unfallatlas CSV ZIPs from opengeodata.nrw.de and imports accidents.';

    private const YEARS       = [2016, 2017, 2018, 2019, 2020, 2021, 2022, 2023, 2024];
    private const BASE_URL    = 'https://www.opengeodata.nrw.de/produkte/transport_verkehr/unfallatlas/Unfallorte{YEAR}_EPSG25832_CSV.zip';
    private const CHUNK_SIZE  = 500;
    private const LOG_EVERY   = 10000;

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->warn('Truncating accidents table…');
            DB::table('accidents')->truncate();
        }

        $years = $this->resolveYears();
        if (empty($years)) {
            $this->error('No valid years to import.');
            return self::FAILURE;
        }

        $dataSource = DataSource::where('key', 'unfallatlas')->first();

        // Pre-load region_id lookup: district_ags → id
        $this->info('Loading region lookup table…');
        $regionLookup = Region::whereIn('level', ['district', 'state'])
            ->pluck('id', 'ags')
            ->all();

        foreach ($years as $year) {
            $this->importYear($year, $dataSource, $regionLookup);
        }

        $this->info('All done.');
        return self::SUCCESS;
    }

    private function resolveYears(): array
    {
        $yearOpt = $this->option('year');

        if ($yearOpt !== null) {
            $y = (int) $yearOpt;
            if (!in_array($y, self::YEARS, true)) {
                $this->error("Year {$y} is not in supported range (" . implode(', ', self::YEARS) . ').');
                return [];
            }
            return [$y];
        }

        return self::YEARS;
    }

    private function importYear(int $year, ?DataSource $dataSource, array &$regionLookup): void
    {
        $url = str_replace('{YEAR}', $year, self::BASE_URL);

        // Check for existing run
        if (!$this->option('force')) {
            $existing = ImportRun::where('data_source_id', $dataSource?->id)
                ->where('source_url', $url)
                ->where('status', 'success')
                ->first();

            if ($existing) {
                $this->line("  Year {$year}: already imported (run #{$existing->id}). Use --force to re-import.");
                return;
            }
        }

        $importRun = ImportRun::create([
            'data_source_id'   => $dataSource?->id,
            'status'           => 'running',
            'source_url'       => $url,
            'records_inserted' => 0,
            'records_updated'  => 0,
            'records_skipped'  => 0,
            'retrieved_at'     => now(),
            'notes'            => "Year {$year}",
        ]);

        $this->info("Year {$year}: downloading {$url}");

        try {
            $zipPath = storage_path("app/unfallatlas_{$year}.zip");
            $this->downloadFile($url, $zipPath);

            $csvPath = $this->extractCsv($zipPath, $year);

            $inserted = $this->parseCsv($csvPath, $year, $importRun->id, $regionLookup);

            $importRun->update([
                'status'           => 'success',
                'records_inserted' => $inserted,
            ]);

            $this->info("  Year {$year}: {$inserted} records inserted.");
        } catch (\Throwable $e) {
            $importRun->update(['status' => 'failed', 'notes' => $e->getMessage()]);
            $this->error("  Year {$year} failed: " . $e->getMessage());
        }
    }

    private function downloadFile(string $url, string $dest): void
    {
        $response = Http::withHeaders(['User-Agent' => 'DMProject/1.0 (academic project)'])
            ->timeout(180)
            ->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()} downloading {$url}");
        }

        file_put_contents($dest, $response->body());
    }

    private function extractCsv(string $zipPath, int $year): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException("Cannot open ZIP file: {$zipPath}");
        }

        $extractDir = storage_path("app/unfallatlas_{$year}");
        if (!is_dir($extractDir)) {
            mkdir($extractDir, 0755, true);
        }

        $zip->extractTo($extractDir);
        $zip->close();

        // Find the data file — older years use .txt inside a csv/ subfolder
        $csvFiles = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($extractDir));
        foreach ($iterator as $file) {
            $ext = strtolower($file->getExtension());
            // Accept .csv and .txt, skip schema/ini files
            if (in_array($ext, ['csv', 'txt']) && !str_contains(strtolower($file->getFilename()), 'schema')) {
                $csvFiles[] = $file->getPathname();
            }
        }

        if (empty($csvFiles)) {
            throw new \RuntimeException("No data file found in ZIP for year {$year}");
        }

        // Prefer the largest file (the actual data, not schema.ini etc.)
        usort($csvFiles, fn($a, $b) => filesize($b) - filesize($a));

        return $csvFiles[0];
    }

    private function parseCsv(string $csvPath, int $year, int $importRunId, array &$regionLookup): int
    {
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open CSV: {$csvPath}");
        }

        // Read header line to detect delimiter
        $headerLine = fgets($handle);
        rewind($handle);

        $delimiter = ';';
        if ($headerLine !== false) {
            $commaCount     = substr_count($headerLine, ',');
            $semicolonCount = substr_count($headerLine, ';');
            if ($commaCount > $semicolonCount) {
                $delimiter = ',';
            }
        }

        // Read header row
        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false || $header === null) {
            fclose($handle);
            throw new \RuntimeException("Empty CSV file: {$csvPath}");
        }

        // Normalize header keys (trim whitespace, uppercase)
        $header = array_map(fn($h) => strtoupper(trim((string)$h)), $header);
        $colMap = array_flip($header);

        $batch    = [];
        $inserted = 0;
        $rowNum   = 0;
        $now      = now();

        // Headers are uppercased — always look up with strtoupper so mixed-case keys work
        $col = fn(string $key) => $colMap[strtoupper($key)] ?? null;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($row === null) {
                continue;
            }

            $rowNum++;
            $get = function (string $key) use ($row, $col): ?string {
                $idx = $col($key);
                if ($idx === null) {
                    return null;
                }
                $val = $row[$idx] ?? null;
                return ($val !== null && $val !== '') ? trim((string) $val) : null;
            };

            // Build AGS codes using UREGBEZ for correct 5-digit official district key
            // Official AGS = Land(2) + RB(1) + Kreis(2) — same as GV-ISys regions.ags
            $uland     = $get('ULAND');
            $uregbez   = $get('UREGBEZ') ?? '0';
            $ukreis    = $get('UKREIS');
            $ugemeinde = $get('UGEMEINDE');

            $stateAgs    = $uland !== null ? str_pad($uland, 2, '0', STR_PAD_LEFT) : null;
            $districtAgs = ($uland !== null && $ukreis !== null)
                ? str_pad($uland, 2, '0', STR_PAD_LEFT)
                  . str_pad($uregbez, 1, '0', STR_PAD_LEFT)
                  . str_pad($ukreis,  2, '0', STR_PAD_LEFT)
                : null;
            $municipalityAgs = ($districtAgs !== null && $ugemeinde !== null)
                ? $districtAgs . str_pad($ugemeinde, 3, '0', STR_PAD_LEFT)
                : null;

            // Look up region_id via district_ags
            $regionId = null;
            if ($districtAgs !== null) {
                $regionId = $regionLookup[$districtAgs] ?? null;
            }
            if ($regionId === null && $stateAgs !== null) {
                $regionId = $regionLookup[$stateAgs] ?? null;
            }

            $lon = $get('XGCSWGS84');
            $lat = $get('YGCSWGS84');

            // Replace German decimal comma if present
            if ($lon !== null) {
                $lon = str_replace(',', '.', $lon);
            }
            if ($lat !== null) {
                $lat = str_replace(',', '.', $lat);
            }

            $batch[] = [
                'year'                => (int) ($get('UJAHR') ?? $year),
                'month'               => $get('UMONAT') !== null ? (int) $get('UMONAT') : null,
                'hour'                => $get('USTUNDE') !== null ? (int) $get('USTUNDE') : null,
                'weekday'             => $get('UWOCHENTAG') !== null ? (int) $get('UWOCHENTAG') : null,
                'category'            => $get('UKATEGORIE') !== null ? (int) $get('UKATEGORIE') : null,
                'accident_type'       => $get('UART') !== null ? (int) $get('UART') : null,
                'crash_type'          => $get('UTYP1') !== null ? (int) $get('UTYP1') : null,
                'light_condition'     => $get('ULICHTVERH') !== null ? (int) $get('ULICHTVERH') : null,
                // IstStrassenzustand: 0=dry, 1=wet/damp, 2=winter/icy — available from 2022
                'road_condition'      => $get('IstStrassenzustand') !== null ? (int) $get('IstStrassenzustand') : null,
                // Column names changed across years: 2016-2024 all use "Ist*" style
                'involves_bicycle'    => (bool)(int)($get('IstRad')  ?? $get('ISTRAD')  ?? '0'),
                'involves_car'        => (bool)(int)($get('IstPKW')  ?? $get('ISTPKW')  ?? '0'),
                'involves_pedestrian' => (bool)(int)($get('IstFuss') ?? $get('ISTFUSS') ?? '0'),
                'involves_motorcycle' => (bool)(int)($get('IstKrad') ?? $get('ISKRAD')  ?? '0'),
                'involves_truck'      => (bool)(int)($get('IstGkfz') ?? $get('ISLKW')   ?? '0'),
                'lon'                 => $lon !== null ? (float) $lon : null,
                'lat'                 => $lat !== null ? (float) $lat : null,
                'district_ags'        => $districtAgs,
                'municipality_ags'    => $municipalityAgs,
                'region_id'           => $regionId,
                'source_system'       => 'unfallatlas',
                'import_run_id'       => $importRunId,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];

            if (count($batch) >= self::CHUNK_SIZE) {
                DB::table('accidents')->insertOrIgnore($batch);
                $inserted += count($batch);
                $batch = [];

                if ($inserted % self::LOG_EVERY === 0) {
                    $this->line("    …{$inserted} rows processed for year {$year}");
                }
            }
        }

        fclose($handle);

        if (!empty($batch)) {
            DB::table('accidents')->insertOrIgnore($batch);
            $inserted += count($batch);
        }

        return $inserted;
    }
}
