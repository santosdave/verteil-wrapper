<?php

namespace Santosdave\VerteilWrapper\Console\Commands;

use Illuminate\Console\Command;
use Santosdave\VerteilWrapper\Services\VerteilService;

class VerteilCacheFlush extends Command
{
    protected $signature = 'verteil:cache:flush {endpoint?}';
    protected $description = 'Flush Verteil API cache';

    public function handle(VerteilService $service)
    {
        $endpoint = $this->argument('endpoint');

        try {
            $service->flushCache($endpoint);

            $this->info(
                $endpoint
                    ? "Cache flushed for endpoint: {$endpoint}"
                    : "All Verteil cache flushed successfully"
            );

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to flush cache: {$e->getMessage()}");
            return 1;
        }
    }
}
