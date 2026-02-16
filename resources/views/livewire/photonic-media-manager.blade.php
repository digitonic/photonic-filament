<div>
    @if($media)
        <div class="space-y-4">
            {{-- Image Preview --}}
            <div class="prose max-w-none">
                <img 
                    src="{{ \Digitonic\Photonic\Filament\Facades\Photonic::for($media)->preset($preset)->url() }}" 
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
