<?php

namespace Tests\Feature\CertificateTypes;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * DDL bajariladi — RefreshDatabase o'rniga DatabaseMigrations.
 */
class CertificateTypeMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private object $migration;

    private int $orgId;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migration = require database_path('migrations/2026_11_09_000001_create_certificate_types.php');
        $this->migration->down();

        $this->orgId = DB::table('organizations')->insertGetId(['name' => 'Org', 'code' => 'ORG-1', 'type' => 'central']);
        $this->userId = DB::table('users')->insertGetId([
            'name' => 'Admin', 'username' => 'admin_m', 'email' => 'admin_m@example.test',
            'password' => 'x', 'role' => 'admin',
        ]);
    }

    private function typeWithExams(string $name, ?int $hours, int $exams): array
    {
        $typeId = DB::table('exam_types')->insertGetId([
            'name' => $name, 'frequency_months' => 12, 'training_hours' => $hours,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $ids = [];
        for ($i = 0; $i < $exams; $i++) {
            $ids[] = DB::table('periodic_exams')->insertGetId([
                'organization_id' => $this->orgId, 'exam_type_id' => $typeId, 'title' => "{$name} {$i}",
                'exam_date' => '2026-11-07', 'frequency_months' => 12, 'next_exam_date' => '2027-11-07',
                'status' => 'planned', 'created_by' => $this->userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    private function typeNameOf(int $examId): ?string
    {
        return DB::table('periodic_exams')
            ->leftJoin('certificate_types', 'certificate_types.id', '=', 'periodic_exams.certificate_type_id')
            ->where('periodic_exams.id', $examId)
            ->value('certificate_types.name');
    }

    public function test_up_seeds_four_types(): void
    {
        $this->migration->up();

        $this->assertSame([
            "288 soatlik qayta tayyorlov" => 288,
            '36 soatlik malaka oshirish'  => 36,
            "36 soatlik o'qish"           => 36,
            '72 soatlik malaka oshirish'  => 72,
        ], DB::table('certificate_types')->orderBy('name')->pluck('hours', 'name')->map(fn ($h) => (int) $h)->all());
    }

    public function test_up_maps_training_hours_to_type(): void
    {
        $seventyTwo = $this->typeWithExams('A', 72, 2);
        $thirtySix = $this->typeWithExams('B', 36, 1);
        $retraining = $this->typeWithExams('C', 288, 1);
        $other = $this->typeWithExams('D', 50, 1);

        $this->migration->up();

        $this->assertSame('72 soatlik malaka oshirish', $this->typeNameOf($seventyTwo[0]));
        $this->assertSame('72 soatlik malaka oshirish', $this->typeNameOf($seventyTwo[1]));
        $this->assertSame('36 soatlik malaka oshirish', $this->typeNameOf($thirtySix[0]));
        $this->assertSame('288 soatlik qayta tayyorlov', $this->typeNameOf($retraining[0]));
        $this->assertNull($this->typeNameOf($other[0]));
        $this->assertFalse(Schema::hasColumn('exam_types', 'training_hours'));
    }

    public function test_up_makes_certificate_number_nullable(): void
    {
        $this->migration->up();

        $columns = collect(Schema::getColumns('certificates'))->keyBy('name');
        $this->assertTrue($columns['number']['nullable']);
        $this->assertTrue($columns['year']['nullable']);
        $this->assertTrue($columns['sequence']['nullable']);
    }

    public function test_up_is_rerunnable(): void
    {
        $this->typeWithExams('A', 72, 1);

        $this->migration->up();
        $this->migration->up();

        $this->assertSame(4, DB::table('certificate_types')->count());
    }
}
