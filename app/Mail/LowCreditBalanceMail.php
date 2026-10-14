<?php
namespace App\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Avviso saldo (richiesta utente, 13 ottobre 2026): inviata da CheckCreditBalances quando
 * fal.ai scende sotto services.fal.low_balance_threshold_usd e/o la spesa OpenAI del mese
 * supera services.openai.budget_alert_threshold_pct del budget di guardia.
 */
class LowCreditBalanceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ?array $openAi,
        public readonly ?array $fal,
    ) {}

    public function build(): self
    {
        return $this->subject('⚠️ Saldo in esaurimento: ' . implode(' + ', array_filter([
                $this->openAi ? 'OpenAI' : null,
                $this->fal ? 'fal.ai' : null,
            ])))
            ->markdown('emails.low-credit-balance', [
                'openAi' => $this->openAi,
                'fal' => $this->fal,
            ]);
    }
}
