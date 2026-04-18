<?php

namespace App\Console\Commands;

use App\Services\PythonApiService;
use Illuminate\Console\Command;

class PingHuggingFace extends Command
{
    protected $signature = 'ping';

    protected $description = 'Ping the Hugging Face Python API during daytime hours';

    public function handle(PythonApiService $pythonApi): int
    {
        if ($pythonApi->ping()) {
            $this->info('Hugging Face is reachable.');

            return self::SUCCESS;
        }

        $this->warn('Hugging Face did not respond.');

        return self::FAILURE;
    }
}
