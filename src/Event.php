<?php

namespace Polar\Events;

use Carbon\Carbon;
use DateTime;

/**
 * Abstract base class for all event types
 */
abstract class Event
{
    protected Carbon $startDate;
    protected Carbon $endDate;
    protected ?Carbon $startTime = null;
    protected ?Carbon $endTime = null;
    protected bool $allDay = false;
    protected int $postId;

    /**
     * Constructor
     *
     * @param int $postId
     * @param array $data Optional data for testing (bypasses WordPress meta)
     */
    public function __construct(int $postId, array $data = [])
    {
        $this->postId = $postId;

        $data = $data ?: $this->getPostMeta();

        $this->init($data);
    }

    /**
     * Initialize event data from array
     */
    protected function init(array $data): void
    {
        $this->allDay = $data['all_day'] ?? false;

        $this->startDate = !empty($data['start_date'])
            ? Carbon::createFromFormat('Ymd', $data['start_date']) ?? Carbon::now()
            : Carbon::now();

        $this->endDate = !empty($data['end_date'])
            ? Carbon::createFromFormat('Ymd', $data['end_date']) ?? $this->startDate->copy()
            : $this->startDate->copy();

        // Skip time processing for all-day events
        if ($this->allDay) {
            return;
        }

        $this->startTime = !empty($data['start_time'])
            ? Carbon::createFromFormat('H:i:s', $data['start_time'])
            : Carbon::now();

        $this->endTime = !empty($data['end_time'])
            ? Carbon::createFromFormat('H:i:s', $data['end_time'])
            : $this->startTime->copy();
    }

    /**
     * Get post meta data
     */
    protected function getPostMeta(): array
    {
        return [
            'all_day' => (bool)(int) get_post_meta($this->postId, 'all_day', true),
            'start_date' => (string) get_post_meta($this->postId, 'start_date', true),
            'end_date' => (string) get_post_meta($this->postId, 'end_date', true),
            'start_time' => (string) get_post_meta($this->postId, 'start_time', true),
            'end_time' => (string) get_post_meta($this->postId, 'end_time', true),
        ];
    }

    /**
     * Get all days this event occurs on
     * Returns array of dates in Ymd format
     *
     * @return array
     */
    abstract public function getDays(): array;

    /**
     * Get formatted date string for this event
     *
     * @param string $dateFormat The date format to use
     * @param string $timeFormat The time format to use
     * @param bool $includeTime Whether to include time in the output
     * @return string
     */
    abstract public function getDateString(
        ?string $dateFormat = 'DD.MM.YYYY',
        ?string $timeFormat = 'HH:mm',
        bool $includeTime = false
    ): string;

    /**
     * Get date range string with smart formatting
     *
     * @param string $dateFormat The date format to use
     * @param string $timeFormat The time format to use
     * @param bool $includeTime Whether to include time in the output
     * @return string
     */
    public function getDateRangeString(
        ?string $dateFormat = 'DD.MM.YYYY',
        ?string $timeFormat = 'HH:mm',
        bool $includeTime = false
    ): string
    {
        $dateFormat = $dateFormat ?? 'DD.MM.YYYY';
        $timeFormat = $timeFormat ?? 'HH:mm';

        $startDate = $this->startDate->isoFormat($dateFormat);
        $endDate = $this->endDate->isoFormat($dateFormat);

        $sameDay = $this->startDate->isSameDay($this->endDate);

        if (!$includeTime || $this->allDay) {
            return $sameDay ? $startDate : "{$startDate} - {$endDate}";
        }

        $startTime = $this->startTime->isoFormat($timeFormat);
        $endTime = $this->endTime->isoFormat($timeFormat);

        if ($sameDay) {
            if ($startTime === $endTime) {
                return "{$startDate} {$startTime}";
            }

            return "{$startDate} {$startTime} - {$endTime}";
        }

        return "{$startDate} {$startTime} - {$endDate} {$endTime}";
    }

    /**
     * Get time string
     *
     * @param bool $concise Whether to use concise format
     * @return string
     */
    public function getTimeString(bool $concise = false): string
    {
        if ($this->allDay) {
            return __('All day', 'polar-events');
        }

        if (!$this->startTime && !$this->endTime) {
            return '';
        }

        $timeString = '';

        if ($this->startTime) {
            $timeString .= $this->startTime->format('H:i');
        }

        if ($this->endTime && $this->endTime != $this->startTime) {
            $timeString .= $concise ? '-' : ' - ';
            $timeString .= $this->endTime->format('H:i');
        }

        return $timeString . ($concise ? '' : 'h');
    }

    /**
     * Get the post ID
     *
     * @return int
     */
    public function getPostId(): int
    {
        return $this->postId;
    }

    /**
     * Get start date
     *
     * @return DateTime
     */
    public function getStartDate(): DateTime
    {
        return $this->startDate;
    }

    /**
     * Get end date
     *
     * @return DateTime
     */
    public function getEndDate(): DateTime
    {
        return $this->endDate;
    }

    /**
     * Check if event is all day
     *
     * @return bool
     */
    public function isAllDay(): bool
    {
        return $this->allDay;
    }
}
