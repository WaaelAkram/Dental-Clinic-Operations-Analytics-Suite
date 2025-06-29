<?php

namespace App\Http\Controllers;

use App\Gateways\ClinicPatientGateway;
use App\Jobs\SendManualWhatsappMessage;
use App\Models\MarketingExclusion;
use App\Models\SentMarketingMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class MarketingAdminController extends Controller
{
    /**
     * Finds a patient by their mobile number and adds them to the
     * permanent marketing exclusion list.
     */
    public function addExclusion(Request $request, ClinicPatientGateway $gateway)
    {
        $request->validate(['mobile' => 'required|string|regex:/^\d{8,15}$/']);
        
        $mobile = $request->input('mobile');
        $patient = $gateway->findPatientByMobile($mobile);

        if (!$patient) {
            return back()->withErrors(['mobile' => 'Patient with this mobile number was not found in the clinic records.'])->withInput();
        }

        MarketingExclusion::firstOrCreate(
            ['patient_id' => $patient->id],
            [
                'mobile' => $patient->mobile,
                'reason' => 'Manually added by admin on ' . now()->toDateString()
            ]
        );

        return Redirect::route('dashboard')->with('status', "Success! Patient ({$patient->pt_name}) has been permanently excluded from marketing.");
    }

    /**
     * Queues a manual broadcast to a list of numbers with staggered delays.
     */
    public function sendManualBroadcast(Request $request)
    {
        $request->validate([
            'manual_numbers' => 'required|string',
            'manual_message' => 'required|string|max:1000',
        ]);

        // 1. Parse and sanitize the input numbers
        $numbersInput = $request->input('manual_numbers');
        $numbers = preg_split('/[\s,]+/', $numbersInput, -1, PREG_SPLIT_NO_EMPTY);
        $sanitizedNumbers = array_unique(array_filter($numbers, 'is_numeric'));

        if (empty($sanitizedNumbers)) {
            return back()->withErrors(['manual_numbers' => 'No valid numeric phone numbers were found in the list.'])->withInput();
        }

        // 2. Safety First: Check against the permanent exclusion list
        $excludedMobiles = MarketingExclusion::whereIn('mobile', $sanitizedNumbers)->pluck('mobile')->all();
        $finalNumbersToSend = array_diff($sanitizedNumbers, $excludedMobiles);
        
        $skippedCount = count($sanitizedNumbers) - count($finalNumbersToSend);

        // 3. Dispatch jobs with a staggered delay
        $totalDelaySeconds = 0;
        
        foreach ($finalNumbersToSend as $number) {
            SendManualWhatsappMessage::dispatch($number, $request->input('manual_message'))
                ->delay(now()->addSeconds($totalDelaySeconds));
            
            // Add a random delay for the *next* job (e.g., between 20 and 60 seconds)
            $totalDelaySeconds += rand(80, 500);
        }

        $successMessage = "Successfully queued " . count($finalNumbersToSend) . " messages with staggered delays.";
        if ($skippedCount > 0) {
            $successMessage .= " {$skippedCount} numbers were skipped because they are on the exclusion list.";
        }

        return Redirect::route('dashboard')->with('status', $successMessage);
    }

    /**
     * Displays the performance report for the marketing campaign.
     */
    public function showReport()
    {
        $totalSent = SentMarketingMessage::where('status', 'sent')->count();
        $totalConversions = SentMarketingMessage::whereNotNull('converted_at')->count();

        $stats = [
            'total_sent' => $totalSent,
            'total_conversions' => $totalConversions,
            'conversion_rate' => $totalSent > 0 ? ($totalConversions / $totalSent) * 100 : 0,
        ];

        $conversions = SentMarketingMessage::whereNotNull('converted_at')
            ->orderBy('converted_at', 'desc')
            ->paginate(20);

        return view('report', compact('stats', 'conversions'));
    }
}