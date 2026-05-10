# Polar Events

A comprehensive WordPress plugin for managing events with different date configurations (single day, multiple days, recurring) and displaying them in a calendar or list format.

## Features

### Event Types
- **Simple Events** - Single day or date range events
- **Recurring Events** - Weekly or monthly recurring events with complex patterns
- **Multiple Date Events** - Events occurring on specific non-consecutive dates

### Event Management
- Advanced Custom Fields (ACF) integration for event configuration
- Conditional field logic based on event type
- Time-based events with all-day support
- Custom database table for efficient date-range queries

### Recurring Event Patterns
- **Weekly**: Select specific days of the week
- **Monthly**: "First Tuesday of month" style patterns
- Smart date calculation with edge case handling

### REST API
Endpoint: `/wp-json/polar-events/v1/agenda`

**Parameters:**
- `days` - Number of days to return (default: 7)
- `offset` - Days to skip from today (pagination)
- `date` - Custom start date (Y-m-d format)
- `event_category` - Filter by event category slug

**Response Features:**
- Events grouped by day
- Smart date labels ("Avui", "Demà", or formatted date)
- Rich event data (images, excerpts, categories, times)
- Multilingual date formatting

### Template Functions

#### Date & Time Functions
```php
// Get full date + time string
polar_get_date_range_string($post, $shortFormat = false)

// Get concise date string
polar_get_short_date_range_string($post)

// Get time-only string
polar_get_time_string($post, $concise = false)
```

#### Event Query Functions
```php
// Get events in date range
polar_get_events_in_range($startDate, $endDate, $args = [])

// Get upcoming events
polar_get_upcoming_events($days = 7, $args = [])

// Get events for specific day
polar_get_events_for_day($date, $args = [])
```

#### Event Data Functions
```php
// Get array of days event occurs (Ymd format)
polar_get_event_days($post)

// Group events by day with smart labels
polar_group_events_by_day($events)

// Get localized day labels
polar_get_day_label($date)
```

#### Event Properties
```php
// Check event properties
polar_is_event_today($post)
polar_is_event_upcoming($post)
polar_is_all_day_event($post)
polar_get_event_type($post) // simple|recurring|multiple
```

### Database Schema

The plugin creates a custom table `wp_polar_events` for efficient event indexing:

```sql
CREATE TABLE wp_polar_events (
    event_id bigint(20) NOT NULL AUTO_INCREMENT,
    post_id bigint(20) NOT NULL,
    event_type varchar(20) NOT NULL DEFAULT 'simple',
    event_start_date date NOT NULL,
    event_end_date date NOT NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id),
    KEY post_id (post_id),
    KEY event_type (event_type),
    KEY date_range (event_start_date, event_end_date)
);
```

### ACF Field Configuration

The plugin automatically registers the following ACF fields for the `polar_event` post type:

- **Event Type** (Radio) - simple|recurring|multiple
- **Start/End Dates** (Date Pickers) - conditional on event type
- **Start/End Times** (Time Pickers) - conditional on all-day setting
- **All Day Event** (True/False)
- **Multiple Dates** (Textarea) - for multiple event type, one date per line
- **Recurrence Frequency** (Radio) - weekly|monthly
- **Days of Week** (Checkbox) - for weekly recurrence
- **Week of Month** (Radio) - 1st|2nd|3rd|4th|last for monthly
- **Day of Week** (Radio) - for monthly recurrence

### Frontend Display

The plugin includes CSS classes for styling events:

```html
<div class="polar-events-calendar">
  <div class="polar-events-day">
    <h3 class="polar-events-day-label">Today</h3>
    <div class="polar-events-event">
      <img class="polar-events-event-image" src="...">
      <div class="polar-events-event-content">
        <h4 class="polar-events-event-title">Event Title</h4>
        <div class="polar-events-event-time">2:00 PM - 5:00 PM</div>
        <div class="polar-events-event-excerpt">Event description...</div>
      </div>
    </div>
  </div>
</div>
```

## Installation

1. Upload the plugin files to `/wp-content/plugins/polar-events/`
2. Activate the plugin through the WordPress admin
3. Ensure Advanced Custom Fields (ACF) is installed and active
4. Start creating events using the "Events" post type

## Requirements

- WordPress 6.0+
- PHP 8.4+
- Advanced Custom Fields plugin

## Post Types & Taxonomies

- **Post Type**: `polar_event` with archive support
- **Taxonomy**: `polar_event_category` for event categorization

## API Usage Example

```javascript
// Get next 7 days of events
fetch('/wp-json/polar-events/v1/agenda?days=7')
  .then(response => response.json())
  .then(data => {
    console.log(data.data); // Events grouped by day
  });

// Get events for specific category
fetch('/wp-json/polar-events/v1/agenda?event_category=workshops&days=14')
  .then(response => response.json())
  .then(data => {
    // Handle filtered events
  });
```

## License

GPL-2.0-or-later

## Author

Victor Guerrero - [https://polar.cat](https://polar.cat)
