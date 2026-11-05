<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 14mm 16mm 12mm 16mm; }
    * { font-family: 'DejaVu Serif', serif; }
    body { margin: 0; color: #111; font-size: 11pt; line-height: 1.25; }

    .frame-outer { position: fixed; top: -9mm; left: -11mm; right: -11mm; bottom: -7mm; border: 2.6pt solid #1F5E63; }
    .frame-inner { position: fixed; top: -7.6mm; left: -9.6mm; right: -9.6mm; bottom: -5.6mm; border: 0.8pt solid #1F5E63; }

    .page { page-break-after: always; }
    .page.last { page-break-after: auto; }

    .center { text-align: center; }
    .emblem { width: 27mm; }
    .heading { font-size: 12.5pt; font-weight: bold; margin-top: 6mm; }
    .title { font-size: 34pt; font-weight: bold; color: #1F5E63; letter-spacing: 1pt; line-height: 1; margin-top: 1mm; }
    .number { font-size: 11pt; font-weight: bold; margin-top: 1mm; }

    .name { font-size: 15pt; font-weight: bold; text-align: center; margin-top: 9mm; padding: 0 2mm 1mm; border-bottom: 1.6pt solid #111; }
    .caption { font-size: 6.5pt; color: #555; text-align: center; }

    .period { text-align: center; font-size: 12pt; margin-top: 3mm; }
    .institution { text-align: center; font-size: 12pt; text-decoration: underline; margin-top: 5mm; }
    .program { text-align: center; font-size: 12pt; font-weight: bold; text-decoration: underline; margin-top: 4mm; }
    .text { margin-top: 4mm; font-size: 11.5pt; }
    .seal { margin-top: 4mm; font-size: 11.5pt; }

    table.signs { width: 100%; border-collapse: collapse; margin-top: 8mm; }
    table.signs td { vertical-align: bottom; padding: 0; height: 15mm; font-size: 11.5pt; }
    .sign-line { border-bottom: 1.4pt solid #111; height: 6mm; margin: 0 4mm; }
    .sign-caption { font-size: 6.5pt; color: #555; text-align: center; }
    .signer { text-align: center; text-decoration: underline; }

    table.footer { width: 100%; border-collapse: collapse; margin-top: 10mm; font-size: 11.5pt; }
    table.footer td { padding: 0; vertical-align: bottom; }
    .underline { text-decoration: underline; }
    .qr-box { width: 30mm; height: 30mm; border: 0.8pt solid #111; text-align: center; vertical-align: middle; }
    .qr-box img { width: 27mm; height: 27mm; margin-top: 1.5mm; }
</style>
</head>
<body>
<div class="frame-outer"></div>
<div class="frame-inner"></div>

@foreach ($pages as $page)
    @php
        $d = $page['data'];
        $blank = '______________';
        $value = fn (string $key, string $empty = '______________') => filled($d[$key] ?? null) ? $d[$key] : $empty;
        $start = \App\Services\CertificatePdf::dateParts($d['course_start_date'] ?? null);
        $end = \App\Services\CertificatePdf::dateParts($d['course_end_date'] ?? null);
        $period = fn (?array $p) => $p
            ? "{$p['year']} yil «{$p['day']}» {$p['month']}"
            : '_____ yil «___» ___________';
        $issue = filled($d['issue_date'] ?? null) ? \Illuminate\Support\Carbon::parse($d['issue_date'])->format('d.m.Y') : '___________';
        // Uzun matnlar sahifadan chiqib ketmasligi uchun shrift o'lchami uzunlikka qarab kichrayadi.
        $fit = function (?string $text, float $base, array $steps): string {
            $length = mb_strlen((string) $text);
            foreach ($steps as $limit => $size) {
                if ($length > $limit) {
                    $base = $size;
                }
            }
            return "font-size: {$base}pt";
        };
        $institution = filled($d['institution_name'] ?? null) ? $d['institution_name'].'da' : $blank;
    @endphp
    <div class="page {{ $loop->last ? 'last' : '' }}">
        <div class="center"><img class="emblem" src="{{ $emblem }}" alt=""></div>

        <div class="center heading">Mehnatni muhofaza qilish sohasida malaka oshirganlik haqida</div>
        <div class="center title">SERTIFIKAT</div>
        <div class="center number">№ {{ $page['number'] ?: '_______' }}</div>

        <div class="name" style="{{ $fit($d['full_name'] ?? '', 15, [40 => 13, 60 => 11, 90 => 9.5]) }}">{{ $value('full_name', ' ') }}</div>
        <div class="caption">(familiyasi, ismi, otasining ismi)</div>

        <div class="period">{{ $period($start) }} dan &nbsp;&nbsp;&nbsp; {{ $period($end) }} gacha</div>

        <div class="institution" style="{{ $fit($institution, 12, [50 => 10.5, 90 => 9]) }}">{{ $institution }}</div>
        <div class="caption">(malaka oshirish va qayta tayyorlash ta’lim muassasasining nomi)</div>

        <div class="program" style="{{ $fit($d['program_name'] ?? '', 12, [50 => 10.5, 90 => 9]) }}">{{ $value('program_name') }}</div>
        <div class="caption">(xodimlar malakasini oshirish dasturining nomi)</div>

        <div class="text">dastur bo‘yicha {{ $value('training_hours', '____') }} soat hajmida malaka oshirish kursida o‘qidi:</div>
        <div class="seal">Muhr o‘rni :</div>

        <table class="signs">
            @foreach ([
                ['Imtixon komissiyasi raisi', 'commission_chair_name'],
                ['Ta’lim muassasi rahbari', 'director_name'],
                ['Mehnatni Muhofaza qilish bo‘yicha (bosh) davlat texnik inspektori', 'inspector_name'],
            ] as [$role, $key])
                <tr>
                    <td style="width: 44%">{{ $role }}</td>
                    <td style="width: 26%">
                        <div class="sign-line"></div>
                        <div class="sign-caption">(imzo)</div>
                    </td>
                    <td style="width: 30%">
                        <div class="signer">{{ $value($key) }}</div>
                        <div class="sign-caption">(F.I.O)</div>
                    </td>
                </tr>
            @endforeach
        </table>

        <table class="footer">
            <tr>
                <td style="width: 60%">Shaxar (tuman) &nbsp;&nbsp; <span class="underline">{{ $value('city') }}</span></td>
                <td style="width: 40%; text-align: right">Sana &nbsp;&nbsp; <span class="underline">{{ $issue }}</span></td>
            </tr>
        </table>

        <table class="footer" style="margin-top: 6mm">
            <tr>
                <td>Ro‘yxatdan o‘tish raqami &nbsp; <span class="underline">{{ $page['registration'] }}</span></td>
                <td class="qr-box"><img src="{{ $page['qr'] }}" alt=""></td>
            </tr>
        </table>
    </div>
@endforeach
</body>
</html>
