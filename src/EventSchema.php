<?php

namespace Polar\Events;

class EventSchema
{
    public static $post_type = 'event';

    private array $graph;

    private ?string $recurrence;
    private ?string $start_date;
    private ?string $end_date;
    private ?string $start_time;
    private ?string $end_time;
    private ?int $duration_days;
    private ?int $duration_hours;
    private ?int $duration_minutes;
    private ?array $event_dates;

    public function __construct()
    {
        add_action('wp_head', [$this, 'init']);
    }

    /**
     * Initializes the schema.
     */
    public function init()
    {
        if (!is_singular(self::$post_type)) {
            return;
        }

        $this->getFields();
        $this->generateSchema();
        $this->renderSchema();
    }

    /**
     * Retrieves the custom fields.
     */
    private function getFields()
    {
        $this->recurrence = get_field('recurrence_type');
        $this->start_date = get_field('start_at', null, false);
        $this->end_date = get_field('end_at', null, false);
        $this->start_time = get_field('start_time', null, false);
        $this->end_time = get_field('end_time', null, false);

        $duration = get_field('duration');
        $this->duration_days = (int) ($duration['days'] ?? 0);
        $this->duration_hours = (int) ($duration['hours'] ?? 0);
        $this->duration_minutes = (int) ($duration['minutes'] ?? 0);

        $this->event_dates = get_field('event_dates') ?: [];
    }

    /**
     * Generates the schema.
     */
    private function generateSchema()
    {
        $this->graph = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => get_the_title(),
        ];

        // Add description if available
        if (has_excerpt()) {
            $this->graph['description'] = get_the_excerpt();
        } elseif (get_the_content()) {
            $this->graph['description'] = wp_strip_all_tags(get_the_content());
        }

        // Add location if available
        $location = get_field('location');
        if ($location) {
            $this->graph['location'] = [
                '@type' => 'Place',
                'name' => $location,
            ];
        }

        // Add URL
        $this->graph['url'] = get_permalink();

        switch ($this->recurrence) {
            case 'recurring':
                $this->setRecurringProperties();
                break;
            case 'multiple':
                $this->setMultipleOccurrencesProperties();
                break;
            default:
                $this->setSingleEventProperties();
                break;
        }

        $this->setDurationProperty();
    }

    /**
     * Sets properties for a single event.
     */
    private function setSingleEventProperties()
    {
        $this->graph = array_merge($this->graph, $this->getEventDateProperties([
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
        ]));
    }

    /**
     * Sets properties for a recurring event.
     */
    private function setRecurringProperties()
    {
        $schedule = [
            '@type' => 'Schedule',
        ];

        $recurrence = get_field('recurrence');

        $byDay = $recurrence['byDay'] ?? [];
        $byMonthWeek = $recurrence['byMonthWeek'] ?? [""];

        if ($this->start_date) {
            $schedule['startDate'] = $this->formatDateTime($this->start_date, $this->start_time);
        }

        if ($this->end_date) {
            $schedule['endDate'] = $this->formatDateTime($this->end_date, $this->end_time);
        }

        $byDayOutput = [];

        foreach ($byDay as $day) {
            foreach ($byMonthWeek as $week) {
                $byDayOutput[] = $week . $day;
            }
        }

        if ($byDayOutput) {
            $schedule['byDay'] = count($byDayOutput) > 1 ? $byDayOutput : $byDayOutput[0];
        }

        if (!empty($recurrence['frequency'])) {
            $schedule['repeatFrequency'] = $recurrence['frequency'];
        }

        $this->graph['eventSchedule'] = $schedule;
    }

    /**
     * Sets properties for multiple occurrences.
     */
    private function setMultipleOccurrencesProperties()
    {
        $this->graph['subEvent'] = array_map(function ($date) {
            $event = [
                '@type' => 'Event',
                'name' => get_the_title(),
            ];

            $event = array_merge($event, $this->getEventDateProperties($date));

            return $event;
        }, $this->event_dates);
    }

    /**
     * Sets the duration property.
     */
    private function setDurationProperty()
    {
        if (!$this->duration_days && !$this->duration_hours && !$this->duration_minutes) {
            return;
        }

        $duration = 'P';

        if ($this->duration_days) {
            $duration .= $this->duration_days . 'D';
        }

        if ($this->duration_hours || $this->duration_minutes) {
            $duration .= 'T';
        }

        if ($this->duration_hours) {
            $duration .= $this->duration_hours . 'H';
        }

        if ($this->duration_minutes) {
            $duration .= $this->duration_minutes . 'M';
        }

        $this->graph['duration'] = $duration;
    }

    /**
     * Extracts event date properties for an individual event.
     */
    private function getEventDateProperties($date)
    {
        $eventProperties = [];

        // Start Date and Time
        if (!empty($date['start_date'])) {
            $eventProperties['startDate'] = $this->formatDateTime($date['start_date'], $date['start_time'] ?? '');
        }

        // End Date and Time
        if (!empty($date['end_date'])) {
            $eventProperties['endDate'] = $this->formatDateTime($date['end_date'], $date['end_time'] ?? '');
        }

        return $eventProperties;
    }

    /**
     * Formats the date and time.
     */
    private function formatDateTime($date, $time = '')
    {
        // Handle Ymd format from ACF
        if (strlen($date) === 8 && is_numeric($date)) {
            $formattedDate = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
        } else {
            $formattedDate = date('Y-m-d', strtotime($date));
        }

        if ($time) {
            return $formattedDate . 'T' . $time;
        }

        return $formattedDate;
    }

    /**
     * Renders the schema.
     */
    private function renderSchema()
    {
        $flags = defined('WP_DEBUG') && WP_DEBUG ? JSON_PRETTY_PRINT : 0;

        echo '<script type="application/ld+json">' . json_encode($this->graph, $flags) . '</script>';
    }
}
