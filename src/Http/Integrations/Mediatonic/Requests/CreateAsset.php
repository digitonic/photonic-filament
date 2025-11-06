<?php

namespace Digitonic\Mediatonic\Filament\Http\Integrations\Mediatonic\Requests;

use Digitonic\Mediatonic\Filament\Http\Integrations\Mediatonic\API;
use Saloon\Contracts\Body\BodyRepository;
use Saloon\Contracts\Body\HasBody;
use Saloon\Data\MultipartValue;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Repositories\Body\MultipartBodyRepository;
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
        protected mixed $file
    ) {}

    /**
     * Define the endpoint for the request
     */
    public function resolveEndpoint(): string
    {
        return '/assets';
    }

    public function defaultBody(): array
    {
        $stream = fopen($this->file->getRealPath(), 'r');
        return [
            new MultipartValue('site_uuid', (string)($this->siteId ?? config('mediatonic.site_uuid'))),
            new MultipartValue(config('mediatonic.file_field'), $stream, $this->file->getClientOriginalName()),
        ];
    }
}
