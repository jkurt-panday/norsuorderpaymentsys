<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    /**
     * Upload a file and return the metadata
     */
    public function upload(UploadedFile $file, int $formInputId, string $directory = 'supporting-documents'): array
    {
        // Generate new filename: YYYYMMDD_HHMMSS_originalfilename.ext
        $timestamp = now()->format('Ymd_His');
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        
        // Sanitize original filename (remove special characters)
        $safeOriginalName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
        $storedFilename = $timestamp . '_' . $safeOriginalName . '.' . $extension;
        
        // Ensure unique filename
        $counter = 1;
        while (Storage::exists('public/' . $directory . '/' . $storedFilename)) {
            $storedFilename = $timestamp . '_' . $safeOriginalName . '_' . $counter . '.' . $extension;
            $counter++;
        }

        // Store the file
        $path = $file->storeAs(
            'public/' . $directory,
            $storedFilename
        );

        if (!$path) {
            throw new \Exception('Failed to upload file: ' . $file->getClientOriginalName());
        }

        // Return metadata
        return [
            'form_input_id' => $formInputId,
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedFilename,
            'file_url' => '/storage/' . $directory . '/' . $storedFilename,
            'mime_type' => $file->getMimeType(),
            'file_extension' => $extension,
            'file_size' => $file->getSize(),
            'uploaded_at' => now(),
        ];
    }

    /**
     * Delete a file
     */
    public function delete(string $storedFilename, string $directory = 'supporting-documents'): bool
    {
        $path = 'public/' . $directory . '/' . $storedFilename;
        if (Storage::exists($path)) {
            return Storage::delete($path);
        }
        return false;
    }

    /**
     * Validate file type
     */
    public function isValidFileType(UploadedFile $file, array $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png']): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        return in_array($extension, $allowedTypes);
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
        return round($bytes, 2) . ' ' . $units[$i];
    }
}