<?php

namespace Tests\Feature\CertificateTypes;

use App\Services\CertificatePdf;
use App\Services\CertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CertificateFixtures;
use Tests\TestCase;

class CertificateTypeContentTest extends TestCase
{
    use CertificateFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function html(iterable $certificates): string
    {
        return html_entity_decode(app(CertificatePdf::class)->html($certificates), ENT_QUOTES | ENT_HTML5);
    }

    public function test_data_snapshot_takes_hours_and_texts_from_type(): void
    {
        $type = $this->makeCertificateType('288 soatlik qayta tayyorlov', 288,
            "Mehnatni muhofaza qilish sohasida qayta tayyorlovdan o'tganlik haqida", 'qayta tayyorlov kursida');
        $exam = $this->makeExam(null, ['certificate_type_id' => $type->id]);

        $data = app(CertificateService::class)->buildData($this->makeResult($exam));

        $this->assertSame(288, $data['training_hours']);
        $this->assertSame('288 soatlik qayta tayyorlov', $data['certificate_type_name']);
        $this->assertSame("Mehnatni muhofaza qilish sohasida qayta tayyorlovdan o'tganlik haqida", $data['title_text']);
        $this->assertSame('qayta tayyorlov kursida', $data['course_text']);
    }

    public function test_data_snapshot_without_type_has_nulls(): void
    {
        $exam = $this->makeExam(null, ['certificate_type_id' => null]);

        $data = app(CertificateService::class)->buildData($this->makeResult($exam));

        $this->assertNull($data['training_hours']);
        $this->assertNull($data['certificate_type_name']);
        $this->assertNull($data['title_text']);
        $this->assertNull($data['course_text']);
    }

    public function test_pdf_html_uses_type_texts(): void
    {
        $cert = $this->makeCertificate($this->makeResult($this->makeExam()), ['data' => [
            'full_name' => 'X', 'training_hours' => 288, 'certificate_type_name' => '288 soatlik qayta tayyorlov',
            'title_text' => "Mehnatni muhofaza qilish sohasida qayta tayyorlovdan o'tganlik haqida",
            'course_text' => 'qayta tayyorlov kursida',
        ]]);

        $html = $this->html([$cert]);

        $this->assertStringContainsString("qayta tayyorlovdan o'tganlik haqida", $html);
        $this->assertStringContainsString('288 soat hajmida qayta tayyorlov kursida', $html);
    }

    public function test_pdf_html_falls_back_to_default_texts(): void
    {
        $cert = $this->makeCertificate($this->makeResult($this->makeExam()), ['data' => ['full_name' => 'X']]);

        $html = $this->html([$cert]);

        $this->assertStringContainsString('Mehnatni muhofaza qilish sohasida malaka oshirganlik haqida', $html);
        $this->assertStringContainsString('soat hajmida malaka oshirish kursida', $html);
    }

    public function test_changing_exam_certificate_type_then_regenerating_updates_texts(): void
    {
        $exam = $this->makeExam();
        $result = $this->makeResult($exam);
        $svc = app(CertificateService::class);
        $svc->issue($result);

        $exam->update(['certificate_type_id' => $this->makeCertificateType("36 soatlik o'qish", 36,
            "Mehnatni muhofaza qilish sohasida o'qiganlik haqida", "o'quv kursida")->id]);
        $data = $svc->issue($result->fresh())->data;

        $this->assertSame("o'quv kursida", $data['course_text']);
        $this->assertSame(36, $data['training_hours']);
    }

    public function test_warns_when_certificate_type_missing(): void
    {
        $exam = $this->makeExam(null, ['certificate_type_id' => null]);

        $warnings = app(CertificateService::class)->missingFieldWarnings($exam);

        $this->assertContains('Sertifikat turi tanlanmagan', $warnings);
        $this->assertNotContains('Soat hajmi kiritilmagan', $warnings);
    }
}
