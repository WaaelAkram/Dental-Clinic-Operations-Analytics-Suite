<?php
// app/Console/Kernel.php
namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // ... any other commands ...

        // THIS IS THE FIX:
        $schedule->command('reminders:send')
                 ->everyFifteenMinutes()
                 ->withoutOverlapping(); // <-- ADD THIS LINE

        // Your feedback command, when added, should also have this
        // $schedule->command('feedback:send')->hourly()->withoutOverlapping();

        // ...
    }

    // ...
}