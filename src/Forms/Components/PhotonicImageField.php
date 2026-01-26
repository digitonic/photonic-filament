<?php

namespace Digitonic\Photonic\Filament\Forms\Components;

use Digitonic\Photonic\Filament\Enums\PresetEnum;
use Digitonic\Photonic\Filament\Http\Integrations\Photonic\API;
use Digitonic\Photonic\Filament\Http\Integrations\Photonic\Requests\DeleteAsset;
use Digitonic\Photonic\Filament\Models\Media;
use Digitonic\Photonic\Filament\Services\MediaUploadService;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;

class PhotonicImageField extends Group
{
    protected string $relationName = 'photonicMedia';

    /**
     * Field key used to mount the upload "dummy" state.
     * We do not dehydrate this field to DB; it only triggers the upload.
     */
    protected string $uploadFieldName = 'photonic_upload';

    /**
     * CDN preset path segment used for preview URL (e.g. 'original', 'featured').
     */
    protected string $previewPreset = PresetEnum::ORIGINAL->value;

    /**
     * Whether to show the remove action.
     */
    protected bool $showRemoveAction = true;

    /**
     * Additional CSS classes applied to the preview <img> tag.
     */
    protected string $previewClasses = 'rounded-xl max-w-full';

    /**
     * Whether to return the media ID instead of using polymorphic relationship.
     * When true, the field will store the media ID in the specified column.
     */
    protected bool $returnMediaId = false;

    /**
     * The field name to store/retrieve the media ID when in ID mode.
     */
    protected ?string $mediaIdField = null;

    /**
     * Reference to the PhotonicInput component for method forwarding.
     */
    protected ?PhotonicInput $inputComponent = null;

    /**
     * Callbacks to apply to the input component after it's created.
     * This stores method calls made before setUp() is called.
     */
    protected array $inputCallbacks = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Build inner schema during setup so it can react to configured properties.
        $this->schema($this->returnMediaId ? $this->buildIdModeSchema() : $this->buildRelationModeSchema());
    }

    /**
     * Build schema for relationship mode (original behavior).
     */
    protected function buildRelationModeSchema(): array
    {
        // Create the input component and store reference
        $this->inputComponent = PhotonicInput::make($this->uploadFieldName)
            ->label('Upload Media')
            ->helperText('Select an image to upload to Photonic CDN')
            // Avoid saving uploader value to model
            ->dehydrated(false)
            // single file for this helper – users can still override by extending later if needed
            ->multiple(false)
            // Ensure it records to media table by default so relation can exist
            ->columnSpanFull();

        // Apply any callbacks that were queued before setUp
        foreach ($this->inputCallbacks as $callback) {
            $callback($this->inputComponent);
        }

        $hasMedia = fn (?Model $record): bool => (bool) ($record?->{$this->relationName} ?? null);

        return [
            Section::make('Photonic Media')
                ->description('Manage your image upload and metadata')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Tabs::make('photonic_tabs')
                        ->tabs([
                            // Tab 1: Upload / Preview
                            Tabs\Tab::make('Media')
                                ->icon('heroicon-o-photo')
                                ->schema([
                            // Show uploader when no media exists
                            Group::make([
                                $this->inputComponent,
                            ])
                                ->visible(fn (?Model $record): bool => !$hasMedia($record))
                                ->columnSpanFull(),

                            // Show preview when media exists
                            Group::make([
                                TextEntry::make('img_preview_'.'_preview_'.Str::random(2))
                                    ->label('Current Image')
                                    ->state(function (?Model $record) {
                                        $media = $record->{$this->relationName} ?? null;
                                        if (! $record || ! $media) {
                                            return 'No image available';
                                        }

                                        $filename = $media->filename ?? null;
                                        if (! $filename) {
                                            return 'No image available';
                                        }

                                        /** @var view-string $viewName */
                                        $viewName = 'photonic-filament::components.image';

                                        $html = view($viewName, [
                                            'filename' => $filename,
                                            'preset' => $this->previewPreset,
                                            'class' => $this->previewClasses,
                                            'alt' => $media->alt ?? $filename,
                                            'media' => $media,
                                        ])->render();

                                        return new HtmlString($html);
                                    })
                                    ->extraAttributes(['class' => 'prose max-w-none'])
                                    ->columnSpanFull(),
                            ])
                                ->visible($hasMedia)
                                ->columnSpanFull(),
                        ]),

                    // Tab 2: Image Details
                    Tabs\Tab::make('Details')
                        ->icon('heroicon-o-document-text')
                        ->visible($hasMedia)
                        ->schema([
                            Section::make()
                                ->schema([
                                    TextInput::make('alt')
                                        ->label('Alt Text')
                                        ->maxLength(255)
                                        ->helperText('Describe the image for accessibility (screen readers)')
                                        ->columnSpan(['sm' => 2]),

                                    TextInput::make('title')
                                        ->label('Title')
                                        ->maxLength(255)
                                        ->helperText('Image title')
                                        ->columnSpan(['sm' => 2]),

                                    Textarea::make('description')
                                        ->label('Description')
                                        ->rows(4)
                                        ->helperText('Detailed description of the image content')
                                        ->columnSpanFull(),

                                    Textarea::make('caption')
                                        ->label('Caption')
                                        ->rows(2)
                                        ->helperText('Caption to display with the image')
                                        ->columnSpanFull(),
                                ])
                                ->relationship($this->relationName)
                                ->columnSpanFull(),
                        ]),

                    // Tab 3: Actions
                    Tabs\Tab::make('Actions')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->visible(fn (?Model $record): bool => $hasMedia($record) && $this->showRemoveAction)
                        ->schema([
                            Section::make()
                                ->schema([
                                    Group::make([
                                        Actions::make([
                                            Action::make('removePhotonicImage')
                                                ->label('Delete Image')
                                                ->color('danger')
                                                ->icon('heroicon-o-trash')
                                                ->requiresConfirmation()
                                                ->modalHeading('Delete Image')
                                                ->modalDescription('Are you sure you want to delete this image? This action cannot be undone and will remove the image from the CDN.')
                                                ->modalSubmitActionLabel('Yes, delete it')
                                                ->action(function (?Model $record, $livewire) {
                                                    if ($record && $record->{$this->relationName}) {
                                                        // Send the API call to remove the asset
                                                        $api = new API;
                                                        $request = new DeleteAsset($record->{$this->relationName}->asset_uuid);
                                                        $response = $api->send($request);

                                                        if ($response->status() === 200) {
                                                            // Delete the related media record
                                                            $relation = $record->{$this->relationName}();
                                                            if (method_exists($relation, 'delete')) {
                                                                $relation->delete();
                                                            } else {
                                                                // If relation is already loaded as a model instance
                                                                $record->{$this->relationName}->delete();
                                                            }

                                                            // Refresh the record and re-render
                                                            $record->refresh();
                                                            $livewire->dispatch('$refresh');
                                                        }
                                                    }
                                                }),
                                        ])->columnSpanFull(),
                                    ])->columnSpanFull(),

                                    Group::make([
                                        TextEntry::make('info')
                                            ->label('')
                                            ->state('Deleting the image will permanently remove it from the CDN.')
                                            ->color('gray')
                                            ->columnSpanFull(),
                                    ])->columnSpanFull(),
                                ])
                                ->columnSpanFull(),
                        ]),
                    ])
                    ->columnSpanFull()
                    ->contained(false),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * Build schema for ID mode (stores media ID directly).
     * In this mode, the media ID is stored in a field on the model (e.g., in a JSON column),
     * rather than using a polymorphic relationship via model_type/model_id.
     */
    protected function buildIdModeSchema(): array
    {
        $mediaIdField = $this->mediaIdField;
        $mediaModelClass = config('photonic-filament.media_model', Media::class);

        $extractMediaId = function (mixed $value): ?int {
            if (is_null($value)) {
                return null;
            }

            if (is_int($value)) {
                return $value;
            }

            if (is_numeric($value)) {
                return (int) $value;
            }

            // Handle array - extract first value
            if (is_array($value)) {
                $first = array_values($value)[0] ?? null;
                if (is_numeric($first)) {
                    return (int) $first;
                }

                return null;
            }

            return null;
        };

        // Helper to get media by ID
        /** @var callable(mixed): ?Media $getMediaById */
        $getMediaById = fn (mixed $value): ?Media => ($id = $extractMediaId($value)) ? $mediaModelClass::find($id) : null;
        $strRandom = md5($mediaIdField);

        // Create the input component - use a separate field name for uploads
        // The actual media ID will be stored via a Hidden field
        $uploadFieldName = $mediaIdField.'_upload';

        // Create a separate upload field that doesn't get saved
        $uploadField = PhotonicInput::make($uploadFieldName)
            ->label('Upload Media')
            ->helperText('Select an image to upload to Photonic CDN. Save the form to complete the upload.')
            ->returnId()
            ->multiple(false)
            ->live(onBlur: true) // Try different live mode
            ->afterStateUpdated(function ($state, Set $set) use ($mediaIdField, $uploadFieldName) {
                if (is_numeric($state)) {
                    $set($mediaIdField, (int) $state);
                }
            })
            ->columnSpanFull();
        
        // The actual field that stores the media ID
        $this->inputComponent = $uploadField;

        // Apply any callbacks that were queued before setUp
        foreach ($this->inputCallbacks as $callback) {
            $callback($this->inputComponent);
        }

        $hasMedia = function (Get $get) use ($mediaIdField): bool {
            $value = $get($mediaIdField);
            // Only return true if we have an actual media ID (integer), not a TemporaryUploadedFile
            return is_numeric($value) && !empty($value);
        };

        return [
            // Hidden field that stores the actual media ID
            // During save, it reads from the upload field
            Hidden::make($mediaIdField)
                ->default(null)
                ->dehydrateStateUsing(function ($state, Get $get) use ($uploadFieldName, $mediaIdField) {
                    // Check if upload field has a value (media ID returned from saveUploadedFileUsing)
                    $uploadValue = $get($uploadFieldName);
                    
                    // If upload field has a numeric value, use it
                    if (is_numeric($uploadValue) && !empty($uploadValue)) {
                        return (int) $uploadValue;
                    }
                    
                    // Otherwise keep existing state
                    return $state;
                }),
                
            Section::make('Photonic Media')
                ->description('Manage your image upload and metadata')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Tabs::make('photonic_tabs')
                        ->tabs([
                            // Tab 1: Upload / Preview
                            Tabs\Tab::make('Media')
                                ->icon('heroicon-o-photo')
                                ->schema([
                            // Uploader - visible when no media ID exists
                            // Keep it in DOM even when hidden so it can be dehydrated
                            Group::make([
                                $this->inputComponent,
                            ])
                                ->hidden(fn (Get $get): bool => $hasMedia($get))
                                ->live()
                                ->columnSpanFull(),

                            // Preview - visible when media ID exists
                            Group::make([
                                ViewField::make('livewire_preview_'.$strRandom)
                                    ->view('photonic-filament::livewire-wrapper')
                                    ->viewData(fn (Get $get) => [
                                        'mediaId' => $get($mediaIdField),
                                        'preset' => $this->previewPreset,
                                        'previewClasses' => $this->previewClasses,
                                        'fieldName' => $mediaIdField,
                                    ])
                                    ->dehydrated(false)
                                    ->columnSpanFull(),
                            ])
                                ->visible($hasMedia)
                                ->live()
                                ->columnSpanFull(),
                        ]),

                    // Tab 2: Image Details
                    Tabs\Tab::make('Details')
                        ->icon('heroicon-o-document-text')
                        ->visible($hasMedia)
                        ->schema([
                            Section::make()
                                ->schema([
                                    TextInput::make('media_alt'.$strRandom)
                                        ->label('Alt Text')
                                        ->maxLength(255)
                                        ->helperText('Describe the image for accessibility (screen readers)')
                                        ->afterStateHydrated(function ($set, Get $get) use ($mediaIdField, $getMediaById, $strRandom) {
                                            $media = $getMediaById($get($mediaIdField));
                                            $set('media_alt'.$strRandom, $media?->alt);
                                        })
                                        ->dehydrateStateUsing(function ($state, Get $get) use ($mediaIdField, $getMediaById) {
                                            $media = $getMediaById($get($mediaIdField));
                                            $media?->update(['alt' => $state]);

                                            return null; // Don't save to parent model
                                        })
                                        ->columnSpan(['sm' => 2]),

                                    TextInput::make('media_title'.$strRandom)
                                        ->label('Title')
                                        ->maxLength(255)
                                        ->helperText('Image title')
                                        ->afterStateHydrated(function ($set, Get $get) use ($mediaIdField, $getMediaById, $strRandom) {
                                            $media = $getMediaById($get($mediaIdField));
                                            $set('media_title'.$strRandom, $media?->title);
                                        })
                                        ->dehydrateStateUsing(function ($state, Get $get) use ($mediaIdField, $getMediaById) {
                                            $media = $getMediaById($get($mediaIdField));
                                            $media?->update(['title' => $state]);

                                            return null;
                                        })
                                        ->columnSpan(['sm' => 2]),

                                    Textarea::make('media_description'.$strRandom)
                                        ->label('Description')
                                        ->rows(4)
                                        ->helperText('Detailed description of the image content')
                                        ->afterStateHydrated(function ($set, Get $get) use ($mediaIdField, $getMediaById, $strRandom) {
                                            $media = $getMediaById($get($mediaIdField));
                                            $set('media_description'.$strRandom, $media?->description);
                                        })
                                        ->dehydrateStateUsing(function ($state, Get $get) use ($mediaIdField, $getMediaById) {
                                            $media = $getMediaById($get($mediaIdField));
                                            $media?->update(['description' => $state]);

                                            return null;
                                        })
                                        ->columnSpanFull(),

                                    Textarea::make('media_caption'.$strRandom)
                                        ->label('Caption')
                                        ->rows(2)
                                        ->helperText('Caption to display with the image')
                                        ->afterStateHydrated(function ($set, Get $get) use ($mediaIdField, $getMediaById, $strRandom) {
                                            $media = $getMediaById($get($mediaIdField));
                                            $set('media_caption'.$strRandom, $media?->caption);
                                        })
                                        ->dehydrateStateUsing(function ($state, Get $get) use ($mediaIdField, $getMediaById) {
                                            $media = $getMediaById($get($mediaIdField));
                                            $media?->update(['caption' => $state]);

                                            return null;
                                        })
                                        ->columnSpanFull(),
                                ])
                                ->columnSpanFull(),
                        ]),

                    // Tab 3: Actions
                    Tabs\Tab::make('Actions')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->visible(fn (Get $get): bool => $hasMedia($get) && $this->showRemoveAction)
                        ->schema([
                            Section::make()
                                ->schema([
                                    Group::make([
                                        Actions::make([
                                            Action::make('removePhotonicImage_'.$strRandom) // Make action name unique per field
                                                ->label('Delete Image')
                                                ->color('danger')
                                                ->icon('heroicon-o-trash')
                                                ->requiresConfirmation()
                                                ->modalHeading('Delete Image')
                                                ->modalDescription('Are you sure you want to delete this image? This action cannot be undone and will remove the image from the CDN.')
                                                ->modalSubmitActionLabel('Yes, delete it')
                                                ->action(function (Get $get, Set $set, $livewire) use ($mediaIdField, $mediaModelClass, $extractMediaId, $uploadFieldName) {
                                                    $mediaId = $extractMediaId($get($mediaIdField));
                                                    
                                                    if (!$mediaId) {
                                                        Log::warning('PhotonicImageField: No media ID to delete', [
                                                            'field' => $mediaIdField,
                                                        ]);
                                                        return;
                                                    }
                                                    
                                                    $media = $mediaModelClass::find($mediaId);
                                                    $apiDeleteSuccess = false;
                                                    
                                                    // Try to delete from CDN if media exists
                                                    if ($media) {
                                                        try {
                                                            $api = new API;
                                                            $request = new DeleteAsset($media->asset_uuid);
                                                            $response = $api->send($request);

                                                            if ($response->status() === 200) {
                                                                $media->delete();
                                                            }
                                                        } catch (\Exception $e) {

                                                        }
                                                    } else {
                                                        Log::warning('PhotonicImageField: Media not found in database, will still clear field', [
                                                            'media_id' => $mediaId,
                                                            'field' => $mediaIdField,
                                                        ]);
                                                    }
                                                    
                                                    // ALWAYS clear the field, even if media doesn't exist or API call failed
                                                    // This fixes orphaned references
                                                    $record = null;
                                                    
                                                    // Try to get the record from Livewire component
                                                    if (method_exists($livewire, 'getRecord')) {
                                                        $record = $livewire->getRecord();
                                                    } elseif (property_exists($livewire, 'record')) {
                                                        $record = $livewire->record;
                                                    } elseif (property_exists($livewire, 'ownerRecord')) {
                                                        $record = $livewire->ownerRecord;
                                                    }
                                                    
                                                    if ($record && method_exists($record, 'update')) {
                                                        // Check if this is a JSON field (contains dots like content.0.data.hero_image)
                                                        if (str_contains($mediaIdField, '.')) {
                                                            // This is a JSON path - we need to update nested JSON
                                                            $parts = explode('.', $mediaIdField);
                                                            $rootField = array_shift($parts); // e.g., "content"
                                                            
                                                            // Get the current JSON data
                                                            $jsonData = $record->{$rootField};
                                                            
                                                            if (is_string($jsonData)) {
                                                                $jsonData = json_decode($jsonData, true);
                                                            }
                                                            
                                                            // Navigate to the nested value and set it to null
                                                            $current = &$jsonData;
                                                            foreach ($parts as $index => $part) {
                                                                if ($index === count($parts) - 1) {
                                                                    // Last part - set to null
                                                                    $current[$part] = null;
                                                                } else {
                                                                    // Navigate deeper
                                                                    if (!isset($current[$part])) {
                                                                        Log::warning('PhotonicImageField: Path does not exist', [
                                                                            'part' => $part,
                                                                            'available_keys' => is_array($current) ? array_keys($current) : 'not an array',
                                                                        ]);
                                                                        break;
                                                                    }
                                                                    $current = &$current[$part];
                                                                }
                                                            }
                                                            
                                                            // Save the updated JSON back to the record
                                                            $record->update([
                                                                $rootField => $jsonData,
                                                            ]);
                                                        } else {
                                                            // Simple field - direct update
                                                            $record->update([
                                                                $mediaIdField => null,
                                                            ]);
                                                        }
                                                    } else {
                                                        Log::warning('PhotonicImageField: Could not find record to update database', [
                                                            'field' => $mediaIdField,
                                                            'livewire_class' => get_class($livewire),
                                                            'has_record_method' => method_exists($livewire, 'getRecord'),
                                                            'has_record_property' => property_exists($livewire, 'record'),
                                                            'has_owner_property' => property_exists($livewire, 'ownerRecord'),
                                                        ]);
                                                    }
                                                    
                                                    // Clear the field state - this will trigger visibility changes automatically
                                                    $set($mediaIdField, null);
                                                    $set($uploadFieldName, null);
                                                }),
                                        ])->columnSpanFull(),
                                    ])->columnSpanFull(),

                                    Group::make([
                                        TextEntry::make('info')
                                            ->label('')
                                            ->state('Deleting the image will permanently remove it from the CDN.')
                                            ->color('gray')
                                            ->columnSpanFull(),
                                    ])->columnSpanFull(),
                                ])
                                ->columnSpanFull(),
                        ]),
                    ])
                    ->columnSpanFull()
                    ->contained(false),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * Configure the field to return and store the media ID.
     * Useful for Filament Builder blocks or when you need a direct reference.
     */
    public function returnId(bool $return = true, ?string $fieldName = null): static
    {
        $this->returnMediaId = $return;
        $this->mediaIdField = $fieldName;

        return $this->refreshSchema();
    }

    /**
     * Set the Eloquent relation name used to detect and delete media.
     */
    public function relation(string $name): static
    {
        $this->relationName = $name;

        // Rebuild schema reflecting the new relation
        return $this->refreshSchema();
    }

    /**
     * Set the preview preset (CDN folder segment).
     */
    public function preset(string $preset): static
    {
        $this->previewPreset = $preset;

        return $this->refreshSchema();
    }

    /**
     * Override CSS classes applied to preview image.
     */
    public function previewClasses(string $classes): static
    {
        $this->previewClasses = $classes;

        return $this->refreshSchema();
    }

    /**
     * Toggle the remove action visibility.
     */
    public function deletable(bool $enabled = true): static
    {
        $this->showRemoveAction = $enabled;

        return $this->refreshSchema();
    }

    /**
     * Utility to force Filament to re-evaluate inner schema after property change.
     */
    protected function refreshSchema(): static
    {
        // Trigger setUp again by resetting schema. Filament will call setUp during hydration; here
        // we simply replace the schema now for builder-time changes.
        $this->schema([]);
        $this->setUp();

        return $this;
    }

    /**
     * Forward method calls to the underlying PhotonicInput component.
     *
     * Example:
     *   PhotonicImageField::make('image')
     *       ->required();
     */
    public function __call(string $method, array $parameters): mixed
    {
        // List of methods that should not be forwarded (handled by this class or parent)
        $reservedMethods = [
            'schema', 'columns', 'columnSpan', 'columnSpanFull',
            'returnId', 'relation', 'preset', 'previewClasses', 'deletable',
        ];

        // If it's a reserved method or the component is already set up, delegate to parent
        if (in_array($method, $reservedMethods, true)) {
            return parent::__call($method, $parameters);
        }

        // If the input component exists, call the method on it directly
        if ($this->inputComponent !== null) {
            $result = $this->inputComponent->{$method}(...$parameters);

            // If the method returns the input component (for chaining), return $this instead
            return $result === $this->inputComponent ? $this : $result;
        }

        // If setUp hasn't been called yet, queue the callback for later
        $this->inputCallbacks[] = function ($component) use ($method, $parameters) {
            $component->{$method}(...$parameters);
        };

        // Return $this for method chaining
        return $this;
    }
}
