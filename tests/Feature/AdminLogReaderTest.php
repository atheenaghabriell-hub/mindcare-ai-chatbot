<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLogReaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_a_local_application_log(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $fileName = 'reader-test-'.bin2hex(random_bytes(8)).'.log';
        $path = storage_path('logs'.DIRECTORY_SEPARATOR.$fileName);
        file_put_contents($path, "Example error from the app\nSecond log entry");

        try {
            $this->actingAs($admin)
                ->get(route('admin.logs', ['file' => $fileName]))
                ->assertOk()
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertSeeText($fileName)
                ->assertSeeText('Example error from the app')
                ->assertSeeText('Second log entry');
        } finally {
            unlink($path);
        }
    }

    public function test_non_admin_cannot_view_application_logs(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('admin.logs'))
            ->assertForbidden();
    }

    public function test_log_reader_does_not_accept_a_file_outside_the_log_directory(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.logs', ['file' => '..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'.env']))
            ->assertNotFound();
    }
}
