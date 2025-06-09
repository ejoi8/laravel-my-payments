<div class="bg-white rounded-lg shadow-md p-6 max-w-2xl mx-auto" 
     @if($autoRefresh) 
         wire:poll.{{ $refreshInterval }}ms="refresh" 
     @endif>
    
    @if($payment)
        <!-- Payment Header -->
        <div class="text-center mb-8">
            <div class="text-6xl mb-4 {{ $this->statusColor }}">
                {{ $this->statusIcon }}
            </div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">
                Payment {{ ucfirst($payment->status) }}
            </h2>
            <p class="text-gray-500 text-sm">
                Reference: <span class="font-mono font-medium">{{ $payment->reference_id }}</span>
            </p>
            
            <!-- Status Badge -->
            <div class="mt-3">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $payment->status_badge }}">
                    {{ ucfirst($payment->status) }}
                </span>
            </div>
        </div>

        <!-- Payment Details Card -->
        <div class="bg-gray-50 border border-gray-100 rounded-lg p-5 mb-6">
            <h3 class="text-md font-medium text-gray-700 mb-4">Payment Details</h3>
            <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
                <!-- Amount -->
                <div class="bg-white p-3 rounded shadow-sm">
                    <dt class="text-xs uppercase font-medium text-gray-500">Amount</dt>
                    <dd class="mt-1 text-lg font-semibold text-gray-900">
                        {{ $payment->formatted_amount }}
                    </dd>
                </div>

                <!-- Payment Method -->
                <div class="bg-white p-3 rounded shadow-sm">
                    <dt class="text-xs uppercase font-medium text-gray-500">Payment Method</dt>
                    <dd class="mt-1 text-sm text-gray-900 capitalize">
                        {{ str_replace('_', ' ', $payment->gateway) }}
                    </dd>
                </div>

                <!-- Description (if available) -->
                @if($payment->description)
                <div class="sm:col-span-2 bg-white p-3 rounded shadow-sm">
                    <dt class="text-xs uppercase font-medium text-gray-500">Description</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $payment->description }}</dd>
                </div>
                @endif

                <!-- Customer Info Section -->
                @if($payment->customer_name || $payment->customer_email)
                <div class="sm:col-span-2 bg-white p-3 rounded shadow-sm">
                    <dt class="text-xs uppercase font-medium text-gray-500 mb-2">Customer Information</dt>
                    @if($payment->customer_name)
                    <div class="flex items-baseline mt-1">
                        <span class="text-xs text-gray-500 w-20">Name:</span>
                        <span class="text-sm text-gray-800">{{ $payment->customer_name }}</span>
                    </div>
                    @endif
                    
                    @if($payment->customer_email)
                    <div class="flex items-baseline mt-1">
                        <span class="text-xs text-gray-500 w-20">Email:</span>
                        <span class="text-sm text-gray-800">{{ $payment->customer_email }}</span>
                    </div>
                    @endif
                </div>
                @endif

                <!-- Timestamps -->                <div class="bg-white p-3 rounded shadow-sm">
                    <dt class="text-xs uppercase font-medium text-gray-500">Created</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        @if($payment && $payment->created_at)
                            {{ $payment->created_at->format('M j, Y \a\t g:i A') }}
                        @else
                            <span class="text-gray-400">Not available</span>
                        @endif
                    </dd>
                </div>

                @if($payment && $payment->paid_at)
                <div class="bg-white p-3 rounded shadow-sm">
                    <dt class="text-xs uppercase font-medium text-gray-500">Paid</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        {{ $payment->paid_at->format('M j, Y \a\t g:i A') }}
                    </dd>
                </div>
                @endif

                <!-- Transaction ID (if available) -->
                @if($payment->gateway_transaction_id)
                <div class="sm:col-span-2 bg-white p-3 rounded shadow-sm">
                    <dt class="text-xs uppercase font-medium text-gray-500">Transaction ID</dt>
                    <dd class="mt-1 text-sm text-gray-900 font-mono">
                        {{ $payment->gateway_transaction_id }}
                    </dd>
                </div>
                @endif
            </dl>
        </div>

        <!-- Action Buttons -->
        <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
            {{-- @if($payment->status === 'pending' && !$autoRefresh)
                <button wire:click="verifyPayment" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition duration-200 shadow-sm flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Check Payment Status
                </button>
            @endif --}}

            @if($payment->is_manual_payment && $payment->status === 'pending' && !$payment->proof_file_path)
                <a href="{{ route('payment-gateway.manual.upload', $payment->id) }}" 
                   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded transition duration-200 shadow-sm flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    Upload Payment Proof
                </a>
            @endif

            @if($payment->is_manual_payment && $payment->proof_file_path && $payment->status === 'pending')
                <div class="text-center bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded shadow-sm">
                    <div class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p>Payment proof uploaded. Awaiting verification.</p>
                    </div>
                </div>
            @endif
        </div>

        <!-- Auto-refresh indicator -->
        @if($autoRefresh && $payment->status === 'pending')
            <div class="mt-4 text-center text-xs text-gray-500 bg-gray-50 py-2 px-3 rounded-md border border-gray-200">
                <div class="flex items-center justify-center">
                    <svg class="animate-spin -ml-1 mr-2 h-3 w-3 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Auto-refreshing every {{ $refreshInterval / 1000 }} seconds
                </div>
            </div>
        @endif

    @else
        <!-- Payment Not Found State -->
        <div class="text-center py-8">
            <div class="text-5xl mb-4 text-gray-300 bg-gray-100 h-24 w-24 rounded-full flex items-center justify-center mx-auto">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h2 class="text-xl font-semibold text-gray-900 mb-2">Payment Not Found</h2>
            <p class="text-gray-600 max-w-sm mx-auto">The payment you're looking for could not be found or may have been removed.</p>
        </div>
    @endif
</div>
