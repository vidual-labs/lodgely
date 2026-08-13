<?php

namespace Tests\Unit;

use App\Support\Dates;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DatesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-13 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_recent_timestamps_stay_relative(): void
    {
        $this->assertSame('5 minutes ago', Dates::relativeOrExact(Carbon::now()->subMinutes(5)));
        $this->assertSame('23 hours ago', Dates::relativeOrExact(Carbon::now()->subHours(23)));
    }

    public function test_anything_over_a_day_old_shows_the_exact_timestamp(): void
    {
        $this->assertSame('2026-08-12 11:00', Dates::relativeOrExact(Carbon::now()->subHours(25)));
        $this->assertSame('2026-07-30 12:00', Dates::relativeOrExact(Carbon::now()->subDays(14)));
    }

    public function test_the_boundary_is_exactly_one_day(): void
    {
        $this->assertSame('2026-08-12 12:00', Dates::relativeOrExact(Carbon::now()->subDay()));
        $this->assertSame('23 hours ago', Dates::relativeOrExact(Carbon::now()->subDay()->addMinute()));
    }

    public function test_null_renders_as_nothing(): void
    {
        $this->assertSame('', Dates::relativeOrExact(null));
    }

    public function test_german_keeps_relative_wording_localized_and_the_exact_stamp_neutral(): void
    {
        app()->setLocale('de');

        $this->assertSame('vor 5 Minuten', Dates::relativeOrExact(Carbon::now()->subMinutes(5)));
        $this->assertSame('2026-07-30 12:00', Dates::relativeOrExact(Carbon::now()->subDays(14)));
    }
}
