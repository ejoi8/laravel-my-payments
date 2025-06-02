<x-payment-gateway::payment-layout title="Payment Form">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Make Payment</h2>
        
        <form wire:submit.prevent="submit" class="space-y-6">
            <!-- Amount -->
            <div>
                <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">
                    Amount *
                </label>
                <div class="relative">
                    <input type="number" 
                           id="amount"
                           wire:model="amount" 
                           step="0.01" 
                           min="0.01"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="0.00">
                    <span class="absolute right-3 top-2 text-gray-500">{{ $currency }}</span>
                </div>
                @error('amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <!-- Gateway Selection -->
            <div>
                <label for="gateway" class="block text-sm font-medium text-gray-700 mb-2">
                    Payment Method *
                </label>
                <select id="gateway" 
                        wire:model="gateway"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    @foreach($availableGateways as $gatewayKey => $gatewayInstance)
                        <option value="{{ $gatewayKey }}">
                            {{ ucfirst(str_replace('_', ' ', $gatewayKey)) }}
                        </option>
                    @endforeach
                </select>
                @error('gateway') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <!-- Description -->
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                    Description
                </label>
                <input type="text" 
                       id="description"
                       wire:model="description"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="What is this payment for?">
                @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <!-- Customer Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-2">
                        Customer Name
                    </label>
                    <input type="text" 
                           id="customer_name"
                           wire:model="customer_name"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Full Name">
                    @error('customer_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="customer_email" class="block text-sm font-medium text-gray-700 mb-2">
                        Email Address
                    </label>
                    <input type="email" 
                           id="customer_email"
                           wire:model="customer_email"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="email@example.com">
                    @error('customer_email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="customer_phone" class="block text-sm font-medium text-gray-700 mb-2">
                    Phone Number
                </label>
                <input type="tel" 
                       id="customer_phone"
                       wire:model="customer_phone"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="+60123456789">
                @error('customer_phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <!-- Submit Button -->
            <div class="pt-4">
                <button type="submit" 
                        wire:loading.attr="disabled"
                        class="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-medium py-3 px-4 rounded-md transition duration-200 flex items-center justify-center">
                    <span wire:loading.remove>
                        Proceed to Payment ({{ $currency }} {{ number_format($amount ?? 0, 2) }})
                    </span>
                    <span wire:loading class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Processing...
                    </span>
                </button>
            </div>

            @error('payment')
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    {{ $message }}
                </div>
            @enderror
        </form>
    </div>
</x-payment-gateway::payment-layout>
