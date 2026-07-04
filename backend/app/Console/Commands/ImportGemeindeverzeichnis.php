<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\DataSource;
use App\Models\ImportRun;

class ImportGemeindeverzeichnis extends Command
{
    protected $signature = 'import:gv-isys
                            {--use-fallback : Use hardcoded list of 16 German states instead of downloading the XLSX}';

    protected $description = 'Downloads the Gemeindeverzeichnis XLSX from Destatis and imports all regions into the regions table.';

    private const DOWNLOAD_URL = 'https://www.destatis.de/DE/Themen/Laender-Regionen/Regionales/Gemeindeverzeichnis/Administrativ/Archiv/GVAuszugQ/AuszugGV4QAktuell.xlsx;jsessionid=?__blob=publicationFile';

    private const FALLBACK_STATES = [
        '01' => 'Schleswig-Holstein',
        '02' => 'Hamburg',
        '03' => 'Niedersachsen',
        '04' => 'Bremen',
        '05' => 'Nordrhein-Westfalen',
        '06' => 'Hessen',
        '07' => 'Rheinland-Pfalz',
        '08' => 'Baden-Württemberg',
        '09' => 'Bayern',
        '10' => 'Saarland',
        '11' => 'Berlin',
        '12' => 'Brandenburg',
        '13' => 'Mecklenburg-Vorpommern',
        '14' => 'Sachsen',
        '15' => 'Sachsen-Anhalt',
        '16' => 'Thüringen',
    ];

    public function handle(): int
    {
        $dataSource = DataSource::where('key', 'gv_isys')->first();

        $importRun = ImportRun::create([
            'data_source_id'   => $dataSource?->id,
            'status'           => 'running',
            'source_url'       => self::DOWNLOAD_URL,
            'records_inserted' => 0,
            'records_updated'  => 0,
            'records_skipped'  => 0,
            'retrieved_at'     => now(),
        ]);

        try {
            if ($this->option('use-fallback')) {
                $inserted = $this->importFallback();
            } else {
                $inserted = $this->importFromXlsx();
            }

            $importRun->update([
                'status'           => 'success',
                'records_inserted' => $inserted,
            ]);

            $this->info("Import complete. {$inserted} region(s) upserted.");
        } catch (\Throwable $e) {
            $importRun->update(['status' => 'failed', 'notes' => $e->getMessage()]);
            $this->error('Import failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function importFromXlsx(): int
    {
        $xlsxPath = storage_path('app/gv_isys.xlsx');
        $csvPath  = storage_path('app/gv_isys.csv');

        // Download XLSX if not already present
        if (!file_exists($xlsxPath)) {
            $this->info('Downloading GV-ISys XLSX…');
            $response = Http::withHeaders(['User-Agent' => 'DMProject/1.0 (academic project)'])
                ->timeout(120)->get(self::DOWNLOAD_URL);

            if (!$response->successful()) {
                $this->warn("Download failed (HTTP {$response->status()}). Falling back to hardcoded states.");
                return $this->importFallback();
            }
            file_put_contents($xlsxPath, $response->body());
            $this->info('Saved XLSX to ' . $xlsxPath);
        } else {
            $this->info('Using cached XLSX: ' . $xlsxPath);
        }

        // Convert XLSX → CSV using Python (memory-efficient streaming read)
        $this->info('Converting XLSX to CSV via Python…');
        $script = base_path('scripts/gv_isys_convert.py');
        $cmd    = escapeshellcmd("python3 {$script} {$xlsxPath} {$csvPath}");
        exec($cmd . ' 2>&1', $output, $rc);

        if ($rc !== 0 || !file_exists($csvPath)) {
            $this->warn('Python conversion failed: ' . implode("\n", $output));
            $this->warn('Falling back to hardcoded state list.');
            return $this->importFallback();
        }

        $this->info('Conversion OK. ' . implode(' ', $output));
        return $this->importFromCsv($csvPath);
    }

    private function importFromCsv(string $csvPath): int
    {
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open CSV: {$csvPath}");
        }

        $header     = fgetcsv($handle); // skip header row
        $batch      = [];
        $count      = 0;
        $now        = now();
        $stateNames = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 3) continue;

            [$satzart, $ags, $name] = $row;
            $population = isset($row[3]) && $row[3] !== '' ? (int)$row[3] : null;
            $area       = isset($row[4]) && $row[4] !== '' ? (float)$row[4] : null;
            $lon        = isset($row[5]) && $row[5] !== '' ? (float)$row[5] : null;
            $lat        = isset($row[6]) && $row[6] !== '' ? (float)$row[6] : null;

            $stateAgs = substr($ags, 0, 2);
            $distAgs  = null;

            switch ($satzart) {
                case '10':
                    $level = 'state';
                    $stateNames[$ags] = $name;
                    break;
                case '40': case '41':
                    $level   = 'district';
                    $distAgs = $ags;
                    break;
                case '60': case '61':
                    $level   = 'municipality';
                    $distAgs = substr($ags, 0, 5);
                    break;
                default:
                    continue 2;
            }

            $batch[] = [
                'ags'          => $ags,
                'name'         => $name,
                'level'        => $level,
                'state_ags'    => $stateAgs,
                'district_ags' => $distAgs,
                'state_name'   => $stateNames[$stateAgs] ?? null,
                'population'   => $population,
                'area_km2'     => $area,
                'lon'          => $lon,
                'lat'          => $lat,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];

            $count++;

            if (count($batch) >= 500) {
                $this->upsertBatch($batch);
                $batch = [];
                if ($count % 2000 === 0) {
                    $this->line("  …{$count} regions processed");
                }
            }
        }

        fclose($handle);

        if (!empty($batch)) {
            $this->upsertBatch($batch);
        }

        return $count;
    }

    private function importFallback(): int
    {
        $this->info('Using hardcoded fallback: 16 German states.');
        $now   = now();
        $batch = [];

        foreach (self::FALLBACK_STATES as $ags => $name) {
            $batch[] = [
                'ags'          => $ags,
                'name'         => $name,
                'level'        => 'state',
                'state_ags'    => $ags,
                'district_ags' => null,
                'state_name'   => $name,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }

        $this->upsertBatch($batch);

        return count($batch);
    }

    private function upsertBatch(array $batch): void
    {
        DB::table('regions')->upsert(
            $batch,
            ['ags'],
            ['name', 'level', 'state_ags', 'district_ags', 'state_name', 'updated_at']
        );
    }
}
