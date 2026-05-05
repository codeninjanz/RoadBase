<?php

namespace Tests\Unit;

use App\Support\Nzgttm;
use PHPUnit\Framework\TestCase;

class NzgttmTest extends TestCase
{
    public function test_returns_null_when_aadt_missing(): void
    {
        $this->assertNull(Nzgttm::classify(null, 50));
    }

    public function test_classifies_lv_below_500(): void
    {
        $this->assertSame('LV', Nzgttm::classify(0, 50));
        $this->assertSame('LV', Nzgttm::classify(499, 100));
    }

    public function test_classifies_level_1_at_500_to_10000(): void
    {
        $this->assertSame('1', Nzgttm::classify(500, 50));
        $this->assertSame('1', Nzgttm::classify(10_000, 100));
    }

    public function test_classifies_level_2_above_10000_at_or_below_75(): void
    {
        $this->assertSame('2', Nzgttm::classify(10_001, 50));
        $this->assertSame('2', Nzgttm::classify(50_000, 75));
    }

    public function test_classifies_level_3_above_10000_above_75(): void
    {
        $this->assertSame('3', Nzgttm::classify(10_001, 80));
        $this->assertSame('3', Nzgttm::classify(80_000, 100));
    }

    public function test_classifies_level_2_when_speed_unknown(): void
    {
        // High AADT with unknown speed defaults to Level 2 (conservative).
        $this->assertSame('2', Nzgttm::classify(20_000, null));
    }
}
