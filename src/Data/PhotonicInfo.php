<?php

namespace Digitonic\Photonic\Filament\Data;

use JsonSerializable;

class PhotonicInfo implements JsonSerializable
{
    public function __construct(
        public ?int $id,
        public string $assetUuid,
        public string $filename,
        public string $preset,
        public ?string $url,
        public ?string $alt,
        public ?string $title,
        public ?string $description,
        public ?string $caption,
        public mixed $config,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'assetUuid' => $this->assetUuid,
            'filename' => $this->filename,
            'preset' => $this->preset,
            'url' => $this->url,
            'alt' => $this->alt,
            'title' => $this->title,
            'description' => $this->description,
            'caption' => $this->caption,
            'config' => $this->config,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
