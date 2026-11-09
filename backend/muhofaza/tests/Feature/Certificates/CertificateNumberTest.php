<?php

namespace Tests\Feature\Certificates;

use App\Models\Certificate;
use App\Services\CertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CertificateFixtures;
use Tests\TestCase;

class CertificateNumberTest extends TestCase
{
    use CertificateFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function issued(?\App\Models\Organization $org = null): Certificate
    {
        return app(CertificateService::class)->issue($this->makeResult($this->makeExam($org)));
    }

    private function pageCount(string $pdf): int
    {
        return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
    }

    public function test_issue_creates_certificate_without_number_or_pdf(): void
    {
        $cert = $this->issued();

        $this->assertNull($cert->number);
        $this->assertNull($cert->generated_path);
        $this->assertSame(1, $cert->registration_number);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_set_number_renders_pdf_by_id(): void
    {
        $cert = $this->issued();
        Sanctum::actingAs($this->makeUser());

        $this->putJson("/api/certificates/{$cert->id}/number", ['number' => ' 0451 '])
            ->assertOk()
            ->assertJsonPath('number', '0451');

        $this->assertSame("certificates/generated/{$cert->id}.pdf", $cert->fresh()->generated_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get("certificates/generated/{$cert->id}.pdf"));
    }

    public function test_set_number_rejects_taken_number_case_insensitively(): void
    {
        $first = $this->issued();
        $second = $this->issued();
        app(CertificateService::class)->setNumber($first, 'MM-0451');
        Sanctum::actingAs($this->makeUser());

        $this->putJson("/api/certificates/{$second->id}/number", ['number' => 'mm-0451'])
            ->assertStatus(422)
            ->assertJsonPath('errors.number.0', 'Bu raqam boshqa sertifikatga berilgan');
    }

    public function test_set_number_rejects_signed(): void
    {
        $cert = $this->issueNumbered($this->makeResult($this->makeExam()), '0001');
        $cert->update(['signed_path' => 'certificates/signed/x.pdf']);
        Sanctum::actingAs($this->makeUser());

        $this->putJson("/api/certificates/{$cert->id}/number", ['number' => '0002'])
            ->assertStatus(422)
            ->assertJsonPath('message', "Imzolangan sertifikat raqamini o'zgartirib bo'lmaydi");
        $this->assertSame('0001', $cert->fresh()->number);
    }

    public function test_number_with_slash_downloads_with_safe_name(): void
    {
        $cert = $this->issueNumbered($this->makeResult($this->makeExam()), '12/2026');
        Sanctum::actingAs($this->makeUser());

        $res = $this->get("/api/certificates/{$cert->id}/download")->assertOk();

        $this->assertStringContainsString('sertifikat-12-2026.pdf', $res->headers->get('Content-Disposition'));
    }

    public function test_download_without_number_returns_422(): void
    {
        $cert = $this->issued();
        Sanctum::actingAs($this->makeUser());

        $this->getJson("/api/certificates/{$cert->id}/download")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Avval sertifikat raqamini kiriting');
    }

    public function test_bulk_numbers_saves_all(): void
    {
        $exam = $this->makeExam();
        $certs = collect(range(1, 3))->map(fn () => app(CertificateService::class)->issue($this->makeResult($exam)));
        Sanctum::actingAs($this->makeUser());

        $this->putJson('/api/certificates/numbers', ['items' => $certs->values()->map(fn ($c, $i) => ['id' => $c->id, 'number' => '045'.$i])->all()])
            ->assertOk()
            ->assertJsonPath('updated', 3);

        foreach ($certs as $i => $c) {
            $this->assertSame('045'.$i, $c->fresh()->number);
            Storage::disk('local')->assertExists("certificates/generated/{$c->id}.pdf");
        }
    }

    public function test_bulk_numbers_can_swap(): void
    {
        $exam = $this->makeExam();
        $a = $this->issueNumbered($this->makeResult($exam), '1');
        $b = $this->issueNumbered($this->makeResult($exam), '2');
        Sanctum::actingAs($this->makeUser());

        $this->putJson('/api/certificates/numbers', ['items' => [['id' => $a->id, 'number' => '2'], ['id' => $b->id, 'number' => '1']]])
            ->assertOk();

        $this->assertSame(['2', '1'], [$a->fresh()->number, $b->fresh()->number]);
    }

    public function test_bulk_numbers_keeps_unchanged_numbers(): void
    {
        $exam = $this->makeExam();
        $kept = $this->issueNumbered($this->makeResult($exam), '0451');
        $fresh = app(CertificateService::class)->issue($this->makeResult($exam));
        Sanctum::actingAs($this->makeUser());

        $this->putJson('/api/certificates/numbers', ['items' => [['id' => $kept->id, 'number' => '0451'], ['id' => $fresh->id, 'number' => '0452']]])
            ->assertOk();

        $this->assertSame(['0451', '0452'], [$kept->fresh()->number, $fresh->fresh()->number]);
    }

    public function test_bulk_numbers_rejects_duplicate_inside_list(): void
    {
        $exam = $this->makeExam();
        $a = app(CertificateService::class)->issue($this->makeResult($exam));
        $b = app(CertificateService::class)->issue($this->makeResult($exam));
        Sanctum::actingAs($this->makeUser());

        $this->putJson('/api/certificates/numbers', ['items' => [['id' => $a->id, 'number' => '7'], ['id' => $b->id, 'number' => ' 7']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.1.number');

        $this->assertNull($a->fresh()->number);
    }

    public function test_bulk_numbers_rejects_number_taken_outside_list(): void
    {
        $exam = $this->makeExam();
        $this->issueNumbered($this->makeResult($exam), '0451');
        $b = app(CertificateService::class)->issue($this->makeResult($exam));
        Sanctum::actingAs($this->makeUser());

        $this->putJson('/api/certificates/numbers', ['items' => [['id' => $b->id, 'number' => '0451']]])
            ->assertStatus(422)
            ->assertJsonPath('errors', ['items.0.number' => ['Bu raqam boshqa sertifikatga berilgan']]);
    }

    public function test_bulk_numbers_rejects_signed_and_saves_nothing(): void
    {
        $exam = $this->makeExam();
        $signed = $this->issueNumbered($this->makeResult($exam), '1');
        $signed->update(['signed_path' => 'certificates/signed/x.pdf']);
        $other = app(CertificateService::class)->issue($this->makeResult($exam));
        Sanctum::actingAs($this->makeUser());

        $this->putJson('/api/certificates/numbers', ['items' => [['id' => $other->id, 'number' => '5'], ['id' => $signed->id, 'number' => '6']]])
            ->assertStatus(422)
            ->assertJsonPath('errors', ['items.1.number' => ["Imzolangan sertifikat raqamini o'zgartirib bo'lmaydi"]]);

        $this->assertNull($other->fresh()->number);
        $this->assertSame('1', $signed->fresh()->number);
    }

    public function test_operator_cannot_number_other_org(): void
    {
        $cert = $this->issued($this->makeOrganization('B'));
        Sanctum::actingAs($this->makeUser('operator', $this->makeOrganization('A')));

        $this->putJson("/api/certificates/{$cert->id}/number", ['number' => '1'])->assertForbidden();
        $this->putJson('/api/certificates/numbers', ['items' => [['id' => $cert->id, 'number' => '1']]])->assertForbidden();
        $this->assertNull($cert->fresh()->number);
    }

    public function test_print_includes_only_numbered_unsigned(): void
    {
        $exam = $this->makeExam();
        Sanctum::actingAs($this->makeUser());
        app(CertificateService::class)->issue($this->makeResult($exam));

        $this->getJson("/api/certificates/print?periodic_exam_id={$exam->id}")
            ->assertNotFound()
            ->assertJsonPath('message', "Chop etish uchun raqamlangan, imzolanmagan sertifikat yo'q");

        $this->issueNumbered($this->makeResult($exam), '9');
        $res = $this->get("/api/certificates/print?periodic_exam_id={$exam->id}")->assertOk();

        $this->assertSame(1, $this->pageCount($res->getContent()));
    }

    public function test_index_allows_per_page_500(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->getJson('/api/certificates?per_page=500')->assertOk()->assertJsonPath('per_page', 500);
    }
}
