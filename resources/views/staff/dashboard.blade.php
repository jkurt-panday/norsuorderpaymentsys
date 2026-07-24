@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Dashboard</h2>
    <div>
        <span class="text-muted">Total Requests: {{ $totalRequests ?? 0 }}</span>
    </div>
</div>

<div class="row">
    <div class="col-md-3 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5 class="card-title">Total Requests</h5>
                <h2 class="mb-0">{{ $totalRequests ?? 0 }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h5 class="card-title">Pending</h5>
                <h2 class="mb-0">{{ $pendingRequests ?? 0 }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title">Approved</h5>
                <h2 class="mb-0">{{ $approvedRequests ?? 0 }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <h5 class="card-title">Cancelled</h5>
                <h2 class="mb-0">{{ $cancelledRequests ?? 0 }}</h2>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-clock me-2"></i>Recent Requests
            </div>
            <div class="card-body">
                @if(isset($recentRequests) && count($recentRequests) > 0)
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentRequests as $request)
                                    <tr>
                                        <td>
                                            <span class="reference-number">{{ $request->reference_number }}</span>
                                        </td>
                                        <td>{{ $request->full_name }}</td>
                                        <td>
                                            @if($request->staffInput)
                                                <span class="status-badge status-{{ $request->staffInput->status }}">
                                                    {{ ucfirst($request->staffInput->status) }}
                                                </span>
                                            @else
                                                <span class="status-badge status-pending">Unprocessed</span>
                                            @endif
                                        </td>
                                        <td>{{ $request->created_at->format('M d, Y') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted text-center">No recent requests</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-bar me-2"></i>Quick Actions
            </div>
            <div class="card-body">
                <div class="list-group">
                    <a href="{{ route('staff.requests.index') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-file-alt me-2"></i>View All Requests
                    </a>
                    <a href="{{ route('staff.requests.index', ['status' => 'pending']) }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-clock me-2"></i>Process Pending Requests
                    </a>
                    <a href="{{ route('staff.memberships.index') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-users me-2"></i>Manage Memberships
                    </a>
                    <a href="{{ route('staff.bank-accounts.index') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-university me-2"></i>Manage Bank Accounts
                    </a>
                    <a href="{{ route('public.submit') }}" target="_blank" class="list-group-item list-group-item-action">
                        <i class="fas fa-external-link-alt me-2"></i>Public Submission Form
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection