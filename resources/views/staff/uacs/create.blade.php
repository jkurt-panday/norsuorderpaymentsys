@extends('layouts.app')

@section('title', 'Add UACS')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-code me-2"></i>Add UACS Account</h2>
    <a href="{{ route('staff.uacs.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-plus-circle me-2"></i>New UACS Account Information
    </div>
    <div class="card-body">
        <form action="{{ route('staff.uacs.store') }}" method="POST">
            @csrf
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label required-field">Object Code</label>
                    <input type="text" name="object_code" class="form-control @error('object_code') is-invalid @enderror" 
                           value="{{ old('object_code') }}" placeholder="Enter object code (e.g., 1010101000)" required>
                    @error('object_code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Unique UACS object code identifier</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label required-field">Account Title</label>
                    <input type="text" name="account_title" class="form-control @error('account_title') is-invalid @enderror" 
                           value="{{ old('account_title') }}" placeholder="Enter account title" required>
                    @error('account_title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Full description of the UACS account</small>
                </div>
            </div>
            
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('staff.uacs.index') }}" class="btn btn-secondary">
                    <i class="fas fa-times me-1"></i>Cancel
                </a>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save me-1"></i>Create UACS
                </button>
            </div>
        </form>
    </div>
</div>
@endsection