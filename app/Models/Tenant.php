<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Billable;

class Tenant extends Model
{
    use HasFactory, Billable;

    protected $fillable = ['name', 'email', 'plan_id', 'status'];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function characters()
    {
        return $this->hasMany(Character::class);
    }

    public function creditLedgerEntries()
    {
        return $this->hasMany(CreditLedger::class);
    }

    public function sceneElements()
    {
        return $this->hasMany(SceneElement::class);
    }

    public function creditBalance(): int
    {
        return (int) ($this->creditLedgerEntries()->latest('id')->value('balance_after') ?? 0);
    }
}
