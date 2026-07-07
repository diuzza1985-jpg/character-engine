<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditLedger extends Model
{
    use HasFactory;

    protected $table = 'credit_ledger';

    protected $fillable = [
        'tenant_id', 'amount', 'balance_after', 'type',
        'reference_type', 'reference_id', 'description',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
