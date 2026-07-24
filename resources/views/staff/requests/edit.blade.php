@extends('layouts.app')

@section('title', 'Edit Processing - ' . $staffInput->formInput->reference_number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2>Edit Processing</h2>
        <p class="text-muted">Reference: <span class="reference-number">{{ $staffInput->formInput->reference_number }}</span></p>
    </div>
    <a href="{{ route('staff.requests.show', $staffInput->formInput) }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back
    </a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-edit me-2"></i>Update Staff Processing
            </div>
            <div class="card-body">
                <form action="{{ route('staff.requests.update', $staffInput) }}" method="POST">
                    @csrf
                    @method('PUT')

                    {{-- ADD THIS HIDDEN FIELD --}}
                    <input type="hidden" name="form_input_id" value="{{ $staffInput->form_input_id }}">
                    
                    <div class="mb-3">
                        <label class="form-label required-field">Bank Account</label>
                        <select name="fundcluster_id" class="form-select @error('fundcluster_id') is-invalid @enderror" required>
                            <option value="">Select Bank Account</option>
                            @foreach($bankAccounts as $account)
                                <option value="{{ $account->id }}" {{ old('fundcluster_id', $staffInput->fundcluster_id) == $account->id ? 'selected' : '' }}>
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
                                <option value="{{ $document->id }}" {{ old('ref_document_id', $staffInput->ref_document_id) == $document->id ? 'selected' : '' }}>
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
                               value="{{ old('ref_date', $staffInput->ref_date->format('Y-m-d')) }}" required>
                        @error('ref_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label required-field">UACS</label>
                        <select name="uacs_id" class="form-select @error('uacs_id') is-invalid @enderror" required>
                            <option value="">Select UACS</option>
                            @foreach($uacsList as $uacs)
                                <option value="{{ $uacs->id }}" {{ old('uacs_id', $staffInput->uacs_id) == $uacs->id ? 'selected' : '' }}>
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
                            <option value="pending" {{ old('status', $staffInput->status) == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="approved" {{ old('status', $staffInput->status) == 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="cancelled" {{ old('status', $staffInput->status) == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                        @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i>Update Processing
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-info-circle me-2"></i>Current Status
            </div>
            <div class="card-body">
                <div class="text-center py-3">
                    <div class="display-4 mb-2">
                        <span class="status-badge status-{{ $staffInput->status }}" style="font-size: 1.2rem; padding: 8px 20px;">
                            {{ ucfirst($staffInput->status) }}
                        </span>
                    </div>
                    <p class="text-muted small">
                        Last updated: {{ $staffInput->updated_at->format('F d, Y H:i:s') }}
                    </p>
                </div>
                <hr>
                <div class="small text-muted">
                    <p><strong>Created:</strong> {{ $staffInput->created_at->format('F d, Y H:i:s') }}</p>
                    <p><strong>Form Input ID:</strong> #{{ $staffInput->form_input_id }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection