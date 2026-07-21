@extends('layouts.public')

@section('title', 'Submit Order of Payment Request')

@section('content')
<div class="form-container">
    <div class="form-header">
        <h1><i class="fas fa-file-invoice me-2"></i>Order of Payment</h1>
        <p>Please fill out all required fields to submit your request</p>
    </div>

    <form action="{{ route('public.submit.store') }}" method="POST" enctype="multipart/form-data" id="submissionForm">
        @csrf
        
        <!-- Personal Information -->
        <div class="form-section">
            <h5><i class="fas fa-user me-2"></i>Personal / Office Information</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label required-field">First Name / Office</label>
                    <input type="text" name="firstname_or_office" class="form-control @error('firstname_or_office') is-invalid @enderror" 
                           value="{{ old('firstname_or_office') }}" required>
                    @error('firstname_or_office')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Middle Name / Project</label>
                    <input type="text" name="middlename_or_project" class="form-control @error('middlename_or_project') is-invalid @enderror" 
                           value="{{ old('middlename_or_project') }}">
                    @error('middlename_or_project')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label required-field">Last Name / Agency</label>
                    <input type="text" name="lastname_or_agency" class="form-control @error('lastname_or_agency') is-invalid @enderror" 
                           value="{{ old('lastname_or_agency') }}" required>
                    @error('lastname_or_agency')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label required-field">Office / College</label>
                    <input type="text" name="office_or_college" class="form-control @error('office_or_college') is-invalid @enderror" 
                           value="{{ old('office_or_college') }}" required>
                    @error('office_or_college')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label required-field">Position / Designation</label>
                    <input type="text" name="position_or_designation" class="form-control @error('position_or_designation') is-invalid @enderror" 
                           value="{{ old('position_or_designation') }}" required>
                    @error('position_or_designation')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label required-field">Contact Number</label>
                    <input type="tel" name="contact_num" class="form-control @error('contact_num') is-invalid @enderror" 
                           value="{{ old('contact_num') }}" placeholder="9XX-XXX-XXXX" required>
                    @error('contact_num')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label required-field">Email Address</label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" 
                           value="{{ old('email') }}" required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label required-field">Address</label>
                    <textarea name="address" class="form-control @error('address') is-invalid @enderror" 
                              rows="3" required>{{ old('address') }}</textarea>
                    @error('address')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Payment Information -->
        <div class="form-section">
            <h5><i class="fas fa-money-bill-wave me-2"></i>Payment Information</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label required-field">Request Type</label>
                    <select name="request_type" class="form-select @error('request_type') is-invalid @enderror" required>
                        <option value="">Select Request Type</option>
                        <option value="New Request" {{ old('request_type') == 'New Request' ? 'selected' : '' }}>New Request</option>
                        <option value="Re-issue Request" {{ old('request_type') == 'Re-issue Request' ? 'selected' : '' }}>Re-issue Request</option>
                    </select>
                    @error('request_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label required-field">Amount (₱)</label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" step="0.01" name="amount" class="form-control @error('amount') is-invalid @enderror" 
                               value="{{ old('amount') }}" placeholder="0.00" required>
                    </div>
                    @error('amount')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label required-field">Membership Type</label>
                    <select name="membership_id" class="form-select @error('membership_id') is-invalid @enderror" required>
                        <option value="">Select Membership</option>
                        @foreach($memberships as $membership)
                            <option value="{{ $membership->id }}" {{ old('membership_id') == $membership->id ? 'selected' : '' }}>
                                {{ $membership->member_code }} - {{ $membership->member_desc }}
                            </option>
                        @endforeach
                    </select>
                    @error('membership_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label required-field">Payment Detail Option</label>
                    <select name="payment_detail_option_id" class="form-select @error('payment_detail_option_id') is-invalid @enderror" required>
                        <option value="">Select Payment Option</option>
                        @foreach($paymentOptions as $option)
                            <option value="{{ $option->id }}" {{ old('payment_detail_option_id') == $option->id ? 'selected' : '' }}>
                                {{ $option->payment_desc }}
                            </option>
                        @endforeach
                    </select>
                    @error('payment_detail_option_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Document Upload -->
        <div class="form-section">
            <h5><i class="fas fa-paperclip me-2"></i>Supporting Documents</h5>
            <div class="row">
                <div class="col-12">
                    <div class="file-upload-area" id="fileUploadArea">
                        <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                        <p class="text-muted">Click or drag files here to upload</p>
                        <p class="text-muted small">Accepted formats: PDF, JPG, JPEG, PNG (Max 10MB each)</p>
                        <input type="file" name="documents[]" id="fileInput" 
                               class="d-none" multiple accept=".pdf,.jpg,.jpeg,.png">
                        <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('fileInput').click()">
                            <i class="fas fa-folder-open me-2"></i>Choose Files
                        </button>
                        <span class="badge bg-secondary ms-2" id="fileCount">0 file(s) selected</span>
                    </div>
                    <div class="file-list" id="fileList"></div>
                    @error('documents.*')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-submit">
                <i class="fas fa-paper-plane me-2"></i>Submit Request
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        let selectedFiles = [];
        
        // File input change
        $('#fileInput').on('change', function(e) {
            const files = Array.from(e.target.files);
            selectedFiles = selectedFiles.concat(files);
            updateFileList();
            updateFileCount();
        });
        
        // Drag and drop
        const dropArea = document.getElementById('fileUploadArea');
        
        dropArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        dropArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });
        
        dropArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            const files = Array.from(e.dataTransfer.files);
            const validFiles = files.filter(file => {
                const validTypes = ['application/pdf', 'image/jpeg', 'image/png'];
                return validTypes.includes(file.type) && file.size <= 10485760;
            });
            
            if (validFiles.length !== files.length) {
                alert('Some files were skipped. Only PDF, JPG, JPEG, PNG files up to 10MB are allowed.');
            }
            
            selectedFiles = selectedFiles.concat(validFiles);
            updateFileList();
            updateFileCount();
            
            // Update input
            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => dataTransfer.items.add(file));
            $('#fileInput')[0].files = dataTransfer.files;
        });
        
        // Remove file
        $(document).on('click', '.remove-file', function() {
            const index = $(this).data('index');
            selectedFiles.splice(index, 1);
            updateFileList();
            updateFileCount();
            
            // Update input
            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => dataTransfer.items.add(file));
            $('#fileInput')[0].files = dataTransfer.files;
        });
        
        function updateFileList() {
            let html = '';
            selectedFiles.forEach((file, index) => {
                const icon = file.type.includes('pdf') ? 'fa-file-pdf text-danger' :
                            file.type.includes('image') ? 'fa-file-image text-primary' : 'fa-file';
                const size = (file.size / 1024 / 1024).toFixed(2);
                html += `
                    <div class="file-item">
                        <i class="fas ${icon}"></i>
                        ${file.name} (${size} MB)
                        <i class="fas fa-times remove-file" data-index="${index}"></i>
                    </div>
                `;
            });
            $('#fileList').html(html);
        }
        
        function updateFileCount() {
            $('#fileCount').text(selectedFiles.length + ' file(s) selected');
        }
    });
</script>
@endpush