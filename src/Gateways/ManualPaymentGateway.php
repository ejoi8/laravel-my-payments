<?php

namespace Ejoi8\PaymentGateway\Gateways;

use Ejoi8\PaymentGateway\Models\Payment;

/**
 * Manual Payment Gateway
 * 
 * This gateway handles manual payments where users upload proof of payment
 * which must be verified by an administrator.
 */
class ManualPaymentGateway extends BaseGateway
{
    /**
     * Payment statuses
     */
    private const STATUS_PENDING = 'pending';
    private const STATUS_PAID    = 'paid';
    private const STATUS_FAILED  = 'failed';

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'manual';
    }

    /**
     * Create a new manual payment record
     * 
     * @param array $data Payment data
     * @return array Response with payment details
     */
    public function createPayment(array $data): array
    {
        $this->validateRequiredFields($data, ['amount']);

        $payment = $this->createPaymentRecord($data);
        $withProof = isset($data['proof_file']) && !empty($data['proof_file']);

        // Handle immediate proof upload if provided
        if ($withProof) {
            $file = $data['proof_file'];
            $validation = $this->validateUploadedFile($file);

            if (!$validation['success']) {
                return $validation;
            }

            // Store file
            $this->storeProofFile($file, $payment);

            return [
                'success' => true,
                'message' => 'Payment created with proof. Awaiting verification.',
                'payment' => $payment,
                'requires_upload' => false,
                'redirect_url' => route('payment-gateway.success', ['payment_id' => $payment->id]), // Add redirect URL
            ];
        }


        // For manual payments, we don't have a payment URL, user uploads proof
        return [
            'success'         => true,
            'payment_url'     => route('payment-gateway.manual.upload', ['payment' => $payment->id]),
            'payment'         => $payment,
            'requires_upload' => true,
        ];
    }

    /**
     * Handle callback - not supported for manual payments
     * 
     * @param array $data Callback data
     * @return array Response with error
     */
    public function handleCallback(array $data): array
    {
        // Manual payments don't have callbacks
        return [
            'success' => false,
            'message' => 'Manual payments do not support callbacks'
        ];
    }

    /**
     * Handle payment proof upload
     * 
     * @param string $paymentId Payment ID
     * @param mixed $file Uploaded file
     * @return array Response with status
     */
    public function handleProofUpload(string $paymentId, $file): array
    {
        $payment = $this->findPayment($paymentId);

        if (!$payment) {
            return ['success' => false, 'message' => 'Payment not found'];
        }

        // Validate file
        $validation = $this->validateUploadedFile($file);
        if (!$validation['success']) {
            return $validation;
        }

        // Store file
        $path = $this->storeProofFile($file, $payment);

        return [
            'success' => true,
            'message' => 'Payment proof uploaded successfully. Awaiting verification.',
            'payment' => $payment
        ];
    }

    /**
     * Store the uploaded proof file
     * 
     * @param mixed $file Uploaded file
     * @param Payment $payment Payment record
     * @return string File path
     */
    private function storeProofFile($file, $payment): string
    {
        $path = $file->store($this->config['upload_path'], 'public');

        $payment->update([
            'proof_file_path' => $path,
            'status'          => Payment::STATUS_PENDING,                 // Awaiting approval
            'metadata'        => array_merge($payment->metadata ?? [], [
                'proof_uploaded_at' => now()->toISOString(),
                'original_filename' => $file->getClientOriginalName(),
            ])
        ]);

        return $path;
    }

    /**
     * Approve a manual payment
     * 
     * @param string $paymentId Payment ID
     * @return array Response with status
     */
    public function approvePayment(string $paymentId): array
    {
        $payment = $this->findPayment($paymentId);

        if (!$payment) {
            return ['success' => false, 'message' => 'Payment not found'];
        }

        if (!$payment->proof_file_path) {
            return ['success' => false, 'message' => 'No proof file uploaded'];
        }

        $payment->markAsPaid(null, ['approved_by_admin' => true]);

        return [
            'success' => true,
            'message' => 'Payment approved successfully',
            'payment' => $payment
        ];
    }

    /**
     * Reject a manual payment
     * 
     * @param string $paymentId Payment ID
     * @param string|null $reason Rejection reason
     * @return array Response with status
     */
    public function rejectPayment(string $paymentId, string $reason = null): array
    {
        $payment = $this->findPayment($paymentId);

        if (!$payment) {
            return ['success' => false, 'message' => 'Payment not found'];
        }

        $payment->markAsFailed($reason ?? 'Payment proof rejected by admin');

        return [
            'success' => true,
            'message' => 'Payment rejected',
            'payment' => $payment
        ];
    }

    /**
     * Validate the uploaded proof file
     * 
     * @param mixed $file Uploaded file
     * @return array Validation result
     */
    private function validateUploadedFile($file): array
    {
        if (!$file) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }

        // Check file size
        if (!$this->validateFileSize($file)) {
            $maxSize = $this->config['max_file_size'];
            return [
                'success' => false,
                'message' => "File size exceeds maximum allowed size of {$maxSize}KB"
            ];
        }

        // Check file extension
        if (!$this->validateFileExtension($file)) {
            $allowedExtensions = implode(', ', $this->config['allowed_extensions']);
            return [
                'success' => false,
                'message' => "File type not allowed. Allowed types: {$allowedExtensions}"
            ];
        }

        return ['success' => true];
    }

    /**
     * Validate file size
     * 
     * @param mixed $file Uploaded file
     * @return bool Whether file size is valid
     */
    private function validateFileSize($file): bool
    {
        $maxSize = $this->config['max_file_size'] * 1024; // Convert KB to bytes
        return $file->getSize() <= $maxSize;
    }

    /**
     * Validate file extension
     * 
     * @param mixed $file Uploaded file
     * @return bool Whether file extension is valid
     */
    private function validateFileExtension($file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = $this->config['allowed_extensions'];

        return in_array($extension, $allowedExtensions);
    }

    /**
     * Find a payment by ID
     * 
     * @param string $paymentId Payment ID
     * @return Payment|null Payment record or null if not found
     */
    private function findPayment(string $paymentId)
    {
        return Payment::where('id', $paymentId)
            ->where('gateway', $this->getName())
            ->first();
    }

    /**
     * Verify payment status - not supported for manual payments
     * 
     * @param string $transactionId Transaction ID
     * @return array Response with error
     */
    public function verifyPayment(string $transactionId): array
    {
        return [
            'success' => false,
            'message' => 'Manual payments require admin verification'
        ];
    }
}
