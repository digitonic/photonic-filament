<?php

namespace Digitonic\MediaTonic\Filament\Http\Integrations\MediaTonic\Requests;

use Digitonic\MediaTonic\Filament\Http\Integrations\MediaTonic\API;
use Saloon\Contracts\Body\HasBody;
use Saloon\Data\MultipartValue;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasMultipartBody;
use Saloon\Traits\Request\HasConnector;

class CreateAsset extends Request implements HasBody
{
    use HasConnector, HasMultipartBody;

    protected string $connector = API::class;

    /**
     * Define the HTTP method
     */
    protected Method $method = Method::POST;

    public function __construct(
        protected ?string $siteId,
        protected mixed $fileStream,
        protected string $fileName,
        protected ?string $alt = null,
        protected ?string $title = null,
        protected ?string $description = null,
        protected ?string $caption = null,
    ) {}

    /**
     * Define the endpoint for the request
     */
    public function resolveEndpoint(): string
    {
        return '/assets';
    }

    /**
     * @return array<int, MultipartValue>
     */
    public function defaultBody(): array
    {
        $body = [
            new MultipartValue('site_uuid', (string) ($this->siteId ?? config('mediatonic-filament.site_uuid'))),
            new MultipartValue(config('mediatonic-filament.file_field'), $this->fileStream, $this->fileName),
        ];

        // Add optional fields if they are provided
        if ($this->alt !== null) {
            $body[] = new MultipartValue('alt', $this->alt);
        }

        if ($this->title !== null) {
            $body[] = new MultipartValue('title', $this->title);
        }

        if ($this->description !== null) {
            $body[] = new MultipartValue('description', $this->description);
        }

        if ($this->caption !== null) {
            $body[] = new MultipartValue('caption', $this->caption);
        }

        return $body;
    }
}
