<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class EditorialDecision extends Model
{
    use HasFactory;
    protected $fillable = ['generation_id', 'decisione', 'motivazione', 'storyline_id', 'used_news'];
    protected function casts(): array
    {
        return ['used_news' => 'boolean'];
    }
    public function generation()
    {
        return $this->belongsTo(Generation::class);
    }
    public function storyline()
    {
        return $this->belongsTo(Storyline::class);
    }
}
