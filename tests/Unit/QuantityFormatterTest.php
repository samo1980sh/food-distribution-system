<?php

namespace Tests\Unit;

use App\Models\Unit;
use App\Support\Formatting\QuantityFormatter;
use Tests\TestCase;

class QuantityFormatterTest extends TestCase
{
    public function test_it_formats_quantities_without_trailing_zeroes_or_thousands_separators(): void
    {
        $unit = new Unit([
            'code' => 'BAG',
            'name_ar' => 'كيس',
            'symbol' => 'كيس',
        ]);

        $this->assertSame('40', QuantityFormatter::format(40.000));
        $this->assertSame('38', QuantityFormatter::format(38.000));
        $this->assertSame('1.5', QuantityFormatter::format(1.500));
        $this->assertSame('2.25', QuantityFormatter::format(2.250));
        $this->assertSame('0', QuantityFormatter::format(0.000));

        $this->assertSame('40 كيس', QuantityFormatter::formatWithUnit(40.000, $unit));
        $this->assertSame('1.5 كيس', QuantityFormatter::formatWithUnit(1.500, $unit));
        $this->assertSame('-2 كيس', QuantityFormatter::formatDifference(-2.000, $unit));
        $this->assertSame('+10 كيس', QuantityFormatter::formatDifference(10.000, $unit));
        $this->assertSame('0 كيس', QuantityFormatter::formatDifference(0.000, $unit));
    }
}
