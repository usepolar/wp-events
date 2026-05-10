<?php

namespace Polar\Events;

use Carbon\Carbon;

class CarbonLocale
{
    public static function applyCurrentLocale(): void
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
}
