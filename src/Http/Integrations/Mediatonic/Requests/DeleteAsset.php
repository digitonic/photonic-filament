<?php

namespace Digitonic\MediaTonic\Filament\Http\Integrations\MediaTonic\Requests;

use Digitonic\MediaTonic\Filament\Http\Integrations\MediaTonic\API;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Request\HasConnector;

class DeleteAsset extends Request
{
    use HasConnector;

    protected string $connector = API::class;

    protected Method $method = Method::DELETE;

    public function __construct(
        protected string $assetUuid,
    ) {}

    /**
     * Define the endpoint for the request
     */
    public function resolveEndpoint(): string
    {
        return '/assets/'.$this->assetUuid;
    }
}
