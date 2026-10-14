<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pannello costi (richiesta utente, 13 ottobre 2026): a differenza di OpenAiTextService (chiave
 * di progetto, usata per generare contenuti), qui serve una Admin API key separata con scope
 * "api.usage.read" — l'unica che può leggere /v1/organization/costs (services.openai.admin_key,
 * vedi commento lì per come generarla).
 *
 * IMPORTANTE: OpenAI non espone, per le API key standard, un vero saldo prepagato residuo come
 * fal.ai (FalBillingService) — solo la spesa storica per bucket temporale. Quello che
 * restituiamo qui è la spesa del mese di calendario in corso confrontata con un budget "di
 * guardia" impostato a mano (services.openai.monthly_budget_usd), non un saldo reale.
 */
class OpenAiBillingService
{
    /**
     * @return array{configured: bool, spent_usd?: float, budget_usd?: float, remaining_usd?: float,
     *     pct_used?: ?float, period_start?: string, over_threshold?: bool, error?: string}
     */
    public function currentMonthSpend(): array
    {
        $adminKey = config('services.openai.admin_key');
        if (! $adminKey) {
            return ['configured' => false];
        }

        $startOfMonth = now('UTC')->startOfMonth();

        try {
            $totalUsd = 0.0;
            $cursor = null;

            // L'endpoint pagina per bucket temporali (fino a 180 per chiamata, "has_more"/
            // "next_page" per continuare) — un mese intero può superare una singola pagina.
            do {
                $response = Http::withToken($adminKey)
                    ->timeout(30)
                    ->get('https://api.openai.com/v1/organization/costs', array_filter([
                        'start_time' => $startOfMonth->timestamp,
                        'limit' => 180,
                        'page' => $cursor,
                    ]))
                    ->throw()
                    ->json();

                foreach ($response['data'] ?? [] as $bucket) {
                    foreach ($bucket['results'] ?? [] as $result) {
                        $totalUsd += (float) ($result['amount']['value'] ?? 0);
                    }
                }

                $cursor = ($response['has_more'] ?? false) ? ($response['next_page'] ?? null) : null;
            } while ($cursor);

            $spentUsd = round($totalUsd, 2);
            $budget = (float) config('services.openai.monthly_budget_usd');
            $thresholdPct = (float) config('services.openai.budget_alert_threshold_pct');

            return [
                'configured' => true,
                'spent_usd' => $spentUsd,
                'budget_usd' => $budget,
                'remaining_usd' => round($budget - $spentUsd, 2),
                'pct_used' => $budget > 0 ? round(($spentUsd / $budget) * 100, 1) : null,
                'period_start' => $startOfMonth->toDateString(),
                'over_threshold' => $budget > 0 && ($spentUsd / $budget) * 100 >= $thresholdPct,
            ];
        } catch (RuntimeException|\Illuminate\Http\Client\RequestException $e) {
            return ['configured' => true, 'error' => $e->getMessage()];
        }
    }
}
