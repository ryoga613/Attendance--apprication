<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\ProposalBreak;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {

        $user1 = User::where('email', 'user1@example.com')->first();
        $user2 = User::where('email', 'user2@example.com')->first();
        $user3 = User::where('email', 'user3@example.com')->first();

        foreach ([$user1, $user2, $user3] as $user) {
            $this->seedPastMonths($user);
            $this->seedCurrentMonthPattern($user);
        }
    }

    private function seedPastMonths(User $user): void
    {
        $daysForWork = 15;
        $monthsForWork = 6;

        for ($monthsAgo = $monthsForWork; $monthsAgo > 0; $monthsAgo--) {
            $monthStart = Carbon::today()->subMonths($monthsAgo)->startOfMonth();

            $weekdayCount = 0;
            $date = $monthStart->copy();

            while ($weekdayCount < $daysForWork) {
                if ($date->isWeekday()) {
                    $attendance = Attendance::create([
                        'user_id' => $user->id,
                        'work_date' => $date->toDateString(),
                        'clock_in_at' => $date->copy()->setTime(9, 0, 0),
                        'clock_out_at' => $date->copy()->setTime(18, 0, 0),
                    ]);
                    $weekdayCount++;
                }
                $date->addDay();
            }

        }
    }

    private function seedCurrentMonthPattern(User $user): void
    {

        $date = Carbon::today()->startOfMonth();

        $patternTypes = [
            ['09:00', '18:00', 10], // 通常
            ['09:00', '20:00', 3],  // 残業
            ['09:30', '18:00', 2],  // 遅刻
            ['09:00', '17:00', 1],  // 早退
            ['08:00', '21:00', 1],  // 長時間労働
        ];

        $date = Carbon::today()->startOfMonth();

        // パターンの種類ぶん(5種類)繰り返す
        foreach ($patternTypes as [$clockIn, $clockOut, $days]) {

            $createdCount = 0;

            while ($createdCount < $days) {

                // 平日じゃなければ、日付だけ進めてこの回はスキップ
                if ($date->isWeekday()) {
                    $attendance = Attendance::create([
                        'user_id' => $user->id,
                        'work_date' => $date->toDateString(),
                        'clock_in_at' => $date->copy()->setTimeFromTimeString($clockIn),
                        'clock_out_at' => $date->copy()->setTimeFromTimeString($clockOut),
                    ]);

                    ProposalBreak::create([
                        'attendance_id' => $attendance->id,
                        'break_start_at' => $date->copy()->setTimeFromTimeString('12:00'),
                        'break_end_at' => $date->copy()->setTimeFromTimeString('13:00'),
                    ]);
                    $createdCount++;
                    $date->addDay();

                    continue;
                }
                $date->addDay();
            }

        }
    }
}
