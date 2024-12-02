<?php

namespace Santosdave\VerteilWrapper\Console\Commands;

use Illuminate\Console\Command;
use Santosdave\VerteilWrapper\Monitoring\HealthMonitor;

class VerteilHealthCheck extends Command
{
    protected $signature = 'verteil:health';
    protected $description = 'Check Verteil API health status';

    public function handle(HealthMonitor $monitor)
    {
        $health = $monitor->checkHealth();
        
        $this->table(
            ['Metric', 'Value'],
            collect($health)->map(function ($value, $key) {
                return [$key, is_array($value) ? json_encode($value) : $value];
            })
        );
        
        return $health['status'] === 'healthy' ? 0 : 1;
    }
}