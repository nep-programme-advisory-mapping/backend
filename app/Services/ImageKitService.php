<?php

namespace App\Services;

use ImageKit\ImageKit;

class ImageKitService
{
    private ImageKit $client;

    public function __construct()
    {
        $this->client = new ImageKit(
            config('services.imagekit.public_key'),
            config('services.imagekit.private_key'),
            config('services.imagekit.url_endpoint'),
        );
    }

    public function upload(string $filePath, string $fileName, string $folder = '/'): string
    {
        $result = $this->client->uploadFile([
            'file'              => base64_encode(file_get_contents($filePath)),
            'fileName'          => $fileName,
            'folder'            => $folder,
            'useUniqueFileName' => true,
        ]);

        if ($result->error) {
            throw new \RuntimeException('ImageKit upload failed: ' . json_encode($result->error));
        }

        return $result->result->fileId . '|' . $result->result->filePath;
    }

    public function delete(string $logoPath): void
    {
        $fileId = explode('|', $logoPath)[0];
        $this->client->deleteFile($fileId);
    }

    public function url(string $fileId): string
    {
        return $this->client->getUrl(['src' => config('services.imagekit.url_endpoint') . '/' . $fileId]);
    }
}
