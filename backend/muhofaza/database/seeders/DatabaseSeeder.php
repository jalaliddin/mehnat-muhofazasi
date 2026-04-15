<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\ExamResult;
use App\Models\ExamType;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PeriodicExam;
use App\Models\Position;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // -------------------------------------------------------
        // Organizations
        // -------------------------------------------------------
        $central = Organization::create([
            'name'    => 'Urganchtransgaz UK Markaziy Apparati',
            'code'    => 'URGENCH-MR',
            'type'    => 'central',
            'address' => 'Urgench sh., Markaziy ko\'cha 1',
            'phone'   => '+998 62 222-00-01',
        ]);

        $xiva = Organization::create([
            'name'    => 'Xiva GTS',
            'code'    => 'XIVA-GTS',
            'type'    => 'branch',
            'address' => 'Xiva sh., Bog\'cha ko\'cha 5',
            'phone'   => '+998 62 375-00-01',
        ]);

        $yangiariq = Organization::create([
            'name'    => 'Yangiariq GTS',
            'code'    => 'YANGIARIQ-GTS',
            'type'    => 'branch',
            'address' => 'Yangiariq tumani, Mustaqillik ko\'cha 3',
            'phone'   => '+998 62 380-00-01',
        ]);

        $orgs = [$central, $xiva, $yangiariq];

        // -------------------------------------------------------
        // Departments & Positions per org
        // -------------------------------------------------------
        $deptNames = ['Texnik bo\'lim', 'Xavfsizlik bo\'limi', 'Ishlab chiqarish bo\'limi'];
        $posNames  = ['Muhandis', 'Texnik', 'Operator'];

        $departments = [];
        $positions   = [];

        foreach ($orgs as $org) {
            $departments[$org->id] = [];
            foreach ($deptNames as $dName) {
                $departments[$org->id][] = Department::create([
                    'organization_id' => $org->id,
                    'name'            => $dName,
                ]);
            }

            $positions[$org->id] = [];
            foreach ($posNames as $pName) {
                $positions[$org->id][] = Position::create([
                    'organization_id' => $org->id,
                    'name'            => $pName,
                ]);
            }
        }

        // -------------------------------------------------------
        // Users
        // -------------------------------------------------------
        $admin = User::create([
            'name'            => 'Administrator',
            'username'        => 'admin',
            'email'           => 'admin@urgench.uz',
            'password'        => Hash::make('admin123'),
            'role'            => 'admin',
            'organization_id' => null,
            'is_active'       => true,
        ]);

        $urgenchOp = User::create([
            'name'            => 'Markaziy Apparat Operatori',
            'username'        => 'urgench_op',
            'email'           => 'urgench@urgench.uz',
            'password'        => Hash::make('pass123'),
            'role'            => 'operator',
            'organization_id' => $central->id,
            'is_active'       => true,
        ]);

        $xivaOp = User::create([
            'name'            => 'Xiva GTS Operatori',
            'username'        => 'xiva_op',
            'email'           => 'xiva@urgench.uz',
            'password'        => Hash::make('pass123'),
            'role'            => 'operator',
            'organization_id' => $xiva->id,
            'is_active'       => true,
        ]);

        // -------------------------------------------------------
        // Exam Types
        // -------------------------------------------------------
        $examTypes = [
            ExamType::create(['name' => 'Mehnat muhofazasi',     'description' => 'Mehnat muhofazasi bo\'yicha bilimlarni tekshirish', 'frequency_months' => 12]),
            ExamType::create(['name' => 'Elektr xavfsizligi',    'description' => 'Elektr qurilmalari xavfsizligi bo\'yicha tekshirish', 'frequency_months' => 12]),
            ExamType::create(['name' => 'Yong\'in xavfsizligi',  'description' => 'Yong\'in xavfsizligi qoidalari bo\'yicha tekshirish', 'frequency_months' => 24]),
            ExamType::create(['name' => 'Sanoat xavfsizligi',    'description' => 'Sanoat xavfsizligi talablari bo\'yicha tekshirish', 'frequency_months' => 24]),
            ExamType::create(['name' => 'Birinchi tibbiy yordam', 'description' => 'Birinchi tibbiy yordam ko\'rsatish bo\'yicha tekshirish', 'frequency_months' => 24]),
        ];

        // -------------------------------------------------------
        // Employees (10+ per org)
        // -------------------------------------------------------
        $employeeNames = [
            'Aliyev Sardor Baxtiyorovich',
            'Yusupov Jasur Hamidovich',
            'Karimov Bobur Salimovich',
            'Toshmatov Ulugbek Norqo\'ziyevich',
            'Razzaqov Sherzod Olimovich',
            'Xolmatov Mirzo Abdullayevich',
            'Nazarov Husan Baxtiyor o\'g\'li',
            'Eshmatov Akbar Ismoilovich',
            'Qodirov Mansur Xurramovich',
            'Sobirov Eldor Vohidovich',
            'Mirzayev Firdavs Kamolovich',
            'Haydarov Nodir Sultonovich',
        ];

        $employees = [];
        foreach ($orgs as $org) {
            $employees[$org->id] = [];
            foreach ($employeeNames as $i => $fullName) {
                $dept = $departments[$org->id][$i % 3];
                $pos  = $positions[$org->id][$i % 3];

                $employees[$org->id][] = Employee::create([
                    'organization_id'  => $org->id,
                    'department_id'    => $dept->id,
                    'position_id'      => $pos->id,
                    'full_name'        => $fullName,
                    'personnel_number' => 'TAB-' . $org->id . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                    'phone'            => '+998 9' . rand(0, 9) . ' ' . rand(100, 999) . '-' . rand(10, 99) . '-' . rand(10, 99),
                    'hire_date'        => Carbon::now()->subYears(rand(1, 10))->subMonths(rand(0, 11)),
                    'is_active'        => true,
                ]);
            }
        }

        // -------------------------------------------------------
        // Orders (2-3 per org)
        // -------------------------------------------------------
        $orders = [];
        foreach ($orgs as $org) {
            $orders[$org->id] = [];
            $orders[$org->id][] = Order::create([
                'organization_id' => $org->id,
                'order_number'    => 'B-' . $org->id . '-001',
                'order_date'      => Carbon::now()->subMonths(6),
                'title'           => 'Mehnat muhofazasi bo\'yicha buyruq',
                'description'     => 'Xodimlarning mehnat muhofazasi bo\'yicha bilimlarini oshirish haqida',
            ]);
            $orders[$org->id][] = Order::create([
                'organization_id' => $org->id,
                'order_number'    => 'B-' . $org->id . '-002',
                'order_date'      => Carbon::now()->subMonths(3),
                'title'           => 'Elektr xavfsizligi buyrug\'i',
                'description'     => 'Elektr qurilmalarini xavfsiz ishlatish tartibi haqida',
            ]);
            $orders[$org->id][] = Order::create([
                'organization_id' => $org->id,
                'order_number'    => 'B-' . $org->id . '-003',
                'order_date'      => Carbon::now()->subMonths(1),
                'title'           => 'Imtihon o\'tkazish haqida buyruq',
                'description'     => 'Davriy imtihonlarni rejalashtirish va o\'tkazish haqida',
            ]);
        }

        // -------------------------------------------------------
        // Periodic Exams (2-3 per org with different statuses)
        // -------------------------------------------------------
        $now = Carbon::now();

        foreach ($orgs as $org) {
            // Completed exam
            $examDate1 = $now->copy()->subMonths(3);
            $completedExam = PeriodicExam::create([
                'organization_id' => $org->id,
                'exam_type_id'    => $examTypes[0]->id,
                'order_id'        => $orders[$org->id][0]->id,
                'title'           => 'Mehnat muhofazasi imtihoni (Bajarilgan)',
                'exam_date'       => $examDate1,
                'frequency_months' => 12,
                'next_exam_date'  => $examDate1->copy()->addMonths(12),
                'status'          => 'completed',
                'created_by'      => $admin->id,
            ]);

            // Add participants and results for completed exam
            $orgEmployees = $employees[$org->id];
            $grades = ['excellent', 'good', 'satisfactory', 'unsatisfactory', 'good', 'excellent', 'satisfactory', 'good', 'excellent', 'good', 'satisfactory', 'good'];

            foreach ($orgEmployees as $idx => $emp) {
                $completedExam->employees()->attach($emp->id);

                $grade = $grades[$idx];
                $isPassed = $grade !== 'unsatisfactory';

                ExamResult::create([
                    'periodic_exam_id' => $completedExam->id,
                    'employee_id'      => $emp->id,
                    'order_id'         => $orders[$org->id][0]->id,
                    'grade'            => $grade,
                    'score'            => $grade === 'excellent' ? rand(90, 100) : ($grade === 'good' ? rand(75, 89) : ($grade === 'satisfactory' ? rand(60, 74) : rand(0, 59))),
                    'is_passed'        => $isPassed,
                    'retake_required'  => !$isPassed,
                ]);
            }

            // In-progress exam
            $examDate2 = $now->copy()->addDays(7);
            PeriodicExam::create([
                'organization_id' => $org->id,
                'exam_type_id'    => $examTypes[1]->id,
                'order_id'        => $orders[$org->id][1]->id,
                'title'           => 'Elektr xavfsizligi imtihoni (Jarayonda)',
                'exam_date'       => $examDate2,
                'frequency_months' => 12,
                'next_exam_date'  => $examDate2->copy()->addMonths(12),
                'status'          => 'in_progress',
                'created_by'      => $admin->id,
            ]);

            // Planned exam
            $examDate3 = $now->copy()->addMonths(2);
            PeriodicExam::create([
                'organization_id' => $org->id,
                'exam_type_id'    => $examTypes[2]->id,
                'order_id'        => $orders[$org->id][2]->id,
                'title'           => 'Yong\'in xavfsizligi imtihoni (Rejalashtirilgan)',
                'exam_date'       => $examDate3,
                'frequency_months' => 24,
                'next_exam_date'  => $examDate3->copy()->addMonths(24),
                'status'          => 'planned',
                'created_by'      => $admin->id,
            ]);
        }
    }
}
