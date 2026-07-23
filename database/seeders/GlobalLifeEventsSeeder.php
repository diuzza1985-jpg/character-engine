<?php

namespace Database\Seeders;

use App\Models\LifeEvent;
use Illuminate\Database\Seeder;

class GlobalLifeEventsSeeder extends Seeder
{
    public function run(): void
    {
        $events = [
            [
                'title' => 'Aperitivo in centro',
                'scene_hint' => [
                    'location' => 'coffee_bar',
                    'activity' => 'aperitivo',
                    'mood' => 'family_warm',
                    'lighting' => 'golden_hour',
                    'props' => ['spritz_glass', 'phone'],
                ],
            ],
            [
                'title' => 'Pomeriggio di shopping',
                'scene_hint' => [
                    'location' => 'small_town_street',
                    'activity' => 'shopping',
                    'mood' => 'curious',
                    'lighting' => 'afternoon',
                    'props' => ['shopping_list', 'backpack'],
                ],
            ],
            [
                'title' => 'Pausa lettura in giardino',
                'scene_hint' => [
                    'location' => 'garden',
                    'activity' => 'reading',
                    'mood' => 'reflective',
                    'lighting' => 'afternoon',
                    'props' => ['kindle', 'coffee_mug'],
                ],
            ],
            [
                'title' => 'Pausa caffè sul balcone',
                'scene_hint' => [
                    'location' => 'balcony',
                    'activity' => 'coffee_break',
                    'mood' => 'tired_but_smiling',
                    'lighting' => 'morning',
                    'props' => ['coffee_mug', 'phone'],
                ],
            ],
        ];

        foreach ($events as $event) {
            LifeEvent::updateOrCreate(
                [
                    'character_id' => null,
                    'category' => 'daily_topic',
                    'title' => $event['title'],
                ],
                [
                    'weight' => 50,
                    'cooldown_days' => 10,
                    'scene_hint' => $event['scene_hint'],
                ]
            );
        }

        $this->command->info('Seeded ' . count($events) . ' life_event globali (character_id = null).');
    }
}
