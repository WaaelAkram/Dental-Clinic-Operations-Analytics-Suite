<x-guest-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Display Success/Error Alerts --}}
            @if (session('status'))
                <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
                    {{ session('status') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Marketing Exclusion Card --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Marketing Exclusion Tool</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        To permanently stop a patient from receiving marketing messages, enter their mobile number below. This will not affect appointment reminders or feedback requests.
                    </p>
                    <form method="POST" action="{{ route('marketing.exclusions.add') }}" class="mt-4">
                        @csrf
                        <div class="max-w-xl">
                            <x-input-label for="mobile" value="Patient Mobile Number" />
                            <div class="flex items-center gap-4 mt-1">
                                <x-text-input id="mobile" class="block w-full" type="text" name="mobile" :value="old('mobile')" required />
                                <x-danger-button>
                                    {{ __('Exclude Patient') }}
                                </x-danger-button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Link to Marketing Report --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Performance Reports</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        View the performance and conversion rates of automated campaigns.
                    </p>
                    <a href="{{ route('marketing.report') }}" class="font-semibold text-indigo-600 hover:text-indigo-800">View Lapsed Patient Campaign Report →</a>
                </div>
            </div>

        </div>
    </div>
</x-guest-layout>