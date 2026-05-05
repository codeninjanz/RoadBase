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

    public function test_context_bands_handle_nulls(): void
    {
        $b = Nzgttm::contextBands(null, null, null, null);
        $this->assertNull($b['aadt']['band']);
        $this->assertNull($b['speed']['band']);
        $this->assertNull($b['heavy']['band']);
        $this->assertNull($b['crash']['band']);
        $this->assertSame('No AADT', $b['aadt']['label']);
        $this->assertSame('Speed unknown', $b['speed']['label']);
    }

    public function test_context_bands_classify_typical_inputs(): void
    {
        $b = Nzgttm::contextBands(8_000, 70, 8.5, 5);
        $this->assertSame('high', $b['aadt']['band']);
        $this->assertSame('transition', $b['speed']['band']);
        $this->assertSame('moderate', $b['heavy']['band']);
        $this->assertSame('cluster', $b['crash']['band']);
    }

    public function test_context_bands_extremes(): void
    {
        $low = Nzgttm::contextBands(100, 30, 1.0, 0);
        $this->assertSame('low', $low['aadt']['band']);
        $this->assertSame('safe_system', $low['speed']['band']);
        $this->assertSame('low', $low['heavy']['band']);
        $this->assertSame('none', $low['crash']['band']);

        $high = Nzgttm::contextBands(40_000, 110, 18.0, 25);
        $this->assertSame('very_high', $high['aadt']['band']);
        $this->assertSame('high_speed', $high['speed']['band']);
        $this->assertSame('high', $high['heavy']['band']);
        $this->assertSame('high', $high['crash']['band']);
    }
}
