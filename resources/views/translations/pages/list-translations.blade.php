<x-filament::page>
    <form wire:submit.prevent="submit" method="POST">
        {{ $this->form }}
    </form>
</x-filament::page>
