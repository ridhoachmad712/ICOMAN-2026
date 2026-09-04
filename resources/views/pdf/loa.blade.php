<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Letter of Acceptance · {{ $submission->submission_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; color: #172334; font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; line-height: 1.75; }
        .wrap { padding: 12px 8px; }
        .brand { border-bottom: 3px solid #d9621c; padding-bottom: 14px; margin-bottom: 26px; }
        .brand small { color: #687789; letter-spacing: 2px; font-size: 10px; text-transform: uppercase; }
        .brand h1 { margin: 6px 0 0; color: #18315e; font-size: 22px; }
        .meta { margin-bottom: 26px; font-size: 12px; }
        .meta div { margin-bottom: 2px; }
        .subject { font-weight: bold; margin-top: 6px; }
        .paper { margin: 20px 0; padding: 14px 16px; border-left: 4px solid #d9621c; background: #f7f9fb; }
        .paper strong { color: #18315e; font-size: 13px; }
        .row { margin-top: 4px; color: #46586c; font-size: 11px; }
        .signature { margin-top: 48px; }
        .signature .name { font-weight: bold; color: #18315e; }
        .foot { margin-top: 40px; border-top: 1px solid #dfe5ea; padding-top: 10px; color: #8a97a6; font-size: 10px; }
    </style>
</head>
<body>
<div class="wrap">
    @php
        $conf = $submission->edition?->name ?: (rescue(fn () => siteSettings()->conference_name, null, false) ?: 'ICOMAN 2026');
        $addressee = $submission->authors->firstWhere('is_corresponding', true)?->name
            ?? $submission->authors->first()?->name
            ?? $submission->author?->name;
        $issued = $submission->loa_issued_at ?? now();
    @endphp

    <div class="brand">
        <small>International Conference of Management</small>
        <h1>{{ $conf }}</h1>
    </div>

    <div class="meta">
        <div>Reference&nbsp;: {{ $submission->submission_number }}</div>
        <div>Date&nbsp;: {{ $issued->format('d F Y') }}</div>
        <div class="subject">Subject: Letter of Acceptance</div>
    </div>

    <p>Dear {{ $addressee }},</p>

    <p>On behalf of the organizing committee, we are pleased to inform you that after peer review, the following paper has been <strong>accepted</strong> for presentation at {{ $conf }}:</p>

    <div class="paper">
        <strong>{{ $submission->title }}</strong>
        <div class="row">Submission ID&nbsp;: {{ $submission->submission_number }}</div>
        <div class="row">Publication track&nbsp;: {{ $submission->journalTargetLabel() }}</div>
    </div>

    <p>To confirm your participation, please complete the presenter registration payment through the author portal. After your payment is verified, you will be able to submit the full paper according to the template and deadline set by the committee.</p>

    <p>We congratulate you on this achievement and look forward to your contribution to the conference.</p>

    <div class="signature">
        <div class="name">Organizing Committee</div>
        <div>{{ $conf }}</div>
    </div>

    <div class="foot">
        This Letter of Acceptance is generated automatically by the {{ $conf }} author portal and is valid without a wet-ink signature. Reference {{ $submission->submission_number }}.
    </div>
</div>
</body>
</html>
