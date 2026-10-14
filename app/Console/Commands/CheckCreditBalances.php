<?php
namespace App\Console\Commands;
use App\Mail\LowCreditBalanceMail;
use App\Services\FalBillingService;
use App\Services\OpenAiBillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Richiesta utente, 13 ottobre 2026: avviso via email quando i fondi di fal.ai/OpenAI si stanno
 * esaurendo — stessa pagina letta dal pannello saldo (CreditBalance), schedulata qui invece di
 * aspettare che qualcuno apra il pannello a mano (lo stesso motivo per cui esiste alerts.log,
 * sez. config/logging.php: un guasto silenzioso scoperto 5 giorni dopo, non il primo giorno).
 */
class CheckCreditBalances extends Command
{
    protected $signature = 'credits:check-balances';

    protected $description = 'Controlla il saldo fal.ai e la spesa OpenAI del mese, invia un avviso via email se sotto soglia';

    // Un solo avviso ogni tot ore mentre si resta sotto soglia: altrimenti ogni giro dello
    // scheduler (routes/console.php) manderebbe una mail nuova finché non si ricarica.
    private const ALERT_COOLDOWN_HOURS = 24;

    public function handle(FalBillingService $falBilling, OpenAiBillingService $openAiBilling): int
    {
        $fal = $falBilling->currentBalance();
        $openAi = $openAiBilling->currentMonthSpend();

        $falLow = ($fal['configured'] ?? false) && (($fal['below_threshold'] ?? false) || isset($fal['error']));
        $openAiOver = ($openAi['configured'] ?? false) && (($openAi['over_threshold'] ?? false) || isset($openAi['error']));

        if (! $falLow && ! $openAiOver) {
            $this->info('Saldo ok su entrambi i provider (o non configurati).');

            return self::SUCCESS;
        }

        $cacheKey = 'credits:low-balance-alert-sent-at';
        if (Cache::has($cacheKey)) {
            $this->comment('Soglia superata ma avviso già inviato nelle ultime ' . self::ALERT_COOLDOWN_HOURS . ' ore, salto.');

            return self::SUCCESS;
        }

        $to = config('mail.alert_to');
        if (! $to) {
            Log::error('credits:check-balances: soglia superata ma nessun MAIL_ALERT_TO configurato, impossibile avvisare.');
            $this->error('Nessun indirizzo di avviso configurato (MAIL_ALERT_TO nel .env).');

            return self::FAILURE;
        }

        Mail::to($to)->send(new LowCreditBalanceMail(
            $openAiOver ? $openAi : null,
            $falLow ? $fal : null,
        ));

        Cache::put($cacheKey, now()->toDateTimeString(), now()->addHours(self::ALERT_COOLDOWN_HOURS));

        Log::warning('credits:check-balances: avviso di saldo basso inviato a ' . $to, [
            'fal' => $falLow ? $fal : null,
            'openai' => $openAiOver ? $openAi : null,
        ]);

        $this->warn("Avviso inviato a {$to}.");

        return self::SUCCESS;
    }
}
