<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Storyline extends Model
{
    use HasFactory;
    protected $fillable = [
        'character_id', 'title', 'status', 'importance',
        'started_at', 'last_updated_at', 'resolution_notes',
    ];
    protected function casts(): array
    {
        return [
            'importance' => 'float', 'started_at' => 'datetime', 'last_updated_at' => 'datetime',
        ];
    }
    public function character()
    {
        return $this->belongsTo(Character::class);
    }
    public function timelineEntries()
    {
        return $this->hasMany(TimelineEntry::class);
    }
    public function editorialDecisions()
    {
        return $this->hasMany(EditorialDecision::class);
    }

    public function daysSinceLastUpdate(): ?int
    {
        $reference = $this->last_updated_at ?? $this->started_at;
        return $reference ? $reference->diffInDays(now()) : null;
    }

    /**
     * Pressione a riprendere questa storyline (11.3), non persistita: sempre ricalcolata da importance/last_updated_at.
     */
    public function pressure(int $windowDays = 10): float
    {
        $daysSinceUpdate = $this->daysSinceLastUpdate();
        if ($daysSinceUpdate === null) {
            return 0.0;
        }
        return $this->importance * min(1, $daysSinceUpdate / $windowDays);
    }
}
