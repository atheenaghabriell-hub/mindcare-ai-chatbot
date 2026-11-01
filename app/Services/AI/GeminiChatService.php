<?php

namespace App\Services\AI;

use App\Models\Conversation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiChatService
{
    public function reply(Conversation $conversation, string $diagnosticId): string
    {
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model');

        if (! \is_string($apiKey) || $apiKey === '') {
            Log::warning('Gemini configuration is incomplete.', [
                'missing_setting' => 'GEMINI_API_KEY',
                'diagnostic_id' => $diagnosticId,
            ]);

            throw new GeminiChatException(
                'missing_api_key',
                'Gemini is not configured on the server (GEMINI_API_KEY is missing). Contact the administrator.',
            );
        }

        if (! \is_string($model) || $model === '') {
            Log::warning('Gemini configuration is incomplete.', [
                'missing_setting' => 'GEMINI_MODEL',
                'diagnostic_id' => $diagnosticId,
            ]);

            throw new GeminiChatException(
                'missing_model',
                'Gemini is not configured on the server (GEMINI_MODEL is missing). Contact the administrator.',
            );
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

        $generationConfig = [
            'maxOutputTokens' => 700,
        ];

        if (! str_starts_with($model, 'gemini-3.8-flash')) {
            $generationConfig['temperature'] = 0.4;
        }

        $response = $this->generateContent($apiKey, $model, $history, $generationConfig);

        if (! $response->successful()) {
            Log::warning('Gemini API request failed.', [
                'http_status' => $response->status(),
                'provider_code' => $response->json('error.code'),
                'provider_status' => $response->json('error.status'),
                'model' => $model,
                'diagnostic_id' => $diagnosticId,
            ]);

            throw $this->exceptionForStatus($response->status());
        }

        $text = collect($response->json('candidates.0.content.parts', []))
            ->filter(fn ($part) => \is_array($part)
                && \is_string($part['text'] ?? null)
                && ($part['thought'] ?? false) !== true)
            ->pluck('text')
            ->implode('');

        if (! \is_string($text) || trim($text) === '') {
            Log::warning('Gemini API returned no text candidate.', [
                'model' => $model,
                'finish_reason' => $response->json('candidates.0.finishReason'),
                'block_reason' => $response->json('promptFeedback.blockReason'),
                'diagnostic_id' => $diagnosticId,
            ]);

            throw new GeminiChatException(
                'empty_response',
                'Gemini returned no visible reply. Try rephrasing your message. If this keeps happening, contact the administrator.',
            );
        }

        return trim($text);
    }

    /**
     * @param  array<int, array{role: string, parts: array<int, array{text: string}>}>  $history
     * @param  array<string, int|float>  $generationConfig
     */
    private function generateContent(
        string $apiKey,
        string $model,
        array $history,
        array $generationConfig,
    ): Response {
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $response = Http::timeout(20)
                    ->acceptJson()
                    ->withHeaders(['x-goog-api-key' => $apiKey])
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                        'systemInstruction' => [
                            'parts' => [[
                                'text' => 'You are a supportive wellbeing assistant, not a clinician. Do not diagnose, prescribe, or claim to provide therapy. Encourage professional support when appropriate. If the user may be in immediate danger, tell them to contact local emergency services and someone they trust.',
                            ]],
                        ],
                        'contents' => $history,
                        'generationConfig' => $generationConfig,
                    ]);
            } catch (ConnectionException $exception) {
                if ($attempt === 2) {
                    throw new GeminiChatException(
                        'connection_failed',
                        'The server could not connect to Gemini after retrying. Check the service network and try again.',
                        $exception,
                    );
                }

                usleep(250_000);
                continue;
            }

            $retryableStatus = $response->status() === 408
                || $response->status() === 429
                || $response->serverError();

            if (! $retryableStatus || $attempt === 2) {
                return $response;
            }

            usleep(250_000);
        }

        throw new GeminiChatException(
            'missing_response',
            'Gemini did not return a response. Contact the administrator with the reference ID.',
        );
    }

    private function exceptionForStatus(int $status): GeminiChatException
    {
        [$failureCode, $message] = match ($status) {
            400 => [
                'invalid_request',
                'Gemini rejected the request. The administrator should check the configured model and request settings.',
            ],
            401, 403 => [
                'authentication_failed',
                'Gemini rejected the server credentials or API access. The administrator should verify the API key and Google project permissions.',
            ],
            404 => [
                'model_not_found',
                'The configured Gemini model was not found or is unavailable to this API key. The administrator should verify GEMINI_MODEL.',
            ],
            408 => [
                'provider_timeout',
                'Gemini timed out after a retry. Please try again shortly.',
            ],
            429 => [
                'quota_or_rate_limit',
                'Gemini rate limit or quota was reached. Please wait and try again; the administrator should check Google AI quota and billing.',
            ],
            default => $status >= 500
                ? [
                    'provider_unavailable',
                    'Gemini is temporarily unavailable after a retry. Please try again shortly.',
                ]
                : [
                    'provider_rejected_request',
                    "Gemini rejected the request (HTTP {$status}). Contact the administrator.",
                ],
        };

        return new GeminiChatException($failureCode, $message);
    }
}
