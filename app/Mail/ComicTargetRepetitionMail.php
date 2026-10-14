<?php
namespace App\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Inviata da CheckComicTargets quando un bersaglio comico supera la soglia di ripetizioni
 * nella finestra di config/comic_targets.php.
 */
class ComicTargetRepetitionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $characterName,
        public readonly Collection $repetitions,
        public readonly int $windowDays,
    ) {}

    public function build(): self
    {
        return $this->subject("⚠️ {$this->characterName} ripete gli stessi bersagli comici")
            ->markdown('emails.comic-target-repetition');
    }
}
