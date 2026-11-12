@extends('docs.layout', [
    'title' => $system->name,
    'description' => $system->summary ?: $system->name . ' manual',
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
            @if ($isRestricted)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-xs font-semibold">
                    @include('docs.partials.lock', ['class' => 'w-3.5 h-3.5']) Restricted
                </span>
            @endif
        </div>
        @if ($system->summary)
            <p class="mt-2 text-gray-600 max-w-3xl">{{ $system->summary }}</p>
        @endif
        <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-gray-500">
            @if ($release)
                <a href="{{ $docs->system($system, 'changes') }}" class="hover:underline">Version <span class="font-semibold text-gray-800">{{ $release->version }}</span></a>
            @endif
            @if ($updatedAt)
                <span>Updated {{ $docs->date($updatedAt) }}</span>
            @endif
        </div>
        <div class="mt-6 flex flex-wrap gap-3 docs-no-print">
            @if ($firstSection)
                <a href="{{ $docs->section($system, $firstSection) }}" class="px-5 py-2.5 rounded docs-accent-bg text-white font-semibold hover:opacity-90">Start reading</a>
                <a href="{{ $docs->system($system, 'pdf') }}" class="px-5 py-2.5 rounded border border-gray-300 bg-white text-gray-800 font-semibold hover:bg-gray-50">Download PDF</a>
            @endif
        </div>
    </div>
</section>

<div class="max-w-7xl mx-auto px-4 py-8 grid grid-cols-1 lg:grid-cols-[16rem_1fr] gap-8">
    {{-- Left column --}}
    <aside class="space-y-6 docs-no-print">
        @if (!empty($tree))
            <div class="bg-white rounded-lg border border-gray-200 p-4 lg:sticky lg:top-24">
                <div class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">On this page</div>
                <ul class="space-y-1 text-sm">
                    @foreach ($tree as $node)
                        <li><a href="#chapter-{{ $node['section']->slug }}" class="text-gray-700 hover:underline">{{ trim(($node['section']->number ? $node['section']->number . ' ' : '') . $node['section']->title) }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <div class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Tools &amp; resources</div>
            <ul class="space-y-2 text-sm">
                @if ($firstSection)
                    <li><a href="{{ $docs->system($system, 'pdf') }}" class="docs-accent hover:underline">Download the manual (PDF)</a></li>
                @endif
                <li><a href="{{ $docs->system($system, 'changes') }}" class="docs-accent hover:underline">What's changed</a></li>
                <li>
                    <form action="{{ $docs->url('docs/search') }}" method="get" role="search" class="mt-1">
                        <input type="hidden" name="system" value="{{ $system->slug }}">
                        <label class="sr-only" for="system-search">Search this manual</label>
                        <input id="system-search" type="search" name="q" placeholder="Search this manual"
                               class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-(--docs-accent)">
                    </form>
                </li>
            </ul>
        </div>

        @if ($system->support_contact || ($docsAccess->isStaff() && $system->escalation_notes))
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">How to get help</div>
                @if ($system->support_contact)
                    <p class="text-sm text-gray-700 whitespace-pre-line">{{ $system->support_contact }}</p>
                @endif
                @if ($docsAccess->isStaff() && $system->escalation_notes)
                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <div class="text-xs font-semibold text-gray-500 mb-1">Escalation (staff only)</div>
                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ $system->escalation_notes }}</p>
                    </div>
                @endif
            </div>
        @endif

        @if ($related->isNotEmpty())
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Related systems</div>
                <ul class="space-y-1 text-sm">
                    @foreach ($related as $other)
                        <li><a href="{{ $docs->system($other) }}" class="text-gray-700 hover:underline">{{ $other->name }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </aside>

    {{-- Chapters --}}
    <div class="space-y-5 min-w-0">
        @if ($hasLocked)
            <div class="flex gap-3 items-start bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-900 docs-no-print">
                @include('docs.partials.lock', ['class' => 'w-5 h-5 mt-0.5'])
                <div>
                    Some chapters of this manual are restricted. Staff can <a href="{{ url('/staff/login') }}" class="underline font-semibold">sign in</a>;
                    other readers need an <a href="{{ $docs->url('docs/access') }}?return={{ urlencode(request()->getRequestUri()) }}" class="underline font-semibold">access code</a>.
                </div>
            </div>
        @endif

        @forelse ($tree as $node)
            @php $chapter = $node['section']; @endphp
            <section id="chapter-{{ $chapter->slug }}" class="bg-white rounded-lg border border-gray-200 shadow-sm scroll-mt-24">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
                    <h2 class="text-lg font-bold text-gray-900 flex-1">
                        <a href="{{ $docs->section($system, $chapter) }}" class="hover:underline {{ $node['locked'] ? 'text-gray-500' : '' }}">
                            {{ trim(($chapter->number ? $chapter->number . ' ' : '') . $chapter->title) }}
                        </a>
                    </h2>
                    @if ($node['locked'])
                        @include('docs.partials.lock', ['class' => 'w-5 h-5 text-gray-400'])
                    @endif
                </div>
                @if (!empty($node['children']))
                    <ul class="px-6 py-4 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-2">
                        @foreach ($node['children'] as $child)
                            <li class="flex items-start gap-1.5">
                                <a href="{{ $docs->section($system, $child['section']) }}" class="text-sm {{ $child['locked'] ? 'text-gray-400' : 'docs-accent' }} hover:underline">
                                    {{ trim(($child['section']->number ? $child['section']->number . ' ' : '') . $child['section']->title) }}
                                </a>
                                @if ($child['locked'])
                                    @include('docs.partials.lock', ['class' => 'w-3.5 h-3.5 mt-0.5 text-gray-400'])
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @empty
            <div class="bg-white rounded-lg border border-gray-200 p-10 text-center text-gray-600">
                This manual has no published sections yet.
            </div>
        @endforelse
    </div>
</div>
@endsection
