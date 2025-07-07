<?php
namespace App\Console\Commands\Marketing;

use App\Gateways\ClinicPatientGateway;
use App\Models\MarketingExclusion;
use App\Models\SentMarketingMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SelectDailyBatch extends Command
{
    protected $signature = 'marketing:select-daily-batch {--dry-run : Preview the selected patients without saving them}';
    protected $description = 'Selects a daily batch of lapsed patients to be queued for marketing messages.';

          public function handle(ClinicPatientGateway $gateway): int
    {
        $isDryRun = $this->option('dry-run');
        if ($isDryRun) {
            $this->info("--- DRY RUN MODE: No changes will be saved. ---");
        }

        $this->info("Starting selection process for the daily marketing batch...");

        $lapsedCandidates = $gateway->getLapsedPatients();
        if ($lapsedCandidates->isEmpty()) {
            $this->info("No lapsed patients without future appointments found. Exiting.");
            return self::SUCCESS;
        }
        $this->info("Found {$lapsedCandidates->count()} potential candidates (already filtered for future appointments).");

        // --- CHANGE #1: Get the daily limit from the config.
        // We will now provide a safe default of 0 if the config is missing,
        // and we will add a check to ensure it's a valid number.
        $dailyLimit = config('marketing.daily_limit', 0);

        // --- CHANGE #2: Add a safety check and clear feedback.
        if ($dailyLimit <= 0) {
            $this->error("Marketing daily limit is not configured or is zero. Exiting to prevent errors.");
            Log::warning("Marketing cron: Daily limit is zero or not set in config/marketing.php.");
            return self::FAILURE;
        }
        $this->info("Daily sending limit is set to: {$dailyLimit}");


        $permanentExclusions = MarketingExclusion::pluck('patient_id');
        $this->info("Found {$permanentExclusions->count()} permanently excluded patients.");

        $cooldownDays = config('marketing.marketing_cooldown_days', 120);
        $cooldownDate = now()->subDays($cooldownDays)->startOfDay();
        $recentMessages = SentMarketingMessage::where('process_date', '>=', $cooldownDate)->pluck('patient_id');
        $this->info("Found {$recentMessages->count()} patients on a temporary marketing cooldown.");

        $doNotContactIds = $permanentExclusions->merge($recentMessages)->unique();

        $finalProspects = $lapsedCandidates->whereNotIn('id', $doNotContactIds);
        $this->info("After all filtering, {$finalProspects->count()} prospects remain.");

        if ($finalProspects->isEmpty()) {
            $this->info("No eligible prospects left after filtering. Exiting.");
            return self::SUCCESS;
        }

        // The old line is now gone. We use the $dailyLimit variable we defined above.
        $batch = $finalProspects->take($dailyLimit);
        $this->info("Selecting a batch of {$batch->count()} prospects (limit was {$dailyLimit}).");

        if ($isDryRun) {
            $this->table(
                ['Patient ID', 'Full Name', 'Mobile'],
                $batch->map(fn($p) => [$p->id, $p->full_name, $p->mobile])->toArray()
            );
            $this->info("--- End of Dry Run ---");
            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($batch) {
                $today = now()->toDateString();
                foreach ($batch as $prospect) {
                    SentMarketingMessage::create([
                        'patient_id' => $prospect->id,
                        'mobile' => $prospect->mobile,
                        'status' => 'staging',
                        'process_date' => $today,
                    ]);
                }
            });
        } catch (\Exception $e) {
            $this->error("Failed to save the marketing batch. Transaction rolled back.");
            Log::critical("Marketing Batch Save Failure: " . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Successfully staged {$batch->count()} patients for today's marketing campaign.");
        Log::info("Marketing cron: Staged {$batch->count()} patients.");

        return self::SUCCESS;
    }
}