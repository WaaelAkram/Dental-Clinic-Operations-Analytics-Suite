<?php

namespace App\Http\Controllers;

use App\Gateways\ClinicPatientGateway;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PublicDashboardController extends Controller
{
    private function getDashboardData(Request $request, ClinicPatientGateway $gateway)
    {
        $selectedDoctorId = $request->input('selected_doctor_id');
        $avgRevPerPatientForDoctor = null;

        if ($selectedDoctorId) {
            // --- THIS IS THE FIX ---
            // Pass the 30-day period to the gateway method.
            $avgRevPerPatientForDoctor = $gateway->getAverageRevenuePerPatientForSingleDoctor($selectedDoctorId, 30);
            // --- END OF FIX ---
        }

        return [
            'kpis' => $gateway->getKpiData(),
            'monthlyRevenue' => $gateway->getMonthlyRevenue(),
            'patientMix' => $gateway->getNewVsReturningPatients(),
            'doctorRevenue' => $gateway->getRevenuePerDoctor(),
            'retentionRate' => $gateway->getPatientRetentionRate(30),
            'arpp' => $gateway->getAverageRevenuePerPatient(30),
            'arpa' => $gateway->getAverageRevenuePerAppointment(30),
            'allDoctors' => $gateway->getAllDoctors(),
            'selectedDoctorId' => $selectedDoctorId,
            'avgRevPerPatientForDoctor' => $avgRevPerPatientForDoctor,
        ];
        
    }
    
    // ... (index and index_ar methods remain unchanged)
    public function index(Request $request, ClinicPatientGateway $gateway)
    {
        $data = $this->getDashboardData($request, $gateway);
        return view('public-dashboard', compact('data'));
    }

    public function index_ar(Request $request, ClinicPatientGateway $gateway)
    {
        app()->setLocale('ar');
        Carbon::setLocale('ar');
        $data = $this->getDashboardData($request, $gateway);
        return view('public-dashboard-ar', compact('data'));
    }
}