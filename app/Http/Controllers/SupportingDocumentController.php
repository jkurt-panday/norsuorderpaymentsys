<?php

namespace App\Http\Controllers;

use App\Models\SupportingDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SupportingDocumentController extends Controller
{
    // Define storage configuration in one place for easy maintenance
    protected string $disk = 'public';
    protected string $folder = 'supporting-documents';

    /**
     * Display a listing of documents
     */
    public function index()
    {
        $documents = SupportingDocument::with('formInput')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('staff.documents.index', compact('documents'));
    }

    /**
     * Display (Stream/Download) the specified document
     */
    public function show(SupportingDocument $supportingDocument)
    {
        $relativePath = $this->folder . '/' . $supportingDocument->stored_filename;

        if (!Storage::disk($this->disk)->exists($relativePath)) {
            abort(404, 'File not found on storage.');
        }

        // Use standard Storage responses instead of hardcoding physical paths
        return Storage::disk($this->disk)->download(
            $relativePath,
            $supportingDocument->original_filename,
            ['Content-Type' => $supportingDocument->mime_type]
        );
    }

    /**
     * Remove the specified document safely
     */
    public function destroy(SupportingDocument $supportingDocument)
    {
        try {
            DB::beginTransaction();

            // Safety check: Ensure the document isn't being referenced
            $hasRelations = $supportingDocument->staffInputs()
                ->withTrashed() // Safe against soft-deletes
                ->exists();

            if ($hasRelations) {
                return back()->with('error', 'Cannot delete document that is currently used as a reference.');
            }

            // 1. Delete database record first
            $supportingDocument->delete();

            DB::commit();

            // 2. Only delete the physical file AFTER database transaction commits successfully.
            // This prevents "orphaned" missing files if database execution fails.
            $relativePath = $this->folder . '/' . $supportingDocument->stored_filename;
            if (Storage::disk($this->disk)->exists($relativePath)) {
                Storage::disk($this->disk)->delete($relativePath);
            }

            return back()->with('success', 'Document deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to delete document ID {$supportingDocument->id}: " . $e->getMessage());

            return back()->with('error', 'Failed to delete document. Please try again.');
        }
    }

    /**
     * Download the specified document (Alias or Explicit Download)
     */
    public function download(SupportingDocument $supportingDocument)
    {
        // Re-routing download directly to our standardized storage-safe response wrapper
        return $this->show($supportingDocument);
    }
}