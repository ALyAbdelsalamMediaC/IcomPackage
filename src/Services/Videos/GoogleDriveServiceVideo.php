<?php

namespace AlyIcom\MyPackage\Services\Videos;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;
use Google\Http\MediaFileUpload;

class GoogleDriveServiceVideo
{
    protected $client;
    protected $service;

    public function __construct()
    {
        $this->client = new Client();
        $credentialsPath = storage_path('app/credentials.json');

        if (!file_exists($credentialsPath)) {
            throw new \Exception('Credentials file not found at: ' . $credentialsPath);
        }

        $credentials = json_decode(file_get_contents($credentialsPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON in credentials file: ' . json_last_error_msg());
        }

        if (!isset($credentials['web']['client_id']) || !isset($credentials['web']['client_secret'])) {
            throw new \Exception('Client ID or Client Secret missing in credentials.json');
        }

        $this->client->setClientId($credentials['web']['client_id']);
        $this->client->setClientSecret($credentials['web']['client_secret']);
        $this->client->setAccessType('offline');
        $this->client->setScopes([Drive::DRIVE_FILE]);
        
        $redirectUri = config('my-package.google_drive.redirect_uri');
        if (!$redirectUri) {
            throw new \Exception('GOOGLE_DRIVE_REDIRECT_URI is not set in your .env file');
        }
        $this->client->setRedirectUri($redirectUri);

        // Load existing token
        $tokenPath = storage_path('app/google-token.json');

        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON in google-token.json: ' . json_last_error_msg());
                throw new \Exception('Invalid JSON in google-token.json');
            }
            $this->client->setAccessToken($accessToken);
        } else {
            Log::warning('No token file found at: ' . $tokenPath);
            throw new \Exception('No token file found. Please authenticate to generate a new token.');
        }

        // Refresh token if expired
        if ($this->client->isAccessTokenExpired()) {
            try {
                if (!isset($accessToken['refresh_token'])) {
                    throw new \Exception('No refresh token available in google-token.json');
                }

                $newAccessToken = $this->client->fetchAccessTokenWithRefreshToken($accessToken['refresh_token']);
                
                if (isset($newAccessToken['error'])) {
                    Log::error('Failed to refresh access token: ' . $newAccessToken['error_description']);
                    throw new \Exception('Failed to refresh access token: ' . $newAccessToken['error_description']);
                }

                // Update created time and save the new token
                $newAccessToken['created'] = time();
                $this->client->setAccessToken($newAccessToken);
                
                // Save the new token to file
                if (file_put_contents($tokenPath, json_encode($newAccessToken, JSON_PRETTY_PRINT)) === false) {
                    Log::error('Failed to write new token to: ' . $tokenPath);
                    throw new \Exception('Failed to save new access token to file');
                }

                Log::info('Access token refreshed and saved successfully');
            } catch (\Exception $e) {
                Log::error('Token refresh failed: ' . $e->getMessage());
                throw new \Exception('Unable to refresh access token: ' . $e->getMessage());
            }
        }

        $this->service = new Drive($this->client);
    }

    public function getAuthUrl()
    {
        $redirectUri = config('my-package.google_drive.redirect_uri');
        if (!$redirectUri) {
            throw new \Exception('GOOGLE_DRIVE_REDIRECT_URI is not set in your .env file');
        }
        $this->client->setRedirectUri($redirectUri);
        return $this->client->createAuthUrl();
    }

    public function getClient()
    {
        return $this->client;
    }

    public function uploadFile($file, $name)
    {
        $folderId = config('my-package.google_drive.videos_folder_id');
        if (!$folderId) {
            throw new \Exception('GOOGLE_DRIVE_FOLDER_V_VIDEOS is not set in your .env file');
        }
        
        $fileMetadata = new DriveFile([
            'name' => $name,
            'parents' => [$folderId],
        ]);

        $service = $this->service;
        $client = $this->client;

        $filePath = $file->getRealPath();
        $mimeType = $file->getMimeType();
        $fileSize = filesize($filePath);

        if ($fileSize === 0) {
            throw new \Exception('File is empty or cannot be read.');
        }

        $chunkSizeBytes = 1 * 1024 * 1024; // 1MB chunks – adjust if needed (min 256KB per Google docs)

        // Defer the request to handle media upload
        $client->setDefer(true);
        $request = $service->files->create($fileMetadata, ['fields' => 'id']);

        // Set up media upload
        $media = new MediaFileUpload(
            $client,
            $request,
            $mimeType,
            null,
            true, // Enable resumable
            $chunkSizeBytes
        );
        $media->setFileSize($fileSize);

        // Open file handle and upload chunks
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            $client->setDefer(false);
            throw new \Exception('Failed to open file for reading: ' . $filePath);
        }

        $status = false;
        while (!$status && !feof($handle)) {
            $chunk = fread($handle, $chunkSizeBytes);
            $status = $media->nextChunk($chunk);
        }

        fclose($handle);
        $client->setDefer(false);

        if ($status === false) {
            throw new \Exception('Failed to upload file to Google Drive.');
        }

        $fileId = $status->getId();

        // Make the file public
        $permission = new \Google\Service\Drive\Permission();
        $permission->setRole('reader');
        $permission->setType('anyone');
        $this->service->permissions->create($fileId, $permission);

        return $fileId;
    }

    public function getFileIdFromUrl($url)
    {
        // Extract file ID from Google Drive URL
        if (preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function deleteFile($fileId)
    {
        try {
            $this->service->files->delete($fileId);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

