@extends('layouts.public')

@section('title', 'Submission Successful')

@section('content')
<div class="form-container">
    <div class="success-container">
        <div class="icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h2 class="text-success">Submission Successful!</h2>
        <p class="text-muted">Your Order of Payment request has been submitted successfully.</p>
        
        <div class="my-4">
            <h5 class="text-muted mb-2">Your Reference Number</h5>
            <div class="reference-number-box">
                {{ $formInput->reference_number }}
            </div>
            <p class="text-muted small mt-2">
                <i class="fas fa-info-circle me-1"></i>
                Please keep this reference number for tracking your request.
            </p>
        </div>
        
        <div class="card mt-4">
            <div class="card-body">
                <h6 class="card-title text-muted">Submitted Details</h6>
                <div class="row text-start">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Name:</strong> {{ $formInput->full_name }}</p>
                        <p class="mb-1"><strong>Email:</strong> {{ $formInput->email }}</p>
                        <p class="mb-1"><strong>Contact:</strong> {{ $formInput->contact_num }}</p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Amount:</strong> ₱{{ number_format($formInput->amount, 2) }}</p>
                        <p class="mb-1"><strong>Request Type:</strong> {{ $formInput->request_type }}</p>
                        <p class="mb-1"><strong>Documents:</strong> {{ count($documents ?? []) }} file(s)</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="{{ route('public.submit') }}" class="btn btn-outline-primary">
                <i class="fas fa-plus me-2"></i>Submit Another Request
            </a>
            <a href="#" onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print me-2"></i>Print Receipt
            </a>
        </div>
    </div>
</div>
@endsection