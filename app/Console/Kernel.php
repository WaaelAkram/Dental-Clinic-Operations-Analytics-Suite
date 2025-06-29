<?php
// app/Console/Kernel.php
namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
   // In app/Console/Kernel.php

protected function schedule(Schedule $schedule): void
{
    // === CONSOLIDATED TASKS ===
  // System 1: Appointment Reminders
    $schedule->command('reminders:send')
             ->everyFiveMinutes() // <-- CHANGED
             ->withoutOverlapping();

    // System 2: Feedback Requests
    $schedule->command('feedback:send')
             ->everyFiveMinutes() // <-- CHANGED
             ->withoutOverlapping();


    // System 3: Lapsed Patient Marketing
   $schedule->command('marketing:select-daily-batch')->dailyAt('02:00')->withoutOverlapping();
   $schedule->command('marketing:queue-staged-messages')->dailyAt('02:05')->withoutOverlapping();
   $schedule->command('marketing:track-conversions')->dailyAt('03:00')->withoutOverlapping();
}
}