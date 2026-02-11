# Photonic Filament Package

A Filament 4 form component package for Laravel 12 that uploads image assets to a third‑party Photonic API, stores metadata in your database, and renders CDN image URLs. It does not persist the uploaded file locally.

We intend this package to be used with the Photonic Server API.

## Features

- Optionally records each upload to a dedicated `photonic` table.
- Includes form component:
  - `PhotonicImageField` – composite helper (upload + preview + delete + meta info).
- Blade component `<x-photonic-filament::image>` for rendering CDN URLs.
- Helpers:
  - `photonic_asset($filename, $assetUuid, $preset = 'original')` URL builder.
  - `get_photonic_table_name()` resolves the media table from config.

## Installation

```bash
composer require digitonic/photonic-filament
```

### 1. Run the installer

```bash
php artisan photonic-filament:install
```

The installer will prompt you for the required Photonic values and will update **both** your `.env` and `.env.example`.

### 2. Publish and run migrations

```bash
php artisan vendor:publish --tag=photonic-filament-migrations
php artisan migrate
```

This creates the `photonic` table in your database to store image metadata.

### Optional: Publish config and views

If you want to customize the config or views:

```bash
php artisan vendor:publish --tag=photonic-filament-config
php artisan vendor:publish --tag=photonic-filament-views
```

## Configuration

All options live in `config/photonic-filament.php` and can be set via environment variables:

| Key | Env var | Default | Description |
| --- | --- | --- | --- |
| endpoint | PHOTONIC_ENDPOINT | https://photonic.test/api/v1 | Base API URL used by the Saloon connector. |
| cdn_endpoint | PHOTONIC_CDN_ENDPOINT | https://minio.herd.test/photonic | Base CDN URL for image rendering. |
| site_uuid | PHOTONIC_SITE_UUID | (null) | Site identifier sent with each upload. |
| api_key | PHOTONIC_API_KEY | (null) | Bearer API token. |
| file_field | PHOTONIC_FILE_FIELD | file | Multipart field name for the uploaded file. |
| response_key | PHOTONIC_RESPONSE_KEY | original_filename | JSON key inside `data` used to pull the stored filename. |
| media_model | (class) | `Digitonic\Photonic\Filament\Models\Media::class` | Eloquent model used to persist uploads. |

## Trait: `UsesPhotonic`

```php
use Digitonic\Photonic\Filament\Concerns\UsesPhotonic;

class Article extends Model
{
    use UsesPhotonic;
}
```

- `$article->photonicMedia` (morphOne)
- `$article->addPhotonicMedia($filename, $presetsArray)`
- `$article->removePhotonicMedia($mediaId)`

## Components

### `PhotonicImageField`

```php
use Digitonic\Photonic\Filament\Forms\Components\PhotonicImageField;

PhotonicImageField::make();
```

## Blade component

```blade
<x-photonic-filament::image
    :media="photonic_media_by_id($model->meta['background_image'])"
/>
```

## Fluent API

The primary API is a facade-first fluent resolver:

```php
use Digitonic\Photonic\Filament\Facades\Photonic;
use Digitonic\Photonic\Filament\Enums\PresetEnum;

$url = Photonic::make()
    ->for($mediaIdOrMediaModel)
    ->preset(PresetEnum::ORIGINAL)
    ->url(); // ?string

$media = Photonic::make()
    ->for($mediaIdOrMediaModel)
    ->media(); // ?Media

$info = Photonic::make()
    ->for($mediaIdOrMediaModel)
    ->preset('featured')
    ->info(); // ?PhotonicInfo
```

`PhotonicInfo` is immutable and provides:

- `id`
- `assetUuid`
- `filename`
- `preset`
- `url`
- `alt`
- `title`
- `description`
- `caption`
- `config`
- `createdAt`
- `updatedAt`

It supports `toArray()` and `jsonSerialize()`.

When media cannot be resolved, `url()`, `media()`, and `info()` all return `null`.

## Helper functions (Compatibility)

- `photonic_asset(string $filename, string $assetUuid, string $preset = 'original'): ?string;`
- `photonic_asset_by_id(int $mediaId, string $preset = 'original', int $cacheTtl = 3600): ?string;`
- `photonic_media_by_id(int $mediaId, int $cacheTtl = 3600): ?Media;`
- `forget_photonic_cache(int $mediaId): void;`
- `get_photonic_table_name(): string;`

# License

Copyright 2026, Digitonic

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License
