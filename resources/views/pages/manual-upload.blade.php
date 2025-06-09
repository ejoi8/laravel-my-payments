<x-payment-gateway::payment-layout title="Upload Payment Proof">
    @if($payment)
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Upload Payment Proof</h1>
            <p class="text-gray-600">Upload your payment receipt or proof of transfer</p>
        </div>

        <!-- Payment Summary -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <h3 class="font-semibold text-gray-900 mb-3">Payment Summary</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-gray-600">Amount:</span>
                    <span class="font-semibold ml-2">{{ $payment->formatted_amount }}</span>
                </div>
                <div>
                    <span class="text-gray-600">Reference:</span>
                    <span class="font-mono ml-2">{{ $payment->reference_id }}</span>
                </div>
                @if($payment->description)
                <div class="md:col-span-2">
                    <span class="text-gray-600">Description:</span>
                    <span class="ml-2">{{ $payment->description }}</span>
                </div>
                @endif
            </div>
        </div>

        @if($payment->proof_file_path)
            <div class="bg-green-50 border border-green-200 text-green-700 p-4 rounded-lg text-center">
                <div class="text-2xl mb-2">✓</div>
                <h3 class="font-semibold">Payment Proof Uploaded</h3>
                <p class="text-sm mt-1">Your payment proof has been uploaded and is being reviewed.</p>
            </div>
        @else
            <!-- Upload Form -->
            <div class="bg-white rounded-lg shadow p-6">
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    
                    <div>
                        <label for="proof_file" class="block text-sm font-medium text-gray-700 mb-2">
                            Payment Proof <span class="text-red-500">*</span>
                        </label>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-gray-400 transition">
                            <input type="file" 
                                   id="proof_file" 
                                   name="proof_file" 
                                   accept=".jpg,.jpeg,.png,.pdf"
                                   class="hidden"
                                   onchange="updateFileName(this)">
                            <label for="proof_file" class="cursor-pointer">
                                <div class="text-gray-600">
                                    <div class="text-4xl mb-3">📄</div>
                                    <p class="text-lg font-medium">Click to upload file</p>
                                    <p class="text-sm">or drag and drop</p>
                                    <p class="text-xs text-gray-500 mt-2">JPG, PNG, PDF up to 5MB</p>
                                </div>
                            </label>
                            <div id="file-name" class="mt-2 text-sm text-gray-600 hidden"></div>
                        </div>
                        @error('proof_file') 
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p> 
                        @enderror
                    </div>

                    <div class="bg-blue-50 border border-blue-200 p-4 rounded-lg">
                        <div class="text-sm text-blue-800">
                            <h4 class="font-semibold mb-1">📋 Instructions</h4>
                            <p>Upload a clear image or PDF of your payment receipt, bank transfer proof, or transaction screenshot.</p>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <button type="submit" 
                                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded transition">
                            Upload Proof
                        </button>
                        <a href="{{ route('payment-gateway.show', $payment->id) }}" 
                           class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-medium py-3 px-4 rounded transition text-center">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        @endif
    @else
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <div class="text-4xl text-gray-300 mb-4">?</div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Payment Not Found</h3>
            <p class="text-gray-600">The payment you're looking for could not be found.</p>
        </div>
    @endif

    <script>
        function updateFileName(input) {
            const fileNameDiv = document.getElementById('file-name');
            if (input.files && input.files.length > 0) {
                fileNameDiv.textContent = `Selected: ${input.files[0].name}`;
                fileNameDiv.classList.remove('hidden');
            } else {
                fileNameDiv.classList.add('hidden');
            }
        }

        // Simple drag and drop
        const dropArea = document.querySelector('.border-dashed');
        const fileInput = document.getElementById('proof_file');

        if (dropArea && fileInput) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, e => {
                    e.preventDefault();
                    e.stopPropagation();
                }, false);
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                dropArea.addEventListener(eventName, () => {
                    dropArea.classList.add('border-blue-500', 'bg-blue-50');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, () => {
                    dropArea.classList.remove('border-blue-500', 'bg-blue-50');
                }, false);
            });

            dropArea.addEventListener('drop', e => {
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    updateFileName(fileInput);
                }
            }, false);
        }
    </script>
</x-payment-gateway::payment-layout>
