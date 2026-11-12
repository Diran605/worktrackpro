@extends('docs.layout', [
    'title' => $section->title . ' | ' . $system->name,
    'description' => $body ? Str::limit(trim(html_entity_decode(strip_tags($body))), 160) : $system->name . ' manual',
    'canonical' => $docs->section($system, $section),
    'noindex' => $isLocked || $system->library_visibility !== \App\Models\System::LIBRARY_PUBLIC || $section->isRestricted(),
])

@section('content')
@php
    $label = fn ($s) => trim(($s->number ? $s->number . ' ' : '') . $s->title);
    $release = $system->latestRelease;
@endphp

<div class="max-w-360 mx-auto px-4 py-6 grid grid-cols-1 lg:grid-cols-[17rem_1fr] xl:grid-cols-[17rem_1fr_14rem] gap-6">

    {{-- Left: contents of this manual --}}
    <aside class="docs-no-print">
        <button type="button" data-toggle="#docs-contents" class="lg:hidden w-full flex items-center justify-between bg-white border border-gray-200 rounded-lg px-4 py-3 text-sm font-semibold">
            Contents
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div id="docs-contents" class="hidden lg:block mt-2 lg:mt-0 bg-white border border-gray-200 rounded-lg lg:sticky lg:top-24 lg:max-h-[calc(100vh-7rem)] flex-col overflow-hidden">
            <div class="p-4 border-b border-gray-100">
                <a href="{{ $docs->system($system) }}" class="block font-bold text-gray-900 hover:underline">{{ $system->name }}</a>
                @if ($release)
                    <a href="{{ $docs->system($system, 'changes') }}" class="text-xs text-gray-500 hover:underline">Version {{ $release->version }}</a>
                @endif
                <form action="{{ $docs->url('docs/search') }}" method="get" role="search" class="mt-3">
                    <input type="hidden" name="system" value="{{ $system->slug }}">
                    <label class="sr-only" for="tree-search">Search this manual</label>
                    <input id="tree-search" type="search" name="q" placeholder="Search this manual"
                           class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-(--docs-accent)">
                </form>
            </div>
            <nav class="docs-tree p-3 overflow-y-auto lg:max-h-[calc(100vh-17rem)]" aria-label="Manual contents">
                @include('docs.partials.tree', ['nodes' => $tree, 'currentId' => $section->id, 'ancestorIds' => $ancestorIds, 'depth' => 0])
            </nav>
        </div>
    </aside>

    {{-- Middle: the section --}}
    <article class="bg-white border border-gray-200 rounded-lg shadow-sm min-w-0">
        <div class="px-6 md:px-10 pt-6">
            @include('docs.partials.breadcrumb', ['crumbs' => [[$system->name, $docs->system($system)], [$section->title, null]]])
            <h1 class="mt-3 text-2xl md:text-3xl font-bold text-gray-900">{{ $label($section) }}</h1>
            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-[13px] text-gray-500">
                @if ($updatedAt)
                    <span>Last updated {{ $docs->date($updatedAt) }}</span>
                @endif
                @if ($updatedInLatest && $release)
                    <a href="{{ $docs->system($system, 'changes') }}#v{{ $release->version }}" class="px-2 py-0.5 rounded-full bg-green-100 text-green-800 font-semibold hover:underline">Updated in version {{ $release->version }}</a>
                @endif
                @if ($section->isRestricted())
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-semibold">@include('docs.partials.lock', ['class' => 'w-3 h-3']) Restricted</span>
                @endif
                @if ($audiences->isNotEmpty())
                    <span class="inline-flex items-center gap-1">For:
                        @foreach ($audiences as $audience)
                            <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 font-semibold">{{ $audience->name }}</span>
                        @endforeach
                    </span>
                @endif
                <button type="button" data-copy-link="{{ $docs->section($system, $section) }}" class="ml-auto inline-flex items-center gap-1 hover:text-gray-800 docs-no-print">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                    <span data-label>Copy link</span>
                </button>
            </div>
        </div>

        <div class="px-6 md:px-10 py-6">
            @if ($isLocked)
                @include('docs.partials.locked-panel', ['system' => $system, 'audiences' => $audiences])
            @elseif ($pdf)
                <div class="space-y-3">
                    <p class="text-sm text-gray-600">
                        This section is a PDF document{{ $pdf['page_from'] ? ' (pages ' . $pdf['page_from'] . '–' . ($pdf['page_to'] ?: 'end') . ')' : '' }}.
                        <a href="{{ $pdf['url'] }}" class="docs-accent underline" target="_blank" rel="noopener">Open {{ $pdf['name'] ?: 'the PDF' }}</a>
                    </p>
                    <iframe src="{{ $pdf['url'] }}{{ $pdf['page_from'] ? '#page=' . $pdf['page_from'] : '' }}" title="{{ $section->title }}" class="w-full h-[75vh] border border-gray-200 rounded"></iframe>
                </div>
            @elseif ($body)
                <div class="docs-content">{!! $body !!}</div>
            @else
                <p class="text-gray-500">This section has no content of its own. Choose one of its parts from the contents.</p>
                @php
                    $findChildren = function (array $nodes) use (&$findChildren, $section): array {
                        foreach ($nodes as $n) {
                            if ($n['section']->id === $section->id) {
                                return $n['children'];
                            }
                            if ($found = $findChildren($n['children'])) {
                                return $found;
                            }
                        }
                        return [];
                    };
                    $own = $findChildren($tree);
                @endphp
                @if (!empty($own))
                    <ul class="mt-4 space-y-2">
                        @foreach ($own as $child)
                            <li><a href="{{ $docs->section($system, $child['section']) }}" class="{{ $child['locked'] ? 'text-gray-400' : 'docs-accent' }} hover:underline">{{ $label($child['section']) }}</a></li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </div>

        {{-- Previous / next --}}
        @if ($previous || $next)
            <nav class="px-6 md:px-10 py-5 border-t border-gray-100 grid grid-cols-2 gap-4 docs-no-print" aria-label="Previous and next">
                <div>
                    @if ($previous)
                        <a href="{{ $docs->section($system, $previous) }}" class="group block">
                            <span class="text-xs text-gray-500">&larr; Previous</span>
                            <span class="block text-sm font-semibold docs-accent group-hover:underline">{{ $label($previous) }}</span>
                        </a>
                    @endif
                </div>
                <div class="text-right">
                    @if ($next)
                        <a href="{{ $docs->section($system, $next) }}" class="group block">
                            <span class="text-xs text-gray-500">Next &rarr;</span>
                            <span class="block text-sm font-semibold docs-accent group-hover:underline">{{ $label($next) }}</span>
                        </a>
                    @endif
                </div>
            </nav>
        @endif

        {{-- Was this helpful? --}}
        @unless ($isLocked)
            <div id="feedback" class="px-6 md:px-10 py-5 border-t border-gray-100 bg-gray-50 rounded-b-lg docs-no-print scroll-mt-24">
                @if (session('docs_feedback_saved'))
                    <p class="text-sm text-green-700 font-semibold">Thank you for your feedback.</p>
                @else
                    <form method="post" action="{{ $docs->section($system, $section) }}/feedback" class="space-y-3">
                        @csrf
                        @php $helpful = (string) old('helpful', $feedback ? ($feedback->helpful ? '1' : '0') : ''); @endphp
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="text-sm font-semibold text-gray-800">Was this helpful?</span>
                            <label class="inline-flex items-center gap-1.5 text-sm cursor-pointer">
                                <input type="radio" name="helpful" value="1" required @checked($helpful === '1')> Yes
                            </label>
                            <label class="inline-flex items-center gap-1.5 text-sm cursor-pointer">
                                <input type="radio" name="helpful" value="0" required @checked($helpful === '0')> No
                            </label>
                        </div>
                        <label class="block">
                            <span class="sr-only">Comment</span>
                            <textarea name="comment" rows="2" maxlength="1000" placeholder="Anything we should add or fix? (optional)"
                                      class="w-full max-w-xl px-3 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-(--docs-accent)">{{ old('comment', $feedback?->comment) }}</textarea>
                        </label>
                        @error('helpful')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        <button type="submit" class="px-4 py-1.5 rounded docs-accent-bg text-white text-sm font-semibold hover:opacity-90">{{ $feedback ? 'Update feedback' : 'Send feedback' }}</button>
                    </form>
                @endif
            </div>
        @endunless
    </article>

    {{-- Right: on this page --}}
    <aside class="hidden xl:block docs-no-print">
        @if (count($headings) > 1)
            <div class="sticky top-24">
                <div class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">On this page</div>
                <ul class="space-y-1.5 text-[13px] border-l border-gray-200">
                    @foreach ($headings as $heading)
                        <li class="{{ $heading['level'] === 3 ? 'pl-6' : 'pl-3' }}">
                            <a href="#{{ $heading['id'] }}" class="text-gray-600 hover:text-gray-900 hover:underline">{{ $heading['text'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </aside>
</div>
@endsection
