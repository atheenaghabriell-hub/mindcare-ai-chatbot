<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendMessageRequest;
use App\Models\Conversation;
use App\Models\CrisisReviewEvent;
use App\Services\AI\GeminiChatService;
use App\Services\AI\GeminiChatException;
use App\Services\Safety\CrisisDetectionService;
use App\Services\Sentiment\SentimentService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class MessageController extends Controller
{
    public function store(
        SendMessageRequest $request,
        Conversation $conversation,
        CrisisDetectionService $crisisDetection,
        SentimentService $sentiment,
        GeminiChatService $gemini,
    ): JsonResponse|RedirectResponse {
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

            $crisisReply = $crisisDetection->response();
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $crisisReply,
            ]);
            $conversation->update(['last_message_at' => now()]);

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'reply' => $crisisReply]);
            }

            return redirect()->route('conversations.show', $conversation)
                ->with('status', 'Support information is available below. This chat does not contact emergency services.');
        }

        if (! $request->user()->hasConsentedToGeminiSharing()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Your message was saved but not sent to Gemini. Review your privacy settings to opt in before using AI chat.',
                ], 403);
            }

            return redirect()->route('settings.privacy')
                ->with('status', 'Your message was saved, but not sent to Gemini. Review the privacy settings to opt in before using AI chat.');
        }

        $startedAt = hrtime(true);
        $diagnosticId = (string) Str::uuid();

        try {
            $reply = $gemini->reply($conversation, $diagnosticId);
        } catch (RuntimeException|ConnectionException $exception) {
            $this->recordUsage($request, $content, null, 'failed', $startedAt);

            $failureCode = $exception instanceof GeminiChatException
                ? $exception->failureCode
                : ($exception instanceof ConnectionException ? 'connection_failed' : 'unexpected_error');

            Log::warning('Gemini response failed.', [
                'conversation_id' => (string) $conversation->getKey(),
                'exception' => $exception::class,
                'failure_code' => $failureCode,
                'diagnostic_id' => $diagnosticId,
            ]);

            $errorMessage = $exception instanceof GeminiChatException
                ? $exception->getMessage()
                : ($exception instanceof ConnectionException
                    ? 'The server could not connect to Gemini after retrying. Check the service network and try again.'
                    : 'The server encountered an unexpected error while generating a reply.');

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => $errorMessage.' Reference ID: '.$diagnosticId,
                    'diagnostic_id' => $diagnosticId,
                ], 502);
            }

            return redirect()->route('conversations.show', $conversation)
                ->withErrors(['chat' => $errorMessage.' Reference ID: '.$diagnosticId]);
        }

        $this->recordUsage($request, $content, $reply, 'succeeded', $startedAt);
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $reply,
        ]);

        $conversation->update(['last_message_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'reply' => $reply]);
        }

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
