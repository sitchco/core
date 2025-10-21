/**
 * Simple Module JavaScript
 *
 * Example script for SimpleModule.
 */

(function ($) {
    'use strict';

    // Wait for DOM ready
    $(document).ready(function () {
        console.log('SimpleModule loaded');

        // Example: Add click handler to notice
        $('.simple-module-notice').on('click', function () {
            $(this).fadeOut();
        });
    });
})(jQuery);
