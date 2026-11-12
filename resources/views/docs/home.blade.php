@extends('docs.layout', ['canonical' => $docs->home()])

@section('content')
@php $organisation = $docs->organisation; @endphp

{{-- Hero with the big search box --}}
<section class="docs-accent-bg text-white">
    <div class="max-w-7xl mx-auto px-4 py-12 md:py-16">
        <h1 class="text-3xl md:text-4xl font-bold">{{ $organisation->docsTitle() }}</h1>
        @if ($organisation->docs_tagline)
            <p class="mt-2 text-white/85 text-lg">{{ $organisation->docs_tagline }}</p>
        @endif
        <form action="{{ $docs->url('docs/search') }}" method="get" role="search" class="mt-8 max-w-3xl">
            <label for="docs-hero-search" class="sr-only">Search all documents</label>
            <div class="flex bg-white rounded-md shadow-lg overflow-hidden">
                <input id="docs-hero-search" type="search" name="q" placeholder="Search all documents"
                       class="flex-1 px-5 py-4 text-gray-900 text-base focus:outline-none">
                <button type="submit" class="px-6 bg-gray-900 text-white font-semibold hover:bg-gray-800">Search</button>
            </div>
        </form>
        @if ($systemCount > 0)
            <p class="mt-3 text-sm text-white/80">{{ $systemCount }} {{ Str::plural('product', $systemCount) }} in the library</p>
        @endif
    </div>
</section>

<div class="max-w-7xl mx-auto px-4">
    {{-- Announcements (Organisation Settings) --}}
    @if (!empty($organisation->docs_banners))
        <section class="flex flex-wrap gap-2 -mt-5 relative" aria-label="Announcements">
            @foreach ($organisation->docs_banners as $banner)
                @php $bannerUrl = !empty($banner['url']) && preg_match('#^(https?://|/)#i', $banner['url']) ? $banner['url'] : null; @endphp
                <div class="w-full sm:w-60 bg-white rounded shadow-sm border border-gray-200 border-l-4 docs-accent-border px-3 py-2">
                    <div class="text-[13px] font-semibold text-gray-900 truncate">{{ $banner['title'] ?? '' }}</div>
                    @if (!empty($banner['text']))
                        <p class="text-xs leading-snug text-gray-600 line-clamp-1">{{ $banner['text'] }}</p>
                    @endif
                    @if ($bannerUrl)
                        <a href="{{ $bannerUrl }}" class="text-xs font-semibold docs-accent hover:underline">{{ $banner['link_label'] ?? 'Learn more' }} &raquo;</a>
                    @endif
                </div>
            @endforeach
        </section>
    @endif

    @if ($systemCount === 0)
        <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-10 mt-10 text-center">
            <h2 class="text-xl font-semibold text-gray-900">No products are published yet</h2>
            <p class="mt-2 text-gray-600">When a product's documentation is added to the library it will appear here.</p>
        </section>
    @else
        {{-- Products by category --}}
        <section id="all-systems" class="mt-10 scroll-mt-24">
            <h2 class="text-2xl font-bold text-gray-900 mb-5">Browse by product</h2>
            @php
                $blocks = $groups->map(fn ($g) => ['name' => $g['category']->name, 'slug' => $g['category']->slug, 'description' => $g['category']->description, 'systems' => $g['systems']]);
                if ($uncategorised->isNotEmpty()) {
                    $blocks->push(['name' => $groups->isEmpty() ? 'Products' : 'Other products', 'slug' => 'other', 'description' => null, 'systems' => $uncategorised]);
                }
            @endphp
            <div class="space-y-8">
                @foreach ($blocks as $block)
                    <div id="category-{{ $block['slug'] }}" class="scroll-mt-24">
                        <div class="mb-3 flex flex-wrap items-baseline gap-x-3">
                            <h3 class="text-sm font-bold uppercase tracking-wider docs-accent">{{ $block['name'] }}</h3>
                            @if ($block['description'])
                                <p class="text-[13px] text-gray-500">{{ $block['description'] }}</p>
                            @endif
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach ($block['systems'] as $system)
                                @php $locked = $system->library_visibility === \App\Models\System::LIBRARY_RESTRICTED && !$docsAccess->canOpenManual($system); @endphp
                                <a href="{{ $docs->system($system) }}"
                                   class="group relative flex flex-col min-h-44 bg-white border border-gray-200 shadow-sm transition
                                          hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-(--docs-accent)">
                                    <span class="h-1 w-full bg-linear-to-r from-(--docs-accent) to-transparent" aria-hidden="true"></span>
                                    <span class="flex flex-1 flex-col px-5 pt-4 pb-4">
                                        <span class="flex items-start gap-2">
                                            <span class="flex-1 text-lg font-semibold leading-snug text-gray-900 group-hover:text-(--docs-accent)">{{ $system->name }}</span>
                                            @if ($locked)
                                                @include('docs.partials.lock', ['class' => 'w-4 h-4 mt-1.5 text-gray-400'])
                                            @endif
                                        </span>
                                        @if ($system->latestRelease)
                                            <span class="mt-2"><span class="px-2 py-0.5 rounded border border-gray-800 text-xs font-medium text-gray-900">{{ $system->latestRelease->version }}</span></span>
                                        @endif
                                        @if ($system->summary)
                                            <span class="mt-3 text-sm leading-snug text-gray-600 line-clamp-2">{{ $system->summary }}</span>
                                        @endif
                                        <span class="mt-auto pt-4 text-xs text-gray-400">
                                            @if ($system->latestRelease)Last updated {{ $docs->date($system->latestRelease->published_at, 'M d, Y') }}@endif
                                        </span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Recently updated: the latest version of each product --}}
        @if ($recent->isNotEmpty())
            <section id="recently-updated" class="mt-12 scroll-mt-24">
                <h2 class="text-lg font-bold text-gray-900 mb-3">Recently updated</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach ($recent as $release)
                        <a href="{{ $docs->system($release->system, 'changes') }}#v{{ $release->version }}"
                           class="group w-full sm:w-60 bg-white rounded border border-gray-200 shadow-sm px-3 py-2 transition hover:shadow-md hover:border-(--docs-accent)">
                            <div class="flex items-center gap-2 text-[11px]">
                                <span class="px-1.5 py-0.5 rounded font-semibold {{ $release->is_major ? 'docs-accent-bg text-white' : 'bg-gray-100 text-gray-700' }}">v{{ $release->version }}</span>
                                <span class="text-gray-500">{{ $docs->date($release->published_at) }}</span>
                            </div>
                            <div class="mt-1 text-[13px] font-semibold text-gray-900 truncate group-hover:text-(--docs-accent)">{{ $release->system->name }}</div>
                            @if ($release->summary)
                                <p class="text-xs leading-snug text-gray-600 line-clamp-1">{{ $release->summary }}</p>
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    @endif
</div>
@endsection
