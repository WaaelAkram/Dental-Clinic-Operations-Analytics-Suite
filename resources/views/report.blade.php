<x-guest-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Lapsed Patient Campaign Report
            </h2>

            <!-- KPI Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 shadow rounded-lg text-center">
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Total Messages Sent</h3>
                    <p class="mt-1 text-3xl font-semibold text-indigo-600">{{ number_format($stats['total_sent']) }}</p>
                </div>
                <div class="bg-white p-6 shadow rounded-lg text-center">
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Patients Returned (Conversions)</h3>
                    <p class="mt-1 text-3xl font-semibold text-indigo-600">{{ number_format($stats['total_conversions']) }}</p>
                </div>
                <div class="bg-white p-6 shadow rounded-lg text-center">
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Conversion Rate</h3>
                    <p class="mt-1 text-3xl font-semibold text-indigo-600">{{ number_format($stats['conversion_rate'], 2) }}%</p>
                </div>
            </div>

            <!-- Recently Converted Patients Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Recently Converted Patients</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient Name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mobile</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Messaged</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Returned</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($conversions as $conversion)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $conversion->patient->full_name ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $conversion->mobile }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $conversion->message_sent_at->format('Y-m-d') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $conversion->new_appointment_date->format('Y-m-d') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">No conversions recorded yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $conversions->links() }}
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <a href="{{ route('dashboard') }}" class="text-sm text-gray-600 hover:text-gray-900">← Back to Dashboard</a>
            </div>

        </div>
    </div>
</x-guest-layout>