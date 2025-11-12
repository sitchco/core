<?php
/**
 * @var array $context
 */

if (!empty($context['block']['return_context'])) {
    $context['render'] = serialize($context);
}
