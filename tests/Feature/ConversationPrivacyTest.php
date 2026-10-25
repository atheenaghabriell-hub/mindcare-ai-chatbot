<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MoodLog;
use App\Models\SentimentLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_only_view_their_own_conversations(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = Conversation::factory()->for($owner)->create();
        $policy = app(\App\Policies\ConversationPolicy::class);

        $this->assertTrue($policy->view($owner, $conversation));
        $this->assertFalse($policy->view($otherUser, $conversation));
    }

    public function test_archived_conversations_cannot_receive_messages(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create(['status' => 'archived']);

        $this->assertFalse(
            app(\App\Policies\ConversationPolicy::class)->sendMessage($user, $conversation)
        );
    }

    public function test_deleting_a_user_cascades_private_mindcare_data(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create();
        $message = Message::factory()->for($conversation)->create();
        $sentiment = SentimentLog::factory()->for($message)->create();
        $mood = MoodLog::factory()->for($user)->create();

        $user->delete();

        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
        $this->assertDatabaseMissing('sentiment_logs', ['id' => $sentiment->id]);
        $this->assertDatabaseMissing('mood_logs', ['id' => $mood->id]);
    }
}
