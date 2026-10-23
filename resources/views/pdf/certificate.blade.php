<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $certificate->typeLabel() }} · {{ $certificate->number }}</title>
    @php
        $edition = $certificate->edition;
        $conference = $edition?->name ?: ($settings?->conference_name ?: 'ICOMAN 2026');
        $theme = $edition ? $edition->getTranslation('theme', 'en', false) : null;
        $dates = null;
        if ($edition?->start_date) {
            $dates = $edition->start_date->format('j F Y');
            if ($edition->end_date && ! $edition->end_date->isSameDay($edition->start_date)) {
                $dates = $edition->start_date->format('j').'–'.$edition->end_date->format('j F Y');
            }
        }
        $venue = collect([$settings?->event_mode, $settings?->event_location])->filter()->unique()->first();
        $isPresentation = $certificate->type === 'presentation';
        $brand = $settings?->brandColor() ?: '#d9621c';
        $brand2 = $settings?->brandColor2() ?: '#18315e';
    @endphp
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; width: 297mm; height: 210mm; font-family: DejaVu Sans, Arial, sans-serif; color: #1f2d3d; }
        .bg { position: absolute; top: 0; left: 0; width: 297mm; height: 210mm; }
        .frame { position: absolute; top: 9mm; left: 9mm; right: 9mm; bottom: 9mm; border: 2.2mm solid {{ $brand2 }}; }
        .frame-inner { position: absolute; top: 13mm; left: 13mm; right: 13mm; bottom: 13mm; border: 0.5mm solid {{ $brand }}; }
        .content { position: absolute; top: 24mm; left: 30mm; right: 30mm; text-align: center; }
        .logo { height: 16mm; margin-bottom: 3mm; }
        .conference-tag { font-size: 10pt; color: #5b6b7d; letter-spacing: 0.5pt; }
        .title { margin-top: 6mm; font-size: 34pt; font-weight: bold; letter-spacing: 6pt; color: {{ $brand2 }}; }
        .subtitle { margin-top: 1mm; font-size: 15pt; color: {{ $brand }}; font-weight: bold; }
        .lead { margin-top: 8mm; font-size: 11pt; color: #5b6b7d; }
        .name { margin: 3mm auto 0; font-family: DejaVu Serif, Georgia, serif; font-size: 27pt; font-weight: bold; color: {{ $brand2 }};
                padding-bottom: 2mm; border-bottom: 0.4mm solid #c7d0da; display: inline-block; min-width: 140mm; }
        .affiliation { margin-top: 2mm; font-size: 11pt; color: #3d4b5c; }
        .body { margin-top: 6mm; font-size: 11pt; line-height: 1.55; color: #3d4b5c; }
        .paper { margin: 2mm auto 0; max-width: 200mm; font-family: DejaVu Serif, Georgia, serif; font-style: italic; font-size: 12.5pt; color: #1f2d3d; }
        .event { margin-top: 2mm; font-weight: bold; color: {{ $brand2 }}; font-size: 12pt; }
        .theme { font-size: 10pt; color: #5b6b7d; font-style: italic; }
        .footer { position: absolute; left: 30mm; right: 30mm; bottom: 22mm; }
        .footer table { width: 100%; border-collapse: collapse; }
        .footer td { vertical-align: bottom; }
        .signature img { height: 17mm; }
        .signature .line { width: 70mm; border-top: 0.4mm solid #1f2d3d; margin-top: 1mm; padding-top: 1.5mm; }
        .signature .who { font-weight: bold; font-size: 10.5pt; color: #1f2d3d; }
        .signature .role { font-size: 9pt; color: #5b6b7d; }
        .verify { text-align: right; }
        .verify img { width: 26mm; height: 26mm; }
        .verify .number { font-size: 8.5pt; color: #3d4b5c; margin-top: 1mm; }
        .verify .hint { font-size: 7.5pt; color: #8090a2; }
    </style>
</head>
<body>
@if($custom ?? false)
    {{-- Template custom: judul, teks, logo, dan tanda tangan sudah ada di desain
         latar. Yang dicetak hanya data dinamis, di posisi dari Pengaturan. --}}
    <img class="bg" src="{{ $background }}" alt="">
    @php
        $box = fn (array $p): string => 'position: absolute; top: '.(float) $p['top'].'mm; left: '.(float) $p['left'].'mm; width: '.(float) ($p['width'] ?? 100).'mm;'
            .' text-align: '.(in_array($p['align'] ?? 'center', ['left', 'center', 'right'], true) ? $p['align'] : 'center').';'
            .' font-size: '.(float) ($p['size'] ?? 12).'pt; line-height: 1.25;'
            .' color: '.(preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) ($p['color'] ?? '')) ? $p['color'] : '#1f2d3d').';';
    @endphp
    <div style="{{ $box($layout['name']) }} font-family: DejaVu Serif, Georgia, serif; font-weight: bold;">{{ $certificate->recipient_name }}</div>
    @if(filled($certificate->recipient_affiliation))
        <div style="{{ $box($layout['affiliation']) }}">{{ $certificate->recipient_affiliation }}</div>
    @endif
    @if($certificate->type === 'presentation' && filled($certificate->paper_title))
        <div style="{{ $box($layout['paper_title']) }} font-family: DejaVu Serif, Georgia, serif; font-style: italic;">“{{ $certificate->paper_title }}”</div>
    @endif
    <div style="{{ $box($layout['number']) }}">No. {{ $certificate->number }}</div>
    <img src="{{ $qr }}" alt="QR" style="position: absolute; top: {{ (float) $layout['qr']['top'] }}mm; left: {{ (float) $layout['qr']['left'] }}mm; width: {{ (float) $layout['qr']['size'] }}mm; height: {{ (float) $layout['qr']['size'] }}mm;">
@else
    @if($background)
        <img class="bg" src="{{ $background }}" alt="">
    @else
        <div class="frame"></div>
        <div class="frame-inner"></div>
    @endif

    <div class="content">
        @if($logo && ! $background)
            <img class="logo" src="{{ $logo }}" alt="{{ $conference }}"><br>
        @endif
        <div class="conference-tag">International Conference on Management · {{ $conference }}</div>

        <div class="title">CERTIFICATE</div>
        <div class="subtitle">{{ $isPresentation ? 'OF PRESENTATION' : 'OF PARTICIPATION' }}</div>

        <div class="lead">This is to certify that</div>
        <div class="name">{{ $certificate->recipient_name }}</div>
        @if(filled($certificate->recipient_affiliation))
            <div class="affiliation">{{ $certificate->recipient_affiliation }}</div>
        @endif

        <div class="body">
            @if($isPresentation)
                has presented the paper entitled
                <div class="paper">“{{ $certificate->paper_title }}”</div>
                at
            @else
                has participated as an attendee in
            @endif
            <div class="event">{{ $conference }}</div>
            @if(filled($theme))
                <div class="theme">{{ $theme }}</div>
            @endif
            @if($dates || $venue)
                <div>{{ collect([$dates, $venue])->filter()->implode(' · ') }}</div>
            @endif
        </div>
    </div>

    <div class="footer">
        <table>
            <tr>
                <td class="signature">
                    @if($signature)
                        <img src="{{ $signature }}" alt=""><br>
                    @else
                        <div style="height: 17mm"></div>
                    @endif
                    <div class="line">
                        <div class="who">{{ $settings?->certificate_signatory_name ?: '—' }}</div>
                        @if(filled($settings?->certificate_signatory_title))
                            <div class="role">{{ $settings->certificate_signatory_title }}</div>
                        @endif
                    </div>
                </td>
                <td class="verify">
                    <img src="{{ $qr }}" alt="QR"><br>
                    <div class="number">No. {{ $certificate->number }}</div>
                    <div class="hint">Scan to verify this certificate</div>
                </td>
            </tr>
        </table>
    </div>
@endif
</body>
</html>
