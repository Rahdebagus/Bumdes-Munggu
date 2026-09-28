<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Root mengarahkan ke dashboard (lalu ke login bila tamu).
     */
    public function test_root_redirects(): void
    {
        $this->get('/')->assertRedirect();
    }
}
