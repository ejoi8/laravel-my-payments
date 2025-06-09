<div class="bg-white rounded-lg shadow p-6">
    @if($payment)
        <!-- Status Header -->
        <div class="text-center mb-6">
            <div class="text-4xl mb-2">
                @if($payment->status === 'paid')
                    <span class="text-green-600">✓</span>
                @elseif($payment->status === 'failed' || $payment->status === 'cancelled')
                    <span class="text-red-600">✗</span>
                @elseif($payment->status === 'refunded')
                    <span class="text-blue-600">↺</span>
                @else
                    <span class="text-yellow-600">⏳</span>
                @endif
            </div>
            <h2 class="text-xl font-semibold text-gray-900">
                Payment {{ ucfirst($payment->status) }}
            </h2>
            <p class="text-sm text-gray-500 mt-1">
                Reference: {{ $payment->reference_id }}
            </p>
        </div>

        <!-- Payment Details -->
        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-gray-50 p-3 rounded">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Amount</dt>
                    <dd class="mt-1 text-lg font-semibold text-gray-900">{{ $payment->formatted_amount }}</dd>
                </div>

                <div class="bg-gray-50 p-3 rounded">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Payment Method</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ str_replace('_', ' ', ucwords($payment->gateway)) }}</dd>
                </div>

                @if($payment->description)
                <div class="md:col-span-2 bg-gray-50 p-3 rounded">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Description</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $payment->description }}</dd>
                </div>
                @endif

                @if($payment->customer_name || $payment->customer_email)
                <div class="md:col-span-2 bg-gray-50 p-3 rounded">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Customer</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        @if($payment->customer_name)
                            <div>{{ $payment->customer_name }}</div>
                        @endif
                        @if($payment->customer_email)
                            <div class="text-gray-600">{{ $payment->customer_email }}</div>
                        @endif
                    </dd>
                </div>
                @endif

                <div class="bg-gray-50 p-3 rounded">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Created</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        {{ $payment->created_at ? $payment->created_at->format('M j, Y g:i A') : 'N/A' }}
                    </dd>
                </div>

                @if($payment->paid_at)
                <div class="bg-gray-50 p-3 rounded">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Paid At</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        {{ $payment->paid_at->format('M j, Y g:i A') }}
                    </dd>
                </div>
                @endif

                @if($payment->gateway_transaction_id)
                <div class="md:col-span-2 bg-gray-50 p-3 rounded">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Transaction ID</dt>
                    <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $payment->gateway_transaction_id }}</dd>
                </div>
                @endif
            </div>
        </div>

        <!-- Actions -->
        <div class="mt-6 text-center space-y-3">
            @if($payment->is_manual_payment && $payment->status === 'pending' && !$payment->proof_file_path)
                <a href="{{ route('payment-gateway.manual.upload', $payment->id) }}" 
                   class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded transition">
                    Upload Payment Proof
                </a>
            @endif

            @if($payment->is_manual_payment && $payment->proof_file_path && $payment->status === 'pending')
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-2 rounded">
                    Payment proof uploaded. Awaiting verification.
                </div>
            @endif

            @if($payment->status === 'pending')
                <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-2 rounded">
                    Payment is being processed.
                </div>
            @endif
        </div>

    @else
        <div class="text-center py-8">
            <div class="text-4xl text-gray-300 mb-4">?</div>
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Payment Not Found</h2>
            <p class="text-gray-600">The payment information could not be found.</p>
        </div>
    @endif
</div>
