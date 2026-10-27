<?php

namespace App\Services\AI;

use App\Models\Conversation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiChatService
{
    public function reply(Conversation $conversation): string
    {
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model');

        if (! is_string($apiKey) || $apiKey === '') {
            Log::warning('Gemini configuration is incomplete.', [
                'missing_setting' => 'GEMINI_API_KEY',
            ]);

            throw new RuntimeException('Gemini is not configured. Add GEMINI_API_KEY to the local environment.');
        }

        if (! is_string($model) || $model === '') {
            Log::warning('Gemini configuration is incomplete.', [
                'missing_setting' => 'GEMINI_MODEL',
            ]);

            throw new RuntimeException('Gemini is not configured. Add GEMINI_MODEL to the local environment.');
        }

        $history = $conversation->messages()
            ->latest('id')
            ->limit(10)
            ->get()
            ->reverse()
            ->map(fn ($message) => [
                'role' => $message->role === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message->content]],
            ])
            ->values()
            ->all();

        if ($conversation->user->context_memory_enabled) {
            $pastMessages = $conversation->user->conversations()
                ->whereKeyNot($conversation->getKey())
                ->with(['messages' => fn ($query) => $query->whereIn('role', ['user', 'assistant'])->latest('id')->limit(3)])
                ->latest('last_message_at')
                ->limit(2)
                ->get()
                ->flatMap(fn ($pastConversation) => $pastConversation->messages->reverse())
                ->map(fn ($message) => [
                    'role' => $message->role === 'assistant' ? 'model' : 'user',
                    'parts' => [['text' => '[Earlier conversation context] '.$message->content]],
                ]);

            $history = [...$pastMessages->all(), ...$history];
        }

        $response = Http::timeout(30)
            ->acceptJson()
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'systemInstruction' => [
                    'parts' => [[
                        'text' => 'You are a supportive wellbeing assistant, not a clinician. Do not diagnose, prescribe, or claim to provide therapy. Encourage professional support when appropriate. If the user may be in immediate danger, tell them to contact local emergency services and someone they trust.',
                    ]],
                ],
                'contents' => $history,
                'generationConfig' => [
                    'temperature' => 0.4,
                    'maxOutputTokens' => 700,
                ],
            ]);

        if (! $response->successful()) {
            Log::warning('Gemini API request failed.', [
                'http_status' => $response->status(),
                'provider_code' => $response->json('error.code'),
                'provider_status' => $response->json('error.status'),
                'model' => $model,
            ]);

            throw new RuntimeException('Gemini could not complete the request. Please try again later.');
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text) || trim($text) === '') {
            Log::warning('Gemini API returned no text candidate.', [
                'model' => $model,
                'finish_reason' => $response->json('candidates.0.finishReason'),
                'block_reason' => $response->json('promptFeedback.blockReason'),
            ]);

            throw new RuntimeException('Gemini returned an empty response. Please try again.');
        }

        return trim($text);
    }
}
