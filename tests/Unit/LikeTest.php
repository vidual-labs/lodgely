<?php

namespace Tests\Unit;

use App\Support\Like;
use PHPUnit\Framework\TestCase;

class LikeTest extends TestCase
{
    public function test_escapes_percent_underscore_and_backslash(): void
    {
        $this->assertSame('100\\%', Like::escape('100%'));
        $this->assertSame('a\\_b', Like::escape('a_b'));
        $this->assertSame('c:\\\\path', Like::escape('c:\\path'));
        $this->assertSame('plain text', Like::escape('plain text'));
    }
}
