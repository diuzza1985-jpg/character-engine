<?php
namespace App\Filament\Pages;
use App\Services\FalBillingService;
use App\Services\OpenAiBillingService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Richiesta utente, 13 ottobre 2026: pagina di sola lettura con il saldo fal.ai e la spesa
 * OpenAI del mese — stessi servizi usati da CheckCreditBalances per l'avviso via email.
 */
class CreditBalance extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Saldo crediti';

    protected static ?string $title = 'Saldo crediti';

    protected static ?string $slug = 'saldo-crediti';

    protected string $view = 'filament.pages.credit-balance';

    public array $openAi = [];

    public array $fal = [];

    public function mount(OpenAiBillingService $openAiBilling, FalBillingService $falBilling): void
    {
        $this->openAi = $openAiBilling->currentMonthSpend();
        $this->fal = $falBilling->currentBalance();
    }
}
