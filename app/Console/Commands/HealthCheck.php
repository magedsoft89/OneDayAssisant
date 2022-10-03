<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\TestOneDayController;

class HealthCheck extends Command
{


    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'healthCheck:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description =  'Check Get Api For OneDay';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $test_object = new TestOneDayController();
        $test_object->test_api();
    }
}
