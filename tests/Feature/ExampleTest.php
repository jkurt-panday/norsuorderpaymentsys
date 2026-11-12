<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_returns_the_landing_page(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page->component('welcome'));
    }
}
