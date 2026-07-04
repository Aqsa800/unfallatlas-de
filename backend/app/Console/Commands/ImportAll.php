<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportAll extends Command
{
    protected $signature = 'import:all';
    protected $description = 'Runs all import commands in sequence: seed-sources → gv-isys → unfallatlas → genesis.';

    public function handle(): int
    {
        $this->info('=== Step 1/4: Seeding data sources ===');
        $result = $this->call('db:seed-sources');
        if ($result !== self::SUCCESS) {
            $this->error('db:seed-sources failed. Aborting.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('=== Step 2/4: Importing Gemeindeverzeichnis (fallback: 16 states) ===');
        $result = $this->call('import:gv-isys', ['--use-fallback' => true]);
        if ($result !== self::SUCCESS) {
            $this->error('import:gv-isys failed. Aborting.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('=== Step 3/4: Importing Unfallatlas (all years 2016–2024) ===');
        $result = $this->call('import:unfallatlas');
        if ($result !== self::SUCCESS) {
            $this->error('import:unfallatlas failed. Aborting.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('=== Step 4/4: Importing GENESIS population data ===');
        $result = $this->call('import:genesis');
        if ($result !== self::SUCCESS) {
            // Genesis failure is non-fatal (may require manual CSV download)
            $this->warn('import:genesis did not complete successfully (may require manual CSV). See instructions above.');
        }

        $this->newLine();
        $this->info('=== import:all complete ===');

        return self::SUCCESS;
    }
}
