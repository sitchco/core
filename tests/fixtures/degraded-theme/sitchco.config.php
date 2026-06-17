<?php

/**
 * Fixture theme config used by ConfigRegistryTest.
 *
 * Stands in for an active theme's sitchco.config.php so the ConfigRegistry completeness guard has a
 * real file on disk to detect. Its contents are intentionally simple — the degraded-state test only
 * needs the file to exist, while the healthy-state test merges these modules with the core config.
 */

return [
    'modules' => [
        'Sitchco\\Tests\\Fake\\ThemeOnlyModule' => true,
    ],
];
