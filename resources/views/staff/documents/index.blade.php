@extends('layouts.app')

@section('title', 'Supporting Documents')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-paperclip me-2"></i>Supporting Documents</h2>
    <div>
        <span class="badge bg-secondary">{{ $documents->total() }} total documents</span>
    </div>
</div>

<div class="card">
    <div class="card-body">
        @if($documents->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Original Filename</th>
                            <th>Request Reference</th>
                            <th>File Type</th>
                            <th>Size</th>
                            <th>Uploaded At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($documents as $document)
                            <tr>
                                <td>{{ $document->id }}</td>
                                <td>
                                    <i class="fas fa-file-{{ $document->file_extension == 'pdf' ? 'pdf text-danger' : 'image text-primary' }} me-2"></i>
                                    {{ $document->original_filename }}
                                </td>
                                <td>
                                    <a href="{{ route('staff.requests.show', $document->formInput) }}" class="reference-number">
                                        {{ $document->formInput->reference_number }}
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-info">{{ strtoupper($document->file_extension) }}</span>
                                </td>
                                <td>{{ $document->formatted_file_size }}</td>
                                <td>{{ $document->uploaded_at->format('M d, Y H:i:s') }}</td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('staff.documents.download', $document) }}" class="btn btn-primary" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <a href="{{ route('staff.requests.show', $document->formInput) }}" class="btn btn-info" title="View Request">
                                            <i class="fas fa-file-alt"></i>
                                        </a>
                                        @if(!$document->staffInputs()->exists())
                                            <button type="button" class="btn btn-danger" 
                                                    onclick="confirmDelete({{ $document->id }})" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-center">
                {{ $documents->links() }}
            </div>
        @else
            <div class="text-center py-5">
                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No Documents Found</h5>
                <p class="text-muted">No supporting documents have been uploaded yet.</p>
            </div>
        @endif
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this document?</p>
                <p class="text-danger"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST">
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
    function confirmDelete(id) {
        const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        const form = document.getElementById('deleteForm');
        form.action = '{{ route("staff.documents.index") }}/' + id;
        modal.show();
    }
</script>
@endpush
@endsection