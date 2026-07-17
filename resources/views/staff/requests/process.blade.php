@extends('layouts.app')

@section('title', 'Process Request - ' . $formInput->reference_number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2>Process Request</h2>
        <p class="text-muted">Reference: <span class="reference-number">{{ $formInput->reference_number }}</span></p>
    </div>
    <a href="{{ route('staff.requests.show', $formInput) }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back
    </a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-clipboard-check me-2"></i>Staff Processing Form
            </div>
            <div class="card-body">
                <form action="{{ route('staff.requests.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="form_input_id" value="{{ $formInput->id }}">
                    
                    <div class="mb-3">
                        <label class="form-label required-field">Bank Account</label>
                        <select name="fundcluster_id" class="form-select @error('fundcluster_id') is-invalid @enderror" required>
                            <option value="">Select Bank Account</option>
                            @foreach($bankAccounts as $account)
                                <option value="{{ $account->id }}" {{ old('fundcluster_id') == $account->id ? 'selected' : '' }}>
                                    {{ $account->account_name }} - {{ $account->bank_name }} ({{ $account->account_num }})
                                </option>
                            @endforeach
                        </select>
                        @error('fundcluster_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reference Document</label>
                        <select name="ref_document_id" class="form-select @error('ref_document_id') is-invalid @enderror">
                            <option value="">Select Reference Document (Optional)</option>
                            @foreach($documents as $document)
                                <option value="{{ $document->id }}" {{ old('ref_document_id') == $document->id ? 'selected' : '' }}>
                                    {{ $document->original_filename }}
                                </option>
                            @endforeach
                        </select>
                        @error('ref_document_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label required-field">Reference Date</label>
                        <input type="date" name="ref_date" class="form-control @error('ref_date') is-invalid @enderror" 
                               value="{{ old('ref_date', date('Y-m-d')) }}" required>
                        @error('ref_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label required-field">UACS</label>
                        <select name="uacs_id" class="form-select @error('uacs_id') is-invalid @enderror" required>
                            <option value="">Select UACS</option>
                            @foreach($uacsList as $uacs)
                                <option value="{{ $uacs->id }}" {{ old('uacs_id') == $uacs->id ? 'selected' : '' }}>
                                    {{ $uacs->object_code }} - {{ $uacs->account_title }}
                                </option>
                            @endforeach
                        </select>
                        @error('uacs_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label required-field">Status</label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                            <option value="">Select Status</option>
                            <option value="pending" {{ old('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="approved" {{ old('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="cancelled" {{ old('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                        @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-1"></i>Process Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-info-circle me-2"></i>Request Information
            </div>
            <div class="card-body">
                <p class="mb-1"><strong>Name:</strong></p>
                <p>{{ $formInput->full_name }}</p>
                
                <p class="mb-1"><strong>Email:</strong></p>
                <p>{{ $formInput->email }}</p>
                
                <p class="mb-1"><strong>Contact:</strong></p>
                <p>{{ $formInput->contact_num }}</p>
                
                <p class="mb-1"><strong>Amount:</strong></p>
                <p class="fw-bold text-primary">₱{{ number_format($formInput->amount, 2) }}</p>
                
                <p class="mb-1"><strong>Request Type:</strong></p>
                <p>{{ $formInput->request_type }}</p>
                
                <p class="mb-1"><strong>Membership:</strong></p>
                <p>{{ $formInput->membership->member_desc ?? 'N/A' }}</p>
                
                <p class="mb-1"><strong>Payment Option:</strong></p>
                <p>{{ $formInput->paymentDetailOption->payment_desc ?? 'N/A' }}</p>
                
                <p class="mb-1"><strong>Documents:</strong></p>
                @foreach($documents as $document)
                    <div class="file-preview">
                        <i class="fas fa-file me-1"></i>
                        <a href="{{ route('staff.documents.download', $document) }}" target="_blank">
                            {{ $document->original_filename }}
                        </a>
                        <span class="badge bg-info text-white ms-1">{{ $document->file_extension }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection