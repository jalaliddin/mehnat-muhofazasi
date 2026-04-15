<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\PeriodicExam;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckExamNotifications extends Command
{
    protected $signature = 'exams:check-notifications';
    protected $description = 'Check upcoming and overdue exam notifications';

    public function handle(): void
    {
        $today = Carbon::today();

        $exams = PeriodicExam::with(['organization'])
            ->where('status', '!=', 'completed')
            ->get();

        $adminUsers = User::where('role', 'admin')->where('is_active', true)->pluck('id')->toArray();

        foreach ($exams as $exam) {
            $nextExamDate = Carbon::parse($exam->next_exam_date);
            $daysLeft = $today->diffInDays($nextExamDate, false);

            // Collect users to notify
            $userIds = $adminUsers;

            // Add operator users of the same org
            $operatorIds = User::where('role', 'operator')
                ->where('organization_id', $exam->organization_id)
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            $userIds = array_unique(array_merge($userIds, $operatorIds));

            $notificationType = null;
            $title = null;
            $message = null;

            if ($daysLeft < 0) {
                $notificationType = 'overdue';
                $title = "MUDDATI O'TGAN: " . $exam->title;
                $message = "'{$exam->title}' imtihonining muddati o'tgan. Org: {$exam->organization->name}";
            } elseif ($daysLeft <= 7) {
                $notificationType = 'urgent_7_days';
                $title = "SHOSHILINCH: 7 kun qoldi - " . $exam->title;
                $message = "'{$exam->title}' imtihoniga {$daysLeft} kun qoldi. Org: {$exam->organization->name}";
            } elseif ($daysLeft <= 30) {
                $notificationType = '30_days';
                $title = "30 kun qoldi: " . $exam->title;
                $message = "'{$exam->title}' imtihoniga {$daysLeft} kun qoldi. Org: {$exam->organization->name}";
            }

            if (!$notificationType) {
                continue;
            }

            foreach ($userIds as $userId) {
                $exists = Notification::where('user_id', $userId)
                    ->where('type', $notificationType)
                    ->where('data->exam_id', $exam->id)
                    ->whereDate('created_at', $today)
                    ->exists();

                if (!$exists) {
                    Notification::create([
                        'user_id' => $userId,
                        'type'    => $notificationType,
                        'title'   => $title,
                        'message' => $message,
                        'data'    => [
                            'exam_id'          => $exam->id,
                            'organization_id'  => $exam->organization_id,
                            'next_exam_date'   => $exam->next_exam_date,
                            'days_left'        => $daysLeft,
                        ],
                    ]);
                }
            }
        }

        $this->info('Exam notifications checked successfully.');
    }
}
