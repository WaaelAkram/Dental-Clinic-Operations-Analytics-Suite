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

       
                  // Same-day CONFIRMED reminders
        $schedule->command('reminders:send')
                 ->everyFifteenMinutes()
                 ->withoutOverlapping();

        // 24-hour UNCONFIRMED reminders
        $schedule->command('reminders:send-24hr-unconfirmed')
                 ->everyFiveMinutes()
                 ->withoutOverlapping();

        // Feedback and Marketing
        $schedule->command('feedback:send')->hourly()->withoutOverlapping();
        $schedule->command('marketing:select-daily-batch')->dailyAt('05:00');
        $schedule->command('marketing:queue-staged-messages')->dailyAt('10:00');
        $schedule->command('marketing:track-conversions')->dailyAt('23:00');

        // Your feedback command, when added, should also have this
        // $schedule->command('feedback:send')->hourly()->withoutOverlapping();

        // ...
    }

    // ...
}