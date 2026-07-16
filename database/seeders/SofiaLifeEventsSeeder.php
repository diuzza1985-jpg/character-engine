<?php
namespace Database\Seeders;
use App\Models\Character;
use App\Models\LifeEvent;
use Illuminate\Database\Seeder;
class SofiaLifeEventsSeeder extends Seeder
{
    public function run(): void
    {
        $character = Character::where('slug', 'sofia')->first();
        if (! $character) {
            $this->command->error("Personaggio 'sofia' non trovato, salto il seeding dei life_events.");
            return;
        }
        $events = [
            ['title' => 'Giornata di debug', 'category' => 'debug_day', 'scene_hint' => ['topic' => 'debug', 'location' => 'home_office', 'mood' => 'focused_tired', 'time_of_day' => 'afternoon', 'props' => ['laptop', 'coffee_mug']], 'mood_deltas' => ['energia' => -0.15, 'valenza' => -0.05, 'stress' => 0.15]],
            ['title' => "Test con l'IA", 'category' => 'ai_experiment', 'scene_hint' => ['topic' => 'ai_test', 'location' => 'home_office', 'mood' => 'curious', 'time_of_day' => 'afternoon', 'props' => ['laptop', 'notebook']], 'news_query' => 'intelligenza artificiale', 'mood_deltas' => ['energia' => 0.05, 'valenza' => 0.1, 'stress' => 0.05], 'can_start_storyline' => true],
            ['title' => 'Passeggiata nella natura', 'category' => 'nature_walk', 'scene_hint' => ['topic' => 'nature_walk', 'location' => 'lake_path', 'mood' => 'relaxed', 'time_of_day' => 'afternoon', 'props' => ['phone']], 'mood_deltas' => ['energia' => 0.1, 'valenza' => 0.15, 'stress' => -0.2]],
            ['title' => 'Progetto con Fernando', 'category' => 'fernando_project', 'scene_hint' => ['topic' => 'fernando_project', 'location' => 'workshop', 'mood' => 'excited', 'time_of_day' => 'afternoon', 'props' => ['fernando_project']], 'mood_deltas' => ['energia' => 0.05, 'valenza' => 0.15, 'stress' => 0.05, 'relationship_sentiment' => 0.15], 'can_start_storyline' => true, 'target_relationship' => 'Fernando'],
            ['title' => 'Serata in famiglia', 'category' => 'family_evening', 'scene_hint' => ['topic' => 'family_evening', 'location' => 'living_room', 'mood' => 'family_warm', 'time_of_day' => 'evening', 'props' => ['phone']], 'mood_deltas' => ['energia' => -0.05, 'valenza' => 0.2, 'stress' => -0.15, 'relationship_sentiment' => 0.2], 'target_relationship' => 'Famiglia'],
            ['title' => 'Giornata con un cliente', 'category' => 'client_work', 'scene_hint' => ['topic' => 'client_meeting', 'location' => 'home_office', 'mood' => 'professional_focused', 'time_of_day' => 'afternoon', 'props' => ['laptop', 'notebook']], 'mood_deltas' => ['energia' => -0.1, 'valenza' => 0.05, 'stress' => 0.15], 'can_start_storyline' => true],
            ['title' => 'Visita in libreria', 'category' => 'library_visit', 'scene_hint' => ['topic' => 'library', 'location' => 'library', 'mood' => 'curious', 'time_of_day' => 'afternoon', 'props' => ['kindle']], 'mood_deltas' => ['energia' => 0.05, 'valenza' => 0.1, 'stress' => -0.05]],
            ['title' => 'Passeggiata con Pippo', 'category' => 'dog_walk', 'scene_hint' => ['topic' => 'dog_walk', 'location' => 'small_town_street', 'mood' => 'happy', 'time_of_day' => 'morning', 'props' => ['walking_pippo']], 'mood_deltas' => ['energia' => 0.1, 'valenza' => 0.15, 'stress' => -0.1, 'relationship_sentiment' => 0.1], 'target_relationship' => 'Pippo'],
        ];
        foreach ($events as $event) {
            LifeEvent::updateOrCreate(
                ['character_id' => $character->id, 'title' => $event['title']],
                [
                    'category' => $event['category'],
                    'scene_hint' => $event['scene_hint'],
                    'weight' => 50,
                    'cooldown_days' => 10,
                    'news_query' => $event['news_query'] ?? null,
                    'news_category' => $event['news_category'] ?? null,
                    'mood_deltas' => $event['mood_deltas'] ?? null,
                    'can_start_storyline' => $event['can_start_storyline'] ?? false,
                    'target_relationship' => $event['target_relationship'] ?? null,
                ]
            );
        }
        $this->command->info('Life events di Sofia ricreati: ' . count($events));
    }
}
