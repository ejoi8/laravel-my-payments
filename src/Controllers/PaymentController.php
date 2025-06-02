<?php

namespace Ejoi8\PaymentGateway\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ejoi8\PaymentGateway\Services\PaymentService;

class PaymentController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function create(Request $request)
    {
        $validated = $request->validate([
            'gateway' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|size:3',
            'description' => 'nullable|string|max:255',
            'customer_name' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'metadata' => 'nullable|array',
        ]);

        $result = $this->paymentService->createPayment($validated);

        if ($result['success']) {
            if (isset($result['requires_upload']) && $result['requires_upload']) {
                // For manual payments, redirect to upload page
                return redirect($result['payment_url']);
            }
            
            // For other gateways, redirect to payment URL
            return redirect($result['payment_url']);
        }

        return back()->withErrors(['payment' => $result['message']]);
    }

    public function callback(Request $request, string $gateway)
    {
        $data = $request->all();
        
        $result = $this->paymentService->handleCallback($gateway, $data);

        if ($result['success']) {
            $payment = $result['payment'];
            
            if ($result['status'] === 'paid') {
                return redirect()->route(config('payment-gateway.success_route', 'payment.success'))
                    ->with('payment', $payment);
            } else {
                return redirect()->route(config('payment-gateway.failed_route', 'payment.failed'))
                    ->with('payment', $payment);
            }
        }

        return response()->json(['error' => $result['message']], 400);
    }

    public function success(Request $request)
    {
        $paymentId = $request->get('payment_id');
        $payment = null;
        
        if ($paymentId) {
            $payment = $this->paymentService->getPayment($paymentId);
        } elseif (session('payment')) {
            $payment = session('payment');
        }

        return view('payment-gateway::pages.thank-you', compact('payment'));
    }

    public function failed(Request $request)
    {
        $paymentId = $request->get('payment_id');
        $payment = null;
        
        if ($paymentId) {
            $payment = $this->paymentService->getPayment($paymentId);
        } elseif (session('payment')) {
            $payment = session('payment');
        }

        return view('payment-gateway::pages.payment-failed', compact('payment'));
    }

    public function show(string $paymentId)
    {
        $payment = $this->paymentService->getPayment($paymentId);
        
        if (!$payment) {
            abort(404, 'Payment not found');
        }

        return view('payment-gateway::pages.payment', compact('payment'));
    }

    public function manualUpload(Request $request, string $paymentId)
    {
        $payment = $this->paymentService->getPayment($paymentId);
        
        if (!$payment || $payment->gateway !== 'manual') {
            abort(404, 'Manual payment not found');
        }

        if ($request->isMethod('post')) {
            $request->validate([
                'proof_file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB max
            ]);

            $result = $this->paymentService->uploadManualPaymentProof(
                $paymentId, 
                $request->file('proof_file')
            );

            if ($result['success']) {
                return redirect()->route('payment-gateway.success')
                    ->with('payment', $result['payment'])
                    ->with('message', $result['message']);
            }

            return back()->withErrors(['proof_file' => $result['message']]);
        }

        return view('payment-gateway::pages.manual-upload', compact('payment'));
    }

    public function verify(Request $request, string $gateway, string $transactionId)
    {
        $result = $this->paymentService->verifyPayment($gateway, $transactionId);
        
        return response()->json($result);
    }

    // Admin methods for manual payment approval
    public function approveManualPayment(Request $request, string $paymentId)
    {
        $result = $this->paymentService->approveManualPayment($paymentId);
        
        if ($result['success']) {
            return back()->with('success', 'Payment approved successfully');
        }

        return back()->withErrors(['error' => $result['message']]);
    }

    public function rejectManualPayment(Request $request, string $paymentId)
    {
        $request->validate([
            'reason' => 'nullable|string|max:255'
        ]);

        $result = $this->paymentService->rejectManualPayment(
            $paymentId, 
            $request->input('reason')
        );
        
        if ($result['success']) {
            return back()->with('success', 'Payment rejected');
        }

        return back()->withErrors(['error' => $result['message']]);
    }
}
