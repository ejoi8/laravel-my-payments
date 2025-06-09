<x-payment-gateway::payment-layout title="Payment Failed">
    <div class="text-center mb-8">
        <div class="text-6xl text-red-600 mb-4">✗</div>
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Payment Failed</h1>
        <p class="text-gray-600">Unfortunately, your payment could not be processed.</p>
    </div>

    @if($payment)
        @include('payment-gateway::partials.payment-status', ['payment' => $payment])
        
        <!-- Additional information for buyer -->
        <div class="mt-6 bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg text-center">
            <p class="font-medium">What can you do?</p>
            <p class="text-sm mt-1">Please check your payment details and try again, or contact support with reference: <strong>{{ $payment->reference_id }}</strong></p>
        </div>
    @else
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <div class="text-4xl text-gray-300 mb-4">?</div>
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Payment Information Not Available</h2>
            <p class="text-gray-600">Unable to retrieve payment details.</p>
        </div>
    @endif
</x-payment-gateway::payment-layout>
