<?php

namespace Polar\Events\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Polar\Events\SimpleEvent;

class SimpleEventTest extends TestCase
{
    public function test_get_date_range_string_formats_requested_cases(): void
    {
        $sameDayAllDay = [
            'all_day' => true,
            'start_date' => '20261023',
            'end_date' => '20261023',
        ];
        $event = new SimpleEvent(101, $sameDayAllDay);
        $this->assertEquals('23.10.2026', $event->getDateRangeString());

        $sameDayWithStartTime = [
            'all_day' => false,
            'start_date' => '20261023',
            'end_date' => '20261023',
            'start_time' => '12:00:00',
            'end_time' => '',
        ];
        $event = new SimpleEvent(102, $sameDayWithStartTime);
        $this->assertEquals('23.10.2026 12:00', $event->getDateRangeString(includeTime: true));

        $multiDayAllDay = [
            'all_day' => true,
            'start_date' => '20261023',
            'end_date' => '20261025',
        ];
        $event = new SimpleEvent(103, $multiDayAllDay);
        $this->assertEquals('23.10.2026 - 25.10.2026', $event->getDateRangeString());

        $sameDayWithStartAndEndTime = [
            'all_day' => false,
            'start_date' => '20261023',
            'end_date' => '20261023',
            'start_time' => '10:00:00',
            'end_time' => '14:00:00',
        ];
        $event = new SimpleEvent(104, $sameDayWithStartAndEndTime);
        $this->assertEquals('23.10.2026 10:00 - 14:00', $event->getDateRangeString(includeTime: true));

        $multiDayWithStartAndEndTime = [
            'all_day' => false,
            'start_date' => '20261023',
            'end_date' => '20261025',
            'start_time' => '10:00:00',
            'end_time' => '14:00:00',
        ];
        $event = new SimpleEvent(105, $multiDayWithStartAndEndTime);
        $this->assertEquals('23.10.2026 10:00 - 25.10.2026 14:00', $event->getDateRangeString(includeTime: true));
    }

    public function test_get_days_single_day(): void
    {
        $data = [
            'all_day' => true,
            'start_date' => '20260427',
            'end_date' => '20260427'
        ];
        $event = new SimpleEvent(123, $data);

        $days = $event->getDays();

        $this->assertSame(['20260427'], $days);
        $this->assertCount(1, $days);
    }

    public function test_get_days_multiple_days(): void
    {
        $data = [
            'all_day' => true,
            'start_date' => '20260427',
            'end_date' => '20260429'
        ];
        $event = new SimpleEvent(456, $data);

        $days = $event->getDays();

        $expected = ['20260427', '20260428', '20260429'];
        $this->assertSame($expected, $days);
        $this->assertCount(3, $days);
    }

    public function test_get_days_across_months(): void
    {
        $data = [
            'all_day' => true,
            'start_date' => '20260430',
            'end_date' => '20260502'
        ];
        $event = new SimpleEvent(789, $data);

        $days = $event->getDays();

        $expected = ['20260430', '20260501', '20260502'];
        $this->assertSame($expected, $days);
        $this->assertCount(3, $days);
    }

    public function test_get_days_across_years(): void
    {
        $data = [
            'all_day' => true,
            'start_date' => '20261230',
            'end_date' => '20270102'
        ];
        $event = new SimpleEvent(999, $data);

        $days = $event->getDays();

        $expected = ['20261230', '20261231', '20270101', '20270102'];
        $this->assertSame($expected, $days);
        $this->assertCount(4, $days);
    }
}
