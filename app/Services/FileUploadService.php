<?php

namespace App\Services;

use App\Models\FormInput;
use App\Models\SupportingDocument;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Backwards-compatible document storage facade.
 *
 * Supporting documents are stored in Google Drive. The previous S3/MinIO
 * implementation is intentionally no longer used; callers keep this service
 * name so public submission flows do not need to know the provider.
 */
class FileUploadService
{
    /**
     * Resolve Drive lazily. Public forms must be able to load without making
     * an OAuth request; Drive is only needed when a file is actually uploaded.
     */
    private function drive(): GoogleDriveStorageService
    {
        return app(GoogleDriveStorageService::class);
    }

    public function uploadDocument(UploadedFile $file, FormInput $formInput): SupportingDocument
    {
        return $this->drive()->uploadDocument($file, $formInput);
    }

    /** @param array<int, mixed> $files */
    public function uploadDocuments(array $files, FormInput $formInput): array
    {
        $uploaded = [];
        try {
            foreach ($files as $file) {
                if ($file instanceof UploadedFile) {
                    $uploaded[] = $this->uploadDocument($file, $formInput);
                }
            }
        } catch (\Throwable $exception) {
            $this->cleanupUploadedDocuments($uploaded);
            throw $exception;
        }

        return $uploaded;
    }

    /** @param list<SupportingDocument> $documents */
    public function cleanupUploadedDocuments(array $documents): void
    {
        foreach ($documents as $document) {
            try {
                $this->deleteFile($document);
                $document->delete();
            } catch (\Throwable) {
                // Cleanup must not hide the original transaction/upload error.
            }
        }
    }

    public function deleteFile(SupportingDocument $document): bool
    {
        $this->drive->delete($document);
        return true;
    }

    public function fileExists(SupportingDocument $document): bool
    {
        return $document->storage_provider === 'google_drive'
            && filled($document->google_drive_file_id);
    }

    public function download(SupportingDocument $document, ?string $customFileName = null): StreamedResponse
    {
        return $this->drive->download($document);
    }

    public function stream(SupportingDocument $document): StreamedResponse
    {
        return $this->drive->download($document, true);
    }

    public function getPublicUrl(SupportingDocument $document): string
    {
        return route('documents.download', ['supportingDocument' => $document]);
    }
}
