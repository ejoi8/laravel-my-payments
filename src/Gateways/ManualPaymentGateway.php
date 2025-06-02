<?php

namespace Ejoi8\PaymentGateway\Gateways;

class ManualPaymentGateway extends BaseGateway
{
    public function getName(): string
    {
        return 'manual';
    }

    public function createPayment(array $data): array
    {
        $this->validateRequiredFields($data, ['amount']);

        $payment = $this->createPaymentRecord($data);

        // For manual payments, we don't have a payment URL, user uploads proof
        return [
            'success' => true,
            'payment_url' => route('payment-gateway.manual.upload', ['payment' => $payment->id]),
            'payment' => $payment,
            'requires_upload' => true,
        ];
    }

    public function handleCallback(array $data): array
    {
        // Manual payments don't have callbacks
        return [
            'success' => false,
            'message' => 'Manual payments do not support callbacks'
        ];
    }

    public function handleProofUpload(string $paymentId, $file): array
    {
        $payment = \Ejoi8\PaymentGateway\Models\Payment::find($paymentId);
        
        if (!$payment || $payment->gateway !== $this->getName()) {
            return ['success' => false, 'message' => 'Payment not found'];
        }

        // Validate file
        $validation = $this->validateUploadedFile($file);
        if (!$validation['success']) {
            return $validation;
        }

        // Store file
        $path = $file->store($this->config['upload_path'], 'public');
        
        $payment->update([
            'proof_file_path' => $path,
            'status' => \Ejoi8\PaymentGateway\Models\Payment::STATUS_PENDING, // Awaiting approval
            'metadata' => array_merge($payment->metadata ?? [], [
                'proof_uploaded_at' => now()->toISOString(),
                'original_filename' => $file->getClientOriginalName(),
            ])
        ]);

        return [
            'success' => true,
            'message' => 'Payment proof uploaded successfully. Awaiting verification.',
            'payment' => $payment
        ];
    }

    public function approvePayment(string $paymentId): array
    {
        $payment = \Ejoi8\PaymentGateway\Models\Payment::find($paymentId);
        
        if (!$payment || $payment->gateway !== $this->getName()) {
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

    public function rejectPayment(string $paymentId, string $reason = null): array
    {
        $payment = \Ejoi8\PaymentGateway\Models\Payment::find($paymentId);
        
        if (!$payment || $payment->gateway !== $this->getName()) {
            return ['success' => false, 'message' => 'Payment not found'];
        }

        $payment->markAsFailed($reason ?? 'Payment proof rejected by admin');

        return [
            'success' => true,
            'message' => 'Payment rejected',
            'payment' => $payment
        ];
    }

    private function validateUploadedFile($file): array
    {
        if (!$file) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }

        // Check file size
        $maxSize = $this->config['max_file_size'] * 1024; // Convert KB to bytes
        if ($file->getSize() > $maxSize) {
            return [
                'success' => false, 
                'message' => 'File size exceeds maximum allowed size of ' . $this->config['max_file_size'] . 'KB'
            ];
        }

        // Check file extension
        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = $this->config['allowed_extensions'];
        
        if (!in_array($extension, $allowedExtensions)) {
            return [
                'success' => false,
                'message' => 'File type not allowed. Allowed types: ' . implode(', ', $allowedExtensions)
            ];
        }

        return ['success' => true];
    }

    public function verifyPayment(string $transactionId): array
    {
        return [
            'success' => false,
            'message' => 'Manual payments require admin verification'
        ];
    }
}
