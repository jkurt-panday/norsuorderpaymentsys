@extends('layouts.app')

@section('title', 'Edit Bank Account')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-university me-2"></i>Edit Bank Account</h2>
    <a href="{{ route('staff.bank-accounts.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-edit me-2"></i>Update Bank Account Information
    </div>
    <div class="card-body">
        <form action="{{ route('staff.bank-accounts.update', $bankAccountInfo) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label required-field">Account Name</label>
                    <input type="text" name="account_name" class="form-control @error('account_name') is-invalid @enderror" 
                           value="{{ old('account_name', $bankAccountInfo->account_name) }}" placeholder="Enter account holder name" required>
                    @error('account_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Full name of the account holder</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label required-field">Bank Name</label>
                    <input type="text" name="bank_name" class="form-control @error('bank_name') is-invalid @enderror" 
                           value="{{ old('bank_name', $bankAccountInfo->bank_name) }}" placeholder="Enter bank name" required>
                    @error('bank_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Name of the bank where the account is held</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label required-field">Account Number</label>
                    <input type="text" name="account_num" class="form-control @error('account_num') is-invalid @enderror" 
                           value="{{ old('account_num', $bankAccountInfo->account_num) }}" placeholder="Enter account number" required>
                    @error('account_num')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Unique account number</small>
                </div>
            </div>
            
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('staff.bank-accounts.index') }}" class="btn btn-secondary">
                    <i class="fas fa-times me-1"></i>Cancel
                </a>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save me-1"></i>Update Bank Account
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header bg-light">
        <i class="fas fa-info-circle me-2"></i>Bank Account Information
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <p class="mb-0"><strong>Created:</strong></p>
                <p>{{ $bankAccountInfo->created_at->format('F d, Y H:i:s') }}</p>
            </div>
            <div class="col-md-4">
                <p class="mb-0"><strong>Last Updated:</strong></p>
                <p>{{ $bankAccountInfo->updated_at->format('F d, Y H:i:s') }}</p>
            </div>
            <div class="col-md-4">
                <p class="mb-0"><strong>Usage Count:</strong></p>
                <p>{{ $bankAccountInfo->staffInputs()->count() }} staff input(s)</p>
            </div>
        </div>
    </div>
</div>
@endsection