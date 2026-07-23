<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use RuntimeException;
class InstagramGraphService
{
    public function buildAuthorizationUrl(string $state): string
    {
        $params = [
            'client_id' => config('services.instagram.app_id'),
            'redirect_uri' => config('services.instagram.redirect_uri'),
            'scope' => 'instagram_business_basic,instagram_business_content_publish,instagram_business_manage_comments',
            'response_type' => 'code',
            'state' => $state,
        ];
        return 'https://api.instagram.com/oauth/authorize?' . http_build_query($params);
    }
    public function exchangeCodeForToken(string $code): array
    {
        $response = Http::asForm()->post('https://api.instagram.com/oauth/access_token', [
            'client_id' => config('services.instagram.app_id'),
            'client_secret' => config('services.instagram.app_secret'),
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.instagram.redirect_uri'),
            'code' => $code,
        ]);
        if ($response->failed()) {
            throw new RuntimeException('Scambio code->token fallito: ' . $response->body());
        }
        $data = $response->json();
        if (empty($data['access_token']) || empty($data['user_id'])) {
            throw new RuntimeException('Risposta token inattesa: ' . $response->body());
        }
        return $data;
    }
    public function exchangeForLongLivedToken(string $shortLivedToken): array
    {
        $response = Http::get('https://graph.instagram.com/access_token', [
            'grant_type' => 'ig_exchange_token',
            'client_secret' => config('services.instagram.app_secret'),
            'access_token' => $shortLivedToken,
        ]);
        if ($response->failed()) {
            throw new RuntimeException('Scambio short->long-lived fallito: ' . $response->body());
        }
        $data = $response->json();
        if (empty($data['access_token']) || empty($data['expires_in'])) {
            throw new RuntimeException('Risposta long-lived inattesa: ' . $response->body());
        }
        return $data;
    }
    public function refreshLongLivedToken(string $currentLongLivedToken): array
    {
        $response = Http::get('https://graph.instagram.com/refresh_access_token', [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $currentLongLivedToken,
        ]);
        if ($response->failed()) {
            throw new RuntimeException('Refresh token fallito: ' . $response->body());
        }
        return $response->json();
    }
    public function getProfile(string $accessToken): array
    {
        $response = Http::get('https://graph.instagram.com/v22.0/me', [
            'fields' => 'user_id,username,account_type',
            'access_token' => $accessToken,
        ]);
        if ($response->failed()) {
            throw new RuntimeException('Recupero profilo fallito: ' . $response->body());
        }
        return $response->json();
    }
    public function createMediaContainer(string $igUserId, string $accessToken, string $imageUrl, string $caption): array
    {
        $response = Http::asForm()->post("https://graph.instagram.com/v22.0/{$igUserId}/media", [
            'access_token' => $accessToken,
            'image_url' => $imageUrl,
            'caption' => $caption,
        ]);
        if ($response->failed()) {
            throw new RuntimeException('Creazione media container fallita: ' . $response->body());
        }
        return $response->json();
    }
    public function createStoryContainer(string $igUserId, string $accessToken, string $imageUrl): array
    {
        $response = Http::asForm()->post("https://graph.instagram.com/v22.0/{$igUserId}/media", [
            'access_token' => $accessToken,
            'image_url' => $imageUrl,
            'media_type' => 'STORIES',
        ]);
        if ($response->failed()) {
            throw new RuntimeException('Creazione story container fallita: ' . $response->body());
        }
        return $response->json();
    }

    public function createCarouselChildContainer(string $igUserId, string $accessToken, string $imageUrl): array
    {
        $response = Http::asForm()->post("https://graph.instagram.com/v22.0/{$igUserId}/media", [
            'access_token' => $accessToken,
            'image_url' => $imageUrl,
            'is_carousel_item' => 'true',
        ]);
        if ($response->failed()) {
            throw new RuntimeException('Creazione carousel child container fallita: ' . $response->body());
        }
        return $response->json();
    }
    public function createCarouselContainer(string $igUserId, string $accessToken, array $childrenIds, string $caption): array
    {
        $response = Http::asForm()->post("https://graph.instagram.com/v22.0/{$igUserId}/media", [
            'access_token' => $accessToken,
            'media_type' => 'CAROUSEL',
            'children' => implode(',', $childrenIds),
            'caption' => $caption,
        ]);
        if ($response->failed()) {
            throw new RuntimeException('Creazione carousel container fallita: ' . $response->body());
        }
        return $response->json();
    }
    public function getContainerStatus(string $containerId, string $accessToken): string
    {
        $response = Http::get("https://graph.instagram.com/v22.0/{$containerId}", [
            'fields' => 'status_code',
            'access_token' => $accessToken,
        ]);
        if ($response->failed()) {
            throw new RuntimeException('Lettura stato container fallita: ' . $response->body());
        }
        return $response->json('status_code', 'UNKNOWN');
    }
    public function publishContainer(string $igUserId, string $accessToken, string $containerId): array
    {
        $response = Http::asForm()->post("https://graph.instagram.com/v22.0/{$igUserId}/media_publish", [
            'access_token' => $accessToken,
            'creation_id' => $containerId,
        ]);
        if ($response->failed()) {
            throw new RuntimeException('Pubblicazione fallita: ' . $response->body());
        }
        return $response->json();
    }
    public function getPublishingLimit(string $igUserId, string $accessToken): array
    {
        $response = Http::get("https://graph.instagram.com/v22.0/{$igUserId}/content_publishing_limit", [
            'fields' => 'quota_usage,config',
            'access_token' => $accessToken,
        ]);
        if ($response->failed()) {
            throw new RuntimeException('Lettura quota fallita: ' . $response->body());
        }
        return $response->json();
    }
    /**
     * Legge la lista dei media pubblicati dall'account, direttamente da
     * Instagram (fonte di verità, non dipende dal database locale).
     */
    public function getUserMedia(string $igUserId, string $accessToken, int $limit = 25): array
    {
        $response = Http::get("https://graph.instagram.com/v22.0/{$igUserId}/media", [
            'fields' => 'id,caption,timestamp,media_type,permalink',
            'access_token' => $accessToken,
            'limit' => $limit,
        ]);
        if ($response->failed()) {
            throw new RuntimeException('Lettura media utente fallita: ' . $response->body());
        }
        return $response->json('data', []);
    }
    public function getMediaComments(string $mediaId, string $accessToken, int $limit = 50): array
    {
        $response = Http::get("https://graph.instagram.com/v22.0/{$mediaId}/comments", [
            'fields' => 'id,text,username,timestamp',
            'access_token' => $accessToken,
            'limit' => $limit,
        ]);
        if ($response->failed()) {
            throw new RuntimeException('Lettura commenti fallita: ' . $response->body());
        }
        return $response->json('data', []);
    }
    public function replyToComment(string $commentId, string $accessToken, string $message): array
    {
        $response = Http::asForm()->post("https://graph.instagram.com/v22.0/{$commentId}/replies", [
            'access_token' => $accessToken,
            'message' => $message,
        ]);
        if ($response->failed()) {
            throw new RuntimeException('Risposta al commento fallita: ' . $response->body());
        }
        return $response->json();
    }
}
