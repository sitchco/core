<?php
/**
 * Example sitchco.config.php entry for SimpleModule
 *
 * Add this to your theme's sitchco.config.php file.
 */

use Sitchco\App\Modules\Simple\SimpleModule;

return [
    'modules' => [
        // Basic registration (no configuration needed)
        SimpleModule::class,

        // Or with configuration (if you add features)
        // SimpleModule::class => [
        //     'featureName' => true,
        // ],
    ],
];
