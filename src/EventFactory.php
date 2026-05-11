<?php

namespace Polar\Events;

/**
 * Factory class for creating event instances
 */
class EventFactory
{
    /**
     * Create an event instance based on the event type
     *
     * @param int $postId The post ID
     * @return Event|null
     */
    public static function createEvent(int $postId): ?Event
    {
        if (get_post_type($postId) !== 'event') {
            return null;
        }

        $recurrenceType = get_field('recurrence_type', $postId);

        switch ($recurrenceType) {
            case 'single':
                return new SingleEvent($postId);

            case 'recurring':
                return new RecurringEvent($postId);

            case 'multiple':
                return new MultipleEvent($postId);

            default:
                // Default to single event if type is not specified
                return new SingleEvent($postId);
        }
    }

    /**
     * Get available event types
     *
     * @return array
     */
    public static function getEventTypes(): array
    {
        return [
            'single' => __('Single', 'polar-events'),
            'multiple' => __('Multiple', 'polar-events'),
            'recurring' => __('Recurring Event', 'polar-events'),
        ];
    }
}
