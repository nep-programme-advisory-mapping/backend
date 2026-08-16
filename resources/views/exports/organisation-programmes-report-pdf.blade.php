<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $organisation->name }} — All Programmes PDF Report</title>
    <style>
        @page {
            margin: 30px 35px 45px 35px;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #1e293b;
            font-size: 10px;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }

        /* Page Layout & Breaks */
        .page-break {
            page-break-before: always;
        }

        /* Header Banner */
        .page-header {
            border-bottom: 2.5px solid #0F5A4D;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }
        .logo-title {
            font-size: 18px;
            font-weight: 800;
            color: #0F5A4D;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .logo-subtitle {
            font-size: 9.5px;
            color: #64748b;
            font-weight: 500;
        }
        .report-main-title {
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 8px;
        }

        /* Footer */
        .footer {
            position: fixed;
            bottom: -25px;
            left: 0;
            right: 0;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
            font-size: 8.5px;
            color: #94a3b8;
            text-align: center;
        }

        /* Utility Grids */
        table.w-full {
            width: 100%;
            border-collapse: collapse;
        }
        td.valign-top {
            vertical-align: top;
        }
        .text-right {
            text-align: right;
        }

        /* KPI Banner Box */
        .kpi-card {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 15px;
        }
        .kpi-title {
            font-size: 10px;
            font-weight: 700;
            color: #0F5A4D;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .stat-label {
            font-size: 8.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
        }
        .stat-val {
            font-size: 12px;
            font-weight: 800;
            color: #0f172a;
        }

        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 12px;
        }
        .data-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            text-align: left;
        }
        .data-table td {
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            font-size: 9.5px;
            vertical-align: top;
        }
        .data-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        /* Section Block */
        .section {
            margin-bottom: 14px;
        }
        .section-header {
            font-size: 10.5px;
            font-weight: 700;
            color: #0F5A4D;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            border-bottom: 1.5px solid #0F5A4D;
            padding-bottom: 3px;
            margin-bottom: 8px;
        }

        /* Badges & Pills */
        .badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 8.5px;
            font-weight: 700;
            border-radius: 12px;
            text-transform: uppercase;
        }
        .badge-verified {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .badge-unverified {
            background-color: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }
        .pill-core {
            background-color: #ccfbf1;
            color: #0f766e;
            font-weight: 700;
            font-size: 8.5px;
            padding: 1px 5px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .pill-supporting {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 600;
            font-size: 8.5px;
            padding: 1px 5px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .code-box {
            font-family: 'Courier', monospace;
            font-weight: 700;
            color: #92400e;
            background-color: #fef3c7;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 9px;
            margin-right: 3px;
        }
        .keyword-chip {
            display: inline-block;
            background-color: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 600;
            margin-right: 4px;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>

    <!-- ================= PAGE 1: COVER & EXECUTIVE SUMMARY ================= -->
    <div class="page-header">
        <table class="w-full">
            <tr>
                <td class="valign-top">
                    <div class="logo-title">NEP CAMBODIA</div>
                    <div class="logo-subtitle">NGO Education Partnership — Consolidated Organisation Summary Sheet</div>
                </td>
                <td class="valign-top text-right">
                    <div style="font-size: 11px; font-weight: 800; color: #0F5A4D;">{{ $organisation->name }}</div>
                    <div style="font-size: 9px; color: #64748b; margin-top: 3px;">
                        Report Date: {{ now()->format('d M Y') }}
                    </div>
                </td>
            </tr>
        </table>
        <div class="report-main-title">Consolidated Organisation Programmes Report</div>
    </div>

    <!-- KPI Box -->
    <div class="kpi-card">
        <div class="kpi-title">Executive Summary</div>
        <table class="w-full">
            <tr>
                <td style="width: 33%;" class="valign-top">
                    <div class="stat-label">Organisation Name</div>
                    <div class="stat-val" style="font-size: 11px;">{{ $organisation->name }}</div>
                </td>
                <td style="width: 33%;" class="valign-top">
                    <div class="stat-label">Total Submitted Programmes</div>
                    <div class="stat-val" style="color: #0F5A4D;">{{ count($entries) }} Programmes</div>
                </td>
                <td style="width: 34%;" class="valign-top">
                    <div class="stat-label">Total Staffing (FTE)</div>
                    <div class="stat-val">{{ number_format($entries->sum('fte_staff'), 1) }} FTE</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Index Table -->
    <div style="font-size: 11px; font-weight: 700; color: #0F5A4D; margin-bottom: 4px; text-transform: uppercase;">
        Programmes Index
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">#</th>
                <th style="width: 28%;">Programme Name</th>
                <th style="width: 14%;">Period</th>
                <th style="width: 16%;">Budget Band</th>
                <th style="width: 25%;">Covered Locations</th>
                <th style="width: 12%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($entries as $index => $entry)
                <tr>
                    <td style="text-align: center; font-weight: 700; color: #0F5A4D;">{{ $index + 1 }}</td>
                    <td><strong>{{ $entry->programme_name }}</strong></td>
                    <td>{{ $entry->start_year ?? 'N/A' }} – {{ $entry->ongoing ? 'Ongoing' : ($entry->end_year ?? 'N/A') }}</td>
                    <td>{{ $entry->budgetBand->band_name ?? 'Not specified' }}</td>
                    <td>
                        @php
                            $locs = $entry->locations->map(function($loc) {
                                $prov = $loc->province->province_name ?? $loc->province_name ?? $loc->country;
                                $dist = $loc->district->name ?? $loc->district->district_name ?? null;
                                if ($prov && $dist) {
                                    return "$prov ($dist)";
                                }
                                return $prov;
                            })->unique()->filter()->values();
                        @endphp
                        {{ $locs->count() ? $locs->join(', ') : 'Not specified' }}
                    </td>
                    <td>
                        <span class="badge {{ $entry->is_unverified ? 'badge-unverified' : 'badge-verified' }}">
                            {{ $entry->is_unverified ? 'Unverified' : 'Verified' }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #64748b; padding: 14px;">
                        No submitted programmes found for this organisation.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Confidential — NGO Education Partnership (NEP) System • Consolidated Report for {{ $organisation->name }}
    </div>


    <!-- ================= PAGES 2..N: 1 PAGE PER DETAILED PROGRAMME ================= -->
    @foreach($entries as $index => $entry)
        <div class="page-break"></div>

        <div class="page-header">
            <table class="w-full">
                <tr>
                    <td class="valign-top">
                        <div class="logo-title">NEP CAMBODIA</div>
                        <div class="logo-subtitle">
                            Programme Entry Report (Programme {{ $index + 1 }} of {{ count($entries) }})
                        </div>
                    </td>
                    <td class="valign-top text-right">
                        <span class="badge {{ $entry->is_unverified ? 'badge-unverified' : 'badge-verified' }}">
                            {{ $entry->is_unverified ? 'Unverified' : 'Verified' }}
                        </span>
                        <div style="font-size: 9px; color: #64748b; margin-top: 3px;">
                            Date: {{ now()->format('d M Y') }}
                        </div>
                    </td>
                </tr>
            </table>
            <div class="report-main-title">{{ $entry->programme_name }}</div>
            <div style="font-size: 11px; color: #475569; font-weight: 600;">
                Organisation: {{ $organisation->name }}
            </div>
        </div>

        <!-- Section 1: Overview & Budget -->
        <div class="section">
            <div class="section-header">1. Programme Overview & Budget</div>
            <div class="kpi-card" style="margin-bottom: 0;">
                <table class="w-full">
                    <tr>
                        <td style="width: 33%;" class="valign-top">
                            <div class="stat-label">Implementation Period</div>
                            <div class="stat-val" style="font-size: 11px;">
                                {{ $entry->start_year ?? 'N/A' }} – {{ $entry->ongoing ? 'Ongoing' : ($entry->end_year ?? 'N/A') }}
                            </div>
                        </td>
                        <td style="width: 33%;" class="valign-top">
                            <div class="stat-label">Annual Budget</div>
                            <div class="stat-val" style="font-size: 11px;">
                                @if(!empty($entry->annual_budget_usd))
                                    ${{ number_format($entry->annual_budget_usd) }} USD
                                @elseif(!empty($entry->budgetBand->band_name))
                                    {{ $entry->budgetBand->band_name }}
                                @else
                                    Not specified
                                @endif
                            </div>
                        </td>
                        <td style="width: 34%;" class="valign-top">
                            <div class="stat-label">Staffing (FTE)</div>
                            <div class="stat-val" style="font-size: 11px;">
                                {{ $entry->fte_staff ? number_format((float)$entry->fte_staff, 1) . ' FTE' : 'N/A' }}
                            </div>
                        </td>
                    </tr>
                    @if($entry->direct_beneficiaries || $entry->indirect_beneficiaries)
                    <tr>
                        <td colspan="3" style="padding-top: 8px;">
                            <div class="stat-label">Beneficiaries</div>
                            <div style="font-size: 10px; font-weight: 600; color: #0f172a;">
                                Direct: {{ number_format($entry->direct_beneficiaries ?? 0) }} · Indirect: {{ number_format($entry->indirect_beneficiaries ?? 0) }}
                            </div>
                        </td>
                    </tr>
                    @endif
                    @if(!empty($entry->method) || !empty($entry->description))
                    <tr>
                        <td colspan="3" style="padding-top: 8px;">
                            <div class="stat-label">Description / Summary</div>
                            <div style="font-size: 9.5px; color: #334155; line-height: 1.4;">
                                {{ $entry->method ?? $entry->description }}
                            </div>
                        </td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>

        <!-- Section 2: Programme Activities -->
        <div class="section">
            <div class="section-header">2. Programme Activities & Taxonomy</div>
            @if($entry->activities && count($entry->activities) > 0)
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 12%;">Role</th>
                            <th style="width: 28%;">Category</th>
                            <th style="width: 38%;">Activity Item Title</th>
                            <th style="width: 22%;">Education Levels</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entry->activities as $act)
                            <tr>
                                <td>
                                    <span class="{{ $act->is_primary ? 'pill-core' : 'pill-supporting' }}">
                                        {{ $act->is_primary ? 'Core' : 'Supporting' }}
                                    </span>
                                </td>
                                <td>
                                    {{ $act->activityItem->subcategory->category->label ?? $act->activityItem->subcategory->category->code ?? 'N/A' }}
                                </td>
                                <td>
                                    @if(!empty($act->activityItem->code))
                                        <span class="code-box">{{ $act->activityItem->code }}</span>
                                    @endif
                                    <strong>{{ $act->activityItem->label ?? $act->activityItem->name ?? 'N/A' }}</strong>
                                    @if(!empty($act->other_text))
                                        <div style="font-size: 8.5px; color: #64748b; margin-top: 2px;">Note: {{ $act->other_text }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($act->activityLevels && count($act->activityLevels) > 0)
                                        {{ $act->activityLevels->map(fn($l) => $l->educationLevel->level_name ?? null)->filter()->join(', ') }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="kpi-card" style="color: #64748b; font-size: 9.5px;">No activities registered for this programme entry.</div>
            @endif
        </div>

        <!-- Section 3: Geographic Coverage -->
        <div class="section">
            <div class="section-header">3. Geographic Coverage</div>
            <div class="kpi-card" style="margin-bottom: 0;">
                @if($entry->locations && count($entry->locations) > 0)
                    <div class="stat-label">Covered Locations & Districts</div>
                    <div style="font-size: 10px; font-weight: 600; color: #0f172a;">
                        @php
                            $locFormatted = $entry->locations->map(function($loc) {
                                $prov = $loc->province->province_name ?? $loc->province_name ?? $loc->country;
                                $dist = $loc->district->name ?? $loc->district->district_name ?? null;
                                if ($prov && $dist) {
                                    return "$prov ($dist)";
                                }
                                return $prov;
                            })->unique()->filter()->values();
                        @endphp
                        {{ $locFormatted->count() ? $locFormatted->join(', ') : 'Not specified' }}
                    </div>
                @else
                    <div style="color: #64748b; font-size: 9.5px;">Geographic coverage not specified.</div>
                @endif
            </div>
        </div>

        <!-- Section 4: Government Agreements -->
        @if($entry->governmentAgreements && count($entry->governmentAgreements) > 0)
        <div class="section">
            <div class="section-header">4. Government Agreements & Counterparts</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 35%;">Agreement / Counterpart</th>
                        <th style="width: 30%;">Nature / Status</th>
                        <th style="width: 35%;">Institution / Entity</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($entry->governmentAgreements as $agr)
                        <tr>
                            <td><strong>{{ $agr->counterpart_agency ?? $agr->counterpart ?? '—' }}</strong></td>
                            <td>{{ $agr->nature ?? $agr->status ?? '—' }}</td>
                            <td>{{ $agr->institution_name ?? $agr->institution ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- Section 5: Keywords -->
        @if($entry->keywords && count($entry->keywords) > 0)
        <div class="section">
            <div class="section-header">5. Keywords & Focus Areas</div>
            <div style="margin-top: 4px;">
                @foreach($entry->keywords as $kw)
                    <span class="keyword-chip">{{ is_string($kw) ? $kw : ($kw->keyword ?? $kw->name ?? '') }}</span>
                @endforeach
            </div>
        </div>
        @endif

        <div class="footer">
            Confidential — NGO Education Partnership (NEP) System • All Programmes Export for {{ $organisation->name }}
        </div>
    @endforeach

</body>
</html>
