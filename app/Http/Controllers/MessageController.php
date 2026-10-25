<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendMessageRequest;
use App\Models\Conversation;
use App\Models\CrisisReviewEvent;
use App\Services\AI\GeminiChatService;
use App\Services\Safety\CrisisDetectionService;
use App\Services\Sentiment\SentimentService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MessageController extends Controller
{
    public function store(
        SendMessageRequest $request,
        Conversation $conversation,
        CrisisDetectionService $crisisDetection,
        SentimentService $sentiment,
        GeminiChatService $gemini,
    ): RedirectResponse {
        $content = $request->validated('content');
        $message = $conversation->messages()->create([
            'role' => 'user',
            'content' => $content,
        ]);
        $conversation->update(['last_message_at' => now()]);

        $analysis = $sentiment->analyze($content);
        $message->sentimentLog()->create($analysis + ['analyzed_at' => now()]);

        if ($crisisDetection->mayIndicateImmediateRisk($content)) {
            if ($request->user()->crisis_review_consented_at !== null) {
                CrisisReviewEvent::query()->create([
                    'user_id' => $request->user()->id,
                    'detected_at' => now(),
                    'expires_at' => now()->addDays(30),
                ]);
            }

            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $crisisDetection->response(),
            ]);
            $conversation->update(['last_message_at' => now()]);

            return redirect()->route('conversations.show', $conversation)
                ->with('status', 'Support information is available below. This chat does not contact emergency services.');
        }

        if (! $request->user()->hasConsentedToGeminiSharing()) {
            return redirect()->route('settings.privacy')
                ->with('status', 'Your message was saved, but not sent to Gemini. Review the privacy settings to opt in before using AI chat.');
        }

        $startedAt = hrtime(true);

        try {
            $reply = $gemini->reply($conversation);
        } catch (RuntimeException|ConnectionException $exception) {
            $this->recordUsage($request, $content, null, 'failed', $startedAt);
            Log::warning('Gemini response failed.', [
                'conversation_id' => $conversation->id,
                'exception' => $exception::class,
            ]);

            return redirect()->route('conversations.show', $conversation)
                ->withErrors(['chat' => 'Your message was saved, but the AI reply failed. Please try again later.']);
        }

        $this->recordUsage($request, $content, $reply, 'succeeded', $startedAt);
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $reply,
        ]);

        $conversation->update(['last_message_at' => now()]);

        return redirect()->route('conversations.show', $conversation);
    }

    private function recordUsage(
        SendMessageRequest $request,
        string $prompt,
        ?string $reply,
        string $status,
        int $startedAt,
    ): void {
        $request->user()->aiUsageLogs()->create([
            'model' => config('services.gemini.model') ?: 'unconfigured',
            'status' => $status,
            'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
            'input_characters' => mb_strlen($prompt),
            'output_characters' => $reply === null ? null : mb_strlen($reply),
            'context_memory_used' => (bool) $request->user()->context_memory_enabled,
            'occurred_at' => now(),
        ]);
    }
}
