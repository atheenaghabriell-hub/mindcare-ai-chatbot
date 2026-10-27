<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\CrisisReviewEvent;
use App\Models\Message;
use App\Models\MoodLog;
use App\Models\User;
use App\Services\Safety\CrisisDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MindCareApplicationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_privacy_acknowledgement_and_signs_user_in(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'mindcare-password-123',
            'password_confirmation' => 'mindcare-password-123',
        ])->assertSessionHasErrors('privacy_acknowledged');

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'mindcare-password-123',
            'password_confirmation' => 'mindcare-password-123',
            'privacy_acknowledged' => '1',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_registration_accepts_a_nine_character_password_with_letters_and_numbers(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Nine Character User',
            'email' => 'nine@example.com',
            'password' => 'mindcare9',
            'password_confirmation' => 'mindcare9',
            'privacy_acknowledged' => '1',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_registration_rejects_passwords_shorter_than_nine_characters(): void
    {
        $this->from(route('register'))
            ->post(route('register.store'), [
                'name' => 'Short Password User',
                'email' => 'short@example.com',
                'password' => 'care1234',
                'password_confirmation' => 'care1234',
                'privacy_acknowledged' => '1',
            ])
            ->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_admin_sign_in_requires_an_existing_admin_account(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'admin-password-123',
            'is_admin' => true,
        ]);

        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Administrator sign in');

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'admin-password-123',
        ])->assertRedirect(route('admin.analytics'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_regular_account_cannot_use_admin_sign_in(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'user-password-123',
            'is_admin' => false,
        ]);

        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), [
                'email' => $user->email,
                'password' => 'user-password-123',
            ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_conversation_history_and_mood_entries_are_private_to_authenticated_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = Conversation::factory()->for($owner)->create();
        MoodLog::factory()->for($owner)->create();

        $this->actingAs($otherUser)
            ->get(route('conversations.show', $conversation))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($conversation->title);
    }

    public function test_conversation_history_uses_bootstrap_pagination(): void
    {
        $user = User::factory()->create();
        Conversation::factory()->count(16)->for($user)->create();

        $this->actingAs($user)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertSee('page-item', false)
            ->assertDontSee('flex items-center justify-between');
    }

    public function test_dashboard_renders_recent_mood_badge_and_chart_data(): void
    {
        $user = User::factory()->create();
        MoodLog::factory()->for($user)->create([
            'mood' => 'good',
            'intensity' => 4,
            'recorded_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('mood-trend-chart')
            ->assertSee('text-bg-success', false)
            ->assertSee('Good')
            ->assertSee('window.mindCareMoodData')
            ->assertSee('last 7 self-reported check-ins')
            ->assertSee('AI Reflection Insight')
            ->assertSee('The mood you selected most often in your recent check-ins is good.');
    }

    public function test_dashboard_shows_actionable_empty_states(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('No conversations yet.')
            ->assertSee('No mood entries recorded.')
            ->assertSee('Start your first conversation')
            ->assertSee('Record your first mood')
            ->assertSee('Your reflections are personal to you.');
    }

    public function test_dashboard_chart_excludes_mood_entries_older_than_the_latest_seven(): void
    {
        $user = User::factory()->create();
        $oldestEntry = now()->startOfDay()->subDays(8);

        for ($daysAgo = 8; $daysAgo >= 1; $daysAgo--) {
            MoodLog::factory()->for($user)->create([
                'mood' => 'neutral',
                'intensity' => 3,
                'recorded_at' => now()->startOfDay()->subDays($daysAgo),
            ]);
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee($oldestEntry->format('M j'))
            ->assertSee(now()->startOfDay()->subDay()->format('M j'));
    }

    public function test_message_is_encrypted_at_rest_and_gemini_requires_explicit_consent(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create();
        Http::preventStrayRequests();

        $this->actingAs($user)
            ->post(route('messages.store', $conversation), ['content' => 'I feel worried today.'])
            ->assertRedirect(route('settings.privacy'));

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
        ]);
        $this->assertNotSame('I feel worried today.', DB::table('messages')->value('content'));
        $this->assertDatabaseHas('sentiment_logs', ['label' => 'negative']);
    }

    public function test_crisis_phrase_skips_gemini_and_only_creates_review_metadata_after_opt_in(): void
    {
        config(['services.gemini.api_key' => 'test-key']);
        $user = User::factory()->create([
            'gemini_data_sharing_consented_at' => now(),
            'crisis_review_consented_at' => now(),
        ]);
        $conversation = Conversation::factory()->for($user)->create();
        Http::preventStrayRequests();

        $this->actingAs($user)
            ->post(route('messages.store', $conversation), ['content' => 'I want to kill myself'])
            ->assertRedirect(route('conversations.show', $conversation));

        $this->assertDatabaseHas('crisis_review_events', ['user_id' => $user->id]);
        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'role' => 'assistant']);
        $this->assertStringContainsString(
            'cannot contact emergency services',
            $conversation->messages()->where('role', 'assistant')->firstOrFail()->content
        );
    }

    public function test_common_crisis_phrase_shows_resources_without_gemini_consent(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create([
            'gemini_data_sharing_consented_at' => null,
            'crisis_review_consented_at' => null,
        ]);
        $conversation = Conversation::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('messages.store', $conversation), ['content' => 'I want to die'])
            ->assertRedirect(route('conversations.show', $conversation));

        $assistantMessage = $conversation->messages()->where('role', 'assistant')->firstOrFail();
        $this->assertStringContainsString('local emergency number', $assistantMessage->content);
        $this->assertDatabaseMissing('crisis_review_events', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('ai_usage_logs', ['user_id' => $user->id]);
    }

    public function test_crisis_detector_matches_supported_english_and_tagalog_phrases(): void
    {
        $detector = app(CrisisDetectionService::class);

        foreach (['I want to die', 'I wish I were dead', 'Ayoko nang mabuhay', 'Gusto ko nang mamatay'] as $phrase) {
            $this->assertTrue($detector->mayIndicateImmediateRisk($phrase), "Expected crisis phrase to match: {$phrase}");
        }

        $this->assertFalse($detector->mayIndicateImmediateRisk('I feel calm and safe today.'));
    }

    public function test_non_crisis_message_is_sent_to_gemini_after_consent(): void
    {
        config([
            'services.gemini.api_key' => 'test-key',
            'services.gemini.model' => 'test-model',
        ]);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Thank you for sharing that.']]],
                ]],
            ]),
        ]);

        $user = User::factory()->create(['gemini_data_sharing_consented_at' => now()]);
        $conversation = Conversation::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('messages.store', $conversation), ['content' => 'I feel calm today.'])
            ->assertRedirect(route('conversations.show', $conversation));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'test-model:generateContent')
            && $request->hasHeader('x-goog-api-key', 'test-key')
            && $request['contents'][0]['parts'][0]['text'] === 'I feel calm today.');
        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'role' => 'assistant']);
        $this->assertDatabaseHas('ai_usage_logs', ['user_id' => $user->id, 'status' => 'succeeded']);
    }

    public function test_failed_gemini_request_logs_provider_status_without_chat_content_or_credentials(): void
    {
        config([
            'services.gemini.api_key' => 'test-key',
            'services.gemini.model' => 'test-model',
        ]);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 429,
                    'status' => 'RESOURCE_EXHAUSTED',
                ],
            ], 429),
        ]);
        Log::spy();

        $user = User::factory()->create(['gemini_data_sharing_consented_at' => now()]);
        $conversation = Conversation::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('messages.store', $conversation), ['content' => 'Private test message'])
            ->assertRedirect(route('conversations.show', $conversation))
            ->assertSessionHasErrors('chat');

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Gemini API request failed.'
                && $context['http_status'] === 429
                && $context['provider_status'] === 'RESOURCE_EXHAUSTED'
                && $context['model'] === 'test-model'
                && ! array_key_exists('api_key', $context)
                && ! array_key_exists('prompt', $context))
            ->once();

        $this->assertDatabaseHas('ai_usage_logs', ['user_id' => $user->id, 'status' => 'failed']);
    }

    public function test_context_memory_is_opt_in_and_only_uses_the_same_users_prior_history(): void
    {
        config([
            'services.gemini.api_key' => 'test-key',
            'services.gemini.model' => 'test-model',
        ]);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'I remember you mentioned that.']]],
                ]],
            ]),
        ]);

        $user = User::factory()->create([
            'gemini_data_sharing_consented_at' => now(),
            'context_memory_enabled' => true,
        ]);
        $otherUser = User::factory()->create();
        $currentConversation = Conversation::factory()->for($user)->create();
        $pastConversation = Conversation::factory()->for($user)->create();
        $privateConversation = Conversation::factory()->for($otherUser)->create();
        $pastConversation->messages()->create(['role' => 'user', 'content' => 'I am preparing for my exams.']);
        $privateConversation->messages()->create(['role' => 'user', 'content' => 'other user private message']);

        $this->actingAs($user)
            ->post(route('messages.store', $currentConversation), ['content' => 'Can we continue?'])
            ->assertRedirect(route('conversations.show', $currentConversation));

        Http::assertSent(function ($request): bool {
            $texts = collect($request['contents'])
                ->flatMap(fn ($content) => collect($content['parts'])->pluck('text'));

            return $texts->contains('[Earlier conversation context] I am preparing for my exams.')
                && ! $texts->contains('other user private message');
        });
    }

    public function test_only_admin_can_view_aggregate_metrics_and_review_queue_excludes_identity(): void
    {
        $user = User::factory()->create();
        CrisisReviewEvent::query()->create([
            'user_id' => $user->id,
            'detected_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $this->actingAs($user)->get(route('admin.analytics'))->assertForbidden();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)
            ->get(route('admin.analytics'))
            ->assertOk()
            ->assertSee('Aggregate usage metrics')
            ->assertDontSee($user->email);

        $this->actingAs($admin)
            ->get(route('admin.safety-reviews'))
            ->assertOk()
            ->assertSee('Case ')
            ->assertDontSee($user->email);
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'view_safety_review_queue',
        ]);
    }

    public function test_expired_crisis_review_metadata_is_purged(): void
    {
        $event = CrisisReviewEvent::query()->create([
            'user_id' => User::factory()->create()->id,
            'detected_at' => now()->subDays(31),
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('mindcare:purge-expired-crisis-reviews')->assertSuccessful();

        $this->assertDatabaseMissing('crisis_review_events', ['id' => $event->id]);
    }

    public function test_user_can_delete_account_and_associated_records_after_password_confirmation(): void
    {
        $user = User::factory()->create(['password' => 'mindcare-password-123']);
        $conversation = Conversation::factory()->for($user)->create();
        Message::factory()->for($conversation)->create();
        MoodLog::factory()->for($user)->create();

        $this->actingAs($user)
            ->delete(route('settings.account.destroy'), ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        $this->delete(route('settings.account.destroy'), ['password' => 'mindcare-password-123'])
            ->assertRedirect(route('register'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
    }
}
