<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // The dashboard is behind auth; a guest is redirected to the login page.
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }
}
