@php
    $organisation = $docs->organisation;
    $pageTitle = trim(($title ?? '') !== '' ? $title . ' | ' . $organisation->docsTitle() : $organisation->docsTitle());
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $description ?? ($organisation->docs_tagline ?: $organisation->docsTitle()) }}">
    @isset($canonical)<link rel="canonical" href="{{ $canonical }}">@endisset
    @if (!empty($noindex))<meta name="robots" content="noindex, nofollow">@endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/docs.css', 'resources/js/docs.js'])
    <style>:root { --docs-accent: {{ $docsAccent }}; }</style>
    @stack('head')
</head>
<body class="bg-gray-100 text-gray-900 font-sans antialiased min-h-screen flex flex-col">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:bg-white focus:px-3 focus:py-2 focus:rounded">Skip to main content</a>

{{-- Header --}}
<header class="bg-white border-b border-gray-200 sticky top-0 z-40 docs-no-print">
    <div class="max-w-7xl mx-auto px-4 h-16 flex items-center gap-4 lg:gap-6">
        <a href="{{ $docs->home() }}" class="flex items-center gap-3 shrink-0">
            @if ($docsLogo)
                <img src="{{ $docsLogo }}" alt="{{ $organisation->name }}" class="h-8 w-auto">
            @else
                <span class="w-8 h-8 rounded docs-accent-bg text-white font-bold flex items-center justify-center">{{ mb_strtoupper(mb_substr($organisation->name, 0, 1)) }}</span>
            @endif
            <span class="text-[13px] font-bold tracking-[0.18em] text-gray-900 uppercase">Document Library</span>
        </a>

        <nav class="hidden lg:flex items-center gap-1 text-sm font-medium text-gray-700" aria-label="Library">
            {{-- Systems mega-menu --}}
            <details class="docs-menu relative">
                <summary class="px-3 py-2 rounded hover:bg-gray-100 flex items-center gap-1">Systems
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="absolute left-0 mt-2 w-[min(56rem,90vw)] bg-white border border-gray-200 rounded-lg shadow-xl p-6">
                    @if ($navGroups->isEmpty())
                        <p class="text-sm text-gray-500">No manuals are published yet.</p>
                    @else
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                            @foreach ($navGroups as $group)
                                <div>
                                    <div class="text-xs font-bold uppercase tracking-wider docs-accent mb-2">{{ $group['name'] }}</div>
                                    <ul class="space-y-1">
                                        @foreach ($group['systems']->take(8) as $navSystem)
                                            <li><a href="{{ $docs->system($navSystem) }}" class="text-sm text-gray-700 hover:underline">{{ $navSystem->name }}</a></li>
                                        @endforeach
                                        @if ($group['systems']->count() > 8)
                                            <li><a href="{{ $docs->home() }}#category-{{ $group['slug'] }}" class="text-sm font-semibold docs-accent">More &raquo;</a></li>
                                        @endif
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-6 pt-4 border-t border-gray-100"><a href="{{ $docs->home() }}#all-systems" class="text-sm font-semibold docs-accent">All systems &raquo;</a></div>
                    @endif
                </div>
            </details>

            {{-- Systems A–Z --}}
            <details class="docs-menu relative">
                <summary class="px-3 py-2 rounded hover:bg-gray-100 flex items-center gap-1">Systems A–Z
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="absolute left-0 mt-2 w-80 bg-white border border-gray-200 rounded-lg shadow-xl p-4">
                    <input type="search" data-az-filter="#docs-az-list" placeholder="Filter systems" aria-label="Filter systems"
                           class="w-full mb-3 px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-(--docs-accent)">
                    <ul id="docs-az-list" class="max-h-80 overflow-y-auto">
                        @php $letter = null; @endphp
                        @forelse ($navSystems as $navSystem)
                            @php $first = mb_strtoupper(mb_substr($navSystem->name, 0, 1)); @endphp
                            @if ($first !== $letter)
                                @php $letter = $first; @endphp
                                <li class="text-xs font-bold text-gray-400 mt-2 mb-1">{{ $letter }}</li>
                            @endif
                            <li data-az-item="{{ $navSystem->name }}">
                                <a href="{{ $docs->system($navSystem) }}" class="block px-2 py-1 rounded text-sm text-gray-700 hover:bg-gray-100">{{ $navSystem->name }}</a>
                            </li>
                        @empty
                            <li class="text-sm text-gray-500">No manuals are published yet.</li>
                        @endforelse
                    </ul>
                </div>
            </details>

            <a href="{{ $docs->home() }}#recently-updated" class="px-3 py-2 rounded hover:bg-gray-100">What's new</a>
        </nav>

        <form action="{{ $docs->url('docs/search') }}" method="get" class="ml-auto hidden md:block" role="search">
            <label class="sr-only" for="docs-search">Search documents</label>
            <div class="relative">
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/></svg>
                <input id="docs-search" type="search" name="q" value="{{ request('q') }}" placeholder="Search documents"
                       class="w-56 lg:w-72 pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-(--docs-accent)">
            </div>
        </form>

        <div class="hidden lg:flex items-center gap-2 text-sm">
            <a href="{{ $docs->url('docs/access') }}" class="px-3 py-2 rounded hover:bg-gray-100 text-gray-700">
                {{ $docsAccess->hasCodes() ? 'Access codes (' . $docsAccess->codes()->count() . ')' : 'Enter access code' }}
            </a>
            @if ($docsAccess->isStaff())
                <a href="{{ url('/staff') }}" class="px-3 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">Staff: {{ $docsAccess->staffUser()->name }}</a>
            @else
                <a href="{{ $staffSignIn }}" class="px-3 py-2 rounded docs-accent-bg text-white font-semibold hover:opacity-90">Staff sign in</a>
            @endif
        </div>

        <button type="button" data-toggle="#docs-mobile-nav" class="lg:hidden ml-auto md:ml-0 p-2 rounded hover:bg-gray-100" aria-label="Menu">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>

    {{-- Mobile navigation --}}
    <div id="docs-mobile-nav" class="hidden lg:hidden border-t border-gray-200 px-4 py-4 space-y-4 bg-white">
        <form action="{{ $docs->url('docs/search') }}" method="get" role="search">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search documents" aria-label="Search documents"
                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded">
        </form>
        @foreach ($navGroups as $group)
            <div>
                <div class="text-xs font-bold uppercase tracking-wider docs-accent mb-1">{{ $group['name'] }}</div>
                @foreach ($group['systems'] as $navSystem)
                    <a href="{{ $docs->system($navSystem) }}" class="block py-1 text-sm text-gray-700">{{ $navSystem->name }}</a>
                @endforeach
            </div>
        @endforeach
        <div class="flex gap-2 pt-2 border-t border-gray-100">
            <a href="{{ $docs->url('docs/access') }}" class="text-sm text-gray-700 underline">Enter access code</a>
            <span class="text-gray-300">·</span>
            <a href="{{ $staffSignIn }}" class="text-sm text-gray-700 underline">Staff sign in</a>
        </div>
    </div>
</header>

@foreach (['docs_status', 'docs_unlocked'] as $flashKey)
    @if (session($flashKey))
        <div class="bg-green-50 border-b border-green-200 text-green-800 text-sm docs-no-print">
            <div class="max-w-7xl mx-auto px-4 py-3">
                {{ $flashKey === 'docs_unlocked' ? 'Access code accepted: ' . session($flashKey) . '. The manuals it opens are now available in this browser.' : session($flashKey) }}
            </div>
        </div>
    @endif
@endforeach

<main id="main" class="flex-1">
    @yield('content')
</main>

{{-- Footer --}}
<footer class="bg-gray-900 text-gray-300 mt-16 docs-no-print">
    <div class="max-w-7xl mx-auto px-4 py-10 grid grid-cols-1 md:grid-cols-3 gap-8 text-sm">
        <div>
            <div class="text-white font-semibold mb-2">{{ $organisation->docsTitle() }}</div>
            @if ($organisation->docs_tagline)
                <p class="text-gray-400">{{ $organisation->docs_tagline }}</p>
            @endif
        </div>
        <div>
            @if (!empty($organisation->docs_footer_links))
                <div class="text-white font-semibold mb-2">Links</div>
                <ul class="space-y-1">
                    @foreach ($organisation->docs_footer_links as $link)
                        @if (!empty($link['url']) && preg_match('#^https?://#i', $link['url']))
                            <li><a href="{{ $link['url'] }}" class="hover:text-white hover:underline" rel="noopener">{{ $link['label'] ?? $link['url'] }}</a></li>
                        @endif
                    @endforeach
                </ul>
            @endif
        </div>
        <div>
            <div class="text-white font-semibold mb-2">Access</div>
            <ul class="space-y-1">
                <li><a href="{{ $docs->url('docs/access') }}" class="hover:text-white hover:underline">Enter an access code</a></li>
                <li><a href="{{ $staffSignIn }}" class="hover:text-white hover:underline">Staff sign in</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-gray-800">
        <div class="max-w-7xl mx-auto px-4 py-4 text-xs text-gray-500">&copy; {{ now()->year }} {{ $organisation->name }}</div>
    </div>
</footer>
</body>
</html>
