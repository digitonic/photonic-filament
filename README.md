# Mediatonic Filament Field

A custom Filament 4 form field that uploads an image to a third‑party Mediatonic service and stores the returned filename in your application's database. No local storage of the uploaded image is performed by the field.

- Sends the uploaded image to a configurable API endpoint.
- Expects the API to return a filename (in JSON or plain text).
- The field state is replaced with the returned filename, so your Eloquent model column stores that value directly.
- Optionally records each upload to the lume_media table with model_type, model_id, filename, and presets.

## Requirements

- PHP 8.2+
- Laravel 12
- Filament 4

## Installation

Install via Composer:

```
composer require digitonic/mediatonic-filament
```

The package is auto-discovered by Laravel. Publish the config and migration files:

```
php artisan vendor:publish --tag=mediatonic-filament-config
php artisan vendor:publish --tag=mediatonic-filament-migrations
```

Then run the migration:

```
php artisan migrate
```

This will create `config/mediatonic.php` in your application and add a migration for the `mediatonic` table.

## Configuration

Default env configuration (config/mediatonic.php):

```
LUME_ENDPOINT=https://mediatonic.test/api/v1
LUME_CDN_ENDPOINT=https://cdn.example.com/igs
LUME_SITE_UUID=
LUME_FILE_FIELD=file
LUME_RESPONSE_KEY=filename
LUME_RECORD_UPLOADS=true
LUME_MEDIA_TABLE=igs_media
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
// Disable globally in config/filament-lume.php
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

- The field sends a `multipart/form-data` POST request to `filament-lume.endpoint` + `/upload/{site_uuid}`, attaching the file under the key from `filament-lume.file_field` (defaults to `file`).
- The field expects the response to contain a filename to store. It will try, in order:
  1. The configured `response_key` (default `filename`) from JSON.
  2. Common keys: `filename`, `file`, or `name` from JSON.
  3. The raw response body text.

If the response is an error (non-2xx), the field will throw an exception and display the error inside Filament.

### Display images anywhere (Blade component)

Render images from your CDN/IGS using a simple Blade component. It requires only the filename, and optionally a preset, classes, and alt text.

```
<x-filament-lume::image 
    :filename="$article->igsMedia()->latest()->first()?->filename"
    preset="featured"
    alt="{{ $article->title }}"
    class="object-cover w-auto rounded"
/>
```

- URL format: `{{ config('filament-lume.cdn_endpoint') }}/{{ config('filament-lume.site_uuid') }}/{preset}/{filename}`
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


### All-in-one helper field (upload + preview + delete)

If you prefer to add a single field to your Filament form that handles the common flow of uploading an image, previewing it from your CDN, and removing/replacing it, use the composite helper:

```
use Digitonic\Filament\IgsField\Forms\Components\IgsImageField;

IgsImageField::make('featured_image')
    ->label('Featured Image')
    // Name of the Eloquent relation that holds the media record (default: 'igsMedia')
    ->relation('igsMedia')
    // Which preset/folder to use when building the preview URL (default: 'originals')
    ->preset('originals')
    // Override preview <img> classes if desired
    ->previewClasses('rounded-xl max-w-full h-auto')
    // Show or hide the delete button (default: true)
    ->deletable(true);
```

What it does under the hood:
- Shows an `IgsInput` uploader when the record has no related media yet. Uploads are recorded to the `igs_media` table by default.
- Once media exists (via the `relation`), it hides the uploader and displays a preview using the package's Blade component `<x-filament-lume::image>` and your configured `cdn_endpoint` and `site_uuid`.
- Renders a “Remove Image” action that deletes the related media row and refreshes the form so the uploader is shown again.

Notes:
- The helper does not dehydrate the uploader state to the model (the filename is stored in the `igs_media` relation table instead). If you also need to store the filename on the model itself, use `IgsInput` directly.
- The relation name is configurable via `->relation('...')`; it defaults to `igsMedia`.
