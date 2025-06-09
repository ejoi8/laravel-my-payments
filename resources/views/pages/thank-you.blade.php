<x-payment-gateway::payment-layout title="Payment Complete">
    <div class="text-center mb-8">
        <div class="text-6xl text-green-600 mb-4">✓</div>
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Payment Successful!</h1>
        <p class="text-gray-600">Thank you for your payment. Your transaction has been completed.</p>
    </div>

    @if($payment)
        @include('payment-gateway::partials.payment-status', ['payment' => $payment])
        
        <!-- Additional buyer information -->
        <div class="mt-6 bg-green-50 border border-green-200 text-green-700 p-4 rounded-lg text-center">
            <p class="font-medium">What's next?</p>
            <p class="text-sm mt-1">You will receive a confirmation email shortly. Keep this reference number for your records: <strong>{{ $payment->reference_id }}</strong></p>
        </div>
    @endif
</x-payment-gateway::payment-layout>
