    @extends('layouts.app')

    @section('title', 'Manage Memberships')

    @section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Memberships</h2>
        <a href="{{ route('staff.memberships.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i>Add Membership
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Member Code</th>
                            <th>Description</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($memberships as $membership)
                            <tr>
                                <td>{{ $membership->id }}</td>
                                <td><span class="badge bg-primary">{{ $membership->member_code }}</span></td>
                                <td>{{ $membership->member_desc }}</td>
                                <td>{{ $membership->created_at->format('M d, Y') }}</td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('staff.memberships.edit', $membership) }}" class="btn btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-danger" 
                                                onclick="confirmDelete({{ $membership->id }})">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <i class="fas fa-users fa-2x text-muted d-block mb-2"></i>
                                    <p class="text-muted">No memberships found</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $memberships->links() }}
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
                    <p>Are you sure you want to delete this record?</p>
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
            form.action = '{{ route("staff.memberships.index") }}/' + id;
            modal.show();
        }
    </script>
    @endpush
    @endsection