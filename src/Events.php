<?php

namespace Polar\Events;

use Carbon\Carbon;

/**
 * Main Events class
 */
class Events
{
    /**
     * Events instance
     *
     * @var Events|null
     */
    private static ?Events $instance = null;

    /**
     * Plugin version
     *
     * @var string
     */
    private string $version;

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct()
    {
        $this->version = POLAR_EVENTS_VERSION ?? '1.0.0';
        $this->init();
    }

    /**
     * Get Events instance (Singleton pattern)
     *
     * @return Events
     */
    public static function getInstance(): Events
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize WordPress hooks
     */
    private function init(): void
    {
        // Set Carbon locale once locale/language is ready
        add_action('init', [$this, 'setLocale'], 20);

        // Load text domain
        add_action('init', [$this, 'loadTextDomain']);

        // Register post types and taxonomies
        add_action('init', [$this, 'registerPostTypes']);
        add_action('init', [$this, 'registerTaxonomies']);

        // Initialize ACF fields and schema immediately since plugins are already loaded
        if (class_exists('Extended\ACF\Location')) {
            new EventFields();
        }

        // Initialize event schema
        new EventSchema();

        // Initialize REST API
        new RestAPI();
    }

    public function setLocale(): void
    {
        $locale = '';

        if (function_exists('determine_locale')) {
            $locale = (string) determine_locale();
        }

        if ($locale === '' && function_exists('get_locale')) {
            $locale = (string) get_locale();
        }

        if ($locale === '') {
            $locale = 'en_US';
        }

        Carbon::setLocale(str_replace('-', '_', $locale));
    }

    /**
     * Load text domain for translations
     */
    public function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'polar-events',
            false,
            dirname(plugin_basename(POLAR_EVENTS_PLUGIN_FILE)) . '/languages'
        );
    }

    /**
     * Register custom post types
     */
    public function registerPostTypes(): void
    {
        $labels = [
            'name' => __('Events', 'polar-events'),
            'singular_name' => __('Event', 'polar-events'),
            'menu_name' => __('Events', 'polar-events'),
            'add_new' => __('Add New', 'polar-events'),
            'add_new_item' => __('Add New Event', 'polar-events'),
            'edit_item' => __('Edit Event', 'polar-events'),
            'new_item' => __('New Event', 'polar-events'),
            'view_item' => __('View Event', 'polar-events'),
            'search_items' => __('Search Events', 'polar-events'),
            'not_found' => __('No events found', 'polar-events'),
            'not_found_in_trash' => __('No events found in trash', 'polar-events'),
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'has_archive' => 'events',
            'menu_icon' => 'dashicons-calendar-alt',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'show_in_rest' => true,
        ];

        register_post_type('event', $args);
    }

    /**
     * Register custom taxonomies
     */
    public function registerTaxonomies(): void
    {
        // Register event type taxonomy
        register_taxonomy('event_type', 'event', [
            'label' => __('Event Types', 'polar-events'),
            'labels' => [
                'name' => __('Event Types', 'polar-events'),
                'singular_name' => __('Event Type', 'polar-events'),
                'add_new_item' => __('Add New Event Type', 'polar-events'),
                'edit_item' => __('Edit Event Type', 'polar-events'),
            ],
            'hierarchical' => true,
            'public' => true,
            'show_in_rest' => true,
        ]);
    }

    /**
     * Get plugin version
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }
}
