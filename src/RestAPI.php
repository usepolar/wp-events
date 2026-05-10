<?php

namespace Polar\Events;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use DateTime;

/**
 * REST API for Polar Events
 */
class RestAPI
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Register REST API routes
     */
    public function registerRoutes(): void
    {
        register_rest_route('polar-events/v1', '/agenda', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getAgenda'],
            'permission_callback' => '__return_true',
            'args' => [
                'days' => [
                    'description' => __('Number of days to return', 'polar-events'),
                    'type' => 'integer',
                    'default' => 7,
                    'minimum' => 1,
                    'maximum' => 365,
                ],
                'offset' => [
                    'description' => __('Days to skip from today', 'polar-events'),
                    'type' => 'integer',
                    'default' => 0,
                    'minimum' => 0,
                ],
                'date' => [
                    'description' => __('Start date (Y-m-d format)', 'polar-events'),
                    'type' => 'string',
                    'format' => 'date',
                ],
                'event_category' => [
                    'description' => __('Filter by event category', 'polar-events'),
                    'type' => 'string',
                ],
            ],
        ]);
    }

    /**
     * Get agenda data
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function getAgenda(WP_REST_Request $request)
    {
        $days = $request->get_param('days') ?: 7;
        $offset = $request->get_param('offset') ?: 0;
        $startDate = $request->get_param('date');
        $eventCategory = $request->get_param('event_category');

        try {
            // Calculate date range
            if ($startDate) {
                $start = new DateTime($startDate);
            } else {
                $start = new DateTime();
                if ($offset > 0) {
                    $start->modify("+{$offset} days");
                }
            }

            $end = clone $start;
            $end->modify("+{$days} days");

            // Get events in date range
            $events = $this->getEventsInRange($start, $end, $eventCategory);

            // Group events by day
            $agenda = $this->groupEventsByDay($events);

            // Sort by date
            ksort($agenda);

            return new WP_REST_Response([
                'success' => true,
                'data' => $agenda,
                'meta' => [
                    'start_date' => $start->format('Y-m-d'),
                    'end_date' => $end->format('Y-m-d'),
                    'days_requested' => $days,
                    'total_events' => count($events),
                    'total_days' => count($agenda),
                ],
            ]);

        } catch (\Exception $e) {
            return new WP_Error(
                'polar_events_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get events in date range
     *
     * @param DateTime $start
     * @param DateTime $end
     * @param string|null $eventCategory
     * @return array
     */
    private function getEventsInRange(DateTime $start, DateTime $end, ?string $eventCategory = null): array
    {
        $args = [
            'post_type' => 'polar_event',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ];

        // Add taxonomy filter if specified
        if ($eventCategory) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'polar_event_category',
                    'field' => 'slug',
                    'terms' => $eventCategory,
                ],
            ];
        }

        $query = new \WP_Query($args);

        // Filter events by date range using Event classes
        $eventsInRange = [];
        foreach ($query->posts as $post) {
            try {
                $event = EventFactory::createEvent($post->ID);
                if ($event) {
                    $eventDays = $event->getDays();

                    // Check if any event day falls within our range
                    foreach ($eventDays as $day) {
                        $eventDate = \DateTime::createFromFormat('Ymd', $day);
                        if ($eventDate >= $start && $eventDate <= $end) {
                            $eventsInRange[] = $post;
                            break; // Only add once even if multiple days match
                        }
                    }
                }
            } catch (\Exception $e) {
                // Skip events with errors
                continue;
            }
        }

        return $eventsInRange;
    }

    /**
     * Group events by day using the activity grouping pattern
     *
     * @param array $events
     * @return array
     */
    private function groupEventsByDay(array $events): array
    {
        $eventsByDay = [];

        foreach ($events as $post) {
            $eventDays = $this->getEventDays($post);

            foreach ($eventDays as $day) {
                if (!isset($eventsByDay[$day])) {
                    $eventsByDay[$day] = [
                        'date' => $day,
                        'label' => $this->getDayLabel($day),
                        'events' => [],
                    ];
                }

                $eventsByDay[$day]['events'][] = $this->formatEventData($post);
            }
        }

        return $eventsByDay;
    }

    /**
     * Get all days an event occurs on
     *
     * @param \WP_Post $post
     * @return array
     */
    private function getEventDays(\WP_Post $post): array
    {
        try {
            $event = EventFactory::createEvent($post->ID);

            if (!$event) {
                return [];
            }

            return $event->getDays();

        } catch (\Exception $e) {
            error_log('Polar Events: Error getting event days for post ' . $post->ID . ': ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get day label (Today/Tomorrow/Date)
     *
     * @param string $day Date in Ymd format
     * @return string
     */
    private function getDayLabel(string $day): string
    {
        $today = new DateTime();
        $eventDate = DateTime::createFromFormat('Ymd', $day);

        if (!$eventDate) {
            return $day;
        }

        $diff = $today->diff($eventDate);

        if ($diff->days === 0 && $diff->invert === 0) {
            return __('Avui', 'polar-events');
        } elseif ($diff->days === 1 && $diff->invert === 0) {
            return __('Demà', 'polar-events');
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

    /**
     * Format event data for API response
     *
     * @param \WP_Post $post
     * @return array
     */
    private function formatEventData(\WP_Post $post): array
    {
        try {
            $event = EventFactory::createEvent($post->ID);

            // Get featured image
            $imageId = get_post_thumbnail_id($post->ID);
            $imageData = null;

            if ($imageId) {
                $imageData = [
                    'id' => $imageId,
                    'url' => get_the_post_thumbnail_url($post->ID, 'medium'),
                    'alt' => get_post_meta($imageId, '_wp_attachment_image_alt', true),
                ];
            }

            // Get taxonomies
            $categories = wp_get_post_terms($post->ID, 'polar_event_category', ['fields' => 'all']);

            return [
                'id' => $post->ID,
                'title' => get_the_title($post->ID),
                'slug' => $post->post_name,
                'excerpt' => get_the_excerpt($post->ID),
                'content' => apply_filters('the_content', $post->post_content),
                'permalink' => get_permalink($post->ID),
                'date' => [
                    'raw' => $event ? $event->getDateString() : '',
                    'short' => $event ? $event->getDateString(true) : '',
                ],
                'time' => [
                    'raw' => $event ? $event->getTimeString() : '',
                    'concise' => $event ? $event->getTimeString(true) : '',
                ],
                'all_day' => $event ? $event->isAllDay() : false,
                'image' => $imageData,
                'categories' => array_map(function($term) {
                    return [
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                    ];
                }, $categories),
                'meta' => [
                    'event_type' => get_field('event_type', $post->ID),
                    'start_date' => get_field('event_start_date', $post->ID),
                    'end_date' => get_field('event_end_date', $post->ID),
                ],
            ];

        } catch (\Exception $e) {
            error_log('Polar Events: Error formatting event data for post ' . $post->ID . ': ' . $e->getMessage());

            return [
                'id' => $post->ID,
                'title' => get_the_title($post->ID),
                'error' => 'Failed to format event data',
            ];
        }
    }
}
