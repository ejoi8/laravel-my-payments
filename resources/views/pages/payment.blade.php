<x-payment-gateway::payment-layout title="Payment Details">
    @if($payment)
        @include('payment-gateway::partials.payment-status', ['payment' => $payment])
    @else
        <div class="text-center py-8">
            <h1 class="text-2xl font-bold text-gray-900 mb-4">Payment Not Found</h1>
            <p class="text-gray-600">The payment you're looking for could not be found.</p>
        </div>
    @endif
</x-payment-gateway::payment-layout>
