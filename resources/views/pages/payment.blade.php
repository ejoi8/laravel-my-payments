<x-payment-gateway::payment-layout title="Payment Details">
    @if($payment)
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Payment Details</h1>
            <p class="text-gray-600">Review your payment information below</p>
        </div>
        
        @include('payment-gateway::partials.payment-status', ['payment' => $payment])
    @else
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <div class="text-4xl text-gray-300 mb-4">?</div>
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Payment Not Found</h2>
            <p class="text-gray-600">The payment you're looking for could not be found.</p>
        </div>
    @endif
</x-payment-gateway::payment-layout>
