<?php

namespace Tests\Feature\CertificateTypes;

use App\Models\CertificateType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CertificateFixtures;
use Tests\TestCase;

class CertificateTypeApiTest extends TestCase
{
    use CertificateFixtures, RefreshDatabase;

    private function payload(array $override = []): array
    {
        return array_merge([
            'name'        => '144 soatlik malaka oshirish',
            'hours'       => 144,
            'title_text'  => 'Mehnatni muhofaza qilish sohasida malaka oshirganlik haqida',
            'course_text' => 'malaka oshirish kursida',
        ], $override);
    }

    public function test_admin_crud(): void
    {
        Sanctum::actingAs($this->makeUser('admin'));

        $names = collect($this->getJson('/api/certificate-types')->assertOk()->json())->pluck('name');
        $this->assertTrue($names->contains("288 soatlik qayta tayyorlov"));

        $id = $this->postJson('/api/certificate-types', $this->payload())->assertCreated()->json('id');
        $this->putJson("/api/certificate-types/{$id}", $this->payload(['hours' => 140]))
            ->assertOk()->assertJsonPath('hours', 140);
        $this->deleteJson("/api/certificate-types/{$id}")->assertOk();

        $this->assertNull(CertificateType::find($id));
    }

    public function test_duplicate_name_uzbek_message(): void
    {
        Sanctum::actingAs($this->makeUser('admin'));

        $this->postJson('/api/certificate-types', $this->payload(['name' => '72 soatlik malaka oshirish']))
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'Bu nomdagi tur allaqachon mavjud');
        $this->postJson('/api/certificate-types', $this->payload(['title_text' => '']))
            ->assertStatus(422)
            ->assertJsonPath('errors.title_text.0', "Maydon to'ldirilishi shart");
    }

    public function test_delete_used_type_returns_422(): void
    {
        $exam = $this->makeExam();
        Sanctum::actingAs($this->makeUser('admin'));

        $this->deleteJson("/api/certificate-types/{$exam->certificate_type_id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Tur davriy imtihonlarda ishlatilmoqda');
    }

    public function test_operator_reads_but_cannot_write(): void
    {
        $type = $this->makeCertificateType();
        Sanctum::actingAs($this->makeUser('operator', $this->makeOrganization()));

        $this->getJson('/api/certificate-types')->assertOk();
        $this->postJson('/api/certificate-types', $this->payload())->assertForbidden();
        $this->putJson("/api/certificate-types/{$type->id}", $this->payload())->assertForbidden();
        $this->deleteJson("/api/certificate-types/{$type->id}")->assertForbidden();
    }

    public function test_periodic_exam_update_accepts_certificate_type(): void
    {
        $exam = $this->makeExam();
        $type = CertificateType::where('name', "36 soatlik o'qish")->firstOrFail();
        Sanctum::actingAs($this->makeUser('admin'));

        $this->putJson("/api/periodic-exams/{$exam->id}", ['certificate_type_id' => $type->id])
            ->assertOk()
            ->assertJsonPath('certificate_type.name', "36 soatlik o'qish");
        $this->putJson("/api/periodic-exams/{$exam->id}", ['certificate_type_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('certificate_type_id');

        $this->assertSame($type->id, $exam->fresh()->certificate_type_id);
    }
}
