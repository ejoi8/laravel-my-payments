<?php

namespace Ejoi8\PaymentGateway\Livewire;

use Livewire\Component;
use Ejoi8\PaymentGateway\Models\Payment;
use Ejoi8\PaymentGateway\Services\PaymentService;

class PaymentStatus extends Component
{
    public $payment;
    public $refreshInterval = 5000; // 5 seconds
    public $autoRefresh = true;

    protected $listeners = ['refreshPaymentStatus' => 'refresh'];

    public function mount($paymentId = null, Payment $paymentModel = null)
    {
        if ($paymentModel) {
            $this->payment = $paymentModel;
        } elseif ($paymentId) {
            $paymentService = app(PaymentService::class);
            $this->payment = $paymentService->getPayment($paymentId);
        }

        // Stop auto-refresh if payment is completed or failed
        if ($this->payment && in_array($this->payment->status, ['paid', 'failed', 'cancelled'])) {
            $this->autoRefresh = false;
        }
    }

    public function refresh()
    {
        if ($this->payment) {
            $this->payment->refresh();
            
            // Stop auto-refresh if payment is completed
            if (in_array($this->payment->status, ['paid', 'failed', 'cancelled'])) {
                $this->autoRefresh = false;
            }
        }
    }

    public function verifyPayment()
    {
        if (!$this->payment || !$this->payment->gateway_transaction_id) {
            return;
        }

        $paymentService = app(PaymentService::class);
        $result = $paymentService->verifyPayment(
            $this->payment->gateway, 
            $this->payment->gateway_transaction_id
        );

        if ($result['success']) {
            $this->refresh();
            
            if ($result['status'] === 'paid' && $this->payment->status !== 'paid') {
                // Update payment status if it changed
                $this->payment->markAsPaid($this->payment->gateway_transaction_id, $result['data'] ?? null);
                $this->refresh();
            }
        }
    }

    public function getStatusColorProperty()
    {
        return match($this->payment->status ?? 'pending') {
            'paid' => 'text-green-600',
            'failed', 'cancelled' => 'text-red-600',
            'refunded' => 'text-blue-600',
            default => 'text-yellow-600'
        };
    }

    public function getStatusIconProperty()
    {
        return match($this->payment->status ?? 'pending') {
            'paid' => '✓',
            'failed', 'cancelled' => '✗',
            'refunded' => '↺',
            default => '⏳'
        };
    }

    public function render()
    {
        return view('payment-gateway::livewire.payment-status');
    }
}
