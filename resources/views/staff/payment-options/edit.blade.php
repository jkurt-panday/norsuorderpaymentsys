@extends('layouts.app')

@section('title', 'Edit Payment Option')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-credit-card me-2"></i>Edit Payment Detail Option</h2>
    <a href="{{ route('staff.payment-options.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-edit me-2"></i>Update Payment Detail Option
    </div>
    <div class="card-body">
        <form action="{{ route('staff.payment-options.update', $paymentOption) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label required-field">Payment Description</label>
                    <input type="text" name="payment_desc" class="form-control @error('payment_desc') is-invalid @enderror" 
                           value="{{ old('payment_desc', $paymentOption->payment_desc) }}" placeholder="Enter payment description" required>
                    @error('payment_desc')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Description of the payment detail option (e.g., "Cash Payment", "Bank Transfer", etc.)</small>
                </div>
            </div>
            
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('staff.payment-options.index') }}" class="btn btn-secondary">
                    <i class="fas fa-times me-1"></i>Cancel
                </a>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save me-1"></i>Update Payment Option
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header bg-light">
        <i class="fas fa-info-circle me-2"></i>Payment Option Information
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <p class="mb-0"><strong>Created:</strong></p>
                <p>{{ $paymentOption->created_at->format('F d, Y H:i:s') }}</p>
            </div>
            <div class="col-md-4">
                <p class="mb-0"><strong>Last Updated:</strong></p>
                <p>{{ $paymentOption->updated_at->format('F d, Y H:i:s') }}</p>
            </div>
            <div class="col-md-4">
                <p class="mb-0"><strong>Usage Count:</strong></p>
                <p>{{ $paymentOption->formInputs()->count() }} form input(s)</p>
            </div>
        </div>
        
        @if($paymentOption->formInputs()->count() > 0)
            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle me-2"></i>
                This payment option is currently being used in <strong>{{ $paymentOption->formInputs()->count() }}</strong> form inputs. 
                Updating it will affect existing records.
            </div>
        @endif
    </div>
</div>
@endsection