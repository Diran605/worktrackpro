{{-- Contents tree (recursive). Expects: $nodes, $system, $currentId (nullable), $ancestorIds (array) --}}
<ul class="{{ ($depth ?? 0) === 0 ? 'space-y-0.5' : 'ml-3 pl-2 border-l border-gray-200 space-y-0.5' }}">
    @foreach ($nodes as $node)
        @php
            $s = $node['section'];
            $isCurrent = $s->id === ($currentId ?? null);
            $label = trim(($s->number ? $s->number . ' ' : '') . $s->title);
        @endphp
        <li>
            @if (!empty($node['children']))
                <details @if ($isCurrent || isset($ancestorIds[$s->id])) open @endif>
                    <summary class="flex items-start gap-1 py-1 pr-1 rounded hover:bg-gray-100">
                        <svg class="chev w-3.5 h-3.5 mt-1 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                        @include('docs.partials.tree-link')
                    </summary>
                    @include('docs.partials.tree', ['nodes' => $node['children'], 'depth' => ($depth ?? 0) + 1])
                </details>
            @else
                <div class="flex items-start gap-1 py-1 pr-1 pl-[1.125rem] rounded hover:bg-gray-100">
                    @include('docs.partials.tree-link')
                </div>
            @endif
        </li>
    @endforeach
</ul>
