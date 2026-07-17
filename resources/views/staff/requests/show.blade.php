@extends('layouts.app')

@section('title', 'Request Details - ' . $formInput->reference_number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2>Request Details</h2>
        <p class="text-muted">Reference: <span class="reference-number">{{ $formInput->reference_number }}</span></p>
    </div>
    <div>
        @if(!$formInput->staffInput)
            <a href="{{ route('staff.requests.process', $formInput) }}" class="btn btn-primary">
                <i class="fas fa-check me-1"></i>Process Request
            </a>
        @else
            <a href="{{ route('staff.requests.edit', $formInput->staffInput) }}" class="btn btn-warning">
                <i class="fas fa-edit me-1"></i>Edit Processing
            </a>
        @endif
        <a href="{{ route('staff.requests.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <!-- Personal Information -->
        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-user me-2"></i>Personal / Office Information
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <th width="40%">First Name / Office</th>
                        <td>{{ $formInput->firstname_or_office }}</td>
                    </tr>
                    <tr>
                        <th>Middle Name / Project</th>
                        <td>{{ $formInput->middlename_or_project ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th>Last Name / Agency</th>
                        <td>{{ $formInput->lastname_or_agency }}</td>
                    </tr>
                    <tr>
                        <th>Office / College</th>
                        <td>{{ $formInput->office_or_college }}</td>
                    </tr>
                    <tr>
                        <th>Position / Designation</th>
                        <td>{{ $formInput->position_or_designation }}</td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><a href="mailto:{{ $formInput->email }}">{{ $formInput->email }}</a></td>
                    </tr>
                    <tr>
                        <th>Contact Number</th>
                        <td>{{ $formInput->contact_num }}</td>
                    </tr>
                    <tr>
                        <th>Address</th>
                        <td>{{ $formInput->address }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Payment Information -->
        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-money-bill-wave me-2"></i>Payment Information
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <th width="40%">Request Type</th>
                        <td><span class="badge bg-info">{{ $formInput->request_type }}</span></td>
                    </tr>
                    <tr>
                        <th>Amount</th>
                        <td class="fw-bold text-primary">₱{{ number_format($formInput->amount, 2) }}</td>
                    </tr>
                    <tr>
                        <th>Membership</th>
                        <td>{{ $formInput->membership->member_code ?? 'N/A' }} - {{ $formInput->membership->member_desc ?? '' }}</td>
                    </tr>
                    <tr>
                        <th>Payment Option</th>
                        <td>{{ $formInput->paymentDetailOption->payment_desc ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th>Date Submitted</th>
                        <td>{{ $formInput->created_at->format('F d, Y H:i:s') }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <!-- Staff Processing Information -->
        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-clipboard-check me-2"></i>Staff Processing
                @if($formInput->staffInput)
                    <span class="status-badge status-{{ $formInput->staffInput->status }} float-end">
                        {{ ucfirst($formInput->staffInput->status) }}
                    </span>
                @endif
            </div>
            <div class="card-body">
                @if($formInput->staffInput)
                    <table class="table table-sm">
                        <tr>
                            <th width="40%">Fund Cluster</th>
                            <td>{{ $formInput->staffInput->bankAccount->account_name ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Bank</th>
                            <td>{{ $formInput->staffInput->bankAccount->bank_name ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Account Number</th>
                            <td>{{ $formInput->staffInput->bankAccount->account_num ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Reference Date</th>
                            <td>{{ $formInput->staffInput->ref_date ? date('F d, Y', strtotime($formInput->staffInput->ref_date)) : 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>UACS</th>
                            <td>{{ $formInput->staffInput->uacs->object_code ?? 'N/A' }} - {{ $formInput->staffInput->uacs->account_title ?? '' }}</td>
                        </tr>
                        <tr>
                            <th>Reference Document</th>
                            <td>
                                @if($formInput->staffInput->referenceDocument)
                                    <a href="{{ route('staff.documents.download', $formInput->staffInput->referenceDocument) }}" target="_blank">
                                        <i class="fas fa-file me-1"></i>
                                        {{ $formInput->staffInput->referenceDocument->original_filename }}
                                    </a>
                                @else
                                    N/A
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Processed By</th>
                            <td>Staff User</td>
                        </tr>
                        <tr>
                            <th>Processed Date</th>
                            <td>{{ $formInput->staffInput->created_at->format('F d, Y H:i:s') }}</td>
                        </tr>
                    </table>
                @else
                    <div class="text-center py-3">
                        <i class="fas fa-clock fa-2x text-warning mb-2 d-block"></i>
                        <p class="text-muted">This request has not been processed yet.</p>
                        <a href="{{ route('staff.requests.process', $formInput) }}" class="btn btn-primary">
                            <i class="fas fa-check me-1"></i>Process Now
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <!-- Supporting Documents -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-paperclip me-2"></i>Supporting Documents
                <span class="badge bg-secondary float-end">{{ count($formInput->supportingDocuments) }} file(s)</span>
            </div>
            <div class="card-body">
                @if(count($formInput->supportingDocuments) > 0)
                    <div class="list-group">
                        @foreach($formInput->supportingDocuments as $document)
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-file-{{ $document->file_extension == 'pdf' ? 'pdf text-danger' : 'image text-primary' }} me-2"></i>
                                    <a href="{{ route('staff.documents.download', $document) }}" target="_blank">
                                        {{ $document->original_filename }}
                                    </a>
                                    <span class="badge bg-secondary ms-2">{{ strtoupper($document->file_extension) }}</span>
                                </div>
                                <div>
                                    <span class="text-muted small">{{ $document->formatted_file_size }}</span>
                                    <a href="{{ route('staff.documents.download', $document) }}" class="btn btn-sm btn-outline-primary ms-2" title="Download">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger ms-1" 
                                            onclick="confirmDelete({{ $document->id }})" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted text-center">No supporting documents uploaded.</p>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Delete Document Modal -->
<div class="modal fade" id="deleteDocumentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this document?</p>
                <p class="text-danger"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteDocumentForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function confirmDelete(documentId) {
        const modal = new bootstrap.Modal(document.getElementById('deleteDocumentModal'));
        const form = document.getElementById('deleteDocumentForm');
        form.action = '/staff/documents/' + documentId;
        modal.show();
    }
</script>
@endpush
@endsection