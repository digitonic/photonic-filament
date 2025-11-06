<?php

namespace Digitonic\Mediatonic\Filament\Http\Integrations\Mediatonic\Requests;

use Digitonic\Mediatonic\Filament\Http\Integrations\Mediatonic\API;
use Saloon\Contracts\Body\BodyRepository;
use Saloon\Contracts\Body\HasBody;
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
        protected mixed $file,
        protected ?string $filename = null,
    ) {}

    /**
     * Define the endpoint for the request
     */
    public function resolveEndpoint(): string
    {
        return '/assets';
    }

    public function body(): BodyRepository
    {
        $body = new MultipartBodyRepository();
        $body->add(config('mediatonic.file_field'), $this->file, $this->filename, [
            'headers' => [
                'Content-Type' => $this->file->getMimeType(),
            ],
        ]);
        $body->add('site_uuid', $this->siteId ?? config('mediatonic.site_uuid'));

        return new MultipartBodyRepository();
    }
}
