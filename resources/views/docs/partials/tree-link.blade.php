<a href="{{ $docs->section($system, $s) }}"
   class="flex-1 text-[13px] leading-snug {{ $isCurrent ? 'font-semibold docs-accent' : ($node['locked'] ? 'text-gray-400' : 'text-gray-700') }}"
   @if ($isCurrent) aria-current="page" @endif>{{ $label }}</a>
@if ($node['locked'])
    @include('docs.partials.lock', ['class' => 'w-3.5 h-3.5 mt-0.5 text-gray-400'])
@endif
