<?php

namespace App\Services\Sentiment;

class SentimentService
{
    private const POSITIVE_TERMS = ['calm', 'good', 'grateful', 'happy', 'hopeful', 'okay', 'relieved'];

    private const NEGATIVE_TERMS = ['afraid', 'anxious', 'bad', 'down', 'lonely', 'sad', 'stressed', 'worried'];

    /**
     * This lightweight lexical signal is experimental and is not a diagnosis.
     *
     * @return array{label: string, score: float, analyzer: string}
     */
    public function analyze(string $text): array
    {
        $tokens = preg_split('/[^\p{L}\p{N}\']+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $positive = count(array_intersect($tokens, self::POSITIVE_TERMS));
        $negative = count(array_intersect($tokens, self::NEGATIVE_TERMS));
        $score = ($positive - $negative) / max(count($tokens), 1);

        return [
            'label' => $score > 0.02 ? 'positive' : ($score < -0.02 ? 'negative' : 'neutral'),
            'score' => max(-1, min(1, $score)),
            'analyzer' => 'lexicon-v1-experimental',
        ];
    }
}
