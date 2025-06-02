<?php

namespace Ejoi8\PaymentGateway\Livewire;

use Livewire\Component;
use Ejoi8\PaymentGateway\Services\PaymentService;

class PaymentForm extends Component
{
    public $amount;
    public $gateway;
    public $currency = 'MYR';
    public $description;
    public $customer_name;
    public $customer_email;
    public $customer_phone;
    public $metadata = [];
    
    public $availableGateways = [];
    public $loading = false;

    protected $rules = [
        'amount' => 'required|numeric|min:0.01',
        'gateway' => 'required|string',
        'currency' => 'required|string|size:3',
        'description' => 'nullable|string|max:255',
        'customer_name' => 'nullable|string|max:255',
        'customer_email' => 'nullable|email|max:255',
        'customer_phone' => 'nullable|string|max:20',
    ];

    public function mount(PaymentService $paymentService, $initialData = [])
    {
        $this->availableGateways = $paymentService->getAvailableGateways();
        $this->gateway = array_key_first($this->availableGateways) ?: config('payment-gateway.default_gateway');
        $this->currency = config('payment-gateway.currency', 'MYR');
        
        // Set initial data if provided
        foreach ($initialData as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function submit()
    {
        $this->validate();
        
        $this->loading = true;
        
        $paymentService = app(PaymentService::class);
        
        $result = $paymentService->createPayment([
            'amount' => $this->amount,
            'gateway' => $this->gateway,
            'currency' => $this->currency,
            'description' => $this->description,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'metadata' => $this->metadata,
        ]);
        
        $this->loading = false;
        
        if ($result['success']) {
            return redirect($result['payment_url']);
        } else {
            $this->addError('payment', $result['message']);
        }
    }

    public function render()
    {
        return view('payment-gateway::livewire.payment-form');
    }
}
