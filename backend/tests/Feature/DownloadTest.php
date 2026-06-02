<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_register(): void
    {
        $response = $this->postJson('/api/register-guest');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'guest_token',
                'is_premium',
                'preferences',
            ]);
    }

    public function test_guest_can_submit_download(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders([
            'X-Guest-Token' => $user->guest_token,
        ])->postJson('/api/download', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'type' => 'video',
            'quality' => '1080',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'download' => [
                    'id',
                    'original_url',
                    'status',
                ],
            ]);
    }

    public function test_duplicate_download_returns_existing(): void
    {
        $user = User::factory()->create();

        // First download
        $this->withHeaders([
            'X-Guest-Token' => $user->guest_token,
        ])->postJson('/api/download', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        // Mark as completed (simulate)
        $download = \App\Models\Download::first();
        $download->update(['status' => \App\Models\Download::STATUS_COMPLETED]);

        // Second download (duplicate)
        $response = $this->withHeaders([
            'X-Guest-Token' => $user->guest_token,
        ])->postJson('/api/download', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'is_duplicate' => true,
            ]);
    }

    public function test_rate_limit_exceeded(): void
    {
        $user = User::factory()->create();

        // Create max pending downloads (10 for free users)
        for ($i = 0; $i < 10; $i++) {
            \App\Models\Download::create([
                'user_id' => $user->id,
                'original_url' => "https://example.com/video{$i}",
                'title' => "Video {$i}",
                'status' => \App\Models\Download::STATUS_PROCESSING,
            ]);
        }

        $response = $this->withHeaders([
            'X-Guest-Token' => $user->guest_token,
        ])->postJson('/api/download', [
            'url' => 'https://www.youtube.com/watch?v=newvideo',
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'error' => 'Rate limit exceeded',
            ]);
    }

    public function test_unauthorized_without_guest_token(): void
    {
        $response = $this->postJson('/api/download', [
            'url' => 'https://www.youtube.com/watch?v=test',
        ]);

        $response->assertStatus(401);
    }

    public function test_share_page_is_public(): void
    {
        $download = \App\Models\Download::factory()->create([
            'status' => \App\Models\Download::STATUS_COMPLETED,
        ]);

        $response = $this->getJson("/api/share/{$download->share_token}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'download' => [
                    'id',
                    'title',
                    'type',
                ],
                'stream_url',
            ]);
    }
}
