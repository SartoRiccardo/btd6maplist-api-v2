<?php

namespace Tests\Feature\Search;

use Tests\TestCase;

class ValidationTest extends TestCase
{
    #[Group('get')]
    #[Group('search')]
    #[Group('validation')]
    public function test_search_requires_q_parameter(): void
    {
        $this->getJson('/api/search')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    #[Group('get')]
    #[Group('search')]
    #[Group('validation')]
    public function test_search_q_parameter_minimum_length(): void
    {
        $this->getJson('/api/search?q=ab')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['q']);

        $this->getJson('/api/search?q=' . urlencode('a b '))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    #[Group('get')]
    #[Group('search')]
    #[Group('validation')]
    public function test_search_validates_entities(): void
    {
        $this->getJson('/api/search?q=test&entities=posts')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['entities']);

        $this->getJson('/api/search?q=test&entities=users,posts')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['entities']);
    }

    #[Group('get')]
    #[Group('search')]
    #[Group('validation')]
    public function test_search_enforces_limit_max(): void
    {
        $this->getJson('/api/search?q=test&limit=11')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    }
}
