@extends('layouts.app')

@section('title', 'Add Payment Option')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-credit-card me-2"></i>Add Payment Detail Option</h2>
    <a href="{{ route('staff.payment-options.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-plus-circle me-2"></i>New Payment Detail Option
    </div>
    <div class="card-body">
        <form action="{{ route('staff.payment-options.store') }}" method="POST">
            @csrf
            
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label required-field">Payment Description</label>
                    <input type="text" name="payment_desc" class="form-control @error('payment_desc') is-invalid @enderror" 
                           value="{{ old('payment_desc') }}" placeholder="Enter payment description" required>
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
                    <i class="fas fa-save me-1"></i>Create Payment Option
                </button>
            </div>
        </form>
    </div>
</div>
@endsection