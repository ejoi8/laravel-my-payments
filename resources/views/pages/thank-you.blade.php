<x-payment-gateway::payment-layout title="Payment Success">
    <div class="text-center">
        <div class="text-6xl mb-6 text-green-600">✓</div>
        <h1 class="text-3xl font-bold text-gray-900 mb-4">Payment Successful!</h1>
        <p class="text-lg text-gray-600 mb-8">Thank you for your payment.</p>
        @if($payment)
            @include('payment-gateway::partials.payment-status', ['payment' => $payment])
        @endif
        
    </div>
</x-payment-gateway::payment-layout>
