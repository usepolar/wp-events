<?php

namespace Polar\Events;

use Extended\ACF\ConditionalLogic;
use Extended\ACF\Fields\Checkbox;
use Extended\ACF\Fields\DatePicker;
use Extended\ACF\Fields\Group;
use Extended\ACF\Fields\RadioButton;
use Extended\ACF\Fields\Repeater;
use Extended\ACF\Fields\Select;
use Extended\ACF\Fields\Text;
use Extended\ACF\Fields\Textarea;
use Extended\ACF\Fields\TimePicker;
use Extended\ACF\Fields\TrueFalse;
use Extended\ACF\Location;

class EventFields
{
    public function __construct()
    {
        add_action('acf/include_fields', [$this, 'register']);
        add_action('acf/save_post', [$this, 'syncMultipleEventMeta'], 20);
        add_action('acf/save_post', [$this, 'clearAllDayEventTimes'], 30);
    }

    public function register()
    {
        register_extended_field_group([
            'key' => 'group_polar_events_details',
            'title' => __('Event Details', 'polar-events'),
            'fields' => $this->fields(),
            'location' => $this->location(),
            'hide_on_screen' => [],
            'style' => 'normal',
            'position' => 'normal',
            'show_in_rest' => true,
        ]);
    }

    public function fields()
    {
        return [
            RadioButton::make(__('Recurrence Type', 'polar-events'), 'recurrence_type')
                ->key('field_polar_events_recurrence_type')
                ->choices([
                    'single' => __('Single', 'polar-events'),
                    'multiple' => __('Multiple dates', 'polar-events'),
                    'recurring' => __('Recurring', 'polar-events'),
                ])
                ->default('single')
                ->required(),
            TrueFalse::make(__('All-day', 'polar-events'), 'all_day')
                ->key('field_polar_events_all_day')
                ->message(__('Event lasts all day', 'polar-events'))
                ->default(false),
            DatePicker::make(__('Start Date', 'polar-events'), 'start_date')
                ->key('field_polar_events_start_date')
                ->column(50)
                ->format('Ymd')
                ->conditionalLogic([
                    ConditionalLogic::where('recurrence_type', '!=', 'multiple', null, 'field_polar_events_recurrence_type')
                ])
                ->required(),
            TimePicker::make(__('Start Time', 'polar-events'), 'start_time')
                ->key('field_polar_events_start_time')
                ->column(50)
                ->displayFormat('H:i')
                ->format('H:i:s')
                ->conditionalLogic([
                    ConditionalLogic::where('all_day', '==', false, null, 'field_polar_events_all_day')
                        ->and('recurrence_type', '!=', 'multiple', null, 'field_polar_events_recurrence_type')
                ])
                ->required(),
            DatePicker::make(__('End Date', 'polar-events'), 'end_date')
                ->key('field_polar_events_end_date')
                ->column(50)
                ->format('Ymd')
                ->conditionalLogic([
                    ConditionalLogic::where('recurrence_type', '!=', 'multiple', null, 'field_polar_events_recurrence_type')
                ]),
            TimePicker::make(__('End Time', 'polar-events'), 'end_time')
                ->key('field_polar_events_end_time')
                ->column(50)
                ->displayFormat('H:i')
                ->format('H:i:s')
                ->conditionalLogic([
                    ConditionalLogic::where('all_day', '==', false, null, 'field_polar_events_all_day')
                        ->and('recurrence_type', '!=', 'multiple', null, 'field_polar_events_recurrence_type')
                ]),
            Repeater::make(__('Event dates', 'polar-events'), 'event_dates')
                ->key('field_polar_events_event_dates')
                ->fields([
                    DatePicker::make(__('Start Date', 'polar-events'), 'start_date')
                        ->key('field_polar_events_event_dates_start_date')
                        ->format('Ymd')
                        ->required(),
                    TimePicker::make(__('Start Time', 'polar-events'), 'start_time')
                        ->key('field_polar_events_event_dates_start_time')
                        ->displayFormat('H:i')
                        ->format('H:i:s')
                        ->conditionalLogic([
                            ConditionalLogic::where('all_day', '==', false, null, 'field_polar_events_all_day')
                        ])
                        ->required(),
                    DatePicker::make(__('End Date', 'polar-events'), 'end_date')
                        ->key('field_polar_events_event_dates_end_date')
                        ->format('Ymd'),
                    TimePicker::make(__('End Time', 'polar-events'), 'end_time')
                        ->key('field_polar_events_event_dates_end_time')
                        ->displayFormat('H:i')
                        ->format('H:i:s')
                        ->conditionalLogic([
                            ConditionalLogic::where('all_day', '==', false, null, 'field_polar_events_all_day')
                        ]),
                ])
                ->minRows(2)
                ->layout('table')
                ->button(__('Add date', 'polar-events'))
                ->conditionalLogic([
                    ConditionalLogic::where('recurrence_type', '==', 'multiple', null, 'field_polar_events_recurrence_type')
                ]),
            Group::make(__('Recurrence', 'polar-events'), 'recurrence')
                ->key('field_polar_events_recurrence')
                ->fields([
                    Checkbox::make(__('Day(s) of the week', 'polar-events'), 'byDay')
                        ->key('field_polar_events_recurrence_by_day')
                        ->choices([
                            'MO' => __('Monday', 'polar-events'),
                            'TU' => __('Tuesday', 'polar-events'),
                            'WE' => __('Wednesday', 'polar-events'),
                            'TH' => __('Thursday', 'polar-events'),
                            'FR' => __('Friday', 'polar-events'),
                            'SA' => __('Saturday', 'polar-events'),
                            'SU' => __('Sunday', 'polar-events'),
                        ])
                        ->layout('horizontal'),
                    Checkbox::make(__('Week(s) of the month', 'polar-events'), 'byMonthWeek')
                        ->key('field_polar_events_recurrence_by_month_week')
                        ->choices([
                            '1' => __('First', 'polar-events'),
                            '2' => __('Second', 'polar-events'),
                            '3' => __('Third', 'polar-events'),
                            '4' => __('Fourth', 'polar-events'),
                            '-1' => __('Last', 'polar-events'),
                        ])
                        ->layout('horizontal'),
                    Select::make(__('Frequency', 'polar-events'), 'frequency')
                        ->key('field_polar_events_recurrence_frequency')
                        ->choices([
                            'P1D' => __('Daily', 'polar-events'),
                            'P1W' => __('Weekly', 'polar-events'),
                            'P1M' => __('Monthly', 'polar-events'),
                        ])
                        ->default('P1W')
                ])
                ->layout('row')
                ->conditionalLogic([
                    ConditionalLogic::where('recurrence_type', '==', 'recurring', null, 'field_polar_events_recurrence_type')
                ]),
            Text::make(__('Location', 'polar-events'), 'location')
                ->key('field_polar_events_location'),
            Textarea::make(__('Comments', 'polar-events'), 'comments')
                ->key('field_polar_events_comments')
                ->rows(3)
                ->newLines('br'),
        ];
    }

    public function location()
    {
        return [
            Location::where('post_type', 'event'),
        ];
    }

    /**
     * Sync top-level date/time fields from repeater rows for multiple events.
     *
     * Takes the earliest start_date row for start_date/start_time.
     * Takes the latest end_date row for end_date/end_time.
     */
    public function syncMultipleEventMeta(int $postId): void
    {
        $postId = (int) $postId;

        if (get_post_type($postId) !== 'event') {
            return;
        }

        $recurrenceType = (string) get_field('recurrence_type', $postId);

        if ($recurrenceType !== 'multiple') {
            return;
        }

        $eventDates = get_field('event_dates', $postId);
        $allDay = (bool) get_field('all_day', $postId);

        $rangeMeta = MultipleEvent::computeDateRange(
            is_array($eventDates) ? $eventDates : [],
            $allDay
        );

        update_post_meta($postId, 'start_date', (string) $rangeMeta['start_date']);
        update_post_meta($postId, 'end_date', (string) $rangeMeta['end_date']);
        update_post_meta($postId, 'start_time', (string) $rangeMeta['start_time']);
        update_post_meta($postId, 'end_time', (string) $rangeMeta['end_time']);
    }

    public function clearAllDayEventTimes(int $postId): void
    {
        $postId = (int) $postId;

        if (get_post_type($postId) !== 'event') {
            return;
        }

        $allDay = (bool) get_field('all_day', $postId);

        if (!$allDay) {
            return;
        }

        delete_post_meta($postId, 'start_time');
        delete_post_meta($postId, 'end_time');
    }
}
