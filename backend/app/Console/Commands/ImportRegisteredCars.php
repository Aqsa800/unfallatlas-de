<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\DataSource;
use App\Models\ImportRun;
use App\Models\Indicator;
use App\Models\Region;

class ImportRegisteredCars extends Command
{
    protected $signature   = 'import:registered-cars';
    protected $description = 'Imports registered passenger cars (Pkw) from registered_cars.csv into indicator_values.';

    private const CSV_PATH   = 'registered_cars.csv';
    private const CHUNK_SIZE = 500;

    public function handle(): int
    {
        $csvPath = storage_path('app/' . self::CSV_PATH);

        if (!file_exists($csvPath)) {
            $this->error("File not found: {$csvPath}");
            $this->line('Place the registered_cars.csv file in backend/storage/app/ and retry.');
            return self::FAILURE;
        }

        $dataSource = DataSource::where('key', 'kba')->orWhere('key', 'genesis')->first();

        $importRun = ImportRun::create([
            'data_source_id'   => $dataSource?->id,
            'status'           => 'running',
            'source_url'       => $csvPath,
            'records_inserted' => 0,
            'records_updated'  => 0,
            'records_skipped'  => 0,
            'retrieved_at'     => now(),
        ]);

        try {
            $indicator = Indicator::firstOrCreate(
                ['code' => 'registered_cars'],
                [
                    'name'          => 'Pkw-Bestand',
                    'unit'          => 'Fahrzeuge',
                    'source_system' => 'kba',
                ]
            );

            $this->info("Using indicator: {$indicator->code} (id={$indicator->id})");

            $this->info('Loading region lookup…');
            $regionLookup = Region::pluck('id', 'ags')->all();

            $inserted = $this->parseCsv($csvPath, $indicator->id, $regionLookup);

            $importRun->update(['status' => 'success', 'records_inserted' => $inserted]);
            $this->info("Import complete. {$inserted} indicator_values upserted.");
        } catch (\Throwable $e) {
            $importRun->update(['status' => 'failed', 'notes' => $e->getMessage()]);
            $this->error('Import failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function parseCsv(string $path, int $indicatorId, array $regionLookup): int
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open: {$path}");
        }

        // Read and detect header
        $headerLine = null;
        while (($line = fgets($handle)) !== false) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || $trimmed[0] === '#' || $trimmed[0] === '"') continue;
            $headerLine = $line;
            break;
        }

        if ($headerLine === null) {
            fclose($handle);
            throw new \RuntimeException('Could not find header line.');
        }

        $delimiter = substr_count($headerLine, ',') > substr_count($headerLine, ';') ? ',' : ';';
        $header    = array_map(fn($h) => strtolower(trim($h)), str_getcsv(trim($headerLine), $delimiter));
        $colMap    = array_flip($header);

        $agsCol  = $colMap['ags']  ?? ($colMap['kreisschluessel'] ?? null);
        $yearCol = $colMap['jahr'] ?? ($colMap['year']            ?? null);
        $carsCol = $colMap['pkw']  ?? ($colMap['anzahl']          ?? ($colMap['value'] ?? null));

        if ($agsCol === null || $yearCol === null || $carsCol === null) {
            fclose($handle);
            throw new \RuntimeException('Expected columns: AGS, Jahr, Pkw. Found: ' . implode(', ', $header));
        }

        $batch    = [];
        $inserted = 0;
        $skipped  = 0;
        $now      = now();

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($row === null || count($row) <= max($agsCol, $yearCol, $carsCol)) {
                $skipped++;
                continue;
            }

            $ags  = preg_replace('/\s+/', '', trim((string) ($row[$agsCol] ?? '')));
            $year = trim((string) ($row[$yearCol] ?? ''));
            $cars = trim((string) ($row[$carsCol] ?? ''));

            if ($ags === '' || $year === '' || $cars === '') { $skipped++; continue; }

            $regionId = $regionLookup[$ags] ?? null;
            if ($regionId === null) { $skipped++; continue; }

            $batch[] = [
                'region_id'    => $regionId,
                'indicator_id' => $indicatorId,
                'year'         => (int) $year,
                'value'        => (float) str_replace(['.', ' ', "\xc2\xa0"], '', str_replace(',', '.', $cars)),
                'created_at'   => $now,
                'updated_at'   => $now,
            ];

            if (count($batch) >= self::CHUNK_SIZE) {
                DB::table('indicator_values')->upsert($batch, ['region_id', 'indicator_id', 'year'], ['value', 'updated_at']);
                $inserted += count($batch);
                $batch = [];
                $this->line("  …{$inserted} rows processed");
            }
        }

        fclose($handle);

        if (!empty($batch)) {
            DB::table('indicator_values')->upsert($batch, ['region_id', 'indicator_id', 'year'], ['value', 'updated_at']);
            $inserted += count($batch);
        }

        if ($skipped > 0) {
            $this->warn("  Skipped {$skipped} rows (missing data or unknown AGS).");
        }

        return $inserted;
    }
}
