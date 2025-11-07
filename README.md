# Mediatonic Filament Package

A Filament 4 form component package for Laravel 12 that uploads image assets to a third‑party Mediatonic API, stores metadata in your database, and renders CDN image URLs. It does not persist the uploaded file locally.

## Features

- Uploads directly to a remote API (via Saloon) on form save.
- Extracts the returned filename (configurable response key) from JSON.
- Optionally records each upload to a dedicated `mediatonic` table.
- Provides:
  - `MediatonicInput` – raw upload field (extends Filament's `FileUpload`).
  - `MediatonicImageField` – composite helper (upload + preview + delete).
  - Blade component `<x-mediatonic-filament::image>` for rendering CDN URLs.
- Helper functions:
  - `mediatonic_asset($filename, $preset = 'original')` URL builder.
  - `get_mediatonic_table_name()` resolves the media table from config.
- Extensible: swap out the media model class in config.

## Requirements

- PHP: 8.2+
- Laravel: 12.x
- Filament: 4.x
- saloonphp/saloon: ^3.0

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

This creates `config/mediatonic.php`, publishes the migration for the `mediatonic` table, and the Blade view component.

## Configuration

All options live in `config/mediatonic.php` and can be set via environment variables:

| Key | Env | Default | Purpose |
|-----|-----|---------|---------|
| endpoint | MEDIATONIC_ENDPOINT | https://mediatonic.test/api/v1 | Base API URL used by the Saloon connector. |
| cdn_endpoint | MEDIATONIC_CDN_ENDPOINT | https://minio.herd.test/mediatonic | Base CDN URL for image rendering. |
| site_uuid | MEDIATONIC_SITE_UUID | (null) | Site identifier sent with each upload. |
| api_key | MEDIATONIC_API_KEY | (null) | Bearer API token (added automatically). |
| file_field | MEDIATONIC_FILE_FIELD | file | Multipart field name for the uploaded file. |
| response_key | MEDIATONIC_RESPONSE_KEY | original_filename | JSON key inside `data` used to pull the stored filename. |
| record_uploads | MEDIATONIC_RECORD_UPLOADS | true | Enable database recording of each uploaded asset. |
| media_model | (class) | `Digitonic\Mediatonic\Filament\Models\Media::class` | Eloquent model used to persist uploads. |

### API Expectations

Upload requests are sent as:

- Method: `POST`
- Endpoint: `{endpoint}/assets`
- Auth: Bearer token (`MEDIATONIC_API_KEY`)
- Multipart fields:
  - `site_uuid` (from `MEDIATONIC_SITE_UUID` or constructor)
  - `{file_field}` (the file stream)

Expected JSON response shape:

```json
{
  "data": {
    "original_filename": "example.jpg",
    "uuid": "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
  }
}
```

We extract `data[response_key]` for the filename. Adjust `response_key` if your API differs.

## Media Table

`Media` model overrides `getTable()` to return `'mediatonic'`. Override in a custom model if required.

## Trait: `UsesMediatonic`

Add to any model needing a single associated media record:

```php
use Digitonic\Mediatonic\Filament\Concerns\UsesMediatonic;

class Article extends Model
{
    use UsesMediatonic;
}
```

Provides:

- `$article->mediatonicMedia` (morphOne)
- `$article->addMediatonicMedia($filename, $presetsArray)`
- `$article->removeMediatonicMedia($mediaId)`

If you need multiple media items, implement a custom `morphMany` relation and adapt the form component.

## Form Components

### 1. `MediatonicInput`

Direct upload field:

```php
use Digitonic\Mediatonic\Filament\Forms\Components\MediatonicInput;

MediatonicInput::make('image_filename')
    ->label('Image');
```

Behavior:

- Forces image mode; enables `multiple()` by default (you can override via standard FileUpload API methods).
- Disables local preview (`previewable(false)`).
- On save:
  - Sends file via Saloon to API.
  - Extracts filename from `data[response_key]`.
  - Computes metadata: mime, extension, size, width/height, hash name.
  - If `record_uploads` is true and a record context exists, inserts a row into the media table with `asset_uuid`, `filename`, and `config` JSON.
  - Returns the filename as the field state.

Recording requires the form to have a current record (e.g. editing, not creating without ID yet). Best-effort resolution checks Livewire component APIs and common properties.

### 2. `MediatonicImageField`

Composite helper providing upload + preview + delete:

```php
use Digitonic\Mediatonic\Filament\Forms\Components\MediatonicImageField;

MediatonicImageField::make('featured_image')
    ->relation('mediatonicMedia')  // default relation from trait
    ->preset('originals')          // preview preset segment
    ->previewClasses('rounded-xl max-w-full h-auto')
    ->deletable(true);
```

Flow:

1. If no related media exists, shows single-file `MediatonicInput`.
2. After successful upload and DB record creation, hides uploader and renders preview.
3. Provides "Remove Image" action to delete the related media row, refreshes form.

Notes:

- Internal uploader field is not dehydrated; relation storage is canonical.
- Only supports one media row (morphOne). Extend for galleries.

### 3. Blade Image Component

```blade
<x-mediatonic-filament::image
    :filename="$article->mediatonicMedia?->filename"
    preset="original"
    alt="{{ $article->title }}"
    class="rounded w-full"
    loading="lazy"
/>
```

Props:

| Prop | Default | Description |
|------|---------|-------------|
| filename | — | Stored original filename |
| preset | original | CDN preset; influences URL & webp conversion |
| alt | filename | Alt text |
| class | object-cover w-auto | CSS classes |

Non-original presets convert the basename to `.webp`.

## Helper Functions

```php
mediatonic_asset(string $filename, string $preset = 'original'): ?string;
get_mediatonic_table_name(): string;
```

`mediatonic_asset` builds:

- Original: `{cdn_endpoint}/{site_uuid}/original/{filename}`
- Preset: `{cdn_endpoint}/{site_uuid}/presets/{preset}/{base}.webp`

Returns `null` if the filename is empty.

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
        MediatonicImageField::make('product_image')
            ->relation('mediatonicMedia')
            ->preset('original')
            ->deletable(),
    ]);
}
```

Blade display:

```blade
<x-mediatonic-filament::image :filename="$product->mediatonicMedia?->filename" preset="original" />
```

## Extending / Customizing

- Change media model: Create a subclass overriding `getTable()` or adding attributes/casts, then set `media_model` in config.
- Response parsing: Customize `MediatonicInput::setUp()` to add fallback logic or alternate payload handling.
- Multiple assets: Swap trait + relation to `morphMany`; adjust composite field to handle collections.
- Remote deletion: Add an API request in the remove action if the Mediatonic API supports deleting assets.

## Troubleshooting

| Symptom | Cause | Resolution |
|---------|-------|------------|
| `asset_uuid` null | API response missing `data.uuid` | Ensure API returns UUID; add guard in code if needed. |
| Empty filename stored | `response_key` mismatch | Set `MEDIATONIC_RESPONSE_KEY` to correct JSON key. |
| Broken preview URL | CDN or preset mismatch | Verify `cdn_endpoint`, `site_uuid`, preset, file exists. |
| No row recorded | No model context / create form without ID yet | Save parent model first or disable recording until edit. |
| Multiple uploads unexpected | `multiple()` is enabled by default | Call `->multiple(false)` if only one file desired. |

## Roadmap Ideas

- Fallback chain for filename extraction.
- Optional WebP bypass / dynamic extension mapping.
- Multi-image gallery component (`morphMany`).
- Remote asset deletion & purge integration.
- Automatic error surface for API validation messages.

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
