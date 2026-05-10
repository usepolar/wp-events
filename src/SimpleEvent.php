<?php

namespace Polar\Events;

use Carbon\CarbonPeriod;

/**
 * Simple event that occurs on a single day or date range
 */
class SimpleEvent extends Event
{
    /**
     * Get all days this event occurs on
     * Returns array of dates in Ymd format
     *
     * @return array
     */
    public function getDays(): array
    {
        $period = CarbonPeriod::create(
            $this->startDate,
            '1 day',
            $this->endDate
        );

        $days = [];
        foreach ($period as $date) {
            $days[] = $date->format('Ymd');
        }

        return $days;
    }

    /**
     * Get formatted date string for this event
     *
     * @return string
     */
    public function getDateString(
        ?string $dateFormat = 'DD.MM.YYYY',
        ?string $timeFormat = 'HH:mm',
        bool $includeTime = false
    ): string
    {
        return $this->getDateRangeString($dateFormat, $timeFormat, $includeTime);
    }
}
