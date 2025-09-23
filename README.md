# Filament IGS Field

A custom Filament 3 form field that uploads an image to a third‑party IGS service and stores the returned filename in your application's database. No local storage of the uploaded image is performed by the field.

- Sends the uploaded image to a configurable API endpoint.
- Expects the API to return a filename (in JSON or plain text).
- The field state is replaced with the returned filename, so your Eloquent model column stores that value directly.
- Optionally records each upload to the igs_media table with model_type, model_id, filename, and presets.

## Requirements

- PHP 8.2+
- Laravel 10/11
- Filament 3

## Installation

Install via Composer:

```
composer require digitonic/igs-field
```

The package is auto-discovered by Laravel. Publish the config and migration files:

```
php artisan vendor:publish --tag=igs-field-config
php artisan vendor:publish --tag=igs-field-migrations
```

Then run the migration:

```
php artisan migrate
```

This will create `config/igs-field.php` in your application and add a migration for the `igs_media` table.

## Configuration

Default configuration (config/igs-field.php):

```
return [
    // The API endpoint that receives the uploaded image and responds with a filename.
    'endpoint' => env('IGS_FIELD_ENDPOINT', 'https://igs.test/api'),

    // The CDN/base URL used to display images in your app (consumed by the Blade component below).
    'cdn_endpoint' => env('IGS_FIELD_CDN_ENDPOINT', 'https://cdn.example.com/igs'),

    'site_uuid' => env('IGS_FIELD_SITE_UUID'),

    // The multipart field name used when sending the file.
    'file_field' => env('IGS_FIELD_FILE_FIELD', 'file'),

    // The response key to read the filename from, if the response is JSON.
    // If null or if the key doesn't exist, the field falls back to common keys
    // or the raw body text.
    'response_key' => env('IGS_FIELD_RESPONSE_KEY', 'filename'),

    // Whether to record uploads to the igs_media table automatically.
    'record_uploads' => env('IGS_FIELD_RECORD_UPLOADS', true),

    // The table to write the records to.
    'media_table' => env('IGS_FIELD_MEDIA_TABLE', 'igs_media'),
];
```

You can override these values in your `.env`:

```
IGS_FIELD_ENDPOINT=https://igs.test/api
IGS_FIELD_CDN_ENDPOINT=https://cdn.example.com/igs
IGS_FIELD_SITE_UUID=
IGS_FIELD_FILE_FIELD=file
IGS_FIELD_RESPONSE_KEY=filename
IGS_FIELD_RECORD_UPLOADS=true
IGS_FIELD_MEDIA_TABLE=igs_media
```

## Usage

Use the field in any Filament form, like you would `FileUpload`. It will send the uploaded image to the configured endpoint during the save cycle and replace the field state with the returned filename.

```
use Digitonic\Filament\IgsField\Forms\Components\IgsInput;

...

IgsInput::make('image_filename')
    ->label('Image')
    ->image(); // optional; defaults to image mode
```

- The model attribute `image_filename` will receive the filename returned by your IGS API.
- The uploaded temporary file is not stored locally by this field.

### Recording uploads to igs_media

By default, the field will record each successful upload to the `igs_media` table if the current Filament form has an Eloquent record context. The following columns are stored:

- model_type: The fully-qualified class name of the model being edited.
- model_id: The primary key of the model record.
- filename: The filename returned by the IGS API.
- presets: If the IGS API response contains a `presets` (or `preset`) key, it's stored as JSON.

You can disable this globally via config or per field:

```
// Disable globally in config/igs-field.php
'record_uploads' => false,

// Or per field instance
IgsInput::make('image_filename')->recordToMedia(false);
```

### Relating models to media (UsesIgsMedia trait)

Add a reusable trait to any Eloquent model that should have images associated with it:

```
use Illuminate\Database\Eloquent\Model;
use Digitonic\Filament\IgsField\Concerns\UsesIgsMedia;

class Post extends Model
{
    use UsesIgsMedia;
}
```

This gives you a polymorphic relation using the `igs_media` table:

- `$post->igsMedia` — access all media rows for the model.
- `$post->igsMedia()->latest()->first()` — access the most recent row.
- `$post->addIgsMedia('filename.jpg', ['thumb' => 'filename-thumb.jpg'])` — convenience helper to attach a new row.
- `$post->removeIgsMedia($id)` — remove a specific media row by id (only if it belongs to the model).

The inverse relation is available on the media model:

```
use Digitonic\Filament\IgsField\Models\IgsMedia;

$media = IgsMedia::query()->first();
$owner = $media->model; // The owning model instance
```

### Per-field overrides

You may override config values per field instance when needed:

```
IgsInput::make('image_filename')
    ->endpoint('https://custom-igs.example.com/api')
    ->fileField('image')
    ->responseKey('filename');
```

### Expected API behavior

- The field sends a `multipart/form-data` POST request to `igs-field.endpoint` + `/upload/{site_uuid}`, attaching the file under the key from `igs-field.file_field` (defaults to `file`).
- The field expects the response to contain a filename to store. It will try, in order:
  1. The configured `response_key` (default `filename`) from JSON.
  2. Common keys: `filename`, `file`, or `name` from JSON.
  3. The raw response body text.

If the response is an error (non-2xx), the field will throw an exception and display the error inside Filament.

### Display images anywhere (Blade component)

Render images from your CDN/IGS using a simple Blade component. It requires only the filename, and optionally a preset, classes, and alt text.

```
<x-igs-field::image 
    :filename="$article->igsMedia()->latest()->first()?->filename"
    preset="featured"
    alt="{{ $article->title }}"
    class="object-cover w-auto rounded"
/>
```

- URL format: `{{ config('igs-field.cdn_endpoint') }}/{{ config('igs-field.site_uuid') }}/{preset}/{filename}`
- Props:
  - `filename` (string, required) — The stored filename returned by the IGS API.
  - `preset` (string, default `featured`) — Path segment between site UUID and filename (e.g., `thumb`, `featured`).
  - `alt` (string, optional) — Alt text, defaults to the filename.
  - `class` (string, default `object-cover w-auto`) — CSS classes; pass your own to override.
- Behavior:
  - If `filename` is empty/null, the component renders nothing.
  - Additional attributes (e.g., `loading="lazy"`, `width`, `height`) are passed through to the underlying `<img>` tag.

## Notes

- Recording to `igs_media` is best-effort and will be skipped if the model context (class and id) is not available at the time the upload is saved.
- Because the field stores only the returned filename, previewing the uploaded image may not work out-of-the-box unless your application can transform that filename into a previewable URL. If you need preview support, consider customizing Filament's display callbacks separately in your form/table to render the image using your CDN/IGS domain.

## License

MIT
