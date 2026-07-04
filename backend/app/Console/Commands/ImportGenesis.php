<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\DataSource;
use App\Models\ImportRun;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Region;

class ImportGenesis extends Command
{
    protected $signature = 'import:genesis';
    protected $description = 'Imports population data from a local GENESIS CSV file into indicator_values.';

    private const CSV_PATH   = 'genesis_population_full.csv';
    private const CHUNK_SIZE = 500;

    private const MANUAL_INSTRUCTIONS = <<<'TEXT'

No local population CSV found at storage/app/genesis_population.csv.

To obtain the data manually:
  1. Visit https://www-genesis.destatis.de/datenbank/online
  2. Search for table "12411-02-03-4" (Bevölkerung nach Geschlecht und Kreisen)
     or go to https://www.regionalstatistik.de/genesis/online and look up table 46241-01-03-4.
  3. Download the data as CSV (semicolon-delimited, UTF-8).
  4. Ensure the file has the following format (header on first non-comment line):
       AGS;Jahr;Bevoelkerung
       01001;2022;87654
       …
  5. Save it as storage/app/genesis_population.csv
  6. Run: php artisan import:genesis

TEXT;

    public function handle(): int
    {
        $csvFullPath = storage_path('app/' . self::CSV_PATH);

        if (!file_exists($csvFullPath)) {
            $this->warn('Population CSV not found.');
            $this->line(self::MANUAL_INSTRUCTIONS);
            return self::FAILURE;
        }

        $dataSource = DataSource::where('key', 'genesis')->first();

        $importRun = ImportRun::create([
            'data_source_id'   => $dataSource?->id,
            'status'           => 'running',
            'source_url'       => $csvFullPath,
            'records_inserted' => 0,
            'records_updated'  => 0,
            'records_skipped'  => 0,
            'retrieved_at'     => now(),
        ]);

        try {
            // Ensure all three population indicators exist
            $indicators = [
                'population'        => Indicator::firstOrCreate(['code' => 'population'],        ['name' => 'Bevölkerung',         'unit' => 'Personen', 'source_system' => 'genesis']),
                'population_male'   => Indicator::firstOrCreate(['code' => 'population_male'],   ['name' => 'Bevölkerung männlich', 'unit' => 'Personen', 'source_system' => 'genesis']),
                'population_female' => Indicator::firstOrCreate(['code' => 'population_female'], ['name' => 'Bevölkerung weiblich', 'unit' => 'Personen', 'source_system' => 'genesis']),
            ];

            foreach ($indicators as $code => $ind) {
                $this->info("Using indicator: {$ind->code} (id={$ind->id})");
            }

            // Pre-load region_id lookup by AGS
            $this->info('Loading region lookup…');
            $regionLookup = Region::pluck('id', 'ags')->all();

            $inserted = $this->parseCsv($csvFullPath, $indicators, $regionLookup);

            $importRun->update([
                'status'           => 'success',
                'records_inserted' => $inserted,
            ]);

            $this->info("Import complete. {$inserted} indicator_values upserted.");
        } catch (\Throwable $e) {
            $importRun->update(['status' => 'failed', 'notes' => $e->getMessage()]);
            $this->error('Import failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function parseCsv(string $path, array $indicators, array $regionLookup): int
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file: {$path}");
        }

        // Skip comment/metadata lines that start with '#' or are empty
        $headerLine = null;
        while (($line = fgets($handle)) !== false) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || $trimmed[0] === '#' || $trimmed[0] === '"') {
                continue;
            }
            $headerLine = $line;
            break;
        }

        if ($headerLine === null) {
            fclose($handle);
            throw new \RuntimeException('Could not find header line in CSV.');
        }

        // Detect delimiter
        $delimiter = ';';
        if (substr_count($headerLine, ',') > substr_count($headerLine, ';')) {
            $delimiter = ',';
        }

        // Parse header
        $header = str_getcsv(trim($headerLine), $delimiter);
        $header = array_map(fn($h) => strtolower(trim((string) $h)), $header);
        $colMap = array_flip($header);

        $agsCol    = $colMap['ags']          ?? ($colMap['kreisschluessel'] ?? ($colMap['rs']   ?? null));
        $yearCol   = $colMap['jahr']         ?? ($colMap['year']            ?? null);
        $totalCol  = $colMap['insgesamt']    ?? ($colMap['bevoelkerung']    ?? ($colMap['value'] ?? ($colMap['wert'] ?? null)));
        $maleCol   = $colMap['maennlich']    ?? ($colMap['männlich']        ?? null);
        $femaleCol = $colMap['weiblich']     ?? null;

        if ($agsCol === null || $yearCol === null || $totalCol === null) {
            fclose($handle);
            throw new \RuntimeException(
                'Could not map CSV columns. Expected: AGS, Jahr, Insgesamt. Found: ' . implode(', ', $header)
            );
        }

        $batch    = [];
        $inserted = 0;
        $skipped  = 0;
        $now      = now();

        $idTotal  = $indicators['population']->id;
        $idMale   = $indicators['population_male']->id;
        $idFemale = $indicators['population_female']->id;

        $flush = function () use (&$batch, &$inserted) {
            DB::table('indicator_values')->upsert(
                $batch,
                ['region_id', 'indicator_id', 'year'],
                ['value', 'updated_at']
            );
            $inserted += count($batch);
            $batch = [];
        };

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($row === null || count($row) <= max($agsCol, $yearCol, $totalCol)) {
                $skipped++;
                continue;
            }

            $ags  = preg_replace('/\s+/', '', trim((string) ($row[$agsCol] ?? '')));
            $year = trim((string) ($row[$yearCol] ?? ''));
            $tot  = trim((string) ($row[$totalCol] ?? ''));

            if ($ags === '' || $year === '' || $tot === '') {
                $skipped++;
                continue;
            }

            $regionId = $regionLookup[$ags] ?? null;
            if ($regionId === null) {
                $skipped++;
                continue;
            }

            $parseNum = fn($v) => (float) str_replace(['.', ' ', "\xc2\xa0"], '', str_replace(',', '.', $v));

            // Total population
            $batch[] = ['region_id' => $regionId, 'indicator_id' => $idTotal,  'year' => (int) $year, 'value' => $parseNum($tot),  'created_at' => $now, 'updated_at' => $now];

            // Male
            if ($maleCol !== null && isset($row[$maleCol]) && trim($row[$maleCol]) !== '') {
                $batch[] = ['region_id' => $regionId, 'indicator_id' => $idMale,   'year' => (int) $year, 'value' => $parseNum($row[$maleCol]),   'created_at' => $now, 'updated_at' => $now];
            }

            // Female
            if ($femaleCol !== null && isset($row[$femaleCol]) && trim($row[$femaleCol]) !== '') {
                $batch[] = ['region_id' => $regionId, 'indicator_id' => $idFemale, 'year' => (int) $year, 'value' => $parseNum($row[$femaleCol]), 'created_at' => $now, 'updated_at' => $now];
            }

            if (count($batch) >= self::CHUNK_SIZE) {
                $flush();
                $this->line("  …{$inserted} rows processed");
            }
        }

        fclose($handle);

        if (!empty($batch)) {
            $flush();
        }

        if ($skipped > 0) {
            $this->warn("  Skipped {$skipped} rows (missing data or unknown AGS).");
        }

        return $inserted;
    }
}
