<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FormInput;
use App\Models\StaffInput;
use App\Models\SupportingDocument;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SupportingDocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $documents = SupportingDocument::query()
            ->with('formInput:id,reference_number,firstname_or_office,lastname_or_agency')
            ->when($request->filled('form_input_id'), fn ($query) => $query->where('form_input_id', $request->integer('form_input_id')))
            ->latest('uploaded_at')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100))
            ->withQueryString();

        return response()->json([
            'data' => $documents->getCollection()->map(fn (SupportingDocument $document): array => $this->documentData($document))->values(),
            'meta' => [
                'current_page' => $documents->currentPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
                'last_page' => $documents->lastPage(),
            ],
            'links' => ['next' => $documents->nextPageUrl(), 'prev' => $documents->previousPageUrl()],
        ]);
    }

    public function store(Request $request, FileUploadService $uploadService): JsonResponse
    {
        $validated = $request->validate([
            'form_input_id' => ['required', 'integer', 'exists:form_inputs,id'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $document = $uploadService->uploadDocument(
            $request->file('document'),
            FormInput::findOrFail($validated['form_input_id']),
        );

        return response()->json([
            'message' => 'Document uploaded successfully.',
            'data' => $this->documentData($document->load('formInput')),
        ], 201);
    }

    public function view(SupportingDocument $supportingDocument, FileUploadService $uploadService): StreamedResponse
    {
        return $uploadService->stream($supportingDocument);
    }

    public function download(SupportingDocument $supportingDocument, FileUploadService $uploadService): StreamedResponse
    {
        return $uploadService->download($supportingDocument);
    }

    public function destroy(SupportingDocument $supportingDocument, FileUploadService $uploadService): Response|JsonResponse
    {
        try {
            DB::transaction(function () use ($supportingDocument): void {
                StaffInput::query()->where('ref_document_id', $supportingDocument->id)->update(['ref_document_id' => null]);
                $supportingDocument->delete();
            });

            $uploadService->deleteFile($supportingDocument);
        } catch (Throwable $exception) {
            Log::error('API supporting document deletion failed', [
                'document_id' => $supportingDocument->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Document deletion failed. Please try again.'], 500);
        }

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function documentData(SupportingDocument $document): array
    {
        return [
            'id' => $document->id,
            'form_input_id' => $document->form_input_id,
            'original_filename' => basename($document->original_filename),
            'mime_type' => $document->mime_type,
            'file_extension' => $document->file_extension,
            'file_size' => $document->file_size,
            'uploaded_at' => $document->uploaded_at?->toISOString(),
            'form_input' => $document->relationLoaded('formInput') && $document->formInput ? [
                'id' => $document->formInput->id,
                'reference_number' => $document->formInput->reference_number,
                'full_name' => $document->formInput->full_name,
            ] : null,
        ];
    }
}
