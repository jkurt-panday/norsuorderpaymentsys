<?php

namespace App\Http\Controllers;

use App\Models\FormInput;
use App\Models\StaffInput;
use App\Models\SupportingDocument;
use App\Services\GoogleDriveStorageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportingDocumentController extends Controller
{
    private const FOLDER_NAME = 'supporting-documents';

    public function index(): View
    {
        $documents = SupportingDocument::with('formInput')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('staff.documents.index', compact('documents'));
    }

    public function store(Request $request, GoogleDriveStorageService $storage): RedirectResponse
    {
        $validated = $request->validate([
            'form_input_id' => ['required', 'exists:form_inputs,id'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $storage->uploadDocument(
            $request->file('document'),
            FormInput::findOrFail($validated['form_input_id']),
        );

        return back()->with('success', 'Document uploaded successfully.');
    }

    public function view(SupportingDocument $supportingDocument, GoogleDriveStorageService $storage): StreamedResponse
    {
        return $storage->download($supportingDocument, true);
    }

    public function download(SupportingDocument $supportingDocument, GoogleDriveStorageService $storage): StreamedResponse
    {
        return $storage->download($supportingDocument);
    }

    public function destroy(SupportingDocument $supportingDocument, GoogleDriveStorageService $storage): RedirectResponse
    {
        try {
            $storage->delete($supportingDocument);
            DB::transaction(function () use ($supportingDocument): void {
                StaffInput::query()->where('ref_document_id', $supportingDocument->id)->update(['ref_document_id' => null]);
                $supportingDocument->delete();
            });

            return back()->with('success', 'Document deleted successfully.');
        } catch (\Throwable $exception) {
            Log::error('Failed to delete supporting document.', [
                'document_id' => $supportingDocument->id,
                'error' => $exception->getMessage(),
            ]);

            return back()->with('error', 'Failed to delete document. Please try again.');
        }
    }
}
