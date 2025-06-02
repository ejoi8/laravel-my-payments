<div class="bg-white rounded-lg shadow-md p-6" 
     @if($autoRefresh) 
         wire:poll.{{ $refreshInterval }}ms="refresh" 
     @endif>
    
    @if($payment)
        <div class="text-center mb-6">
            <div class="text-6xl mb-4 {{ $this->statusColor }}">
                {{ $this->statusIcon }}
            </div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Payment {{ ucfirst($payment->status) }}
            </h2>
            <p class="text-gray-600">
                Payment ID: <span class="font-mono">{{ $payment->reference_id }}</span>
            </p>
        </div>

        <!-- Payment Details -->
        <div class="border-t border-gray-200 pt-6">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Amount</dt>
                    <dd class="mt-1 text-lg font-semibold text-gray-900">
                        {{ $payment->formatted_amount }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Payment Method</dt>
                    <dd class="mt-1 text-sm text-gray-900 capitalize">
                        {{ str_replace('_', ' ', $payment->gateway) }}
                    </dd>
                </div>

                @if($payment->description)
                <div class="sm:col-span-2">
                    <dt class="text-sm font-medium text-gray-500">Description</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $payment->description }}</dd>
                </div>
                @endif

                @if($payment->customer_name)
                <div>
                    <dt class="text-sm font-medium text-gray-500">Customer</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $payment->customer_name }}</dd>
                </div>
                @endif

                @if($payment->customer_email)
                <div>
                    <dt class="text-sm font-medium text-gray-500">Email</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $payment->customer_email }}</dd>
                </div>
                @endif

                <div>
                    <dt class="text-sm font-medium text-gray-500">Created</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        {{ $payment->created_at->format('M j, Y \a\t g:i A') }}
                    </dd>
                </div>

                @if($payment->paid_at)
                <div>
                    <dt class="text-sm font-medium text-gray-500">Paid</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        {{ $payment->paid_at->format('M j, Y \a\t g:i A') }}
                    </dd>
                </div>
                @endif

                @if($payment->gateway_transaction_id)
                <div class="sm:col-span-2">
                    <dt class="text-sm font-medium text-gray-500">Transaction ID</dt>
                    <dd class="mt-1 text-sm text-gray-900 font-mono">
                        {{ $payment->gateway_transaction_id }}
                    </dd>
                </div>
                @endif
            </dl>
        </div>

        <!-- Status Badge -->
        <div class="mt-6 flex justify-center">
            <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium {{ $payment->status_badge }}">
                {{ ucfirst($payment->status) }}
            </span>
        </div>

        <!-- Action Buttons -->
        <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
            @if($payment->status === 'pending' && !$autoRefresh)
                <button wire:click="verifyPayment" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition duration-200">
                    Check Payment Status
                </button>
            @endif

            @if($payment->is_manual_payment && $payment->status === 'pending' && !$payment->proof_file_path)
                <a href="{{ route('payment-gateway.manual.upload', $payment->id) }}" 
                   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md transition duration-200 text-center">
                    Upload Payment Proof
                </a>
            @endif

            @if($payment->is_manual_payment && $payment->proof_file_path && $payment->status === 'pending')
                <div class="text-center text-sm text-gray-600">
                    <p>Payment proof uploaded. Awaiting verification.</p>
                </div>
            @endif
        </div>

        <!-- Auto-refresh indicator -->
        @if($autoRefresh && $payment->status === 'pending')
            <div class="mt-4 text-center text-xs text-gray-500">
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
        <div class="text-center py-8">
            <div class="text-6xl mb-4 text-gray-400">❓</div>
            <h2 class="text-xl font-semibold text-gray-900 mb-2">Payment Not Found</h2>
            <p class="text-gray-600">The payment you're looking for could not be found.</p>
        </div>
    @endif
</div>
