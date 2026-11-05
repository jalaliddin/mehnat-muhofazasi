<?php

namespace Tests\Feature\Certificates;

use App\Services\CertificatePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CertificateFixtures;
use Tests\TestCase;

class CertificatePdfTest extends TestCase
{
    use CertificateFixtures, RefreshDatabase;

    private function pageCount(string $pdf): int
    {
        return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
    }

    public function test_renders_single_page_pdf(): void
    {
        $pdf = app(CertificatePdf::class)->render([$this->makeCertificate($this->makeResult($this->makeExam()))]);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame(1, $this->pageCount($pdf));
    }

    public function test_renders_one_page_per_certificate(): void
    {
        $exam = $this->makeExam();
        $certs = collect(range(1, 3))->map(fn () => $this->makeCertificate($this->makeResult($exam)));

        $this->assertSame(3, $this->pageCount(app(CertificatePdf::class)->render($certs)));
    }

    public function test_long_name_and_institution_stay_on_one_page(): void
    {
        $cert = $this->makeCertificate($this->makeResult($this->makeExam()), ['data' => [
            'full_name'        => str_repeat('Abdurahmonov ', 10),
            'institution_name' => str_repeat('"JAHON MEHNAT MUHOFAZASI" MCHJ ', 5),
            'program_name'     => str_repeat('Mehnatni muhofaza qilish ', 6),
        ]]);

        $this->assertSame(1, $this->pageCount(app(CertificatePdf::class)->render([$cert])));
    }

    public function test_missing_fields_render_as_blanks(): void
    {
        $cert = $this->makeCertificate($this->makeResult($this->makeExam()), ['data' => ['full_name' => 'X']]);

        $this->assertSame(1, $this->pageCount(app(CertificatePdf::class)->render([$cert])));
    }

    public function test_verify_url_trims_trailing_slash(): void
    {
        config(['app.url' => 'https://mehnatmuhofazasi.uz/']);

        $this->assertSame('https://mehnatmuhofazasi.uz/sertifikat/abc', CertificatePdf::verifyUrl('abc'));
    }

    public function test_date_parts(): void
    {
        $this->assertSame(['year' => '2026', 'day' => '07', 'month' => 'noyabr'], CertificatePdf::dateParts('2026-11-07'));
        $this->assertNull(CertificatePdf::dateParts(null));
    }
}
