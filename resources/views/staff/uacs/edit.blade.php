@extends('layouts.app')

@section('title', 'Edit UACS')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-code me-2"></i>Edit UACS Account</h2>
    <a href="{{ route('staff.uacs.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-edit me-2"></i>Update UACS Account Information
    </div>
    <div class="card-body">
        <form action="{{ route('staff.uacs.update', $uacs) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label required-field">Object Code</label>
                    <input type="text" name="object_code" class="form-control @error('object_code') is-invalid @enderror" 
                           value="{{ old('object_code', $uacs->object_code) }}" placeholder="Enter object code (e.g., 1010101000)" required>
                    @error('object_code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Unique UACS object code identifier</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label required-field">Account Title</label>
                    <input type="text" name="account_title" class="form-control @error('account_title') is-invalid @enderror" 
                           value="{{ old('account_title', $uacs->account_title) }}" placeholder="Enter account title" required>
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
                    <i class="fas fa-save me-1"></i>Update UACS
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header bg-light">
        <i class="fas fa-info-circle me-2"></i>UACS Information
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <p class="mb-0"><strong>Created:</strong></p>
                <p>{{ $uacs->created_at->format('F d, Y H:i:s') }}</p>
            </div>
            <div class="col-md-4">
                <p class="mb-0"><strong>Last Updated:</strong></p>
                <p>{{ $uacs->updated_at->format('F d, Y H:i:s') }}</p>
            </div>
            <div class="col-md-4">
                <p class="mb-0"><strong>Usage Count:</strong></p>
                <p>{{ $uacs->staffInputs()->count() }} staff input(s)</p>
            </div>
        </div>
        
        @if($uacs->staffInputs()->count() > 0)
            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle me-2"></i>
                This UACS is currently being used in <strong>{{ $uacs->staffInputs()->count() }}</strong> staff inputs. 
                Updating it will affect existing records.
            </div>
        @endif
    </div>
</div>
@endsection