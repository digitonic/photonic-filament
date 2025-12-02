<?php

namespace Digitonic\MediaTonic\Filament\Services;

use Digitonic\MediaTonic\Filament\Http\Integrations\Mediatonic\API;
use Digitonic\MediaTonic\Filament\Http\Integrations\Mediatonic\Requests\CreateAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Digitonic\MediaTonic\Filament\Models\Media;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class MediaUploadService
{
    /**
     * Upload a file to the Mediatonic API and persist a media record.
     * Returns the created Media model.
     *
     * @param  UploadedFile|TemporaryUploadedFile|string|resource  $file  A file instance, local path, or stream resource
     * @param  array<string,mixed>  $options Additional metadata: alt, title, description, caption, model (Eloquent model), site_uuid override
     */
    public function upload($file, array $options = []): Media
    {
        $endpoint = config('mediatonic-filament.endpoint');
        if (blank($endpoint)) {
            throw new \RuntimeException('Mediatonic endpoint is not configured. Set mediatonic-filament.endpoint.');
        }

        $model = $options['model'] ?? null;
        if ($model !== null && ! $model instanceof Model) {
            throw new \InvalidArgumentException('The model option must be an instance of '.Model::class.' or null.');
        }

        [$fileStream, $originalName] = $this->resolveFileStreamAndName($file);

        $request = new CreateAsset(
            siteId: config('mediatonic-filament.site_uuid'),
            fileStream: $fileStream,
            fileName: $originalName,
            alt: $options['alt'] ?? null,
            title: $options['title'] ?? null,
            description: $options['description'] ?? null,
            caption: $options['caption'] ?? null,
        );

        $api = new API();
        $response = $api->send($request);
        $json = $response->json()['data'] ?? [];

        $responseKey = config('mediatonic-filament.response_key', 'original_filename');
        $filename = $json[$responseKey] ?? $json['filename'] ?? $originalName;
        $assetUuid = $json['uuid'] ?? null;

        $fileConfig = $this->buildFileConfig($file, $fileStream);

        /** @var class-string<Media> $mediaModelClass */
        $mediaModelClass = config('mediatonic-filament.media_model', Media::class);

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
     * @param  array<string,mixed> $options
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
     * @param  resource $stream
     * @return array<string,int|string|null>
     */
    protected function buildFileConfig($file, $stream): array
    {
        $mime = null; $size = null; $extension = null; $hashName = null; $width = null; $height = null;

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

