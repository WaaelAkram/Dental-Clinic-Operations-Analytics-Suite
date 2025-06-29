<?php

namespace App\Gateways;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Database\Query\Builder;
use stdClass;

/**
 * A gateway to interact with the external clinic patient database.
 * All logic for querying the 'mssql_clinic' connection lives here.
 */
class ClinicPatientGateway
{
    protected $connection;

    public function __construct()
    {
        $this->connection = DB::connection('mssql_clinic');
    }

    /**
     * A private helper to get a unified stream of all revenue transactions.
     */
    private function _getTotalRevenueQuery(): Builder
    {
        $invoiceQuery = $this->connection->table('invoice_h')
            ->select(
                'inv_dt as transaction_date',
                DB::raw('cash + span as amount'),
                'pt_id',
                'doc_id'
            );

        $receiptQuery = $this->connection->table('receipts')
            ->select(
                'rcpt_dt as transaction_date',
                'amount',
                'pt_id',
                'doc_id'
            );

        return $invoiceQuery->unionAll($receiptQuery);
    }

    public function getKpiData(): stdClass
    {
        $today = now()->format('Y-m-d');
        $startOfMonth = now()->startOfMonth()->format('Y-m-d');

        // FIXED: Use the subquery pattern to filter the UNION result
        $unifiedQuery = $this->_getTotalRevenueQuery();
        $totalRevenueToday = DB::connection('mssql_clinic')->table(DB::raw("({$unifiedQuery->toSql()}) as unified_revenue"))
            ->mergeBindings($unifiedQuery)
            ->where(DB::raw("CONVERT(date, transaction_date)"), '=', $today)
            ->sum('amount');
        
        $newPatientsThisMonth = $this->connection->table('patient')->where(DB::raw("CONVERT(date, trans_dt)"), '>=', $startOfMonth)->count('id');
        $appointmentsToday = $this->connection->table('appointment')->where(DB::raw("CONVERT(date, app_dt)"), '=', $today)->count('id');

        return (object)[
            'total_revenue_today' => $totalRevenueToday,
            'new_patients_this_month' => $newPatientsThisMonth,
            'appointments_today' => $appointmentsToday,
        ];
    }
    
    public function getMonthlyRevenue(): Collection
    {
        $startDate = now()->subMonths(12)->startOfMonth()->format('Y-m-d');
        
        $unifiedRevenueQuery = $this->_getTotalRevenueQuery();
        $unifiedRevenue = DB::connection('mssql_clinic')->table(DB::raw("({$unifiedRevenueQuery->toSql()}) as unified_revenue"))
            ->mergeBindings($unifiedRevenueQuery);

        return $unifiedRevenue
            ->select(
                DB::raw("CONVERT(VARCHAR(7), transaction_date, 120) as month"),
                DB::raw('SUM(amount) as total_revenue')
            )
            ->where(DB::raw("CONVERT(date, transaction_date)"), '>=', $startDate)
            ->groupBy(DB::raw("CONVERT(VARCHAR(7), transaction_date, 120)"))
            ->orderBy('month', 'asc')
            ->get();
    }

    public function getRevenuePerDoctor(int $days = 30): Collection
    {
        $startDate = now()->subDays($days)->format('Y-m-d');
        
        $unifiedRevenueQuery = $this->_getTotalRevenueQuery();
        $unifiedRevenue = DB::connection('mssql_clinic')->table(DB::raw("({$unifiedRevenueQuery->toSql()}) as unified_revenue"))
            ->mergeBindings($unifiedRevenueQuery);
        
        return $unifiedRevenue
            ->join('doctor as d', 'unified_revenue.doc_id', '=', 'd.id')
            ->select(
                'd.name_a as doctor_name',
                DB::raw('SUM(unified_revenue.amount) as total_revenue')
            )
            ->where(DB::raw("CONVERT(date, unified_revenue.transaction_date)"), '>=', $startDate)
            ->whereNotNull('d.name_a')
            ->where('d.name_a', '<>', '')
            ->groupBy('d.name_a')
            ->orderByDesc('total_revenue')
            ->take(10)
            ->get();
    }
    
    public function getAverageRevenuePerAppointment(int $days = 30): float
    {
        $startDate = now()->subDays($days)->format('Y-m-d');

        // FIXED: Use the subquery pattern here as well
        $unifiedQuery = $this->_getTotalRevenueQuery();
        $totalRevenue = DB::connection('mssql_clinic')->table(DB::raw("({$unifiedQuery->toSql()}) as unified_revenue"))
            ->mergeBindings($unifiedQuery)
            ->where(DB::raw("CONVERT(date, transaction_date)"), '>=', $startDate)
            ->sum('amount');

        $totalAppointments = $this->connection->table('appointment')
            ->where(DB::raw("CONVERT(date, app_dt)"), '>=', $startDate)
            ->count();
            
        if ($totalAppointments === 0) {
            return 0.0;
        }

        return $totalRevenue / $totalAppointments;
    }

    public function getAverageRevenuePerPatient(int $days = 30): float
    {
        $startDate = now()->subDays($days)->format('Y-m-d');

        $unifiedRevenueQuery = $this->_getTotalRevenueQuery();
        $unifiedRevenue = DB::connection('mssql_clinic')->table(DB::raw("({$unifiedRevenueQuery->toSql()}) as unified_revenue"))
            ->mergeBindings($unifiedRevenueQuery);
        
        $revenueData = $unifiedRevenue
            ->where(DB::raw("CONVERT(date, transaction_date)"), '>=', $startDate)
            ->select(
                DB::raw('SUM(amount) as total_revenue'),
                DB::raw('COUNT(DISTINCT pt_id) as unique_patients')
            )
            ->first();

        if (!$revenueData || $revenueData->unique_patients == 0) {
            return 0.0;
        }

        return $revenueData->total_revenue / $revenueData->unique_patients;
    }
    
    public function getPatientRetentionRate(int $days = 30): float
    {
        $startDate = now()->subDays($days)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $sql = "
            SELECT
                SUM(CASE WHEN p.id IS NOT NULL AND CONVERT(date, a.app_dt) > CONVERT(date, p.trans_dt) THEN 1 ELSE 0 END) as returning_patients,
                SUM(1) as total_appointments
            FROM appointment as a
            LEFT JOIN patient as p ON a.pt_id = p.id
            WHERE CONVERT(date, a.app_dt) >= ? AND CONVERT(date, a.app_dt) <= ?;
        ";

        $result = DB::connection('mssql_clinic')->selectOne($sql, [$startDate, $endDate]);

        if (!$result || $result->total_appointments == 0) {
            return 0.0;
        }

        return ($result->returning_patients / $result->total_appointments) * 100;
    }

    public function getTotalPaidForPatient(int $patientId): float
    {
        // FIXED: This method now correctly gets total paid from both tables
        $unifiedRevenueQuery = $this->_getTotalRevenueQuery();
        $unifiedRevenue = DB::connection('mssql_clinic')->table(DB::raw("({$unifiedRevenueQuery->toSql()}) as unified_revenue"))
            ->mergeBindings($unifiedRevenueQuery);
        
        return $unifiedRevenue->where('pt_id', $patientId)->sum('amount') ?? 0.0;
    }

    public function getTotalPaidForPatients(array $patientIds): Collection
    {
        if (empty($patientIds)) {
            return collect();
        }
        
        // FIXED: This method now correctly gets total paid from both tables
        $unifiedRevenueQuery = $this->_getTotalRevenueQuery();
        $unifiedRevenue = DB::connection('mssql_clinic')->table(DB::raw("({$unifiedRevenueQuery->toSql()}) as unified_revenue"))
            ->mergeBindings($unifiedRevenueQuery);
        
        return $unifiedRevenue
            ->whereIn('pt_id', $patientIds)
            ->select('pt_id', DB::raw('SUM(amount) as total_paid'))
            ->groupBy('pt_id')
            ->get()->keyBy('pt_id');
    }
    
    // --- The following methods did not need changes as they don't involve revenue ---

public function getNewVsReturningPatients(int $days = 30): \Illuminate\Support\Collection
{
    $startDate = now()->subDays($days)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');

    // This query now gets the total and the new patients count.
    // It is simpler and more robust.
    $sql = "
        SELECT
            CONVERT(VARCHAR(10), a.app_dt, 120) as date,
            COUNT(a.id) as total_appointments,
            SUM(CASE WHEN CONVERT(date, a.app_dt) = CONVERT(date, p.trans_dt) THEN 1 ELSE 0 END) as new_patients
        FROM appointment as a
        LEFT JOIN patient as p ON a.pt_id = p.id
        WHERE CONVERT(date, a.app_dt) >= ? AND CONVERT(date, a.app_dt) <= ?
        GROUP BY CONVERT(VARCHAR(10), a.app_dt, 120)
        ORDER BY date ASC;
    ";
    
    $dailyData = collect(DB::connection('mssql_clinic')->select($sql, [$startDate, $endDate]));

    // Now, we calculate 'returning_patients' here in PHP.
    // This GUARANTEES that new + returning will always equal the total for that day.
    foreach ($dailyData as $item) {
        $item->new_patients = (int)($item->new_patients ?? 0); // Ensure it's an integer
        $item->returning_patients = $item->total_appointments - $item->new_patients;
    }

    return $dailyData;
}
    
    public function findPatientByMobile(string $mobile): ?stdClass
    {
        return $this->connection->table('patient')->where('mobile', $mobile)->first();
    }

    public function findPatientById(int $patientId): ?stdClass
    {
        return $this->connection->table('patient')->where('id', $patientId)->first();
    }
    
    public function findPatientsByIds(array $patientIds): Collection
    {
        if (empty($patientIds)) {
            return collect();
        }
        return $this->connection->table('patient')->whereIn('id', $patientIds)->get()->keyBy('id');
    }
    
    public function getAppointmentsInWindow(string $startTime, string $endTime): Collection
    {
        $today_yyyymmdd = now()->format('Y/m/d'); 
        $statusesToFetch = [0, 1];

        return $this->connection->table('appointment')
            ->where('app_dt', $today_yyyymmdd) 
            ->whereTime('from_tm', '>=', $startTime)
            ->whereTime('from_tm', '<', $endTime)
            ->whereIn('app_status', $statusesToFetch)
            ->select(
                'id as appointment_id',
                'pt_name as full_name',
                'mobile',
                'from_tm as appointment_time',
                'doc_nm as doctor_name',
                'app_dt as appointment_date', 
                'app_status',
                'trans_dt as created_at'
            )
            ->get();
    }
    
    public function getAppointmentsFinishedInWindow(string $startTime, string $endTime): Collection
    {
        $today_yyyymmdd = now()->format('Y/m/d');

        return $this->connection->table('appointment')
            ->where('app_dt', $today_yyyymmdd)
            ->whereTime('to_tm', '>=', $startTime)
            ->whereTime('to_tm', '<', $endTime)
            ->whereNotNull('to_tm')
            ->select(
                'id as appointment_id',
                'pt_name as full_name',
                'mobile',
                'doc_nm as doctor_name',
                'app_dt',
                'to_tm'
            )
            ->get();
    }
      public function getAllDoctors(): Collection
    {
        return $this->connection->table('doctor')
            ->select('id', 'name_a')
            ->whereNotNull('name_a')
            ->where('name_a', '<>', '')
            ->orderBy('name_a')
            ->get();
    }

    /**
     * Calculate the average revenue per patient for a single doctor.
     */
     public function getAverageRevenuePerPatientForSingleDoctor(int $doctorId, int $days = 30): float // <-- FIX: Added $days parameter
    {
        // --- THIS IS THE FIX ---
        $startDate = now()->subDays($days)->format('Y-m-d');
        // --- END OF FIX ---

        $unifiedRevenueQuery = $this->_getTotalRevenueQuery();

        $unifiedRevenue = DB::connection('mssql_clinic')->table(
            DB::raw("({$unifiedRevenueQuery->toSql()}) as unified_revenue")
        )->mergeBindings($unifiedRevenueQuery);
        
        $result = $unifiedRevenue
            ->where('doc_id', $doctorId)
            // --- THIS IS THE FIX ---
            ->where(DB::raw("CONVERT(date, transaction_date)"), '>=', $startDate)
            // --- END OF FIX ---
            ->selectRaw('SUM(amount) as total_revenue, COUNT(DISTINCT pt_id) as unique_patients')
            ->first();

        if (!$result || $result->unique_patients == 0) {
            return 0.0;
        }

        return $result->total_revenue / $result->unique_patients;
    }
    public function getLapsedPatients(int $daysAgoStart = 365): \Illuminate\Support\Collection
{
    $cutoffDate = now()->subDays($daysAgoStart)->format('Y-m-d');
    $sql = "
        SELECT 
            p.id, 
            (p.fname_a + ' ' + p.sname_a + ' ' + p.lname_a) as full_name, 
            p.mobile
        FROM patient p
        JOIN (
            SELECT pt_id, MAX(CONVERT(date, app_dt)) as last_appointment_date
            FROM appointment
            GROUP BY pt_id
        ) as last_app ON p.id = last_app.pt_id
        WHERE last_app.last_appointment_date <= ?
        ORDER BY last_app.last_appointment_date DESC
    ";
    return collect(DB::connection('mssql_clinic')->select($sql, [$cutoffDate]));
}

public function getFirstAppointmentsForPatientsAfter(array $patientIds, \Carbon\Carbon $startDate): \Illuminate\Support\Collection
{
    if (empty($patientIds)) { return collect(); }
    $patientIdString = implode(',', $patientIds);
    $startDateString = $startDate->format('Y-m-d');
    $sql = "
        WITH NumberedAppointments AS (
            SELECT id as new_appointment_id, pt_id, CONVERT(date, app_dt) as new_appointment_date,
                   ROW_NUMBER() OVER(PARTITION BY pt_id ORDER BY app_dt ASC) as rn
            FROM appointment
            WHERE pt_id IN ({$patientIdString}) AND CONVERT(date, app_dt) >= ?
        )
        SELECT new_appointment_id, pt_id, new_appointment_date
        FROM NumberedAppointments WHERE rn = 1
    ";
    return collect(DB::connection('mssql_clinic')->select($sql, [$startDateString]));
}
}