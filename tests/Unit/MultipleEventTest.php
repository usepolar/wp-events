<?php

namespace Polar\Events\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Polar\Events\MultipleEvent;

class MultipleEventTest extends TestCase
{
    public function test_compute_date_range_returns_empty_values_for_empty_rows(): void
    {
        $range = MultipleEvent::computeDateRange([], true);

        $this->assertSame([
            'start_date' => '',
            'end_date' => '',
            'start_time' => '',
            'end_time' => '',
        ], $range);
    }

    public function test_compute_date_range_uses_date_and_time_fallbacks_for_timed_rows(): void
    {
        $rows = [
            [
                'start_date' => '20260503',
                'end_date' => '',
                'start_time' => '10:00:00',
                'end_time' => '',
            ],
            [
                'start_date' => '20260501',
                'end_date' => '20260504',
                'start_time' => '08:30:00',
                'end_time' => '11:00:00',
            ],
        ];

        $range = MultipleEvent::computeDateRange($rows, false);

        $this->assertSame('20260501', $range['start_date']);
        $this->assertSame('20260504', $range['end_date']);
        $this->assertSame('08:30:00', $range['start_time']);
        $this->assertSame('11:00:00', $range['end_time']);
    }

    public function test_compute_date_range_clears_times_for_all_day_rows(): void
    {
        $rows = [
            [
                'start_date' => '20260502',
                'end_date' => '20260503',
                'start_time' => '09:00:00',
                'end_time' => '12:00:00',
            ],
            [
                'start_date' => '20260501',
                'end_date' => '',
                'start_time' => '13:00:00',
                'end_time' => '',
            ],
        ];

        $range = MultipleEvent::computeDateRange($rows, true);

        $this->assertSame('20260501', $range['start_date']);
        $this->assertSame('20260503', $range['end_date']);
        $this->assertSame('', $range['start_time']);
        $this->assertSame('', $range['end_time']);
    }

    public function test_compute_date_range_skips_rows_without_start_date(): void
    {
        $rows = [
            [
                'start_date' => '',
                'end_date' => '20260506',
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
            ],
            [
                'start_date' => '20260504',
                'end_date' => '',
                'start_time' => '09:15:00',
                'end_time' => '',
            ],
        ];

        $range = MultipleEvent::computeDateRange($rows, false);

        $this->assertSame('20260504', $range['start_date']);
        $this->assertSame('20260504', $range['end_date']);
        $this->assertSame('09:15:00', $range['start_time']);
        $this->assertSame('09:15:00', $range['end_time']);
    }

    public function test_get_days_multiple_single_dates(): void
    {
        $data = [
            'all_day' => true,
            'start_date' => '20260429',
            'end_date' => '20260503',
            'event_dates' => [
                ['start_date' => '20260429', 'end_date' => ''],
                ['start_date' => '20260501', 'end_date' => ''],
                ['start_date' => '20260503', 'end_date' => '']
            ]
        ];
        $event = new MultipleEvent(123, $data);

        $days = $event->getDays();

        $expected = ['20260429', '20260501', '20260503'];
        $this->assertSame($expected, $days);
        $this->assertCount(3, $days);
    }

    public function test_get_days_multiple_ranges(): void
    {
        $data = [
            'all_day' => true,
            'start_date' => '20260429',
            'end_date' => '20260504',
            'event_dates' => [
                ['start_date' => '20260429', 'end_date' => '20260430'],
                ['start_date' => '20260502', 'end_date' => '20260504']
            ]
        ];
        $event = new MultipleEvent(456, $data);

        $days = $event->getDays();

        $expected = ['20260429', '20260430', '20260502', '20260503', '20260504'];
        $this->assertSame($expected, $days);
        $this->assertCount(5, $days);
    }

    public function test_get_days_mixed_single_and_ranges(): void
    {
        $data = [
            'all_day' => true,
            'start_date' => '20260429',
            'end_date' => '20260506',
            'event_dates' => [
                ['start_date' => '20260429', 'end_date' => ''],
                ['start_date' => '20260501', 'end_date' => '20260503'],
                ['start_date' => '20260506', 'end_date' => '']
            ]
        ];
        $event = new MultipleEvent(789, $data);

        $days = $event->getDays();

        $expected = ['20260429', '20260501', '20260502', '20260503', '20260506'];
        $this->assertSame($expected, $days);
        $this->assertCount(5, $days);
    }

    public function test_get_days_with_overlapping_dates(): void
    {
        $data = [
            'all_day' => true,
            'start_date' => '20260429',
            'end_date' => '20260502',
            'event_dates' => [
                ['start_date' => '20260429', 'end_date' => '20260501'],
                ['start_date' => '20260430', 'end_date' => '20260502']
            ]
        ];
        $event = new MultipleEvent(111, $data);

        $days = $event->getDays();

        // Should remove duplicates and sort
        $expected = ['20260429', '20260430', '20260501', '20260502'];
        $this->assertSame($expected, $days);
        $this->assertCount(4, $days);
    }

    public function test_get_days_across_months_and_years(): void
    {
        $data = [
            'all_day' => true,
            'start_date' => '20261230',
            'end_date' => '20270316',
            'event_dates' => [
                ['start_date' => '20261230', 'end_date' => '20270102'],
                ['start_date' => '20270315', 'end_date' => '20270316']
            ]
        ];
        $event = new MultipleEvent(222, $data);

        $days = $event->getDays();

        $expected = ['20261230', '20261231', '20270101', '20270102', '20270315', '20270316'];
        $this->assertSame($expected, $days);
        $this->assertCount(6, $days);
    }

    public function test_get_days_with_timed_events(): void
    {
        $data = [
            'all_day' => false,
            'start_date' => '20260429',
            'end_date' => '20260501',
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'event_dates' => [
                [
                    'start_date' => '20260429',
                    'end_date' => '',
                    'start_time' => '14:00:00',
                    'end_time' => '16:00:00'
                ],
                [
                    'start_date' => '20260501',
                    'end_date' => '',
                    'start_time' => '09:00:00',
                    'end_time' => ''
                ]
            ]
        ];
        $event = new MultipleEvent(333, $data);

        $days = $event->getDays();

        $expected = ['20260429', '20260501'];
        $this->assertSame($expected, $days);
        $this->assertCount(2, $days);
        $this->assertFalse($event->isAllDay());
    }

    public function test_get_days_empty_event_dates(): void
    {
        $data = [
            'all_day' => true,
            'start_date' => '20260427',
            'end_date' => '20260427',
            'event_dates' => []
        ];
        $event = new MultipleEvent(444, $data);

        $days = $event->getDays();

        $this->assertSame([], $days);
        $this->assertCount(0, $days);
    }

    public function test_get_dates_with_invalid_start_date(): void
    {
        $data = [
            'all_day' => true,
            'start_date' => '20260502',
            'end_date' => '20260503',
            'event_dates' => [
                ['start_date' => '', 'end_date' => '20260430'], // Invalid start date
                ['start_date' => '20260502', 'end_date' => '20260503'] // Valid
            ]
        ];
        $event = new MultipleEvent(555, $data);

        $days = $event->getDays();

        // Should skip invalid date and only process valid one
        $expected = ['20260502', '20260503'];
        $this->assertSame($expected, $days);
        $this->assertCount(2, $days);
    }
}
