<?php

namespace Digitonic\Photonic\Filament\Services;

use Digitonic\Photonic\Filament\Http\Integrations\Photonic\API;
use Digitonic\Photonic\Filament\Http\Integrations\Photonic\Requests\CreateAsset;
use Digitonic\Photonic\Filament\Http\Integrations\Photonic\Requests\CreateSignedUrl;
use Digitonic\Photonic\Filament\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class MediaUploadService
{
    /**
     * Upload a file to the Mediatonic API and persist a media record.
     * Returns the created Media model.
     *
     * @param  UploadedFile|TemporaryUploadedFile|string|resource  $file  A file instance, local path, or stream resource
     * @param  array<string,mixed>  $options  Additional metadata: alt, title, description, caption, model (Eloquent model), site_uuid override
     */
    public function upload($file, array $options = []): Media
    {
        $endpoint = config('photonic-filament.endpoint');
        if (blank($endpoint)) {
            throw new \RuntimeException('Photonic endpoint is not configured. Set photonic-filament.endpoint.');
        }

        $model = $options['model'] ?? null;
        if ($model !== null && ! $model instanceof Model) {
            throw new \InvalidArgumentException('The model option must be an instance of '.Model::class.' or null.');
        }

        [$fileStream, $originalName] = $this->resolveFileStreamAndName($file);
        $fileConfig = $this->buildFileConfig($file, $fileStream);
        $contentType = $fileConfig['mime_type'] ?? 'application/octet-stream';

        // Step 1: Request a signed URL from the API
        $signedUrlRequest = new CreateSignedUrl(
            siteId: $options['site_uuid'] ?? config('photonic-filament.site_uuid'),
            fileName: $originalName,
            contentType: $contentType,
        );

        $api = new API;
        $signedUrlResponse = $api->send($signedUrlRequest);
        $signedUrlData = $signedUrlResponse->json() ?? [];

        if (empty($signedUrlData['url']) || empty($signedUrlData['key'])) {
            throw new \RuntimeException('Failed to get signed URL from Photonic API. Response: '.json_encode($signedUrlResponse->json().' response context: '.json_encode($signedUrlResponse)));
        }

        $signedUrl = $signedUrlData['url'];
        $s3Key = $signedUrlData['key'];

        // Step 2: Upload the file to S3 using the signed URL
        // Read the file content from the stream
        $streamMeta = stream_get_meta_data($fileStream);

        if ($streamMeta['seekable']) {
            rewind($fileStream);
        }

        $fileContent = stream_get_contents($fileStream);

        // Close the stream if it's a resource we opened
        if (is_resource($fileStream)) {
            fclose($fileStream);
        }

        $uploadResponse = Http::withHeaders([
            'Content-Type' => $contentType,
        ])
            ->withBody($fileContent, $contentType)
            ->put($signedUrl);

        if (! $uploadResponse->successful()) {
            throw new \RuntimeException('Failed to upload file to S3. Status: '.$uploadResponse->status());
        }

        // Step 3: Create the asset record with the S3 key
        $createAssetRequest = new CreateAsset(
            siteId: $options['site_uuid'] ?? config('photonic-filament.site_uuid'),
            key: $s3Key,
            fileName: $originalName,
            alt: $options['alt'] ?? null,
            title: $options['title'] ?? null,
            description: $options['description'] ?? null,
            caption: $options['caption'] ?? null,
        );

        $response = $api->send($createAssetRequest);
        $json = $response->json()['data'] ?? [];

        $responseKey = config('photonic-filament.response_key', 'original_filename');
        $filename = $json[$responseKey] ?? $json['filename'] ?? $originalName;
        $assetUuid = $json['uuid'] ?? null;

        /** @var class-string<Media> $mediaModelClass */
        $mediaModelClass = config('photonic-filament.media_model', Media::class);

        $attributes = [
            'asset_uuid' => $assetUuid,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model?->getKey(),
            'filename' => (string) $filename,
            'alt' => $options['alt'] ?? null,
            'title' => $options['title'] ?? null,
            'description' => $options['description'] ?? null,
            'caption' => $options['caption'] ?? null,
            'config' => $fileConfig,
        ];

        /** @var Media $media */
        $media = $mediaModelClass::create($attributes);

        return $media;
    }

    /**
     * Convenience method: upload and return only the media ID.
     *
     * @param  UploadedFile|TemporaryUploadedFile|string|resource  $file
     * @param  array<string,mixed>  $options
     */
    public function uploadAndReturnId($file, array $options = []): int
    {
        return $this->upload($file, $options)->id;
    }

    /**
     * @param  UploadedFile|TemporaryUploadedFile|string|resource  $file
     * @return array{0: resource,1: string}
     */
    protected function resolveFileStreamAndName($file): array
    {
        // Already a resource
        if (is_resource($file)) {
            return [$file, 'uploaded-file'];
        }

        // Livewire temporary upload
        if ($file instanceof TemporaryUploadedFile) {
            $realPath = $file->getRealPath();
            if (! $realPath || ! file_exists($realPath)) {
                $stream = fopen($file->temporaryUrl(), 'r');
            } else {
                $stream = fopen($realPath, 'r');
            }

            return [$stream, $file->getClientOriginalName()];
        }

        // Standard UploadedFile
        if ($file instanceof UploadedFile) {
            $stream = fopen($file->getRealPath(), 'r');

            return [$stream, $file->getClientOriginalName()];
        }

        // String path
        if (is_string($file)) {
            if (! is_file($file)) {
                throw new \InvalidArgumentException("File path '{$file}' does not exist or is not a file.");
            }
            $stream = fopen($file, 'r');

            return [$stream, basename($file)];
        }

        throw new \InvalidArgumentException('Unsupported file type provided to MediaUploadService::upload');
    }

    /**
     * Build config array (mime, extension, size, dimensions, hash_name placeholder) similar to form component.
     *
     * @param  UploadedFile|TemporaryUploadedFile|string|resource  $file
     * @param  resource  $stream
     * @return array<string,int|string|null>
     */
    protected function buildFileConfig($file, $stream): array
    {
        $mime = null;
        $size = null;
        $extension = null;
        $hashName = null;
        $width = null;
        $height = null;

        // Width/height only if we can get a real path
        $path = null;
        if ($file instanceof TemporaryUploadedFile || $file instanceof UploadedFile) {
            $path = $file->getRealPath();
        } elseif (is_string($file)) {
            $path = $file;
        }

        if ($path && is_file($path)) {
            $imageInfo = @getimagesize($path);
            if ($imageInfo !== false) {
                $width = $imageInfo[0];
                $height = $imageInfo[1];
            }
        }

        if ($file instanceof TemporaryUploadedFile) {
            $mime = $file->getMimeType();
            $size = $file->getSize();
            $extension = $file->getClientOriginalExtension();
            $hashName = $file->hashName();
        } elseif ($file instanceof UploadedFile) {
            $mime = $file->getClientMimeType();
            $size = $file->getSize();
            $extension = $file->getClientOriginalExtension();
            $hashName = $file->hashName();
        } elseif (is_string($file)) {
            $size = filesize($file);
            $mime = mime_content_type($file) ?: null;
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            $hashName = md5_file($file).'.'.$extension;
        } else {
            // Resource case – attempt minimal metadata
            $meta = stream_get_meta_data($stream);
            $hashName = md5(json_encode($meta)).'.bin';
        }

        return [
            'mime_type' => $mime,
            'extension' => $extension,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'hash_name' => $hashName,
        ];
    }
}
