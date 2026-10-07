<?php

namespace Tests\Unit;

use App\Support\KursiPenuh;
use PHPUnit\Framework\TestCase;

class KursiPenuhTest extends TestCase
{
    public function test_the_score_is_the_rounded_share_of_liked_ratings(): void
    {
        $this->assertSame(87, KursiPenuh::score(87, 100));
        $this->assertSame(67, KursiPenuh::score(4, 6));
        $this->assertSame(0, KursiPenuh::score(0, 5));
    }

    public function test_there_is_no_score_below_the_minimum_number_of_ratings(): void
    {
        $this->assertNull(KursiPenuh::score(4, 4));
    }

    public function test_seats_are_filled_per_ten_percent(): void
    {
        $this->assertSame(9, KursiPenuh::filledSeats(87));
        $this->assertSame(8, KursiPenuh::filledSeats(75));
        $this->assertSame(10, KursiPenuh::filledSeats(100));
        $this->assertSame(0, KursiPenuh::filledSeats(null));
    }
}
