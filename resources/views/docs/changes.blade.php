@extends('docs.layout', [
    'title' => "What's changed | " . $system->name,
    'canonical' => $docs->system($system, 'changes'),
    'noindex' => $system->library_visibility !== \App\Models\System::LIBRARY_PUBLIC,
])

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    @include('docs.partials.breadcrumb', ['crumbs' => [[$system->name, $docs->system($system)], ["What's changed", null]]])
    <h1 class="mt-3 text-3xl font-bold text-gray-900">What's changed in the {{ $system->name }} manual</h1>
    <p class="mt-2 text-gray-600">Each version lists the sections that were added or updated.</p>

    <div class="mt-8 space-y-5">
        @forelse ($releases as $entry)
            @php $release = $entry['release']; @endphp
            <section id="v{{ $release->version }}" class="bg-white rounded-lg border border-gray-200 shadow-sm scroll-mt-24 {{ $loop->first ? 'border-l-4 docs-accent-border' : '' }}">
                <div class="px-6 py-4 border-b border-gray-100 flex flex-wrap items-center gap-3">
                    <h2 class="text-lg font-bold text-gray-900">Version {{ $release->version }}</h2>
                    @if ($release->is_major)
                        <span class="px-2 py-0.5 rounded-full docs-accent-bg text-white text-xs font-semibold">Major</span>
                    @endif
                    @if ($loop->first)
                        <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-800 text-xs font-semibold">Current</span>
                    @endif
                    <span class="ml-auto text-sm text-gray-500">{{ $docs->date($release->published_at) }}</span>
                </div>
                <div class="px-6 py-4">
                    @if ($release->summary)
                        <p class="text-gray-700 whitespace-pre-line">{{ $release->summary }}</p>
                    @endif
                    @if ($entry['sections']->isNotEmpty())
                        <div class="mt-3 text-xs font-bold uppercase tracking-wider text-gray-500">Sections in this version</div>
                        <ul class="mt-2 space-y-1">
                            @foreach ($entry['sections'] as $changed)
                                <li><a href="{{ $docs->section($system, $changed) }}" class="text-sm docs-accent hover:underline">{{ trim(($changed->number ? $changed->number . ' ' : '') . $changed->title) }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($entry['hidden'] > 0)
                        <p class="mt-2 text-sm text-gray-500 flex items-center gap-1">
                            @include('docs.partials.lock', ['class' => 'w-3.5 h-3.5'])
                            {{ $entry['hidden'] }} restricted {{ Str::plural('section', $entry['hidden']) }} also changed.
                        </p>
                    @endif
                </div>
            </section>
        @empty
            <div class="bg-white rounded-lg border border-gray-200 p-10 text-center text-gray-600">No versions have been published yet.</div>
        @endforelse
    </div>
</div>
@endsection
