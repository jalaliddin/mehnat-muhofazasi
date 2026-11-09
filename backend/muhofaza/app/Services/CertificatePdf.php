<?php

namespace App\Services;

use App\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Carbon;

class CertificatePdf
{
    private const MONTHS = [
        1 => 'yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun',
        'iyul', 'avgust', 'sentabr', 'oktabr', 'noyabr', 'dekabr',
    ];

    /**
     * @param  iterable<Certificate>  $certificates
     */
    public function render(iterable $certificates): string
    {
        return Pdf::loadHTML($this->html($certificates))
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Serif')
            ->output();
    }

    /**
     * Sertifikatlar HTML'i (PDF shu HTML'dan chiziladi; testlar matnni shu yerda tekshiradi).
     *
     * @param  iterable<Certificate>  $certificates
     */
    public function html(iterable $certificates): string
    {
        $pages = [];

        foreach ($certificates as $certificate) {
            $pages[] = [
                'number'       => $certificate->number,
                'registration' => $certificate->registrationLabel(),
                'qr'           => $this->qrDataUri(self::verifyUrl($certificate->public_code)),
                'data'         => $certificate->data ?? [],
            ];
        }

        return view('certificates.certificate', [
            'pages'  => $pages,
            'emblem' => 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path('certificates/emblem.png'))),
        ])->render();
    }

    public static function verifyUrl(string $publicCode): string
    {
        return rtrim((string) config('app.url'), '/').'/sertifikat/'.$publicCode;
    }

    /**
     * @return array{year: string, day: string, month: string}|null
     */
    public static function dateParts(?string $ymd): ?array
    {
        if (! $ymd) {
            return null;
        }

        $date = Carbon::parse($ymd);

        return [
            'year'  => $date->format('Y'),
            'day'   => $date->format('d'),
            'month' => self::MONTHS[$date->month],
        ];
    }

    private function qrDataUri(string $url): string
    {
        $options = new QROptions([
            'outputType'    => QROutputInterface::GDIMAGE_PNG,
            'outputBase64'  => true,
            'scale'         => 6,
            'eccLevel'      => EccLevel::M,
            'addQuietzone'  => true,
            'quietzoneSize' => 1,
        ]);

        return (new QRCode($options))->render($url);
    }
}
