@extends('layouts.app')

@section('title', isset($membership) ? 'Edit Membership' : 'Add Membership')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>{{ isset($membership) ? 'Edit Membership' : 'Add Membership' }}</h2>
    <a href="{{ route('staff.memberships.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ isset($membership) ? route('staff.memberships.update', $membership) : route('staff.memberships.store') }}" 
              method="POST">
            @csrf
            @if(isset($membership))
                @method('PUT')
            @endif
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label required-field">Member Code</label>
                    <input type="text" name="member_code" class="form-control @error('member_code') is-invalid @enderror" 
                           value="{{ old('member_code', $membership->member_code ?? '') }}" required>
                    @error('member_code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Unique identifier for this membership type</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label required-field">Description</label>
                    <input type="text" name="member_desc" class="form-control @error('member_desc') is-invalid @enderror" 
                           value="{{ old('member_desc', $membership->member_desc ?? '') }}" required>
                    @error('member_desc')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <div class="d-flex justify-content-end gap-2">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save me-1"></i>
                    {{ isset($membership) ? 'Update' : 'Create' }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection