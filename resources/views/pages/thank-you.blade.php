<x-payment-gateway::payment-layout title="Payment Success">
    <div class="text-center">
        <div class="text-6xl mb-6 text-green-600">✓</div>
        <h1 class="text-3xl font-bold text-gray-900 mb-4">Payment Successful!</h1>
        <p class="text-lg text-gray-600 mb-8">Thank you for your payment.</p>
        
        @if($payment)
            <livewire:payment-status :payment-model="$payment" :auto-refresh="false" />
        @endif
        
        <div class="mt-8">
            <a href="/" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-md transition duration-200">
                Continue
            </a>
        </div>
    </div>
</x-payment-gateway::payment-layout>
