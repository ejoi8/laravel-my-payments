<x-payment-gateway::payment-layout title="Payment Failed">
    <div class="text-center">
        <div class="text-6xl mb-6 text-red-600">✗</div>
        <h1 class="text-3xl font-bold text-gray-900 mb-4">Payment Failed</h1>
        <p class="text-lg text-gray-600 mb-8">Unfortunately, your payment could not be processed.</p>
          @if($payment)
            @include('payment-gateway::partials.payment-status', ['payment' => $payment])
        @endif
    </div>
</x-payment-gateway::payment-layout>
