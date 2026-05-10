<?php

namespace Polar\Events;

use Carbon\Carbon;

/**
 * Recurring event that occurs on a repeating schedule
 */
class RecurringEvent extends Event
{
    protected string $frequency;
    protected array $byDays = [];
    protected ?string $byWeekNo = null;
    protected ?string $byDay = null;

    /**
     * Initialize event and recurrence data from ACF fields
     */
    protected function init(array $data): void
    {
        parent::init($data);

        $recurrence = get_field('recurrence', $this->postId);

        if ($recurrence && is_array($recurrence)) {
            $this->frequency = $recurrence['frequency'] ?: 'P1W';
            $this->byDays = $recurrence['byDay'] ?: [];

            // Convert byMonthWeek array to single value for compatibility
            $byMonthWeek = $recurrence['byMonthWeek'] ?: [];
            $this->byWeekNo = !empty($byMonthWeek) ? $byMonthWeek[0] : null;

            // Use first selected ISO day for monthly events
            $this->byDay = !empty($this->byDays) ? strtoupper((string) $this->byDays[0]) : null;
            return;
        }

        // Fallback defaults
        $this->frequency = 'P1W';
        $this->byDays = [];
        $this->byWeekNo = null;
        $this->byDay = null;
    }

    /**
     * Get all days this event occurs on
     * Returns array of dates in Ymd format
     *
     * @return array
     */
    public function getDays(): array
    {
        $days = [];

        switch ($this->frequency) {
            case 'P1D':
                $days = $this->getDailyDays();
                break;
            case 'P1W':
                $days = $this->getWeeklyDays();
                break;
            case 'P1M':
                $days = $this->getMonthlyDays();
                break;
            default:
                // Fallback for old format
                if ($this->frequency === 'weekly') {
                    $days = $this->getWeeklyDays();
                } elseif ($this->frequency === 'monthly') {
                    $days = $this->getMonthlyDays();
                }
                break;
        }

        return $days;
    }

    /**
     * Get days for daily recurring events
     *
     * @return array
     */
    private function getDailyDays(): array
    {
        $days = [];
        $current = clone $this->startDate;
        $end = clone $this->endDate;

        while ($current <= $end) {
            $days[] = $current->format('Ymd');
            $current->addDay();
        }

        return $days;
    }

    /**
     * Get days for weekly recurring events
     *
     * @return array
     */
    private function getWeeklyDays(): array
    {
        $days = [];

        if (empty($this->byDays)) {
            // If no specific days selected, use the start date day
            $days[] = $this->startDate->format('Ymd');
            return $days;
        }

        $selectedIsoDays = array_map(static fn($day) => strtoupper((string) $day), $this->byDays);
        $weekdayToIso = [
            '0' => 'SU',
            '1' => 'MO',
            '2' => 'TU',
            '3' => 'WE',
            '4' => 'TH',
            '5' => 'FR',
            '6' => 'SA',
        ];
        $current = clone $this->startDate;
        $end = clone $this->endDate;

        while ($current <= $end) {
            $dayOfWeek = $current->format('w'); // 0 (Sunday) to 6 (Saturday)
            $isoDay = $weekdayToIso[$dayOfWeek] ?? null;

            if ($isoDay !== null && in_array($isoDay, $selectedIsoDays, true)) {
                $days[] = $current->format('Ymd');
            }

            $current->addDay();
        }

        return $days;
    }

    /**
     * Get days for monthly recurring events
     *
     * @return array
     */
    private function getMonthlyDays(): array
    {
        $days = [];

        if (!$this->byWeekNo || !$this->byDay) {
            return $days;
        }

        // Map ISO day codes to day names
        $dayNames = [
            'SU' => 'sunday',
            'MO' => 'monday',
            'TU' => 'tuesday',
            'WE' => 'wednesday',
            'TH' => 'thursday',
            'FR' => 'friday',
            'SA' => 'saturday',
        ];

        $byDay = strtoupper((string) $this->byDay);
        if (!isset($dayNames[$byDay])) {
            return $days;
        }

        $dayName = $dayNames[$byDay];

        // Start from the beginning of the start month
        $current = clone $this->startDate;
        $current->modify('first day of this month');

        $end = clone $this->endDate;
        $end->modify('last day of this month');

        while ($current <= $end) {
            $eventDate = $this->getMonthlyEventDate($current, $this->byWeekNo, $dayName);

            if ($eventDate &&
                $eventDate >= $this->startDate &&
                $eventDate <= $this->endDate) {
                $days[] = $eventDate->format('Ymd');
            }

            // Move to next month
            $current->modify('first day of next month');
        }

        return $days;
    }

    /**
     * Get event date for a specific month
     *
     * @param Carbon $month
     * @param string $weekNo
     * @param string $dayName
     * @return Carbon|null
     */
    private function getMonthlyEventDate(Carbon $month, string $weekNo, string $dayName): ?Carbon
    {
        $eventDate = clone $month;

        try {
            if ($weekNo === 'last') {
                $eventDate->modify("last {$dayName} of this month");
            } else {
                // For 1st, 2nd, 3rd, 4th
                $ordinals = [
                    '1' => 'first',
                    '2' => 'second',
                    '3' => 'third',
                    '4' => 'fourth'
                ];

                if (isset($ordinals[$weekNo])) {
                    $ordinal = $ordinals[$weekNo];
                    $eventDate->modify("{$ordinal} {$dayName} of this month");

                    // Check if the date is still in the same month
                    if ($eventDate->format('n') != $month->format('n')) {
                        return null;
                    }
                } else {
                    return null;
                }
            }

            return $eventDate;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get formatted date string for this event
     *
     * @param string $dateFormat The date format to use
     * @param string $timeFormat The time format to use
     * @param bool $includeTime Whether to include time in the output
     * @return string
     */
    public function getDateString(
        ?string $dateFormat = 'LL',
        ?string $timeFormat = 'HH:mm',
        bool $includeTime = true
    ): string
    {
        $dateFormat = $dateFormat ?? 'LL';
        $timeFormat = $timeFormat ?? 'HH:mm';

        if ($this->frequency === 'P1W' || $this->frequency === 'weekly') {
            return $this->getWeeklyDateString($dateFormat, $timeFormat);
        }

        if ($this->frequency === 'P1M' || $this->frequency === 'monthly') {
            return $this->getMonthlyDateString($dateFormat, $timeFormat);
        }

        return $this->getDateRangeString($dateFormat, $timeFormat, $includeTime);
    }

    /**
     * Get date string for weekly events
     */
    private function getWeeklyDateString(string $dateFormat, string $timeFormat): string
    {
        if (empty($this->byDays)) {
            return '';
        }

        $dayNames = [
            'SU' => __('Sunday', 'polar-events'),
            'MO' => __('Monday', 'polar-events'),
            'TU' => __('Tuesday', 'polar-events'),
            'WE' => __('Wednesday', 'polar-events'),
            'TH' => __('Thursday', 'polar-events'),
            'FR' => __('Friday', 'polar-events'),
            'SA' => __('Saturday', 'polar-events'),
        ];

        $selectedDays = [];
        foreach ($this->byDays as $day) {
            $dayCode = strtoupper((string) $day);

            if (isset($dayNames[$dayCode])) {
                $selectedDays[] = $dayNames[$dayCode];
            }
        }

        if (empty($selectedDays)) {
            return '';
        }

        $lastDay = array_pop($selectedDays);
        $and = ' ' . __('and', 'polar-events') . ' ';
        $daysString = empty($selectedDays)
            ? $lastDay
            : implode(', ', $selectedDays) . $and . $lastDay;

        $dateRange = $this->getDateRangeString(
            dateFormat: $dateFormat,
            timeFormat: $timeFormat,
            includeTime: false
        );
        $timeRange = $this->getTimeString();

        return sprintf(__('Every %s, %s, %s', 'polar-events'), $daysString, $dateRange, $timeRange);
    }

    /**
     * Get date string for monthly events
     */
    private function getMonthlyDateString(string $dateFormat, string $timeFormat): string
    {
        if (!$this->byWeekNo || !$this->byDay) {
            return '';
        }

        $dayNames = [
            'SU' => __('Sunday', 'polar-events'),
            'MO' => __('Monday', 'polar-events'),
            'TU' => __('Tuesday', 'polar-events'),
            'WE' => __('Wednesday', 'polar-events'),
            'TH' => __('Thursday', 'polar-events'),
            'FR' => __('Friday', 'polar-events'),
            'SA' => __('Saturday', 'polar-events'),
        ];

        $ordinals = [
            '1' => __('first', 'polar-events'),
            '2' => __('second', 'polar-events'),
            '3' => __('third', 'polar-events'),
            '4' => __('fourth', 'polar-events'),
            'last' => __('last', 'polar-events')
        ];

        $dayName = $dayNames[strtoupper((string) $this->byDay)] ?? '';
        $ordinal = $ordinals[$this->byWeekNo] ?? '';
        $dateRange = $this->getDateRangeString(
            dateFormat: $dateFormat,
            timeFormat: $timeFormat,
            includeTime: false
        );
        $timeRange = $this->getTimeString();

        return sprintf(__('Every %s %s of the month, %s, %s', 'polar-events'), $ordinal, $dayName, $dateRange, $timeRange);
    }
}
