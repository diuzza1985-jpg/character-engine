<?php
namespace App\Console\Commands;
use App\Models\ScheduledPost;
use App\Models\WeeklyScheduleSlot;
use Carbon\Carbon;
use Illuminate\Console\Command;
class MaterializeWeeklySchedule extends Command
{
    protected $signature = 'schedule:materialize-weekly {--days=1 : Quanti giorni in avanti materializzare, a partire da oggi}';
    protected $description = 'Trasforma gli slot ricorrenti settimanali (weekly_schedule_slots) in righe concrete su scheduled_posts per i prossimi giorni';
    public function handle(): int
    {
        $daysAhead = (int) $this->option('days');
        $slots = WeeklyScheduleSlot::where('enabled', true)->get();
        if ($slots->isEmpty()) {
            $this->info('Nessuno slot settimanale abilitato.');
            return self::SUCCESS;
        }
        $created = 0;
        for ($i = 0; $i < $daysAhead; $i++) {
            $date = Carbon::today()->addDays($i);
            $dayOfWeek = $date->dayOfWeek;
            foreach ($slots->where('day_of_week', $dayOfWeek) as $slot) {
                $scheduledAt = $date->copy()->setTimeFromTimeString($slot->time_of_day);
                $exists = ScheduledPost::where('weekly_schedule_slot_id', $slot->id)
                    ->whereDate('scheduled_at', $date->toDateString())
                    ->exists();
                if ($exists) {
                    continue;
                }
                ScheduledPost::create([
                    'character_id' => $slot->character_id,
                    'weekly_schedule_slot_id' => $slot->id,
                    'post_type' => $slot->post_type,
                    'mode' => $slot->mode,
                    'life_event_id' => $slot->life_event_id,
                    'instructions' => $slot->instructions,
                    'scheduled_at' => $scheduledAt,
                    'status' => 'pending',
                ]);
                $created++;
            }
        }
        $this->info("Creati {$created} scheduled_posts da slot ricorrenti.");
        return self::SUCCESS;
    }
}
