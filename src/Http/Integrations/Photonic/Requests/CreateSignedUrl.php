<?php

namespace Digitonic\Photonic\Filament\Http\Integrations\Photonic\Requests;

use Digitonic\Photonic\Filament\Http\Integrations\Photonic\API;
use Saloon\Contracts\Body\HasBody;
use Saloon\Data\MultipartValue;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasMultipartBody;
use Saloon\Traits\Request\HasConnector;

class CreateSignedUrl extends Request implements HasBody
{
    use HasConnector, HasMultipartBody;

    protected string $connector = API::class;

    /**
     * Define the HTTP method
     */
    protected Method $method = Method::POST;

    public function __construct(
        protected ?string $siteId,
        protected string $fileName,
        protected ?string $contentType,
    ) {}

    /**
     * Define the endpoint for the request
     */
    public function resolveEndpoint(): string
    {
        return '/assets/signed-upload';
    }

    /**
     * @return array<int, MultipartValue>
     */
    public function defaultBody(): array
    {
        $siteUuid = $this->siteId ?? config('photonic-filament.site_uuid');

        return [
            new MultipartValue('site_uuid', (string) $siteUuid),
            new MultipartValue('filename', $this->fileName),
            new MultipartValue('content_type', $this->contentType ?? 'application/octet-stream'),
        ];
    }
}
