<?php
namespace App\Console\Commands;
use App\Jobs\ProcessScheduledPostJob;
use App\Models\ScheduledPost;
use Illuminate\Console\Command;
use Throwable;
class ProcessDueScheduledPosts extends Command
{
    protected $signature = 'schedule:process-due';
    protected $description = 'Elabora e pubblica tutti gli scheduled_posts pending la cui data/ora è arrivata';
    public function handle(): int
    {
        $due = ScheduledPost::where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->get();
        if ($due->isEmpty()) {
            $this->info('Nessun post da elaborare al momento.');
            return self::SUCCESS;
        }
        foreach ($due as $scheduledPost) {
            $this->info("Elaboro scheduled_post #{$scheduledPost->id} (character_id={$scheduledPost->character_id}, post_type={$scheduledPost->post_type})...");
            try {
                ProcessScheduledPostJob::dispatchSync($scheduledPost->id);
                $this->info(' -> completato.');
            } catch (Throwable $e) {
                $this->error(" -> fallito: {$e->getMessage()}");
            }
        }
        return self::SUCCESS;
    }
}
