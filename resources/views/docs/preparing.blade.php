@extends('docs.layout', ['title' => 'Preparing PDF | ' . $system->name, 'noindex' => true])

@section('content')
@unless ($failed)
    @push('head')<meta http-equiv="refresh" content="4">@endpush
@endunless
<div class="max-w-2xl mx-auto px-4 py-16 text-center">
    @if ($failed)
        <h1 class="text-2xl font-bold text-gray-900">The PDF could not be created</h1>
        <p class="mt-3 text-gray-600">Something went wrong while building the {{ $system->name }} manual. Please try again later.</p>
        <a href="{{ $docs->system($system, 'pdf') }}" class="mt-6 inline-block px-5 py-2.5 rounded docs-accent-bg text-white font-semibold">Try again</a>
    @else
        <svg class="mx-auto w-10 h-10 animate-spin docs-accent" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
        <h1 class="mt-5 text-2xl font-bold text-gray-900">Preparing the PDF…</h1>
        <p class="mt-3 text-gray-600">The {{ $system->name }} manual is being put together. The download starts automatically when it is ready.</p>
        <a href="{{ $docs->system($system, 'pdf') }}" class="mt-6 inline-block text-sm docs-accent underline">Check again</a>
    @endif
    <p class="mt-8"><a href="{{ $docs->system($system) }}" class="text-sm text-gray-600 hover:underline">&larr; Back to {{ $system->name }}</a></p>
</div>
@endsection
