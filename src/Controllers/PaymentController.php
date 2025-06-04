<?php

namespace Ejoi8\PaymentGateway\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Routing\Controller;
use Ejoi8\PaymentGateway\Services\PaymentService;
use Ejoi8\PaymentGateway\Models\Payment;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Payment Gateway Controller
 * 
 * Handles all payment gateway related HTTP requests
 */
class PaymentController extends Controller
{
    /**
     * @var PaymentService
     */
    protected $paymentService;

    /**
     * Constructor
     * 
     * @param PaymentService $paymentService
     */
    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }    /**
     * Create a new payment
     * 
     * @param Request $request
     * @return RedirectResponse
     */
    public function create(Request $request): RedirectResponse
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

    /**
     * Handle payment callback/webhook from payment provider
     * 
     * @param Request $request
     * @param string $gateway
     * @return RedirectResponse|JsonResponse
     */
    public function callback(Request $request, string $gateway)
    {
        // Log the incoming callback request
        \Log::info('Payment callback received', [
            'gateway' => $gateway,
            'data' => $request->all(),
            'ip' => $request->ip(),
            'headers' => $request->headers->all()
        ]);
        
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

    /**
     * Display payment success page
     * 
     * @param Request $request
     * @return View
     */
    public function success(Request $request): View
    {
        $payment = $this->getPaymentFromRequestOrSession($request);
        return view('payment-gateway::pages.thank-you', compact('payment'));
    }

    /**
     * Display payment failed page
     * 
     * @param Request $request
     * @return View
     */
    public function failed(Request $request): View
    {
        $payment = $this->getPaymentFromRequestOrSession($request);
        return view('payment-gateway::pages.payment-failed', compact('payment'));
    }
    
    /**
     * Get payment from request parameter or session
     * 
     * @param Request $request
     * @return Payment|null
     */
    protected function getPaymentFromRequestOrSession(Request $request): ?Payment
    {
        $paymentId = $request->get('payment_id');
        
        if ($paymentId) {
            return $this->paymentService->getPayment($paymentId);
        } 
        
        if (session('payment')) {
            return session('payment');
        }
        
        return null;
    }    /**
     * Show payment details
     * 
     * @param string $paymentId
     * @return View
     */
    public function show(string $paymentId): View
    {
        $payment = $this->paymentService->getPayment($paymentId);
        
        if (!$payment) {
            abort(404, 'Payment not found');
        }

        return view('payment-gateway::pages.payment', compact('payment'));
    }

    /**
     * Handle manual payment proof upload
     * 
     * @param Request $request
     * @param string $paymentId
     * @return View|RedirectResponse
     */
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
    }    /**
     * Verify payment status with payment provider
     * 
     * @param Request $request
     * @param string $gateway
     * @param string $transactionId
     * @return JsonResponse
     */
    public function verify(Request $request, string $gateway, string $transactionId): JsonResponse
    {
        $result = $this->paymentService->verifyPayment($gateway, $transactionId);
        
        return response()->json($result);
    }

    /**
     * Approve a manual payment (admin action)
     * 
     * @param Request $request
     * @param string $paymentId
     * @return RedirectResponse
     */
    public function approveManualPayment(Request $request, string $paymentId): RedirectResponse
    {
        $result = $this->paymentService->approveManualPayment($paymentId);
        
        if ($result['success']) {
            return back()->with('success', 'Payment approved successfully');
        }

        return back()->withErrors(['error' => $result['message']]);
    }

    /**
     * Reject a manual payment (admin action)
     * 
     * @param Request $request
     * @param string $paymentId
     * @return RedirectResponse
     */
    public function rejectManualPayment(Request $request, string $paymentId): RedirectResponse
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
