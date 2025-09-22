# Filament IGS Field

A custom Filament 3 form field that uploads an image to a third‑party IGS service and stores the returned filename in your application's database. No local storage of the uploaded image is performed by the field.

- Sends the uploaded image to a configurable API endpoint.
- Expects the API to return a filename (in JSON or plain text).
- The field state is replaced with the returned filename, so your Eloquent model column stores that value directly.

## Requirements

- PHP 8.2+
- Laravel 10/11
- Filament 3

## Installation

Install via Composer:

```
composer require digitonic/igs-field
```

The package is auto-discovered by Laravel. Optionally, publish the config file:

```
php artisan vendor:publish --tag=igs-field-config
```

This will create `config/igs-field.php` in your application, where you can customize the API endpoint and response handling.

## Configuration

Default configuration (config/igs-field.php):

```
return [
    // The API endpoint that receives the uploaded image and responds with a filename.
    'endpoint' => env('IGS_FIELD_ENDPOINT', 'https://igs.test'),

    // The multipart field name used when sending the file.
    'file_field' => env('IGS_FIELD_FILE_FIELD', 'file'),

    // The response key to read the filename from, if the response is JSON.
    // If null or if the key doesn't exist, the field falls back to common keys
    // or the raw body text.
    'response_key' => env('IGS_FIELD_RESPONSE_KEY', 'filename'),
];
```

You can override these values in your `.env`:

```
IGS_FIELD_ENDPOINT=https://igs.test
IGS_FIELD_FILE_FIELD=file
IGS_FIELD_RESPONSE_KEY=filename
```

## Usage

Use the field in any Filament form, like you would `FileUpload`. It will send the uploaded image to the configured endpoint during the save cycle and replace the field state with the returned filename.

```
use Digitonic\Filament\IgsField\Forms\Components\IgsInput;

...

IgsInput::make('image_filename')
    ->label('Image')
    ->image() // optional; defaults to image mode
;
```

- The model attribute `image_filename` will receive the filename returned by your IGS API.
- The uploaded temporary file is not stored locally by this field.

### Per-field overrides

You may override config values per field instance when needed:

```
IgsInput::make('image_filename')
    ->endpoint('https://custom-igs.example.com/upload')
    ->fileField('image')
    ->responseKey('filename');
```

### Expected API behavior

- The field sends a `multipart/form-data` POST request to `igs-field.endpoint`, attaching the file under the key from `igs-field.file_field` (defaults to `file`).
- The field expects the response to contain a filename to store. It will try, in order:
  1. The configured `response_key` (default `filename`) from JSON.
  2. Common keys: `filename`, `file`, or `name` from JSON.
  3. The raw response body text.

If the response is an error (non-2xx), the field will throw an exception and display the error inside Filament.

## Notes

- Because the field stores only the returned filename, previewing the uploaded image may not work out-of-the-box unless your application can transform that filename into a previewable URL. If you need preview support, consider customizing Filament's display callbacks separately in your form/table to render the image using your CDN/IGS domain.

## License

MIT
