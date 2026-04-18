<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('calculate:monthly-debts')
    ->monthlyOn(1, '00:00')
    ->timezone('Europe/Chisinau');

Schedule::command('ping')
    ->everyFiveMinutes()
    ->between('8:00', '22:00')
    ->timezone('Europe/Chisinau');
