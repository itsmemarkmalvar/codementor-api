<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DropTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'drop:table {table}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drops a specific table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $table = $this->argument('table');
        
        try {
            DB::statement("DROP TABLE IF EXISTS {$table}");
            $this->info("Table [{$table}] dropped successfully!");
        } catch (\Exception $e) {
            $this->error("Failed to drop table: " . $e->getMessage());
        }
    }
}
