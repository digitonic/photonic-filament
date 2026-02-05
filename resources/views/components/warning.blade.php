<x-filament::empty-state
    :contained="false"
    icon="heroicon-o-exclamation-triangle"
    icon-color="warning"
>
    <x-slot name="heading">
        Resource has not been saved yet.
    </x-slot>

    <x-slot name="description">
        For an image to be uploaded and related to a this resource, it first must be saved.
    </x-slot>
</x-filament::empty-state>
