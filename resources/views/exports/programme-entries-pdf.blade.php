<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programme Entries Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            padding-bottom: 15px;
            border-bottom: 3px solid #2c3e50;
            margin-bottom: 20px;
        }
        
        .header h1 {
            font-size: 18pt;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .header .meta {
            font-size: 9pt;
            color: #666;
        }
        
        .summary {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }
        
        .summary h2 {
            font-size: 12pt;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .entry {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            page-break-inside: avoid;
        }
        
        .entry-header {
            background-color: #2c3e50;
            color: white;
            padding: 8px 12px;
            margin: -15px -15px 12px -15px;
            border-radius: 4px 4px 0 0;
        }
        
        .entry-header h3 {
            font-size: 11pt;
            margin: 0;
        }
        
        .entry-section {
            margin-bottom: 10px;
        }
        
        .entry-section h4 {
            font-size: 9pt;
            color: #3498db;
            text-transform: uppercase;
            margin-bottom: 4px;
            border-bottom: 1px solid #eee;
            padding-bottom: 2px;
        }
        
        .entry-section p {
            margin: 3px 0;
            font-size: 9pt;
        }
        
        .label {
            font-weight: bold;
            color: #555;
            display: inline-block;
            min-width: 100px;
        }
        
        .value {
            color: #333;
        }
        
        .grid {
            display: inline-block;
            width: 48%;
            vertical-align: top;
            margin-right: 2%;
        }
        
        .grid-full {
            width: 100%;
        }
        
        ul {
            margin: 3px 0;
            padding-left: 20px;
        }
        
        li {
            margin: 2px 0;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 8pt;
            color: #777;
        }
        
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Programme Entries Summary Report</h1>
        <div class="meta">
            Generated: {{ $generatedAt }} | Total Entries: {{ $totalEntries }}
        </div>
    </div>

    @if($totalEntries > 0)
        <div class="summary">
            <h2>Export Summary</h2>
            <p><span class="label">Total Entries:</span> <span class="value">{{ $totalEntries }}</span></p>
            <p><span class="label">Report Date:</span> <span class="value">{{ $generatedAt }}</span></p>
        </div>

        @foreach($entries as $entry)
            <div class="entry">
                <div class="entry-header">
                    <h3>#{{ $entry->id }} - {{ $entry->programme_name }}</h3>
                </div>

                <div class="entry-section">
                    <h4>Basic Information</h4>
                    <div class="grid">
                        <p><span class="label">Organisation:</span> <span class="value">{{ $entry->organisation->name ?? 'N/A' }}</span></p>
                        <p><span class="label">Budget Band:</span> <span class="value">{{ $entry->budgetBand->label ?? 'N/A' }}</span></p>
                        <p><span class="label">Start Year:</span> <span class="value">{{ $entry->start_year }}</span></p>
                        <p><span class="label">End Year:</span> <span class="value">{{ $entry->end_year ?? 'Ongoing' }}</span></p>
                    </div>
                    <div class="grid">
                        <p><span class="label">Ongoing:</span> <span class="value">{{ $entry->ongoing ? 'Yes' : 'No' }}</span></p>
                        <p><span class="label">Method:</span> <span class="value">{{ $entry->method }}</span></p>
                        <p><span class="label">Verified:</span> <span class="value">{{ $entry->verified_date?->format('Y-m-d') ?? 'N/A' }}</span></p>
                        <p><span class="label">Last Updated:</span> <span class="value">{{ $entry->last_updated_at?->format('Y-m-d H:i') ?? 'N/A' }}</span></p>
                    </div>
                </div>

                <div class="entry-section">
                    <h4>Staff & Beneficiaries</h4>
                    <div class="grid">
                        <p><span class="label">FTE Staff:</span> <span class="value">{{ $entry->fte_staff }}</span></p>
                        <p><span class="label">Direct Beneficiaries:</span> <span class="value">{{ $entry->direct_beneficiaries }}</span></p>
                    </div>
                    <div class="grid">
                        <p><span class="label">Indirect Beneficiaries:</span> <span class="value">{{ $entry->indirect_beneficiaries }}</span></p>
                    </div>
                </div>

                @if($entry->keywords->isNotEmpty())
                    <div class="entry-section">
                        <h4>Keywords</h4>
                        <p>{{ $entry->keywords->pluck('keyword')->implode(', ') }}</p>
                    </div>
                @endif

                @php
                    $locations = $entry->locations->map(function ($loc) {
                        $parts = [];
                        if ($loc->province) {
                            $parts[] = $loc->province->province_name;
                        }
                        if ($loc->district) {
                            $parts[] = $loc->district->name;
                        }
                        return implode('/', $parts);
                    })->filter()->unique();
                @endphp

                @if($locations->isNotEmpty())
                    <div class="entry-section">
                        <h4>Locations</h4>
                        <ul>
                            @foreach($locations as $location)
                                <li>{{ $location }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @php
                    $activities = $entry->activities->map(function ($activity) {
                        $taxonomy = [];
                        if ($activity->activityItem && $activity->activityItem->subcategory) {
                            $subcat = $activity->activityItem->subcategory;
                            if ($subcat->category) {
                                $taxonomy[] = $subcat->category->category_name;
                            }
                            $taxonomy[] = $subcat->subcategory_name;
                        }
                        if ($activity->activityItem) {
                            $taxonomy[] = $activity->activityItem->item_name;
                        }
                        return implode(' > ', array_filter($taxonomy));
                    })->filter()->unique();
                @endphp

                @if($activities->isNotEmpty())
                    <div class="entry-section">
                        <h4>Activities (Taxonomy)</h4>
                        <ul>
                            @foreach($activities as $activity)
                                <li>{{ $activity }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @php
                    $educationLevels = $entry->activities->flatMap(function ($activity) {
                        return $activity->activityLevels->map(function ($al) {
                            return $al->educationLevel?->level_name;
                        });
                    })->filter()->unique();
                @endphp

                @if($educationLevels->isNotEmpty())
                    <div class="entry-section">
                        <h4>Education Levels</h4>
                        <p>{{ $educationLevels->implode(', ') }}</p>
                    </div>
                @endif

                @php
                    $inclusionGroups = $entry->activities->pluck('inclusion_group')->filter()->unique();
                    $inclusionTypes = $entry->activities->pluck('inclusion_type')->filter()->unique();
                @endphp

                @if($inclusionGroups->isNotEmpty() || $inclusionTypes->isNotEmpty())
                    <div class="entry-section">
                        <h4>Inclusion</h4>
                        @if($inclusionGroups->isNotEmpty())
                            <p><span class="label">Groups:</span> {{ $inclusionGroups->implode(', ') }}</p>
                        @endif
                        @if($inclusionTypes->isNotEmpty())
                            <p><span class="label">Types:</span> {{ $inclusionTypes->implode(', ') }}</p>
                        @endif
                    </div>
                @endif

                @php
                    $agreements = $entry->governmentAgreements->map(function ($agreement) {
                        return sprintf(
                            '%s (%s) - %s [%s]',
                            $agreement->counterpart_agency,
                            $agreement->institution_name,
                            $agreement->status,
                            $agreement->nature
                        );
                    });
                @endphp

                @if($agreements->isNotEmpty())
                    <div class="entry-section">
                        <h4>Government Agreements</h4>
                        <ul>
                            @foreach($agreements as $agreement)
                                <li>{{ $agreement }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            @if(!$loop->last)
                <div class="page-break"></div>
            @endif
        @endforeach

        <div class="footer">
            <p>NEP Programme System - Confidential Report</p>
            <p>Generated on {{ $generatedAt }}</p>
        </div>
    @else
        <div class="summary">
            <h2>No Entries Found</h2>
            <p>No programme entries match the selected filters.</p>
        </div>
    @endif
</body>
</html>