<?php

namespace App\Http\Controllers;

use App\Models\SupportingDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SupportingDocumentController extends Controller
{
    /**
     * Display a listing of documents (optional)
     */
    public function index()
    {
        $documents = SupportingDocument::with('formInput')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return view('staff.documents.index', compact('documents'));
    }

    /**
     * Display the specified document
     */
    public function show(SupportingDocument $supportingDocument)
    {
        return response()->download(
            Storage::path('public/supporting-documents/' . $supportingDocument->stored_filename),
            $supportingDocument->original_filename
        );
    }

    /**
     * Remove the specified document
     */
    public function destroy(SupportingDocument $supportingDocument)
    {
        // Check if document is used as reference
        if ($supportingDocument->staffInputs()->exists()) {
            return back()->with('error', 'Cannot delete document that is used as reference.');
        }

        // Delete physical file
        $filePath = 'public/supporting-documents/' . $supportingDocument->stored_filename;
        if (Storage::exists($filePath)) {
            Storage::delete($filePath);
        }

        $supportingDocument->delete();
        return back()->with('success', 'Document deleted successfully.');
    }

    /**
     * Download the specified document
     */
    public function download(SupportingDocument $supportingDocument)
    {
        $filePath = storage_path('app/public/supporting-documents/' . $supportingDocument->stored_filename);
        
        if (!file_exists($filePath)) {
            abort(404, 'File not found');
        }

        return response()->download(
            $filePath,
            $supportingDocument->original_filename,
            [
                'Content-Type' => $supportingDocument->mime_type,
            ]
        );
    }
}