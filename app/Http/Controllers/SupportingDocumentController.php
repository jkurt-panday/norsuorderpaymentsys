<?php

namespace App\Http\Controllers;

use App\Models\FormInput;
use App\Models\SupportingDocument;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SupportingDocumentController extends Controller
{
    protected FileUploadService $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Display a listing of documents
     */
    public function index()
    {
        $documents = SupportingDocument::with('formInput')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('staff/documents/Index', [
            'documents' => $documents,
        ]);    
    }

    /**
     * Store a newly created document
     */
    public function store(Request $request)
    {
        $request->validate([
            'form_input_id' => 'required|exists:form_inputs,id',
            'document'      => 'required|file|mimes:pdf,jpg,png,docx,zip|max:10240',
        ]);

        try {
            $formInput = FormInput::findOrFail($request->form_input_id);
            
            $document = $this->fileUploadService->uploadDocument(
                $request->file('document'),
                $formInput
            );

            return back()->with('success', 'Document uploaded successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to upload document: ' . $e->getMessage());
            return back()->with('error', 'Failed to upload document: ' . $e->getMessage());
        }
    }

    /**
     * Display (Stream/Download) the specified document
     */
    public function show(SupportingDocument $supportingDocument)
    {
        $relativePath = 'supporting-documents/' . $supportingDocument->stored_filename;

        if (!Storage::disk('public')->exists($relativePath)) {
            abort(404, 'File not found on storage.');
        }

        return Storage::disk('public')->download(
            $relativePath,
            $supportingDocument->original_filename,
            ['Content-Type' => $supportingDocument->mime_type]
        );
    }

    /**
     * Remove the specified document
     */
    public function destroy(SupportingDocument $supportingDocument)
    {
        try {
            DB::beginTransaction();
            
            // Delete the file
            $this->fileUploadService->deleteFile($supportingDocument);
            
            // Delete the database record
            $supportingDocument->delete();
            
            DB::commit();
            
            return back()->with('success', 'Document deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete document ID ' . $supportingDocument->id . ': ' . $e->getMessage());
            return back()->with('error', 'Failed to delete document. Please try again.');
        }
    }

    /**
     * Download the specified document
     */
    public function download(SupportingDocument $supportingDocument)
    {
        return $this->show($supportingDocument);
    }
}