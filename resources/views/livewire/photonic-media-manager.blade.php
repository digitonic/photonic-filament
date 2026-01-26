<div>
    @if($media)
        <div class="space-y-4">
            {{-- Image Preview --}}
            <div class="prose max-w-none">
                <img 
                    src="{{ photonic_asset($media->filename, $media->asset_uuid, $preset) }}" 
                    alt="{{ $media->alt ?? $media->filename }}"
                    class="{{ $previewClasses }}"
                />
            </div>
        </div>
    @else
        <div class="text-sm text-gray-500 dark:text-gray-400">
            No image available
        </div>
    @endif
</div>
