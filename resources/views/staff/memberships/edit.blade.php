@extends('layouts.app')

@section('title', 'Edit Membership')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-users me-2"></i>Edit Membership</h2>
    <a href="{{ route('staff.memberships.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-edit me-2"></i>Update Membership Information
    </div>
    <div class="card-body">
        <form action="{{ route('staff.memberships.update', $membership) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label required-field">Member Code</label>
                    <input type="text" name="member_code" class="form-control @error('member_code') is-invalid @enderror" 
                           value="{{ old('member_code', $membership->member_code) }}" placeholder="Enter unique member code" required>
                    @error('member_code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Unique identifier for this membership type (e.g., MEM-001)</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label required-field">Description</label>
                    <input type="text" name="member_desc" class="form-control @error('member_desc') is-invalid @enderror" 
                           value="{{ old('member_desc', $membership->member_desc) }}" placeholder="Enter membership description" required>
                    @error('member_desc')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Full description of the membership type</small>
                </div>
            </div>
            
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('staff.memberships.index') }}" class="btn btn-secondary">
                    <i class="fas fa-times me-1"></i>Cancel
                </a>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save me-1"></i>Update Membership
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header bg-light">
        <i class="fas fa-info-circle me-2"></i>Membership Information
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <p class="mb-0"><strong>Created:</strong></p>
                <p>{{ $membership->created_at->format('F d, Y H:i:s') }}</p>
            </div>
            <div class="col-md-4">
                <p class="mb-0"><strong>Last Updated:</strong></p>
                <p>{{ $membership->updated_at->format('F d, Y H:i:s') }}</p>
            </div>
            <div class="col-md-4">
                <p class="mb-0"><strong>Usage Count:</strong></p>
                <p>{{ $membership->formInputs()->count() }} form input(s)</p>
            </div>
        </div>
        
        @if($membership->formInputs()->count() > 0)
            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle me-2"></i>
                This membership is currently being used in <strong>{{ $membership->formInputs()->count() }}</strong> form inputs. 
                Updating it will affect existing records.
            </div>
        @endif
    </div>
</div>
@endsection