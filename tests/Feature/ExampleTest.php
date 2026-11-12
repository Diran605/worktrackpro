<?php

namespace Tests\Feature;

use App\Models\Organisation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * "/" is the documentation library of the default organisation.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        Organisation::factory()->create();

        $this->get('/')->assertStatus(200);
    }
}
