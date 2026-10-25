<?php

namespace App\Services\Safety;

class CrisisDetectionService
{
    private const PATTERNS = [
        '/\b(kill myself|suicid(?:e|al)|want to die|wanna die|wish (?:i|I) (?:was|were) dead|better off dead|end my life|end it all|take my own life|hurt myself|self[- ]harm)\b/i',
        '/\b(?:i don[\'’]?t want to (?:live|be alive)|i can[\'’]?t go on|i(?: am|\'m) not worth living)\b/i',
        '/\b(magpakamatay|magpapakamatay|ayoko na(?:ng)? mabuhay|gusto ko(?: na)?ng mamatay|sasaktan ko ang sarili ko|saktan ang sarili)\b/i',
    ];

    public function mayIndicateImmediateRisk(string $text): bool
    {
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    public function response(): string
    {
        return 'I’m sorry you’re facing this. I’m an AI tool, not an emergency service or therapist. '
            .'If you may be in immediate danger, contact your local emergency number or go to the nearest emergency department now. '
            .'If possible, reach out to someone you trust and stay with them. You can also contact a local crisis-support service. '
            .'This chat cannot contact emergency services or guarantee that anyone is monitoring it.';
    }
}
