<?php

namespace App\Http\Controllers;

use App\Models\SupportingDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
     * Store a newly created document in storage and database.
     */
    public function store(Request $request)
    {
        // 1. Validate incoming file and related foreign key
        $request->validate([
            'form_input_id' => 'required|exists:form_inputs,id',
            'document'      => 'required|file|mimes:pdf,jpg,png,docx,zip|max:10240', // Max 10MB
        ]);

        if ($request->hasFile('document')) {
            $file = $request->file('document');

            // 2. Gather metadata
            $originalName = $file->getClientOriginalName();
            $extension    = $file->getClientOriginalExtension();
            $mimeType     = $file->getClientMimeType();
            $fileSize     = $file->getSize();

            // 3. Generate a unique stored filename to prevent overwriting existing files
            $storedFilename = Str::uuid() . '.' . $extension;

            // 4. Save file to storage/app/public/supporting-documents/
            $file->storeAs($this->folder, $storedFilename, $this->disk);

            // 5. Generate relative storage URL (e.g., /storage/supporting-documents/...)
            $fileUrl = Storage::disk($this->disk)->url($this->folder . '/' . $storedFilename);

            // 6. Save metadata record into database
            SupportingDocument::create([
                'form_input_id'     => $request->form_input_id,
                'original_filename' => $originalName,
                'stored_filename'   => $storedFilename,
                'file_url'          => $fileUrl,
                'mime_type'         => $mimeType,
                'file_extension'    => $extension,
                'file_size'         => $fileSize,
                'uploaded_at'       => now(),
            ]);

            return back()->with('success', 'Document uploaded successfully!');
        }

        return back()->with('error', 'No file was provided.');
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