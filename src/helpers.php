<?php

use Polar\Events\EventFactory;
use Polar\Events\Event;

/**
 * Helper functions for Polar Events
 * These functions are available globally for use in themes and other plugins
 */

if (!function_exists('polar_resolve_post_id')) {
    /**
     * Resolve post input to a post ID.
     *
     * @param int|\WP_Post|null $post
     * @return int|null
     */
    function polar_resolve_post_id($post = null): ?int
    {
        if ($post === null) {
            $postId = get_the_ID();
        } else {
            $postId = is_object($post) ? $post->ID : $post;
        }

        return is_numeric($postId) ? (int) $postId : null;
    }
}

if (!function_exists('polar_get_event_instance')) {
    /**
     * Create an event instance for a given post input.
     *
     * @param int|\WP_Post|null $post
    * @return Event|null
     */
    function polar_get_event_instance($post = null): ?Event
    {
        $postId = polar_resolve_post_id($post);

        if (!$postId) {
            return null;
        }

        try {
            return EventFactory::createEvent($postId);
        } catch (Exception $e) {
            error_log('Polar Events: Error creating event for post ' . $postId . ': ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('polar_get_date_range_string')) {
    /**
     * Get date range string for an event
     *
     * @param string $format The date format
     * @param int|\WP_Post|null $post The post ID, post object, or null for current post
     * @return string
     */
    function polar_get_date_range_string(
        string $format = 'DD.MM.YYYY',
        $post = null
    ): string
    {
        $event = polar_get_event_instance($post);

        if (!$event) {
            return '';
        }

        return $event->getDateRangeString(dateFormat: $format, includeTime: false);
    }
}

if (!function_exists('polar_get_datetime_range_string')) {
    /**
     * Get date + time range string for an event
     *
     * @param string $dateFormat The date format
     * @param string $timeFormat The time format
     * @param int|\WP_Post|null $post The post ID, post object, or null for current post
     * @return string
     */
    function polar_get_datetime_range_string(
        string $dateFormat = 'DD.MM.YYYY',
        string $timeFormat = 'HH:mm',
        $post = null
    ): string {
        $event = polar_get_event_instance($post);

        if (!$event) {
            return '';
        }

        return $event->getDateRangeString(
            dateFormat: $dateFormat,
            timeFormat: $timeFormat,
            includeTime: true
        );
    }
}

if (!function_exists('polar_get_time_string')) {
    /**
     * Get time-only string for an event
     *
     * @param int|\WP_Post|null $post The post ID, post object, or null for current post
     * @param bool $concise Whether to use concise format
     * @return string
     */
    function polar_get_time_string($post = null, bool $concise = false): string
    {
        $event = polar_get_event_instance($post);

        if (!$event) {
            return '';
        }

        return $event->getTimeString($concise);
    }
}

if (!function_exists('polar_get_date_string')) {
    /**
     * Get date string for an event (used for recurring events)
     *
     * @param string $dateFormat The date format to use
     * @param string $timeFormat The time format to use
     * @param bool $includeTime Whether to include time in the output
     * @param int|\WP_Post|null $post The post ID, post object, or null for current post
     * @return string
     */
    function polar_get_date_string(
        ?string $dateFormat = null,
        ?string $timeFormat = null,
        bool $includeTime = true,
        $post = null
    ): string
    {
        $event = polar_get_event_instance($post);

        if (!$event) {
            return '';
        }

        return $event->getDateString($dateFormat, $timeFormat, $includeTime);
    }
}

if (!function_exists('polar_get_event_days')) {
    /**
     * Get array of days an event occurs on
     * Returns array of dates in Ymd format
     *
     * @param int|\WP_Post|null $post The post ID, post object, or null for current post
     * @return array
     */
    function polar_get_event_days($post = null): array
    {
        $event = polar_get_event_instance($post);

        if (!$event) {
            return [];
        }

        return $event->getDays();
    }
}

if (!function_exists('polar_get_day_label')) {
    /**
     * Get localized day labels: Today, Tomorrow, or formatted date based on current language
     *
     * @param string $date Date in Ymd format
     * @return string
     */
    function polar_get_day_label(string $date): string
    {
        $today = new DateTime();
        $eventDate = DateTime::createFromFormat('Ymd', $date);

        if (!$eventDate) {
            return $date;
        }

        $diff = $today->diff($eventDate);

        if ($diff->days === 0 && $diff->invert === 0) {
            return __('Today', 'polar-events');
        } elseif ($diff->days === 1 && $diff->invert === 0) {
            return __('Tomorrow', 'polar-events');
        } else {
            // Format based on current language
            $locale = get_locale();
            if (strpos($locale, 'ca') === 0) {
                // Catalan format
                return $eventDate->format('j \d\e F');
            } elseif (strpos($locale, 'es') === 0) {
                // Spanish format
                return $eventDate->format('j \d\e F');
            } else {
                // Default English format
                return $eventDate->format('F j');
            }
        }
    }
}

if (!function_exists('polar_group_events_by_day')) {
    /**
     * Group events by day using the WordPress activity grouping pattern
     *
     * @param array $events Array of post objects or post IDs
     * @return array
     */
    function polar_group_events_by_day(array $events): array
    {
        $eventsByDay = [];

        foreach ($events as $post) {
            $postId = is_object($post) ? $post->ID : $post;
            $eventDays = polar_get_event_days($postId);

            foreach ($eventDays as $day) {
                if (!isset($eventsByDay[$day])) {
                    $eventsByDay[$day] = [
                        'date' => $day,
                        'label' => polar_get_day_label($day),
                        'events' => [],
                    ];
                }

                $eventsByDay[$day]['events'][] = is_object($post) ? $post : get_post($postId);
            }
        }

        // Sort by day
        ksort($eventsByDay);

        return $eventsByDay;
    }
}

if (!function_exists('polar_get_events_in_range')) {
    /**
     * Get events within a date range
     *
     * @param string $startDate Start date in Y-m-d format
     * @param string $endDate End date in Y-m-d format
     * @param array $args Additional WP_Query arguments
     * @return array
     */
    function polar_get_events_in_range(string $startDate, string $endDate, array $args = []): array
    {
        $defaultArgs = [
            'post_type' => 'event',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ];

        $args = wp_parse_args($args, $defaultArgs);

        $query = new WP_Query($args);

        // Filter events by date range using Event classes
        $eventsInRange = [];
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);

        foreach ($query->posts as $post) {
            try {
                $event = EventFactory::createEvent($post->ID);
                if ($event) {
                    $eventDays = $event->getDays();

                    // Check if any event day falls within our range
                    foreach ($eventDays as $day) {
                        $eventDate = DateTime::createFromFormat('Ymd', $day);
                        if ($eventDate >= $start && $eventDate <= $end) {
                            $eventsInRange[] = $post;
                            break; // Only add once even if multiple days match
                        }
                    }
                }
            } catch (Exception $e) {
                // Skip events with errors
                continue;
            }
        }

        return $eventsInRange;
    }
}

if (!function_exists('polar_get_events_for_day')) {
    /**
     * Get events for a specific day
     *
     * @param string $date Date in Y-m-d format
     * @param array $args Additional WP_Query arguments
     * @return array
     */
    function polar_get_events_for_day(string $date, array $args = []): array
    {
        return polar_get_events_in_range($date, $date, $args);
    }
}

if (!function_exists('polar_is_event_today')) {
    /**
     * Check if an event occurs today
     *
     * @param int|\WP_Post $post The post ID or post object
     * @return bool
     */
    function polar_is_event_today($post): bool
    {
        $today = (new DateTime())->format('Ymd');
        $eventDays = polar_get_event_days($post);

        return in_array($today, $eventDays);
    }
}

if (!function_exists('polar_is_event_upcoming')) {
    /**
     * Check if an event is upcoming (starts today or later)
     *
     * @param int|\WP_Post $post The post ID or post object
     * @return bool
     */
    function polar_is_event_upcoming($post): bool
    {
        $today = (new DateTime())->format('Ymd');
        $eventDays = polar_get_event_days($post);

        foreach ($eventDays as $day) {
            if ($day >= $today) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('polar_get_event_type')) {
    /**
     * Get the recurrence type (single, multiple, recurring)
     *
     * @param int|\WP_Post $post The post ID or post object
     * @return string|null
     */
    function polar_get_event_type($post): ?string
    {
        $event = polar_get_event_instance($post);

        if (!$event) {
            return null;
        }

        return match (true) {
            $event instanceof \Polar\Events\RecurringEvent => 'recurring',
            $event instanceof \Polar\Events\MultipleEvent => 'multiple',
            default => 'single',
        };
    }
}

if (!function_exists('polar_is_all_day_event')) {
    /**
     * Check if an event is all day (no times set)
     *
     * @param int|\WP_Post $post The post ID or post object
     * @return bool
     */
    function polar_is_all_day_event($post): bool
    {
        $event = polar_get_event_instance($post);

        if (!$event) {
            return false;
        }

        return $event->isAllDay();
    }
}
