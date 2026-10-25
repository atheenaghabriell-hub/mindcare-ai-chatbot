<?php

namespace Tests\Feature;

use App\Http\Requests\SendMessageRequest;
use App\Http\Requests\StoreMoodLogRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MindCareRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_requests_reject_empty_and_oversized_content(): void
    {
        $request = new SendMessageRequest;
        $validator = Validator::make([], $request->rules());
        $oversized = Validator::make(['content' => str_repeat('a', 4001)], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('content'));
        $this->assertTrue($oversized->fails());
    }

    public function test_mood_request_accepts_only_defined_scale_and_intensity(): void
    {
        $request = new StoreMoodLogRequest;
        $validator = Validator::make([
            'mood' => 'unknown',
            'intensity' => 9,
            'recorded_at' => now()->toDateTimeString(),
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('mood'));
        $this->assertTrue($validator->errors()->has('intensity'));
    }
}
