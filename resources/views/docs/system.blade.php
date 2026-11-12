@extends('docs.layout', [
    'title' => $system->name,
    'description' => $system->summary ?: $system->name . ' documentation',
    'canonical' => $docs->system($system),
    'noindex' => $system->library_visibility !== \App\Models\System::LIBRARY_PUBLIC,
])

@section('content')
@php
    $isRestricted = $system->library_visibility === \App\Models\System::LIBRARY_RESTRICTED;
    $release = $system->latestRelease;
@endphp

{{-- Title band --}}
<section class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 py-8">
        @include('docs.partials.breadcrumb', ['crumbs' => array_values(array_filter([
            $system->category ? [$system->category->name, $docs->home() . '#category-' . $system->category->slug] : null,
            [$system->name, null],
        ]))])
        <div class="mt-3 flex flex-wrap items-center gap-3">
            <h1 class="text-3xl font-bold text-gray-900">{{ $system->name }}</h1>
            @if ($release)
                <a href="{{ $docs->system($system, 'changes') }}" class="px-2 py-0.5 rounded border border-gray-800 text-sm font-semibold text-gray-900 hover:bg-gray-900 hover:text-white">{{ $release->version }}</a>
            @endif
            @if ($isRestricted)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-xs font-semibold">
                    @include('docs.partials.lock', ['class' => 'w-3.5 h-3.5']) Restricted
                </span>
            @endif
        </div>
        @if ($system->summary)
            <p class="mt-2 text-gray-600 max-w-3xl">{{ $system->summary }}</p>
        @endif

        <div class="mt-6 flex flex-col md:flex-row md:items-center gap-3 docs-no-print">
            <form action="{{ $docs->url('docs/search') }}" method="get" role="search" class="flex-1 max-w-xl">
                <input type="hidden" name="system" value="{{ $system->slug }}">
                <label class="sr-only" for="system-search">Search {{ $system->name }} documentation</label>
                <div class="flex border border-gray-300 rounded overflow-hidden focus-within:ring-2 focus-within:ring-(--docs-accent)">
                    <input id="system-search" type="search" name="q" placeholder="Search {{ $system->name }} documentation"
                           class="flex-1 px-4 py-2.5 text-sm focus:outline-none">
                    <button type="submit" class="px-5 bg-gray-900 text-white text-sm font-semibold hover:bg-gray-800">Search</button>
                </div>
            </form>
            <div class="flex flex-wrap gap-2 text-sm">
                @if ($firstSection)
                    <a href="{{ $docs->section($system, $firstSection) }}" class="px-4 py-2.5 rounded docs-accent-bg text-white font-semibold hover:opacity-90">Start reading</a>
                @endif
                <a href="{{ $docs->system($system, 'changes') }}" class="px-4 py-2.5 rounded border border-gray-300 bg-white text-gray-800 font-semibold hover:bg-gray-50">What's changed</a>
            </div>
        </div>
        @if ($updatedAt)
            <p class="mt-3 text-xs text-gray-500">Last updated {{ $docs->date($updatedAt) }}</p>
        @endif
    </div>
</section>

<div class="max-w-7xl mx-auto px-4 py-8">
    @if ($hasLocked)
        <div class="mb-8 flex gap-3 items-start bg-amber-50 border border-amber-200 rounded-md p-4 text-sm text-amber-900 docs-no-print">
            @include('docs.partials.lock', ['class' => 'w-5 h-5 mt-0.5'])
            <div>
                Some guides are only for particular readers (marked with a lock). If {{ $docs->organisation->name }} gave you an
                <a href="{{ $docs->url('docs/access') }}?return={{ urlencode(request()->getRequestUri()) }}" class="underline font-semibold">access code</a>,
                enter it to open them, or open a guide to ask for access.
            </div>
        </div>
    @endif

    {{-- Guides, grouped like docs.fortinet.com --}}
    @forelse ($groups as $group)
        <section id="guides-{{ $group['key'] }}" class="scroll-mt-24 {{ $loop->first ? '' : 'mt-10 pt-10 border-t border-gray-200' }}">
            <div class="flex items-center gap-3 mb-1">
                @if ($group['audience'])
                    {{-- people icon --}}
                    <svg class="w-7 h-7 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                @else
                    {{-- compass icon --}}
                    <svg class="w-7 h-7 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke-width="1.6"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M15.5 8.5l-2 5-5 2 2-5 5-2z"/></svg>
                @endif
                <h2 class="text-2xl font-bold text-gray-900">{{ $group['title'] }}</h2>
            </div>
            @if ($group['description'])
                <p class="ml-10 mb-4 text-sm text-gray-500">{{ $group['description'] }}</p>
            @else
                <div class="mb-4"></div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($group['cards'] as $card)
                    @php $chapter = $card['section']; @endphp
                    <a href="{{ $docs->section($system, $chapter) }}"
                       class="group relative flex flex-col min-h-44 bg-white border border-gray-200 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-(--docs-accent)">
                        <span class="h-1 w-full bg-linear-to-r from-(--docs-accent) to-transparent" aria-hidden="true"></span>
                        <span class="flex flex-1 flex-col px-5 pt-4 pb-4">
                            <span class="flex items-start gap-2">
                                <span class="flex-1 text-[17px] font-semibold leading-snug {{ $card['locked'] ? 'text-gray-500' : 'text-gray-900 group-hover:text-(--docs-accent)' }}">{{ $chapter->title }}</span>
                                @if ($card['locked'])
                                    @include('docs.partials.lock', ['class' => 'w-4 h-4 mt-1 text-gray-400'])
                                @endif
                            </span>
                            <span class="mt-2 flex flex-wrap items-center gap-1.5">
                                @if ($card['version'])
                                    <span class="px-2 py-0.5 rounded border border-gray-800 text-xs font-medium text-gray-900">{{ $card['version'] }}</span>
                                @endif
                                @if ($card['restricted'])
                                    <span class="px-2 py-0.5 rounded bg-amber-50 text-amber-800 text-xs">Restricted</span>
                                @endif
                                @if ($group['audience'] && $card['audiences']->count() > 1)
                                    <span class="text-xs text-gray-500">Also for {{ $card['audiences']->where('name', '!=', $group['title'])->pluck('name')->join(', ') }}</span>
                                @endif
                            </span>
                            @if ($card['topics'] > 0)
                                <span class="mt-2 text-[13px] text-gray-500">{{ $card['topics'] }} {{ Str::plural('topic', $card['topics']) }}</span>
                            @endif
                            <span class="mt-auto pt-4 text-xs text-gray-400">
                                @if ($card['updated_at'])Last updated {{ $docs->date($card['updated_at'], 'M d, Y') }}@endif
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @empty
        <div class="bg-white rounded-md border border-gray-200 p-10 text-center text-gray-600">
            This product has no published documentation yet.
        </div>
    @endforelse

    {{-- Help and related products --}}
    @if ($system->support_contact || ($docsAccess->isStaff() && $system->escalation_notes) || $related->isNotEmpty())
        <div class="mt-12 pt-10 border-t border-gray-200 grid grid-cols-1 md:grid-cols-2 gap-6 docs-no-print">
            @if ($system->support_contact || ($docsAccess->isStaff() && $system->escalation_notes))
                <div>
                    <h2 class="text-lg font-bold text-gray-900 mb-3">How to get help</h2>
                    <div class="bg-white border border-gray-200 rounded-md p-5 text-sm text-gray-700">
                        @if ($system->support_contact)
                            <p class="whitespace-pre-line">{{ $system->support_contact }}</p>
                        @endif
                        @if ($docsAccess->isStaff() && $system->escalation_notes)
                            <div class="mt-3 pt-3 border-t border-gray-100">
                                <div class="text-xs font-semibold text-gray-500 mb-1">Escalation (staff only)</div>
                                <p class="whitespace-pre-line">{{ $system->escalation_notes }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
            @if ($related->isNotEmpty())
                <div>
                    <h2 class="text-lg font-bold text-gray-900 mb-3">Related products</h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($related as $other)
                            <a href="{{ $docs->system($other) }}" class="px-3 py-2 bg-white border border-gray-200 rounded text-sm font-medium text-gray-800 hover:border-(--docs-accent) hover:text-(--docs-accent)">{{ $other->name }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
