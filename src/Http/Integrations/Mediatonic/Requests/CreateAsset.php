<?php

namespace Digitonic\Mediatonic\Filament\Http\Integrations\Mediatonic\Requests;

use Digitonic\Mediatonic\Filament\Http\Integrations\Mediatonic\API;
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
        return [
            new MultipartValue('site_uuid', (string) ($this->siteId ?? config('mediatonic-filament.site_uuid'))),
            new MultipartValue(config('mediatonic-filament.file_field'), $this->fileStream, $this->fileName),
        ];
    }
}
