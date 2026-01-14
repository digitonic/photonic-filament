<?php

namespace Digitonic\Photonic\Filament\Http\Integrations\Photonic\Requests;

use Digitonic\Photonic\Filament\Http\Integrations\Photonic\API;
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
        protected mixed $key,
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
        $siteUuid = $this->siteId ?? config('photonic-filament.site_uuid');

        $body = [
            new MultipartValue('site_uuid', (string) $siteUuid),
            new MultipartValue('filename', $this->fileName),
            new MultipartValue('key', $this->key),
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
