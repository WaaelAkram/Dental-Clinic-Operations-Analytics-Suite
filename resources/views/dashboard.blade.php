<x-guest-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                Marketing & Operations Dashboard
            </h2>

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

            {{-- Manual Broadcast Card --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Manual Message Broadcast</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Paste a list of numbers (separated by spaces, commas, or new lines) to send a one-time message. Numbers on the exclusion list will be skipped automatically.
                    </p>
                    <form method="POST" action="{{ route('marketing.manual.send') }}" class="mt-4">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <x-input-label for="manual_numbers" value="Mobile Numbers" />
                                <textarea id="manual_numbers" name="manual_numbers" rows="4" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('manual_numbers') }}</textarea>
                                <x-input-error :messages="$errors->get('manual_numbers')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="manual_message" value="Message Content" />
                                <textarea id="manual_message" name="manual_message" rows="3" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('manual_message') }}</textarea>
                                <x-input-error :messages="$errors->get('manual_message')" class="mt-2" />
                            </div>
                        </div>
                        <div class="flex justify-end mt-4">
                            <x-primary-button>
                                {{ __('Queue Manual Broadcast') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Marketing Exclusion Card --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Marketing Exclusion Tool</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        To permanently stop a patient from receiving marketing messages, enter their mobile number below.
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