<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Alcuni account (es. quelli creati prima che esistesse la registrazione pubblica, via
     * seeder o Filament) non hanno mai avuto un tenant assegnato — tenant_id è nullable da
     * schema. Prima del questionario di creazione personaggio, nessun percorso del codice si
     * aspettava un utente autenticato SENZA tenant; qui lo si crea al volo invece di andare in
     * errore fatale quando quell'utente prova a salvare un personaggio.
     */
    public function resolveOrCreateTenant(): Tenant
    {
        if ($this->tenant_id) {
            return $this->tenant;
        }

        // Un tenant con la stessa email potrebbe già esistere (creato per un'altra via, es.
        // admin Filament) senza mai essere stato collegato a questo utente — va riusato, non
        // duplicato: tenants.email ha un vincolo unique, un Tenant::create() diretto qui
        // fallirebbe con una violazione di integrità su qualunque account già noto al sistema
        // sotto la stessa email (scoperto in produzione: l'utente admin pre-esistente aveva
        // già un tenant "Claudia"/Sofia con la stessa email, mai collegato).
        $tenant = Tenant::firstOrCreate(
            ['email' => $this->email],
            ['name' => $this->name, 'plan_id' => null, 'status' => 'trial']
        );

        $this->update(['tenant_id' => $tenant->id]);

        return $tenant;
    }

    public function characters()
    {
        return $this->belongsToMany(Character::class)->withPivot('role');
    }
}
