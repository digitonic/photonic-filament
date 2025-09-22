<?php

namespace Digitonic\Filament\IgsField\Forms\Components;

use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class IgsInput extends FileUpload
{
    protected ?string $igsEndpoint = null;
    protected ?string $igsFileField = null;
    protected ?string $igsResponseKey = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Treat as image upload by default
        $this->image();

        // Intercept the save process to send the file to the IGS API and
        // store the returned filename in the field state / database.
        $this->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string {
            $endpoint = $this->igsEndpoint ?? config('igs-field.endpoint');
            $fileField = $this->igsFileField ?? config('igs-field.file_field', 'file');
            $responseKey = $this->igsResponseKey ?? config('igs-field.response_key', 'filename');

            if (blank($endpoint)) {
                throw new \RuntimeException('IGS endpoint is not configured. Set igs-field.endpoint in your config.');
            }

            $stream = Storage::readStream($file->getRealPath());


            try {
                $response = Http::asMultipart()
                    ->attach($fileField, $stream, $file->getClientOriginalName())
                    ->post($endpoint . '/upload/' . config('igs-field.site_uuid'));
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            // Throw on HTTP errors
            $response->throw();

            // Try to parse a filename from JSON first
            $json = $response->json();
            if (is_array($json)) {
                if ($responseKey && array_key_exists($responseKey, $json)) {
                    return (string) $json[$responseKey];
                }

                // Fallback common keys
                foreach (['filename', 'file', 'name'] as $key) {
                    if (array_key_exists($key, $json)) {
                        return (string) $json[$key];
                    }
                }
            }

            // Fallback to raw body
            $body = trim((string) $response->body());
            if ($body !== '') {
                return $body;
            }

            throw new \RuntimeException('IGS endpoint response did not contain a filename.');
        });
    }

    /**
     * Override API endpoint per field instance.
     */
    public function endpoint(?string $endpoint): static
    {
        $this->igsEndpoint = $endpoint;

        return $this;
    }

    /**
     * Override the multipart file field name per field instance.
     */
    public function fileField(string $name): static
    {
        $this->igsFileField = $name;

        return $this;
    }

    /**
     * Override the response key used to extract the filename per field instance.
     */
    public function responseKey(?string $key): static
    {
        $this->igsResponseKey = $key;

        return $this;
    }
}
