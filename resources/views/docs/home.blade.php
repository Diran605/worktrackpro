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
            <p class="mt-3 text-sm text-white/80">{{ $systemCount }} {{ Str::plural('manual', $systemCount) }} in the library</p>
        @endif
    </div>
</section>

<div class="max-w-7xl mx-auto px-4">
    {{-- Banners (announcements set in Organisation Settings) --}}
    @if (!empty($organisation->docs_banners))
        <section class="grid grid-cols-1 md:grid-cols-3 gap-4 -mt-6 relative">
            @foreach ($organisation->docs_banners as $banner)
                @php $bannerUrl = !empty($banner['url']) && preg_match('#^(https?://|/)#i', $banner['url']) ? $banner['url'] : null; @endphp
                <div class="bg-white rounded-lg shadow border-t-4 docs-accent-border p-5">
                    <div class="font-semibold text-gray-900">{{ $banner['title'] ?? '' }}</div>
                    @if (!empty($banner['text']))
                        <p class="mt-1 text-sm text-gray-600">{{ $banner['text'] }}</p>
                    @endif
                    @if ($bannerUrl)
                        <a href="{{ $bannerUrl }}" class="mt-3 inline-block text-sm font-semibold docs-accent hover:underline">{{ $banner['link_label'] ?? 'Learn more' }} &raquo;</a>
                    @endif
                </div>
            @endforeach
        </section>
    @endif

    @if ($systemCount === 0)
        <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-10 mt-10 text-center">
            <h2 class="text-xl font-semibold text-gray-900">No manuals are published yet</h2>
            <p class="mt-2 text-gray-600">When a system's manual is added to the library it will appear here.</p>
            @unless ($docsAccess->isStaff())
                <p class="mt-4 text-sm text-gray-500">Staff can <a href="{{ $staffSignIn }}" class="docs-accent underline">sign in</a> to read internal manuals in the staff portal.</p>
            @endunless
        </section>
    @else
        {{-- Systems by category --}}
        <section id="all-systems" class="mt-10">
            <h2 class="text-2xl font-bold text-gray-900 mb-5">Browse by system</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                @php
                    $blocks = $groups->map(fn ($g) => ['name' => $g['category']->name, 'slug' => $g['category']->slug, 'description' => $g['category']->description, 'systems' => $g['systems']]);
                    if ($uncategorised->isNotEmpty()) {
                        $blocks->push(['name' => $groups->isEmpty() ? 'Systems' : 'Other systems', 'slug' => 'other', 'description' => null, 'systems' => $uncategorised]);
                    }
                @endphp
                @foreach ($blocks as $block)
                    <div id="category-{{ $block['slug'] }}" class="bg-white rounded-lg border border-gray-200 shadow-sm flex flex-col scroll-mt-24">
                        <div class="px-5 pt-5 pb-3 border-b border-gray-100">
                            <h3 class="text-sm font-bold uppercase tracking-wider docs-accent">{{ $block['name'] }}</h3>
                            @if ($block['description'])
                                <p class="mt-1 text-[13px] text-gray-500">{{ $block['description'] }}</p>
                            @endif
                        </div>
                        <ul class="px-5 py-3 space-y-2 flex-1">
                            @foreach ($block['systems'] as $i => $system)
                                <li class="{{ $i >= 6 ? 'hidden' : '' }}" @if ($i >= 6) data-more-{{ $block['slug'] }} @endif>
                                    <a href="{{ $docs->system($system) }}" class="group flex items-start gap-2">
                                        <span class="flex-1">
                                            <span class="text-[15px] font-medium text-gray-900 group-hover:underline">{{ $system->name }}</span>
                                            @if ($system->summary)
                                                <span class="block text-[13px] text-gray-500 line-clamp-1">{{ $system->summary }}</span>
                                            @endif
                                        </span>
                                        @if ($system->library_visibility === \App\Models\System::LIBRARY_RESTRICTED && !$docsAccess->canOpenManual($system))
                                            @include('docs.partials.lock', ['class' => 'w-4 h-4 mt-1 text-gray-400'])
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        @if ($block['systems']->count() > 6)
                            <div class="px-5 pb-4">
                                <button type="button" class="text-sm font-semibold docs-accent hover:underline"
                                        onclick="document.querySelectorAll('[data-more-{{ $block['slug'] }}]').forEach(e => e.classList.remove('hidden')); this.remove();">
                                    Show all {{ $block['systems']->count() }} &raquo;
                                </button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Recently updated --}}
        @if ($recent->isNotEmpty())
            <section id="recently-updated" class="mt-12 scroll-mt-24">
                <h2 class="text-2xl font-bold text-gray-900 mb-5">Recently updated</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                    @foreach ($recent as $release)
                        <a href="{{ $docs->system($release->system, 'changes') }}#v{{ $release->version }}"
                           class="bg-white rounded-lg border border-gray-200 shadow-sm p-5 hover:shadow-md transition-shadow">
                            <div class="flex items-center gap-2 text-xs">
                                <span class="px-2 py-0.5 rounded-full font-semibold {{ $release->is_major ? 'docs-accent-bg text-white' : 'bg-gray-100 text-gray-700' }}">Version {{ $release->version }}</span>
                                <span class="text-gray-500">{{ $docs->date($release->published_at) }}</span>
                            </div>
                            <div class="mt-2 font-semibold text-gray-900">{{ $release->system->name }}</div>
                            @if ($release->summary)
                                <p class="mt-1 text-sm text-gray-600 line-clamp-2">{{ $release->summary }}</p>
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    @endif
</div>
@endsection
