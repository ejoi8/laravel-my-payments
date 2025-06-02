<x-payment-gateway::payment-layout title="Upload Payment Proof">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Upload Payment Proof</h2>
        
        @if($payment)
            <!-- Payment Details -->
            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                <h3 class="font-semibold text-gray-900 mb-2">Payment Details</h3>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600">Amount:</span>
                        <span class="font-semibold">{{ $payment->formatted_amount }}</span>
                    </div>
                    <div>
                        <span class="text-gray-600">Reference:</span>
                        <span class="font-mono">{{ $payment->reference_id }}</span>
                    </div>
                    @if($payment->description)
                    <div class="col-span-2">
                        <span class="text-gray-600">Description:</span>
                        <span>{{ $payment->description }}</span>
                    </div>
                    @endif
                </div>
            </div>

            @if($payment->proof_file_path)
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center">
                        <div class="text-green-600 text-2xl mr-3">✓</div>
                        <div>
                            <h3 class="font-semibold text-green-900">Payment Proof Uploaded</h3>
                            <p class="text-sm text-green-700">Your payment proof has been uploaded and is being reviewed.</p>
                        </div>
                    </div>
                </div>
            @else
                <!-- Upload Form -->
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    
                    <div>
                        <label for="proof_file" class="block text-sm font-medium text-gray-700 mb-2">
                            Payment Proof *
                        </label>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-gray-400 transition-colors">
                            <input type="file" 
                                   id="proof_file" 
                                   name="proof_file" 
                                   accept=".jpg,.jpeg,.png,.pdf"
                                   class="hidden"
                                   onchange="updateFileName(this)">
                            <label for="proof_file" class="cursor-pointer">
                                <div class="text-gray-600">
                                    <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <p class="text-lg font-medium">Click to upload file</p>
                                    <p class="text-sm">or drag and drop</p>
                                    <p class="text-xs text-gray-500 mt-2">JPG, PNG, PDF up to 5MB</p>
                                </div>
                            </label>
                            <div id="file-name" class="mt-2 text-sm text-gray-600 hidden"></div>
                        </div>
                        @error('proof_file') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex">
                            <div class="text-blue-600 text-xl mr-3">ℹ️</div>
                            <div class="text-sm text-blue-800">
                                <h4 class="font-semibold mb-1">Payment Instructions</h4>
                                <p>Please upload a clear image or PDF of your payment receipt, bank transfer proof, or screenshot showing the completed transaction.</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <button type="submit" 
                                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-md transition duration-200">
                            Upload Proof
                        </button>
                        <a href="{{ route('payment-gateway.show', $payment->id) }}" 
                           class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-medium py-3 px-4 rounded-md transition duration-200 text-center">
                            Cancel
                        </a>
                    </div>
                </form>
            @endif
        @else
            <div class="text-center py-8">
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Payment Not Found</h3>
                <p class="text-gray-600">The payment you're looking for could not be found.</p>
            </div>
        @endif
    </div>

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

        // Drag and drop functionality
        const dropArea = document.querySelector('.border-dashed');
        const fileInput = document.getElementById('proof_file');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            dropArea.classList.add('border-blue-500', 'bg-blue-50');
        }

        function unhighlight(e) {
            dropArea.classList.remove('border-blue-500', 'bg-blue-50');
        }

        dropArea.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                fileInput.files = files;
                updateFileName(fileInput);
            }
        }
    </script>
</x-payment-gateway::payment-layout>
