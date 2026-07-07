<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Generation extends Model
{
    use HasFactory;

    protected $fillable = [
        'character_id', 'tenant_id', 'purpose', 'status', 'input',
        'output', 'provider_costs', 'credits_charged', 'credit_ledger_id', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'provider_costs' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function character()
    {
        return $this->belongsTo(Character::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creditLedgerEntry()
    {
        return $this->belongsTo(CreditLedger::class, 'credit_ledger_id');
    }

    public function post()
    {
        return $this->hasOne(Post::class);
    }
}
