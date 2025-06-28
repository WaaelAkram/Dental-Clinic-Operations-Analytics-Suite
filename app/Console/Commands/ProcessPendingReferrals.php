<?php
// app/Console/Commands/ProcessPendingReferrals.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Referral;
use App\Models\Reward;
use App\Gateways\ClinicPatientGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config; // <-- Make sure this is imported
use Throwable;

class ProcessPendingReferrals extends Command
{
    protected $signature = 'referrals:process';
    protected $description = 'Processes pending referrals to check if they have met the reward threshold.';

    public function __construct(private ClinicPatientGateway $clinicGateway)
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('Starting to process pending referrals...');
        Log::info('Manual or scheduled referral processing job started.'); // Add a log entry

        $pendingReferrals = Referral::where('reward_issued', false)->get();

        if ($pendingReferrals->isEmpty()) {
            $this->info('No pending referrals to process.');
            Log::info('Referral processing job finished: No pending referrals found.');
            return self::SUCCESS;
        }

        // Fetch rules from our dedicated config file.
        $rewardThreshold = Config::get('referrals.reward_threshold');
        $rewardValue = Config::get('referrals.reward_value');
        $rewardType = Config::get('referrals.reward_type');

        $processedCount = 0;
        $rewardedCount = 0;

        foreach ($pendingReferrals as $referral) {
            try {
                $totalPaid = $this->clinicGateway->getTotalPaidForPatient($referral->referred_patient_id);
                $referral->total_paid = $totalPaid;

                if ($totalPaid >= $rewardThreshold) {
                    DB::transaction(function () use ($referral, $rewardValue, $rewardType) {
                        $referral->status = 'completed';
                        $referral->reward_issued = true;
                        $referral->save();
                        Reward::create([
                            'referral_id' => $referral->id,
                            'reward_value' => $rewardValue,
                            'reward_type' => $rewardType,
                            'issued_at' => now(),
                        ]);
                    });
                    
                    
                    $this->line("✅ Rewarded referral ID: {$referral->id}. Referred patient paid: {$totalPaid} SAR.");
                    Log::info("Reward issued for referral ID: {$referral->id}");
                    $rewardedCount++;
                } else {
                    $referral->save();
                    $this->line(".. Pending referral ID: {$referral->id}. Paid: {$totalPaid} SAR (Threshold: {$rewardThreshold} SAR)");
                }
                $processedCount++;
            } catch (Throwable $e) {
                Log::error("Failed to process referral ID {$referral->id}: " . $e->getMessage());
                $this->error("Failed to process referral ID {$referral->id}. Check logs.");
            }
        }

        $summary = "Finished processing. Processed: {$processedCount}. Newly Rewarded: {$rewardedCount}.";
        $this->info($summary);
        Log::info($summary); // Also log the summary
        return self::SUCCESS;
    }
}