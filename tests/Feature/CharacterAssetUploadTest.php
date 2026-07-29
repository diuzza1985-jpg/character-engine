<?php

namespace Tests\Feature;

use App\Livewire\CharacterDetail;
use App\Models\AssetUploadConsent;
use App\Models\Character;
use App\Models\CharacterAsset;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AssetUploadConsentText;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CharacterAssetUploadTest extends TestCase
{
    use DatabaseTransactions;

    public function test_uploading_a_photo_creates_an_asset_and_an_auditable_consent_event(): void
    {
        Storage::fake('local');
        $tenant = Tenant::create(['name' => 'Upload Test', 'email' => 'upload-test@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        $character = Character::create(['tenant_id' => $tenant->id, 'name' => 'Comparsa', 'one_liner' => 'Test', 'slug' => 'comparsa-' . uniqid(), 'status' => 'draft']);

        Livewire::actingAs($user)->test(CharacterDetail::class, ['character' => $character])
            ->set('newAssetType', 'face')
            ->set('newAssetFile', UploadedFile::fake()->image('foto.jpg'))
            ->set('newAssetConsent', true)
            ->call('uploadAsset');

        $asset = CharacterAsset::where('character_id', $character->id)->first();
        $this->assertNotNull($asset);
        $this->assertSame('user_uploaded', $asset->source);
        $this->assertSame('face', $asset->type);
        Storage::disk('local')->assertExists($asset->file_path);

        $consent = AssetUploadConsent::where('character_asset_id', $asset->id)->first();
        $this->assertNotNull($consent);
        $this->assertSame($user->id, $consent->user_id);
        $this->assertSame(AssetUploadConsentText::VERSION, $consent->consent_text_version);
        $this->assertNotNull($consent->accepted_at);
    }

    public function test_uploading_without_accepting_consent_is_rejected_and_creates_nothing(): void
    {
        Storage::fake('local');
        $tenant = Tenant::create(['name' => 'No Consent Test', 'email' => 'no-consent-test@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        $character = Character::create(['tenant_id' => $tenant->id, 'name' => 'Comparsa', 'one_liner' => 'Test', 'slug' => 'comparsa-' . uniqid(), 'status' => 'draft']);

        Livewire::actingAs($user)->test(CharacterDetail::class, ['character' => $character])
            ->set('newAssetType', 'face')
            ->set('newAssetFile', UploadedFile::fake()->image('foto.jpg'))
            ->set('newAssetConsent', false)
            ->call('uploadAsset')
            ->assertHasErrors(['newAssetConsent']);

        $this->assertSame(0, CharacterAsset::where('character_id', $character->id)->count());
        $this->assertSame(0, AssetUploadConsent::count());
    }

    public function test_uploading_without_a_type_is_rejected(): void
    {
        Storage::fake('local');
        $tenant = Tenant::create(['name' => 'No Type Test', 'email' => 'no-type-test@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        $character = Character::create(['tenant_id' => $tenant->id, 'name' => 'Comparsa', 'one_liner' => 'Test', 'slug' => 'comparsa-' . uniqid(), 'status' => 'draft']);

        Livewire::actingAs($user)->test(CharacterDetail::class, ['character' => $character])
            ->set('newAssetType', '')
            ->set('newAssetFile', UploadedFile::fake()->image('foto.jpg'))
            ->set('newAssetConsent', true)
            ->call('uploadAsset')
            ->assertHasErrors(['newAssetType']);

        $this->assertSame(0, CharacterAsset::where('character_id', $character->id)->count());
    }

    public function test_cannot_open_a_character_belonging_to_another_tenant(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A', 'email' => 'tenant-a@example.com', 'status' => 'trial']);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'email' => 'tenant-b@example.com', 'status' => 'trial']);
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'owner']);
        $characterB = Character::create(['tenant_id' => $tenantB->id, 'name' => 'Non Tuo', 'slug' => 'non-tuo-' . uniqid(), 'status' => 'draft']);

        Livewire::actingAs($userA)->test(CharacterDetail::class, ['character' => $characterB])
            ->assertStatus(403);
    }

    public function test_pre_existing_assets_default_to_ai_generated_source(): void
    {
        $tenant = Tenant::create(['name' => 'Default Source Test', 'email' => 'default-source-test@example.com', 'status' => 'trial']);
        $character = Character::create(['tenant_id' => $tenant->id, 'name' => 'Legacy', 'slug' => 'legacy-' . uniqid(), 'status' => 'draft']);

        $asset = CharacterAsset::create([
            'character_id' => $character->id,
            'type' => 'face',
            'file_path' => 'characters/1/1/legacy.png',
        ]);

        $this->assertSame('ai_generated', $asset->fresh()->source);
    }
}
