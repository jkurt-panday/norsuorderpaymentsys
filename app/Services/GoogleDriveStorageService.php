<?php

namespace App\Services;

use App\Models\FormInput;
use App\Models\SupportingDocument;
use Google\Client;
use Google\Http\MediaFileUpload;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GoogleDriveStorageService
{
    private const FOLDER_NAME = 'supporting-documents';

    private Client $client;

    private Drive $drive;

    public function __construct()
    {
        $this->client = new Client;
        $this->client->setClientId((string) config('services.google_drive.client_id'));
        $this->client->setClientSecret((string) config('services.google_drive.client_secret'));
        $this->client->setAccessType('offline');
        $this->client->setScopes([Drive::DRIVE_FILE]);

        $refreshToken = trim((string) config('services.google_drive.refresh_token'));
        if ($refreshToken === '') {
            throw new \RuntimeException('GOOGLE_DRIVE_REFRESH_TOKEN is not configured.');
        }

        // fetchAccessTokenWithRefreshToken() returns the token but does not
        // reliably install it on every google/apiclient version. Explicitly
        // set it so Drive requests include the Bearer access token.
        $accessToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
        if (isset($accessToken['error'])) {
            throw new \RuntimeException(
                'Google Drive OAuth refresh failed: '.($accessToken['error_description'] ?? $accessToken['error'])
            );
        }
        $this->client->setAccessToken($accessToken);

        if ($this->client->isAccessTokenExpired()) {
            throw new \RuntimeException('Google Drive OAuth access token is expired and could not be refreshed.');
        }

        $this->drive = new Drive($this->client);
    }

    public function uploadDocument(UploadedFile $file, FormInput $formInput): SupportingDocument
    {
        $filename = $this->safeFilename($file->getClientOriginalName());
        $metadata = new DriveFile([
            'name' => $filename,
            'parents' => [$this->folderId()],
            'description' => 'NORSU supporting document for form input '.$formInput->id,
        ]);

        $this->client->setDefer(true);
        $request = $this->drive->files->create($metadata, [
            'fields' => 'id,name,mimeType,size',
            'uploadType' => 'resumable',
        ]);
        $uploader = new MediaFileUpload(
            $this->client,
            $request,
            $file->getMimeType() ?: 'application/octet-stream',
            null,
            true,
            1024 * 1024
        );
        $uploader->setFileSize($file->getSize());
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            $this->client->setDefer(false);
            throw new \RuntimeException('Unable to read uploaded file.');
        }

        try {
            $uploaded = null;
            while (! feof($handle)) {
                $uploaded = $uploader->nextChunk(fread($handle, 1024 * 1024));
            }
        } finally {
            fclose($handle);
            $this->client->setDefer(false);
        }

        if (! $uploaded instanceof DriveFile || ! $uploaded->getId()) {
            throw new \RuntimeException('Google Drive upload failed.');
        }

        return SupportingDocument::create([
            'form_input_id' => $formInput->id,
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $filename,
            'file_url' => 'gdrive://'.$uploaded->getId(),
            'google_drive_file_id' => $uploaded->getId(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'file_extension' => strtolower($file->getClientOriginalExtension()),
            'file_size' => $file->getSize(),
            'uploaded_at' => now(),
        ]);
    }

    public function download(SupportingDocument $document, bool $inline = false): StreamedResponse
    {
        $fileId = $document->google_drive_file_id;
        if (! $fileId) {
            abort(404, 'Google Drive file is not configured for this document.');
        }

        $name = basename($document->original_filename);
        $mime = $document->mime_type ?: 'application/octet-stream';

        return response()->stream(function () use ($fileId): void {
            $response = $this->drive->files->get($fileId, ['alt' => 'media']);
            $body = $response->getBody();
            while (! $body->eof()) {
                echo $body->read(1024 * 1024);
                flush();
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$name.'"',
            'Content-Length' => (string) $document->file_size,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function delete(SupportingDocument $document): void
    {
        if ($document->google_drive_file_id) {
            $this->drive->files->delete($document->google_drive_file_id);
        }
    }

    private function folderId(): string
    {
        $folderId = trim((string) config('services.google_drive.folder_id'));
        if ($folderId === '') {
            throw new \RuntimeException('GOOGLE_DRIVE_FOLDER_ID is not configured.');
        }
        return $folderId;
    }

    private function safeFilename(string $original): string
    {
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $base = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($original, PATHINFO_FILENAME)) ?: 'document';
        return now()->format('Ymd_His').'_'.$base.'_'.bin2hex(random_bytes(4)).($extension ? '.'.$extension : '');
    }
}
