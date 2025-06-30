<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة متابعة أداء العيادة</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <!-- ADDING ARABIC FONT -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { 
            background-color: #f3f4f6; 
            font-family: 'Tajawal', sans-serif;
        }
    </style>
</head>
<body class="font-sans antialiased">
    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">لوحة متابعة أداء العيادة</h1>
            <p class="text-gray-600">نظرة عامة.</p>
        </header>

        <!-- ========== KPI Cards ========== -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <!-- Today's Revenue Card -->
            <div class="bg-white p-6 shadow rounded-lg text-center">
                <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">إيرادات اليوم</h3>
                <p class="mt-1 text-3xl font-semibold text-indigo-600">{{ number_format($data['kpis']->total_revenue_today ?? 0, 2) }} <span class="text-lg">ريال</span></p>
            </div>
            
           

            <!-- Average Revenue Per Patient Card (AR) -->
            <div class="bg-white p-6 shadow rounded-lg text-center">
                <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">متوسط الإيرادات لكل مريض (30 يوم)</h3>
                <p class="mt-1 text-3xl font-semibold text-indigo-600">{{ number_format($data['arpp'] ?? 0, 2) }} <span class="text-lg">ريال</span></p>
            </div>

            <!-- Appointments Today Card -->
            <div class="bg-white p-6 shadow rounded-lg text-center">
                <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">مواعيد اليوم</h3>
                <p class="mt-1 text-3xl font-semibold text-indigo-600">{{ $data['kpis']->appointments_today ?? 0 }}</p>
            </div>

        

            <!-- New Patients Card -->
            <div class="bg-white p-6 shadow rounded-lg text-center">
                <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">مرضى جدد (هذا الشهر)</h3>
                <p class="mt-1 text-3xl font-semibold text-indigo-600">{{ $data['kpis']->new_patients_this_month ?? 0 }}</p>
            </div>
        </div>

        <!-- ========== Charts Grid ========== -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Monthly Revenue Chart -->
            <div class="bg-white p-6 shadow rounded-lg">
                <h3 class="font-semibold text-lg text-gray-800 mb-4">الإيرادات الشهرية (آخر 12 شهر)</h3>
                <div id="monthlyRevenueChart"></div>
            </div>

            <!-- Revenue Per Doctor Chart -->
            <div class="bg-white p-6 shadow rounded-lg">
                <h3 class="font-semibold text-lg text-gray-800 mb-4">دخل الدكاترة(آخر 30 يوم)</h3>
                <div id="doctorRevenueChart"></div>
            </div>
        </div>
        
        <!-- New vs Returning Patients Chart -->
        <div class="bg-white p-6 shadow rounded-lg mt-8">
            <h3 class="font-semibold text-lg text-gray-800 mb-4">المرضى الجدد و المرضى القدامى (آخر 30 يوم)</h3>
            <div id="patientMixChart"></div>
        </div>
    </div>

    <!-- Charting Library and Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // --- Monthly Revenue Chart ---
            const monthlyRevenueData = {!! json_encode($data['monthlyRevenue']) !!};
            if (monthlyRevenueData && monthlyRevenueData.length > 0) {
                const revenueOptions = {
                    series: [{
                        name: 'الإيرادات (ريال)',
                        data: monthlyRevenueData.map(item => parseFloat(item.total_revenue).toFixed(2))
                    }],
                    chart: { type: 'area', height: 350, toolbar: { show: false }, zoom: { enabled: false } },
                    xaxis: { categories: monthlyRevenueData.map(item => item.month), labels: { style: { colors: '#6b7280', fontFamily: 'Tajawal, sans-serif' } } },
                    yaxis: { title: { text: 'ريال', style: { color: '#6b7280', fontFamily: 'Tajawal, sans-serif' } }, labels: { style: { style: { colors: '#6b7280', fontFamily: 'Tajawal, sans-serif' } } } },
                    stroke: { curve: 'smooth' },
                    dataLabels: { enabled: false },
                    tooltip: { x: { format: 'MMMM yyyy' } }
                };
                new ApexCharts(document.querySelector("#monthlyRevenueChart"), revenueOptions).render();
            }

            // --- Revenue Per Doctor Chart ---
            const doctorRevenueData = {!! json_encode($data['doctorRevenue']) !!};
            if (doctorRevenueData && doctorRevenueData.length > 0) {
                const doctorOptions = {
                    series: [{
                        name: 'الإيرادات',
                        data: doctorRevenueData.map(item => parseFloat(item.total_revenue).toFixed(2))
                    }],
                    chart: { type: 'bar', height: 350, toolbar: { show: false } },
                    plotOptions: { bar: { horizontal: true } },
                    xaxis: { categories: doctorRevenueData.map(item => item.doctor_name), labels: { style: { colors: '#6b7280', fontFamily: 'Tajawal, sans-serif' } } },
                    yaxis: { labels: { style: { colors: '#6b7280', fontFamily: 'Tajawal, sans-serif' } } },
                    dataLabels: { enabled: false },
                    tooltip: { y: { formatter: (val) => `${val} ريال` } }
                };
                new ApexCharts(document.querySelector("#doctorRevenueChart"), doctorOptions).render();
            }

            // --- New vs Returning Patients Chart ---
            const patientMixData = {!! json_encode($data['patientMix']) !!};
            if (patientMixData && patientMixData.length > 0) {
                const patientMixOptions = {
                    series: [{
                        name: 'مرضى جدد',
                        data: patientMixData.map(item => item.new_patients)
                    }, {
                        name: 'مرضى قدامى',
                        data: patientMixData.map(item => item.returning_patients)
                    }],
                    chart: { type: 'bar', height: 350, stacked: true, toolbar: { show: false } },
                    plotOptions: { bar: { horizontal: false } },
                    xaxis: { 
                        type: 'category', 
                        categories: patientMixData.map(item => item.date), 
                        labels: { style: { colors: '#6b7280', fontFamily: 'Tajawal, sans-serif' } } 
                    },
                    yaxis: { labels: { style: { colors: '#6b7280', fontFamily: 'Tajawal, sans-serif' } } },
                    legend: { position: 'top', fontFamily: 'Tajawal, sans-serif' },
                    fill: { opacity: 1 }
                };
                new ApexCharts(document.querySelector("#patientMixChart"), patientMixOptions).render();
            }
        });
    </script>
    
       <!-- ========== Doctor-Specific Average Revenue (ARABIC) ========== -->
        <div class="bg-white p-6 shadow rounded-lg mt-8">
            <h3 class="font-semibold text-lg text-gray-800 mb-4">متوسط الإيرادات لكل مريض  (آخر 30 يوم)</h3>

            <form action="{{ route('public.dashboard.ar') }}" method="GET" class="mb-4">
                <label for="selected_doctor_id" class="block text-sm font-medium text-gray-700">اختر الدكتور:</label>
                <select name="selected_doctor_id" id="selected_doctor_id" onchange="this.form.submit()" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                    <option value="">-- متوسط العيادة بالكامل --</option>
                    @foreach($data['allDoctors'] as $doctor)
                        <option value="{{ $doctor->id }}" @if($doctor->id == $data['selectedDoctorId']) selected @endif>
                            {{ $doctor->name_a }}
                        </option>
                    @endforeach
                </select>
            </form>

            <div class="text-center border-t pt-4">
                @if(isset($data['avgRevPerPatientForDoctor']))
                    <p class="text-3xl font-semibold text-indigo-600">{{ number_format($data['avgRevPerPatientForDoctor'], 2) }} <span class="text-lg">ريال</span></p>
                    <p class="text-sm text-gray-500">متوسط الإيرادات لكل مريض للطبيب المحدد.</p>
                @else
                    <p class="text-3xl font-semibold text-gray-700">{{ number_format($data['arpp'], 2) }} <span class="text-lg">ريال</span></p>
                    <p class="text-sm text-gray-500">متوسط الإيرادات لكل مريض للعيادة بأكملها.</p>
                @endif
            </div>
        </div>

    </div>

    <!-- Charting Library and Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        // ... (Keep your existing Arabic chart scripts) ...
        // NO NEW SCRIPT IS NEEDED
    </script>
</body>
</html>
</body>
</html>