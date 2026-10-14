<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pannello saldo (richiesta utente, 13 ottobre 2026): usa una chiave fal.ai SEPARATA da
 * FAL_API_KEY (services.fal.billing_key, vedi commento lì per come generarla) — l'unica con lo
 * scope "billing:usage:read" necessario per GET /v1/account/billing. A differenza di OpenAI,
 * qui esiste davvero un saldo residuo prepagato (current_balance), non solo spesa storica.
 */
class FalBillingService
{
    /**
     * @return array{configured: bool, balance_usd?: float, below_threshold?: bool, error?: string}
     */
    public function currentBalance(): array
    {
        $billingKey = config('services.fal.billing_key');
        if (! $billingKey) {
            return ['configured' => false];
        }

        try {
            $response = Http::withHeaders(['Authorization' => 'Key ' . $billingKey])
                ->timeout(30)
                ->get('https://api.fal.ai/v1/account/billing', ['expand' => 'credits'])
                ->throw()
                ->json();

            $balance = $response['credits']['current_balance'] ?? null;
            if ($balance === null) {
                throw new RuntimeException('Risposta fal.ai senza credits.current_balance: ' . json_encode($response));
            }

            $threshold = (float) config('services.fal.low_balance_threshold_usd');

            return [
                'configured' => true,
                'balance_usd' => (float) $balance,
                'below_threshold' => (float) $balance <= $threshold,
            ];
        } catch (RuntimeException|\Illuminate\Http\Client\RequestException $e) {
            return ['configured' => true, 'error' => $e->getMessage()];
        }
    }
}
