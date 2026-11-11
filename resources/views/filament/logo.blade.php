<div class="flex items-center gap-2">
    @php
        $logo = null;
        $name = 'WorkTrack Pro';
        if (auth()->check() && auth()->user()->organisation_id) {
            $org = auth()->user()->organisation;
            if ($org?->logo) {
                $logo = asset('storage/' . $org->logo);
            }
            if ($org?->name) {
                $name = $org->name;
            }
        }
    @endphp

    @if($logo)
        <img src="{{ $logo }}" alt="{{ $name }}" class="h-10 w-auto object-contain rounded" style="max-height: 40px; border-radius: 4px;">
    @else
        <span class="text-xl font-bold tracking-tight text-primary-600 dark:text-primary-500">
            {{ $name }}
        </span>
    @endif
</div>
