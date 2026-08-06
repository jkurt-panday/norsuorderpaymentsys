<?php

namespace App\Services;

use App\Models\FormInput;
use App\Models\SupportingDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class FileUploadService
{
    protected string $disk = 'public';
    protected string $directory = 'supporting-documents';
    protected array $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'svg'];
    protected int $maxFileSize = 10240; // 10MB in KB

    /**
     * Upload a file and create SupportingDocument record
     */
    public function uploadDocument(UploadedFile $file, FormInput $formInput): SupportingDocument
    {
        // Validate file
        $this->validateFile($file);

        // Generate filename
        $storedFilename = $this->generateUniqueFilename($file);

        // Store the file
        $path = $file->storeAs(
            $this->directory,
            $storedFilename,
            $this->disk
        );

        if (! $path) {
            throw new \Exception('Failed to upload file: '.$file->getClientOriginalName());
        }

        // Generate URL
        $fileUrl = Storage::disk($this->disk)->url($this->directory . '/' . $storedFilename);

        // Create database record
        return SupportingDocument::create([
            'form_input_id' => $formInput->id,
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedFilename,
            'file_url' => $fileUrl,
            'mime_type' => $file->getMimeType(),
            'file_extension' => strtolower($file->getClientOriginalExtension()),
            'file_size' => $file->getSize(),
            'uploaded_at' => now(),
        ]);
    }

    /**
     * Upload multiple documents
     */
    public function uploadDocuments(array $files, FormInput $formInput): array
    {
        $uploadedDocuments = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                try {
                    $document = $this->uploadDocument($file, $formInput);
                    $uploadedDocuments[] = $document;
                } catch (\Exception $e) {
                    // Log error but continue with other files
                    \Log::error('Failed to upload document: ' . $e->getMessage());
                }
            }
        }

        return $uploadedDocuments;
    }

    /**
     * Generate a unique filename with timestamp
     */
    protected function generateUniqueFilename(UploadedFile $file): string
    {
        $timestamp = now()->format('Ymd_His');
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        
        // Sanitize original filename
        $safeOriginalName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
        $baseFilename = $timestamp . '_' . $safeOriginalName;
        
        // Ensure unique filename
        $filename = $baseFilename . '.' . $extension;
        $counter = 1;
        
        while (Storage::disk($this->disk)->exists($this->directory . '/' . $filename)) {
            $filename = $baseFilename . '_' . $counter . '.' . $extension;
            $counter++;
        }
        
        return $filename;
    }

    /**
     * Validate file
     */
    protected function validateFile(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $sizeInKB = $file->getSize() / 1024;

        if (!in_array($extension, $this->allowedTypes)) {
            throw new \Exception('File type not allowed. Allowed types: ' . implode(', ', $this->allowedTypes));
        }

        if ($sizeInKB > $this->maxFileSize) {
            throw new \Exception('File size exceeds limit. Maximum size: ' . $this->maxFileSize . ' KB');
        }
    }

    /**
     * Delete a file
     */
    public function deleteFile(SupportingDocument $document): bool
    {
        try {
            $path = $this->directory . '/' . $document->stored_filename;
            
            if (Storage::disk($this->disk)->exists($path)) {
                return Storage::disk($this->disk)->delete($path);
            }
            
            return false;
        } catch (\Exception $e) {
            \Log::error('Failed to delete file: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get file size in human readable format
     */
    public function getHumanReadableSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Check if file exists
     */
    public function fileExists(SupportingDocument $document): bool
    {
        $path = $this->directory . '/' . $document->stored_filename;
        return Storage::disk($this->disk)->exists($path);
    }

    /**
     * Download a file
     */
    public function download(SupportingDocument $document, ?string $customFileName = null): StreamedResponse
    {
        try {
            $path = $this->directory . '/' . $document->stored_filename;
            
            // Check if file exists
            if (!Storage::disk($this->disk)->exists($path)) {
                throw new \Exception('File not found on storage.');
            }

            // Use custom filename if provided, otherwise use original filename
            $downloadFileName = $customFileName ?? $document->original_filename;

            // Return the download response
            return Storage::disk($this->disk)->download(
                $path,
                $downloadFileName,
                [
                    'Content-Type' => $document->mime_type,
                    'Content-Disposition' => 'attachment; filename="' . $downloadFileName . '"',
                ]
            );

        } catch (\Exception $e) {
            Log::error('Failed to download document: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Stream a file for viewing (inline display) instead of downloading
     */
    public function stream(SupportingDocument $document): StreamedResponse
    {
        try {
            $path = $this->directory . '/' . $document->stored_filename;
            
            if (!Storage::disk($this->disk)->exists($path)) {
                throw new \Exception('File not found on storage.');
            }

            // Return the file for inline viewing
            return Storage::disk($this->disk)->response(
                $path,
                $document->original_filename,
                [
                    'Content-Type' => $document->mime_type,
                    'Content-Disposition' => 'inline; filename="' . $document->original_filename . '"',
                ]
            );

        } catch (\Exception $e) {
            Log::error('Failed to stream document: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get the full storage path of a document
     */
    public function getStoragePath(SupportingDocument $document): string
    {
        return Storage::disk($this->disk)->path($this->directory . '/' . $document->stored_filename);
    }

    /**
     * Get the public URL of a document
     */
    public function getPublicUrl(SupportingDocument $document): string
    {
        return Storage::disk($this->disk)->url($this->directory . '/' . $document->stored_filename);
    }
}
