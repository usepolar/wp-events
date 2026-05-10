<?php

namespace Polar\Events;

use Carbon\Carbon;

/**
 * Event that occurs on multiple specific dates
 */
class MultipleEvent extends Event
{
    protected array $eventDates = [];

    /**
     * Initialize event data from array
     */
    protected function init(array $data): void
    {
        parent::init($data);

        // Load multiple dates from event_dates field
        $eventDates = $data['event_dates'] ?? [];

        if ($eventDates && is_array($eventDates)) {
            $this->parseEventDates($eventDates);
        }
    }

    /**
     * Compute top-level range meta from multiple event rows.
     */
    public static function computeDateRange(array $eventDates, bool $allDay): array
    {
        $earliestDate = '';
        $latestDate = '';
        $earliestRow = null;
        $latestRow = null;

        foreach ($eventDates as $dateData) {
            if (!is_array($dateData)) {
                continue;
            }

            $normalized = self::normalizeDateRow($dateData, $allDay);

            if ($normalized === null) {
                continue;
            }

            if ($earliestDate === '' || $normalized['start_date'] < $earliestDate) {
                $earliestDate = $normalized['start_date'];
                $earliestRow = $normalized;
            }

            if ($latestDate === '' || $normalized['end_date'] > $latestDate) {
                $latestDate = $normalized['end_date'];
                $latestRow = $normalized;
            }
        }

        if ($earliestRow === null || $latestRow === null) {
            return [
                'start_date' => '',
                'end_date' => '',
                'start_time' => '',
                'end_time' => '',
            ];
        }

        return [
            'start_date' => $earliestDate,
            'end_date' => $latestDate,
            'start_time' => $earliestRow['start_time'],
            'end_time' => $latestRow['end_time'],
        ];
    }

    /**
     * Get post meta data including event dates
     */
    protected function getPostMeta(): array
    {
        $data = parent::getPostMeta();
        $data['event_dates'] = get_field('event_dates', $this->postId);

        return $data;
    }

    /**
     * Parse event dates from repeater field
     *
    * @param array $eventDates
     */
    private function parseEventDates(array $eventDates): void
    {
        $this->eventDates = [];

        foreach ($eventDates as $dateData) {
            if (!is_array($dateData)) {
                continue;
            }

            $normalized = self::normalizeDateRow($dateData, $this->allDay);

            if ($normalized === null) {
                continue;
            }

            // Create Carbon from Ymd format
            $startDate = Carbon::createFromFormat('Ymd', $normalized['start_date']) ?: Carbon::now();
            $endDate = Carbon::createFromFormat('Ymd', $normalized['end_date']) ?: clone $startDate;

            // Store the date info with optional times
            $eventDate = [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'start_time' => null,
                'end_time' => null,
            ];

            // Process times only if not all day
            if (!$this->allDay) {
                $eventDate['start_time'] = Carbon::createFromFormat('H:i:s', $normalized['start_time']) ?: Carbon::now();
                $eventDate['end_time'] = Carbon::createFromFormat('H:i:s', $normalized['end_time']) ?: clone $eventDate['start_time'];
            }

            $this->eventDates[] = $eventDate;
        }

        // Sort dates by start date
        usort($this->eventDates, function($a, $b) {
            return $a['start_date'] <=> $b['start_date'];
        });
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

        foreach ($this->eventDates as $dateInfo) {
            $startDate = $dateInfo['start_date'];
            $endDate = $dateInfo['end_date'] ?: $startDate;

            // Add all days between start and end for this occurrence
            $current = clone $startDate;
            while ($current <= $endDate) {
                $days[] = $current->format('Ymd');
                $current->addDay();
            }
        }

        // Remove duplicates and sort
        $days = array_unique($days);
        sort($days);

        return $days;
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
        ?string $dateFormat = 'DD.MM.YYYY',
        ?string $timeFormat = 'HH:mm',
        bool $includeTime = false
    ): string
    {
        $dateFormat = $dateFormat ?? 'DD.MM.YYYY';
        $timeFormat = $timeFormat ?? 'HH:mm';

        if (empty($this->eventDates)) {
            return '';
        }

        $dateStrings = [];

        foreach ($this->eventDates as $dateInfo) {
            $startDate = $dateInfo['start_date'];
            $endDate = $dateInfo['end_date'] ?: $startDate;
            $sameDay = $startDate->isSameDay($endDate);
            $startDateText = $startDate->isoFormat($dateFormat);
            $endDateText = $endDate->isoFormat($dateFormat);

            if (!$includeTime || $this->allDay) {
                $dateStrings[] = $sameDay
                    ? $startDateText
                    : "{$startDateText} - {$endDateText}";
                continue;
            }

            $startTime = $dateInfo['start_time']?->isoFormat($timeFormat);
            $endTime = $dateInfo['end_time']?->isoFormat($timeFormat);

            if ($sameDay) {
                if ($startTime === $endTime) {
                    $dateStrings[] = "{$startDateText} {$startTime}";
                    continue;
                }

                $dateStrings[] = "{$startDateText} {$startTime} - {$endTime}";
                continue;
            }

            $dateStrings[] = "{$startDateText} {$startTime} - {$endDateText} {$endTime}";
        }

        if (count($dateStrings) === 1) {
            return $dateStrings[0];
        }

        return implode("\n", $dateStrings);
    }

    /**
     * Normalize one repeater row with start/end fallbacks.
     */
    private static function normalizeDateRow(array $dateData, bool $allDay): ?array
    {
        $startDate = (string) ($dateData['start_date'] ?? '');

        if ($startDate === '') {
            return null;
        }

        $endDate = (string) ($dateData['end_date'] ?? '') ?: $startDate;

        if ($allDay) {
            return [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'start_time' => '',
                'end_time' => '',
            ];
        }

        $startTime = (string) ($dateData['start_time'] ?? '');
        $endTime = (string) ($dateData['end_time'] ?? '') ?: $startTime;

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];
    }
}
