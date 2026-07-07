<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Character extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'name', 'slug', 'status'];

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
        return $this->hasOne(SocialAccount::class);
    }
}
