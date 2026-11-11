<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $system->name }} manual</title>
    <style>
        @page { margin: 60px 55px 70px 55px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; line-height: 1.5; color: #1f2937; }
        .accent { color: {{ $accent }}; }

        /* Cover */
        .cover { text-align: center; page-break-after: always; }
        .cover .brand { margin-top: 10px; }
        .cover .brand img { max-width: 100%; max-height: 110px; }
        .cover .company { font-size: 14pt; font-weight: bold; }
        .cover h1 { font-size: 26pt; margin: 150px 0 6px 0; }
        .cover .kind { font-size: 11pt; letter-spacing: 2px; text-transform: uppercase; color: #6b7280; }
        .cover .summary { font-size: 11pt; color: #4b5563; margin: 18px 60px 0 60px; }
        .cover .facts { margin: 90px auto 0 auto; border-collapse: collapse; font-size: 9.5pt; }
        .cover .facts td { padding: 4px 10px; text-align: left; }
        .cover .facts td.label { color: #6b7280; text-align: right; }
        .cover .extract { margin-top: 30px; font-size: 9.5pt; color: #92400e; }

        /* Contents */
        .toc { page-break-after: always; }
        .toc h2 { font-size: 16pt; margin: 0 0 14px 0; }
        .toc table { width: 100%; border-collapse: collapse; }
        .toc td { padding: 3px 0; border-bottom: 1px dotted #d1d5db; vertical-align: bottom; }
        .toc td.num { width: 55px; color: #6b7280; }
        .toc td.page { width: 40px; text-align: right; }
        .toc tr.level-0 td { font-weight: bold; padding-top: 9px; }
        .toc tr.level-1 td.title { padding-left: 14px; }
        .toc tr.level-2 td.title { padding-left: 28px; }
        .toc tr.level-3 td.title { padding-left: 42px; }
        .toc .pdf-tag { font-size: 7pt; color: #1d4ed8; font-weight: normal; }

        /* Sections */
        .chapter-break { page-break-before: always; }
        .section { margin-bottom: 14px; }
        .section h1 { font-size: 17pt; margin: 0 0 10px 0; padding-bottom: 6px; border-bottom: 2px solid {{ $accent }}; }
        .section h2.heading { font-size: 13.5pt; margin: 16px 0 8px 0; }
        .section h3.heading { font-size: 11.5pt; margin: 14px 0 6px 0; }
        .section .num { color: #9ca3af; margin-right: 6px; }

        /* Written content */
        .kb h1, .kb h2, .kb h3, .kb h4 { font-size: 11pt; margin: 12px 0 4px 0; }
        .kb p { margin: 0 0 7px 0; }
        .kb ul, .kb ol { margin: 0 0 8px 0; padding-left: 20px; }
        .kb li { margin-bottom: 2px; }
        .kb img { max-width: 100%; margin: 6px 0; border: 1px solid #e5e7eb; }
        .kb table { width: 100%; border-collapse: collapse; margin: 6px 0 10px 0; }
        .kb th, .kb td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; vertical-align: top; }
        .kb th { background: #f3f4f6; }
        .kb blockquote { margin: 8px 0; padding: 6px 10px; border-left: 3px solid {{ $accent }}; background: #f0fdfa; }
        .kb pre { background: #f3f4f6; padding: 8px; font-size: 8.5pt; white-space: pre-wrap; }
        .kb code { font-size: 9pt; background: #f3f4f6; }
        .kb a { color: {{ $accent }}; }
        .kb .link-url { font-size: 8pt; color: #6b7280; }
        .empty { color: #9ca3af; font-style: italic; }

        /* Uploaded PDF title page */
        /* The uploaded pages are inserted right after this page; whatever follows starts a new page. */
        .pdf-slot { page-break-before: always; text-align: center; padding-top: 220px; }
        .pdf-slot h1 { border: none; font-size: 20pt; }
        .pdf-slot .num { color: #9ca3af; margin-right: 8px; }
        .pdf-slot .note { color: #6b7280; font-size: 10pt; margin-top: 10px; }

        /* Appendices */
        .appendix { page-break-before: always; }
        .appendix h1 { font-size: 16pt; margin: 0 0 12px 0; padding-bottom: 6px; border-bottom: 2px solid {{ $accent }}; }
        .appendix h2 { font-size: 11.5pt; margin: 14px 0 6px 0; }
        .appendix table { width: 100%; border-collapse: collapse; font-size: 9pt; }
        .appendix th, .appendix td { border-bottom: 1px solid #e5e7eb; padding: 4px 6px; text-align: left; vertical-align: top; }
        .appendix th { color: #6b7280; font-weight: normal; }
        .pre-line { white-space: pre-line; }
    </style>
</head>
<body>

{{-- Cover --}}
<div class="cover">
    <div class="brand">
        @if ($headerImage)
            <img src="{{ $headerImage }}" alt="">
        @elseif ($companyName)
            <div class="company accent">{{ $companyName }}</div>
        @endif
    </div>

    <h1 class="accent">{{ $system->name }}</h1>
    <div class="kind">{{ $isExtract ? 'Manual extract' : 'User manual' }}</div>
    @if ($system->summary)
        <div class="summary">{{ $system->summary }}</div>
    @endif

    <table class="facts">
        <tr><td class="label">Manual version</td><td>{{ $system->manual_version }}</td></tr>
        <tr><td class="label">Generated</td><td>{{ $generatedOn }}</td></tr>
        @if ($owners->isNotEmpty())
            <tr><td class="label">Owner{{ $owners->count() > 1 ? 's' : '' }}</td><td>{{ $owners->join(', ') }}</td></tr>
        @endif
        @if ($system->url)
            <tr><td class="label">System link</td><td>{{ $system->url }}</td></tr>
        @endif
    </table>

    @if ($isExtract)
        <div class="extract">This document contains selected sections only. Section numbers match the full manual.</div>
    @endif
</div>

{{-- Contents --}}
<div class="toc">
    <h2 class="accent">Contents</h2>
    <table>
        @foreach ($sections as $s)
            <tr class="level-{{ min($s['level'], 3) }}">
                <td class="num">{{ $s['number'] }}</td>
                <td class="title">
                    {{ $s['title'] }}
                    @if ($s['kind'] === 'uploaded_pdf')<span class="pdf-tag"> PDF</span>@endif
                </td>
                <td class="page">{{ $tocPages[$s['id']] ?? '00' }}</td>
            </tr>
        @endforeach
        @if ($contacts)
            <tr class="level-0"><td class="num"></td><td class="title">Appendix: Contacts &amp; escalation</td><td class="page">{{ $tocPages['app-contacts'] ?? '00' }}</td></tr>
        @endif
        @if ($changelog)
            <tr class="level-0"><td class="num"></td><td class="title">Appendix: Change log</td><td class="page">{{ $tocPages['app-changelog'] ?? '00' }}</td></tr>
        @endif
    </table>
</div>

{{-- Sections --}}
@php $afterPdf = false; @endphp
@foreach ($sections as $index => $s)
    @if ($s['kind'] === 'uploaded_pdf')
        <div class="pdf-slot" id="sec-{{ $s['id'] }}">
            <h1 class="accent"><span class="num">{{ $s['number'] }} </span>{{ $s['title'] }}</h1>
            @if ($s['pdf'])
                <div class="note">
                    The next {{ $s['pdf']['count'] }} page{{ $s['pdf']['count'] === 1 ? '' : 's' }}
                    {{ $s['pdf']['count'] === 1 ? 'is' : 'are' }} from the uploaded document "{{ $s['pdf']['name'] }}".
                </div>
            @else
                <div class="note">
                    This part of the manual is a separate document{{ $s['pdfName'] ? ' ("' . $s['pdfName'] . '")' : '' }}.
                    Open it from this manual in WorkTrack Pro.
                </div>
            @endif
        </div>
        @php $afterPdf = true; @endphp
    @else
        <div class="section {{ ($s['level'] === 0 && $index > 0) || $afterPdf ? 'chapter-break' : '' }}" id="sec-{{ $s['id'] }}">
        @php $afterPdf = false; @endphp
            @if ($s['level'] === 0)
                <h1><span class="num">{{ $s['number'] }} </span>{{ $s['title'] }}</h1>
            @elseif ($s['level'] === 1)
                <h2 class="heading"><span class="num">{{ $s['number'] }} </span>{{ $s['title'] }}</h2>
            @else
                <h3 class="heading"><span class="num">{{ $s['number'] }} </span>{{ $s['title'] }}</h3>
            @endif

            @if (trim(strip_tags($s['body'] ?? '', '<img>')) !== '')
                {{-- Stored content is sanitised when it is saved --}}
                <div class="kb">{!! $s['body'] !!}</div>
            @endif
        </div>
    @endif
@endforeach

{{-- Appendices --}}
@if ($contacts)
    <div class="appendix" id="app-contacts">
        <h1 class="accent">Contacts &amp; escalation</h1>

        @if ($contacts['people']->isNotEmpty())
            <table>
                <tr><th>Name</th><th>Role</th><th>Email</th></tr>
                @foreach ($contacts['people'] as $person)
                    <tr><td>{{ $person['name'] }}</td><td>{{ $person['role'] }}</td><td>{{ $person['email'] }}</td></tr>
                @endforeach
            </table>
        @endif

        <h2>How to get help</h2>
        <div class="pre-line">{{ $contacts['support'] ?: 'Not recorded.' }}</div>

        @if ($contacts['escalation'])
            <h2>Escalation</h2>
            <div class="pre-line">{{ $contacts['escalation'] }}</div>
        @endif
    </div>
@endif

@if ($changelog)
    <div class="appendix" id="app-changelog">
        <h1 class="accent">Change log</h1>
        @if ($changelog->isEmpty())
            <p class="empty">No changes recorded.</p>
        @else
            <table>
                <tr><th>Date</th><th>Version</th><th>Section</th><th>Change</th><th>By</th></tr>
                @foreach ($changelog as $entry)
                    <tr>
                        <td>{{ $entry['date'] }}</td>
                        <td>{{ $entry['version'] }}</td>
                        <td>{{ $entry['section'] }}</td>
                        <td>{{ $entry['summary'] ?: '—' }}</td>
                        <td>{{ $entry['author'] }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    </div>
@endif

</body>
</html>
