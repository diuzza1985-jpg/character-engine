<?php
namespace App\Filament\Resources\Characters\Pages;
use App\Filament\Resources\Characters\CharacterResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
class EditCharacter extends EditRecord
{
    protected static string $resource = CharacterResource::class;
    protected function getHeaderActions(): array
    {
        return [
            Action::make('collegaInstagram')
                ->label(fn () => $this->record->socialAccount?->status === 'connected'
                    ? 'Instagram collegato ✓ (@' . ($this->record->socialAccount->ig_user_id ?? '') . ')'
                    : 'Collega Instagram')
                ->icon('heroicon-o-link')
                ->color(fn () => $this->record->socialAccount?->status === 'connected' ? 'success' : 'primary')
                ->url(fn () => route('instagram.connect', $this->record))
                ->openUrlInNewTab(),
            ActionGroup::make([
                DeleteAction::make()
                    ->label('Elimina personaggio')
                    ->requiresConfirmation()
                    ->modalHeading(fn () => "Eliminare {$this->record->name}?")
                    ->modalDescription('Il personaggio verrà spostato nel cestino, NON cancellato per sempre: potrai ripristinarlo dalla lista personaggi filtrando per "Cestinati". Per una cancellazione davvero definitiva serve un\'azione separata, apposta più difficile da raggiungere per errore.')
                    ->modalSubmitActionLabel('Sì, sposta nel cestino'),
            ])
                ->label('Altre azioni')
                ->icon('heroicon-o-ellipsis-vertical')
                ->color('gray'),
        ];
    }
}
