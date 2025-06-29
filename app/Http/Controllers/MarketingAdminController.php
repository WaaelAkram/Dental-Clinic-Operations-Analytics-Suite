<?php

namespace App\Http\Controllers;

use App\Gateways\ClinicPatientGateway;
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

        // Use the new view path as requested.
        return view('report', compact('stats', 'conversions'));
    }
}