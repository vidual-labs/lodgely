<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadSearchEscapingTest extends TestCase
{
    use RefreshDatabase;

    private function makeLead(array $overrides = []): Lead
    {
        Tenant::firstOrCreate(['id' => Tenant::DEFAULT_ID], ['slug' => 'default', 'name' => 'lodgely']);

        return Lead::create(array_merge([
            'tenant_id' => Tenant::DEFAULT_ID,
            'source'    => 'manual',
            'full_name' => 'Jane Doe',
            'email'     => 'jane@example.com',
            'status'    => 'new',
            'priority'  => 'medium',
        ], $overrides));
    }

    public function test_percent_is_matched_literally(): void
    {
        $this->makeLead(['message' => 'Get 100% satisfaction']);
        $this->makeLead(['email' => 'other@example.com', 'message' => 'Contains 100 but no percent sign']);

        $results = Lead::query()->search('100%')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Get 100% satisfaction', $results->first()->message);
    }

    public function test_underscore_is_matched_literally(): void
    {
        $this->makeLead(['campaign_name' => 'spring_sale']);
        $this->makeLead(['email' => 'other@example.com', 'campaign_name' => 'springQsale']);

        $results = Lead::query()->search('spring_sale')->get();

        $this->assertCount(1, $results);
        $this->assertSame('spring_sale', $results->first()->campaign_name);
    }

    public function test_backslash_does_not_break_the_query(): void
    {
        $this->makeLead(['message' => 'path C:\\Users\\jane']);

        $results = Lead::query()->search('C:\\Users')->get();

        $this->assertCount(1, $results);
    }
}
