# Mediatonic Filament Package

A Filament 4 form component package for Laravel 12 that uploads image assets to a third‑party Mediatonic API, stores metadata in your database, and renders CDN image URLs. It does not persist the uploaded file locally.
We intend this package to be used with the MediaTonic Server API. No support will be given for other APIs, or reverse engineering any of the calls done here.

<p align="center">
<img src="https://img.shields.io/github/actions/workflow/status/digitonic/mediatonic-filament/.github%2Fworkflows%2Ftests.yml?style=for-the-badge&logo=laravel&logoColor=white" alt="workflow build status">
</p>

## Features

- Uploads directly to a remote API (via Saloon) on form save.
- Extracts the returned filename (configurable response key) from JSON.
- Optionally records each upload to a dedicated `mediatonic` table.
- Provides:
  - `MediaTonicInput` – raw upload field (extends Filament's `FileUpload`).
  - `MediaTonicImageField` – composite helper (upload + preview + delete + meta info).
  - Blade component `<x-mediatonic-filament::image>` for rendering CDN URLs.
- Helper functions:
  - `mediatonic_asset($filename, $preset = 'original')` URL builder.
  - `get_mediatonic_table_name()` resolves the media table from config.
- Extensible: swap out the media model class in config.

## Requirements

- PHP: 8.2+
- Filament: 4.x
- MediaTonic Server API

## Installation

```bash
composer require digitonic/mediatonic-filament
```

Publish config, migration, and views:

```bash
php artisan vendor:publish --tag=mediatonic-filament-config
php artisan vendor:publish --tag=mediatonic-filament-migrations
php artisan vendor:publish --tag=mediatonic-filament-views
php artisan migrate
```

This creates `config/mediatonic-filament.php`, publishes the migration for the `mediatonic` table, and the Blade view component.

## Configuration

All options live in `config/mediatonic-filament.php` and can be set via environment variables:

| Key | Env | Default | Purpose                                                  |
|-----|-----|---------|----------------------------------------------------------|
| endpoint | MEDIATONIC_ENDPOINT | https://mediatonic.test/api/v1 | Base API URL used by the Saloon connector.               |
| cdn_endpoint | MEDIATONIC_CDN_ENDPOINT | https://minio.herd.test/mediatonic | Base CDN URL for image rendering.                        |
| site_uuid | MEDIATONIC_SITE_UUID | (null) | Site identifier sent with each upload.                   |
| api_key | MEDIATONIC_API_KEY | (null) | Bearer API token (created within mediatonic).            |
| file_field | MEDIATONIC_FILE_FIELD | file | Multipart field name for the uploaded file.              |
| response_key | MEDIATONIC_RESPONSE_KEY | original_filename | JSON key inside `data` used to pull the stored filename. |
| media_model | (class) | `Digitonic\Mediatonic\Filament\Models\Media::class` | Eloquent model used to persist uploads.                  |

## Trait: `UsesMediaTonic`

Add to any model needing a single associated media record:

```php
use Digitonic\Mediatonic\Filament\Concerns\UsesMediaTonic;

class Article extends Model
{
    use UsesMediaTonic;
}
```

Provides:

- `$article->mediatonicMedia` (morphOne)
- `$article->addMediatonicMedia($filename, $presetsArray)`
- `$article->removeMediatonicMedia($mediaId)`

## Form Components

### 1. `MediatonicInput`

Direct upload field:

```php
use Digitonic\Mediatonic\Filament\Forms\Components\MediatonicInput;

MediaTonicInput::make('image_filename')
    ->label('Image');
```

Behavior:

- Forces image mode.
- Disables local preview as we implement our own.
- On save:
  - Sends file via Saloon to API.
  - Extracts filename from `data[response_key]`.
  - Computes metadata: mime, extension, size, width/height, hash name.
  - Returns the filename as the field state.

Recording requires the form to have a current record (e.g. editing, not creating without ID yet). Best-effort resolution checks Livewire component APIs and common properties.

### 2. `MediatonicImageField`

Composite helper providing upload + preview + delete:

```php
use Digitonic\Mediatonic\Filament\Forms\Components\MediatonicImageField;
// Relational Mode (store media relation on model)
MediaTonicImageField::make()
    ->previewClasses('rounded-xl max-w-full h-auto')
    ->columnSpan([
        'sm' => 2,
    ]),


// ID Mode (store media ID in JSON field)
MediaTonicImageField::make([])
    ->previewClasses('rounded-xl max-w-full h-auto')
    ->relation('heroBackgroundImage')
    ->returnId(true, 'hero.background_image')
    ->columnSpan([
        'sm' => 2,
    ]),
```

### 3. Blade Image Component

```blade
<x-mediatonic-filament::image
    :media="mediatonic_media_by_id($model->meta['background_image'])"
    preset="original"
    style="object-position: 50% 50%"
    class="max-w-[320px] min-w-[200px] w-full h-auto shrink grow"
/>
```

## Helper Functions

### Basic URL Building

```php
// If you have the relation, you can just use the filename and asset id to build the URL
mediatonic_asset(string $filename, string $assetUuid, string $preset = 'original'): ?string;

get_mediatonic_table_name(): string;
```

`mediatonic_asset` builds:

- Original: `{cdn_endpoint}/{site_uuid}/{assetUuid}/original/{filename}`
- Preset: `{cdn_endpoint}/{site_uuid}/{assetUuid}/presets/{preset}/{base}.webp`

Returns `null` if the filename is empty.

### ID-Based Functions (with Caching)

For optimal performance when working with media IDs (ID mode), use these cached helper functions:

```php
// Get CDN URL by media ID (cached for 1 hour by default)
mediatonic_asset_by_id(int $mediaId, string $preset = 'original', int $cacheTtl = 3600): ?string;

// Get full Media model by ID (cached for 1 hour by default)
mediatonic_media_by_id(int $mediaId, int $cacheTtl = 3600): ?Media;

// Clear cache for a specific media ID
forget_mediatonic_cache(int $mediaId): void;
```

**Example Usage:**

```php
// In a loop - only queries database once per media ID/preset
@foreach($contentBlocks as $block)
    <img src="{{ mediatonic_asset_by_id($block['media_id'], 'thumbnail') }}">
@endforeach

// Access full media with metadata
$media = mediatonic_media_by_id(42);
echo $media->alt; // Alt text
echo $media->title; // Title
echo $media->getUrl('large'); // CDN URL
```

**Performance Benefits:**
- Automatic caching reduces database queries
- Cache is automatically cleared when media is updated/deleted
- Ideal for Filament Builder blocks and repeated access


## Example End-to-End

Model:

```php
class Product extends Model
{
    use UsesMediatonic;
}
```

Filament form:

```php
public static function form(Form $form): Form
{
    return $form->schema([
        MediatonicImageField::make()
            ->preset('original')
            ->deletable(),
    ]);
}
```

Blade display:

```blade
<x-mediatonic-filament::image
    :media="$product->mediaTonicMedia"
    preset="original"
    style="object-position: 50% 50%"
    class="max-w-[320px] min-w-[200px] w-full h-auto shrink grow"
/>
```

## Extending / Customizing

- Change media model: Create a subclass overriding `getTable()` or adding attributes/casts, then set `media_model` in config.
- Multiple assets: Swap trait + relation to `morphMany`; adjust composite field to handle collections.

# License

Copyright 2025, Digitonic

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License
