<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Advisory Note</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.5pt;
            color: #1e293b;
            background: #ffffff;
        }

        /* ── Cover ── */
        .cover {
            background-color: #0f3460;
            padding: 32px 36px 24px 36px;
        }
        .cover-eyebrow {
            font-size: 7pt;
            color: #93c5fd;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .cover-title {
            font-size: 17pt;
            font-weight: bold;
            color: #ffffff;
            line-height: 1.3;
            margin-bottom: 6px;
        }
        .cover-org {
            font-size: 9pt;
            color: #bfdbfe;
        }

        /* ── Info strip ── */
        .info-strip {
            background-color: #f1f5f9;
            border-bottom: 2px solid #0f3460;
            padding: 0;
        }
        .info-strip table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-strip td {
            padding: 8px 14px;
            font-size: 8pt;
            color: #475569;
            border-right: 1px solid #e2e8f0;
        }
        .info-strip td:last-child { border-right: none; }
        .info-strip td strong { color: #0f172a; display: block; margin-bottom: 1px; }

        /* ── Status pill (table-based) ── */
        .pill {
            font-size: 7.5pt;
            font-weight: bold;
            padding: 2px 8px;
            border-radius: 8px;
        }
        .pill-blue     { background-color: #dbeafe; color: #1d4ed8; }
        .pill-green    { background-color: #dcfce7; color: #15803d; }
        .pill-yellow   { background-color: #fef9c3; color: #854d0e; }

        /* ── Page body ── */
        .page-body { padding: 24px 36px; }

        /* ── Meta table ── */
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }
        .meta-table td {
            padding: 8px 12px;
            font-size: 8.5pt;
            vertical-align: top;
            border-bottom: 1px solid #e2e8f0;
            width: 50%;
        }
        .meta-table tr:last-child td { border-bottom: none; }
        .meta-table .field-label {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            display: block;
            margin-bottom: 3px;
        }
        .meta-table .field-value { color: #0f172a; }

        /* ── Section ── */
        .section { margin-bottom: 22px; }

        .section-heading {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            border-bottom: 2px solid #0f3460;
            padding-bottom: 0;
        }
        .section-heading td { padding-bottom: 6px; vertical-align: middle; }
        .section-badge {
            width: 24px;
            height: 24px;
            background-color: #0f3460;
            color: #ffffff;
            font-size: 10pt;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            padding: 2px 0;
        }
        .section-label {
            font-size: 10.5pt;
            font-weight: bold;
            color: #0f3460;
            padding-left: 8px;
        }

        .section-text {
            font-size: 9pt;
            color: #334155;
            line-height: 1.7;
            white-space: pre-wrap;
            border-left: 3px solid #cbd5e1;
            padding: 10px 14px;
            background-color: #f8fafc;
        }

        /* ── Rec cards ── */
        .rec-card {
            border: 1px solid #e2e8f0;
            padding: 10px 14px;
            margin-bottom: 8px;
            background-color: #ffffff;
            page-break-inside: avoid;
        }
        .rec-card-top {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .rec-card-top td { vertical-align: middle; padding: 0; }
        .rec-org-name {
            font-size: 9.5pt;
            font-weight: bold;
            color: #0f172a;
        }
        .rec-type-cell {
            text-align: right;
            white-space: nowrap;
        }
        .rec-type-label {
            font-size: 7.5pt;
            color: #475569;
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 2px 8px;
        }
        .rec-linked {
            font-size: 8pt;
            color: #64748b;
            font-style: italic;
            margin-bottom: 5px;
        }
        .rec-notes {
            font-size: 8.5pt;
            color: #334155;
            line-height: 1.6;
        }

        .no-data {
            font-size: 8.5pt;
            color: #94a3b8;
            font-style: italic;
        }

        /* ── Footer ── */
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 7.5pt;
            color: #94a3b8;
        }
    </style>
</head>
<body>

{{-- ── Cover ── --}}
<div class="cover">
    <div class="cover-eyebrow">NEP Programme System &mdash; Confidential Advisory Note</div>
    <div class="cover-title">{{ $note->document_name }}</div>
    <div class="cover-org">Submitting Organisation: <strong style="color:#fff;">{{ $note->submitting_party }}</strong></div>
</div>

{{-- ── Info Strip ── --}}
@php
    $pillClass = match($note->status) {
        'advice_delivered' => 'pill-green',
        'analysed'         => 'pill-yellow',
        default            => 'pill-blue',
    };
    $statusLabel = ucwords(str_replace('_', ' ', $note->status));
@endphp
<div class="info-strip">
    <table>
        <tr>
            <td>
                <strong>Status</strong>
                <span class="pill {{ $pillClass }}">{{ $statusLabel }}</span>
            </td>
            <td>
                <strong>Scope</strong>
                {{ ucfirst($note->analysis_scope ?? 'Full Map') }}
            </td>
            <td>
                <strong>Coordinator</strong>
                {{ $note->coordinator->name ?? 'Unassigned' }}
            </td>
            <td>
                <strong>Generated</strong>
                {{ $generatedAt }}
            </td>
        </tr>
    </table>
</div>

<div class="page-body">

    {{-- ── Meta ── --}}
    <table class="meta-table">
        <tr>
            <td>
                <span class="field-label">Submitting Organisation</span>
                <span class="field-value">{{ $note->submitting_party }}</span>
            </td>
            <td>
                <span class="field-label">Submitted At</span>
                <span class="field-value">{{ $note->submitted_at?->format('d M Y, H:i') ?? 'N/A' }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="field-label">Source Programme</span>
                <span class="field-value">
                    @if($note->programmeEntry)
                        #{{ $note->programmeEntry->id }} &mdash; {{ $note->programmeEntry->programme_name }}
                        ({{ $note->programmeEntry->organisation->name ?? 'Unknown' }})
                    @else
                        N/A
                    @endif
                </span>
            </td>
            <td>
                <span class="field-label">Delivered At</span>
                <span class="field-value">{{ $note->delivered_at?->format('d M Y, H:i') ?? 'Not yet delivered' }}</span>
            </td>
        </tr>
        @if($note->analysis_scope_detail)
        <tr>
            <td colspan="2">
                <span class="field-label">Scope Detail</span>
                <span class="field-value">{{ $note->analysis_scope_detail }}</span>
            </td>
        </tr>
        @endif
    </table>

    {{-- ── Section A ── --}}
    @if($note->section_profile)
    <div class="section">
        <table class="section-heading"><tr>
            <td class="section-badge">A</td>
            <td class="section-label">Programme Profile as Interpreted</td>
        </tr></table>
        <div class="section-text">{{ $note->section_profile }}</div>
    </div>
    @endif

    {{-- ── Section B ── --}}
    <div class="section">
        <table class="section-heading"><tr>
            <td class="section-badge">B</td>
            <td class="section-label">Coordination Recommendations</td>
        </tr></table>

        @if($note->recommendations->isNotEmpty())
            @foreach($note->recommendations as $i => $rec)
            @php $linkedName = $rec->programme_name ?? $rec->programmeEntry?->programme_name; @endphp
            <div class="rec-card">
                <table class="rec-card-top"><tr>
                    <td>
                        <span class="rec-org-name">
                            {{ $i + 1 }}. {{ $rec->organisation_name ?? $rec->programmeEntry?->organisation?->name ?? 'Unknown Organisation' }}
                        </span>
                    </td>
                    <td class="rec-type-cell">
                        <span class="rec-type-label">{{ $rec->type }}</span>
                    </td>
                </tr></table>
                @if($linkedName)
                <div class="rec-linked">Linked: {{ $linkedName }}</div>
                @endif
                @if($rec->relational)
                <div class="rec-notes">{{ $rec->relational }}</div>
                @endif
            </div>
            @endforeach
        @else
            <p class="no-data">No overlapping programmes identified.</p>
        @endif
    </div>

    {{-- ── Section C ── --}}
    @if($note->section_gaps)
    <div class="section">
        <table class="section-heading"><tr>
            <td class="section-badge">C</td>
            <td class="section-label">Gaps &amp; Coverage Analysis</td>
        </tr></table>
        <div class="section-text">{{ $note->section_gaps }}</div>
    </div>
    @endif

    {{-- ── Section D ── --}}
    @if($note->section_coordinators_notes)
    <div class="section">
        <table class="section-heading"><tr>
            <td class="section-badge">D</td>
            <td class="section-label">Coordinator Notes</td>
        </tr></table>
        <div class="section-text">{{ $note->section_coordinators_notes }}</div>
    </div>
    @endif

    <div class="footer">
        <strong>NEP Programme System</strong> &mdash; Confidential &mdash; For internal use only<br>
        Generated on {{ $generatedAt }}
    </div>

</div>
</body>
</html>
