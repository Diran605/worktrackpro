<x-filament::page>
    <form wire:submit.prevent="mountAction('save')">
        {{ $this->form }}

        <div class="mt-6">
            {{ $this->saveAction }}
        </div>
    </form>

</x-filament::page>
