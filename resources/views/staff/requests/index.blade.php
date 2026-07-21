@extends('layouts.app')

@section('title', 'Manage Requests')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Order of Payment Requests</h2>
    <div>
        <a href="{{ route('staff.requests.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-sync me-1"></i>Refresh
        </a>
    </div>
</div>

<!-- Search and Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('staff.requests.index') }}" class="row g-3">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by reference, name, or email" 
                           value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    <option value="unprocessed" {{ request('status') == 'unprocessed' ? 'selected' : '' }}>Unprocessed</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="date_from" class="form-control" placeholder="Date From" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" class="form-control" placeholder="Date To" value="{{ request('date_to') }}">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-1"></i>Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Requests Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Reference #</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Amount</th>
                        <th>Membership</th>
                        <th>Status</th>
                        <th>Date Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($formInputs as $formInput)
                        <tr>
                            <td>
                                <span class="reference-number">{{ $formInput->reference_number }}</span>
                            </td>
                            <td>{{ $formInput->full_name }}</td>
                            <td>{{ $formInput->email }}</td>
                            <td>₱{{ number_format($formInput->amount, 2) }}</td>
                            <td>{{ $formInput->membership->member_code ?? 'N/A' }}</td>
                            <td>
                                @if($formInput->staffInput)
                                    <span class="status-badge status-{{ $formInput->staffInput->status }}">
                                        {{ ucfirst($formInput->staffInput->status) }}
                                    </span>
                                @else
                                    <span class="status-badge status-pending">Unprocessed</span>
                                @endif
                            </td>
                            <td>{{ $formInput->created_at->timezone('Asia/Manila')->format('F d, Y h:i:s A') }}</td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('staff.requests.show', $formInput) }}" 
                                       class="btn btn-info" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @if(!$formInput->staffInput)
                                        <a href="{{ route('staff.requests.process', $formInput) }}" 
                                           class="btn btn-primary" title="Process">
                                            <i class="fas fa-check"></i>
                                        </a>
                                    @else
                                        <a href="{{ route('staff.requests.edit', $formInput->staffInput) }}" 
                                           class="btn btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <i class="fas fa-inbox fa-2x text-muted d-block mb-2"></i>
                                <p class="text-muted">No requests found</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        {{ $formInputs->links() }}
    </div>
</div>
@endsection