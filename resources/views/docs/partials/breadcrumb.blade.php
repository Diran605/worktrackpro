{{-- Expects: $crumbs = [[label, url|null], ...] --}}
<nav aria-label="Breadcrumb" class="text-[13px] text-gray-500 docs-no-print">
    <ol class="flex flex-wrap items-center gap-1">
        <li><a href="{{ $docs->home() }}" class="hover:underline">Home</a></li>
        @foreach ($crumbs as [$label, $url])
            <li aria-hidden="true">/</li>
            <li>
                @if ($url)
                    <a href="{{ $url }}" class="hover:underline">{{ $label }}</a>
                @else
                    <span class="text-gray-700" aria-current="page">{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
