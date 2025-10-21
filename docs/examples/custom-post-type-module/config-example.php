<?php
/**
 * Example sitchco.config.php entry for EventModule
 *
 * Add this to your theme's sitchco.config.php file.
 */

use Sitchco\App\Modules\Event\EventModule;

return [
    'modules' => [
        // Option 1: Basic registration (no features enabled)
        EventModule::class,

        // Option 2: With all features enabled
        // EventModule::class => [
        //     'customAdminColumn' => true,
        //     'emailNotifications' => true,
        // ],

        // Option 3: Selective feature enablement
        // EventModule::class => [
        //     'customAdminColumn' => true,      // Enable admin columns
        //     'emailNotifications' => false,    // Disable email notifications
        // ],
    ],

    // Optional: DI container configuration
    'container' => [
        // If you need to configure the EventRepository
        // \Sitchco\App\Modules\Event\EventRepository::class => \DI\create(),
    ],
];
