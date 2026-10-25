<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_guest_visitors_are_sent_to_sign_in(): void
    {
        $this->get('/')
            ->assertRedirect(route('login'));
    }
}
