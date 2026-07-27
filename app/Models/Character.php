<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Character extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['tenant_id', 'name', 'one_liner', 'slug', 'status'];
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    public function bibleSections()
    {
        return $this->hasMany(CharacterBibleSection::class);
    }
    public function visualProfile()
    {
        return $this->hasOne(CharacterVisualProfile::class);
    }
    public function assets()
    {
        return $this->hasMany(CharacterAsset::class);
    }
    public function sceneElements()
    {
        return $this->belongsToMany(SceneElement::class, 'character_scene_elements');
    }
    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('role');
    }
    public function lifeEvents()
    {
        return $this->hasMany(LifeEvent::class);
    }
    public function timelineEntries()
    {
        return $this->hasMany(TimelineEntry::class);
    }
    public function generations()
    {
        return $this->hasMany(Generation::class);
    }
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
    public function socialAccount()
    {
        return $this->hasOne(\App\Models\SocialAccount::class)->where('platform', 'instagram');
    }
    public function characterState()
    {
        return $this->hasOne(CharacterState::class);
    }
    public function storylines()
    {
        return $this->hasMany(Storyline::class);
    }
    public function relationships()
    {
        return $this->hasMany(CharacterRelationship::class);
    }
    public function editorialSettings()
    {
        return $this->hasOne(CharacterEditorialSettings::class);
    }
    public function draft()
    {
        return $this->hasOne(CharacterDraft::class);
    }
    /** Parentele create da questo personaggio verso altri (es. Sofia -> Fernando "marito"). */
    public function kinships()
    {
        return $this->hasMany(CharacterKinship::class);
    }
    /** Parentele create da altri personaggi verso questo (per mostrare l'etichetta invertita). */
    public function kinshipsAsRelated()
    {
        return $this->hasMany(CharacterKinship::class, 'related_character_id');
    }
}
