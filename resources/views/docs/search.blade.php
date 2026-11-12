@extends('docs.layout', ['title' => $term !== '' ? "Search: {$term}" : 'Search', 'noindex' => true])

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-900">Search{{ $scope ? ' the ' . $scope->name . ' manual' : '' }}</h1>

    <form action="{{ $docs->url('docs/search') }}" method="get" role="search" class="mt-5">
        @if ($scope)
            <input type="hidden" name="system" value="{{ $scope->slug }}">
        @endif
        <label for="search-page-q" class="sr-only">Search</label>
        <div class="flex bg-white rounded-md border border-gray-300 overflow-hidden focus-within:ring-2 focus-within:ring-(--docs-accent)">
            <input id="search-page-q" type="search" name="q" value="{{ $term }}" autofocus placeholder="Search documents"
                   class="flex-1 px-4 py-3 focus:outline-none">
            <button type="submit" class="px-5 docs-accent-bg text-white font-semibold">Search</button>
        </div>
    </form>
    @if ($scope)
        <p class="mt-2 text-sm text-gray-500">Searching in {{ $scope->name }} only. <a href="{{ $docs->url('docs/search') }}?q={{ urlencode($term) }}" class="docs-accent underline">Search all manuals</a></p>
    @endif

    <div class="mt-8">
        @if ($term === '')
            <p class="text-gray-600">Type at least two characters to search.</p>
        @elseif (mb_strlen($term) < 2)
            <p class="text-gray-600">Type at least two characters to search.</p>
        @elseif ($results->isEmpty())
            <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
                <p class="text-gray-800 font-semibold">No results for “{{ $term }}”.</p>
                <p class="mt-1 text-sm text-gray-600">Try different words. Restricted manuals are only searched once you have access to them.</p>
            </div>
        @else
            <p class="text-sm text-gray-500 mb-4">{{ $results->count() }}{{ $results->count() >= 50 ? '+' : '' }} {{ Str::plural('result', $results->count()) }} for “{{ $term }}”</p>
            <ul class="space-y-3">
                @foreach ($results as $result)
                    <li class="bg-white rounded-lg border border-gray-200 p-5 hover:shadow-sm">
                        <div class="text-xs text-gray-500">{{ $result['system']->name }}</div>
                        <a href="{{ $docs->section($result['system'], $result['section']) }}" class="mt-0.5 block text-lg font-semibold docs-accent hover:underline">
                            {{ trim(($result['section']->number ? $result['section']->number . ' ' : '') . $result['section']->title) }}
                        </a>
                        @if ($result['excerpt'])
                            @php
                                $excerpt = e($result['excerpt']);
                                $highlighted = preg_replace('/' . preg_quote(e($term), '/') . '/iu', '<mark class="bg-yellow-100 rounded px-0.5">$0</mark>', $excerpt);
                            @endphp
                            <p class="mt-1 text-sm text-gray-600">{!! $highlighted ?? $excerpt !!}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
