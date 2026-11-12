@extends('docs.layout', ['title' => ($section?->title ? $section->title . ' | ' : '') . $system->name, 'noindex' => true])

@section('content')
<div class="max-w-4xl mx-auto px-4 py-10">
    @include('docs.partials.breadcrumb', ['crumbs' => array_values(array_filter([
        [$system->name, $section ? $docs->system($system) : null],
        $section ? [$section->title, null] : null,
    ]))])
    <h1 class="mt-3 text-3xl font-bold text-gray-900">{{ $section?->title ?? $system->name }}</h1>
    @if (!$section && $system->summary)
        <p class="mt-2 text-gray-600">{{ $system->summary }}</p>
    @endif
    <div class="mt-6 bg-white rounded-lg border border-gray-200 shadow-sm p-6">
        @include('docs.partials.locked-panel', ['system' => $system])
    </div>
</div>
@endsection
