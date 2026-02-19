<?php
/**
 * Expected:
 * @var array $context
 * @var ContainerInterface $container
 */

use Psr\Container\ContainerInterface;
use Sitchco\Modules\UIModal\ModalData;
use Sitchco\Modules\UIModal\ModalType;
use Sitchco\Modules\UIModal\UIModal;

if (empty($context['fields']['post'])) {
    return '';
}
$post = \Timber\Timber::get_post($context['fields']['post']);
if (!$post) {
    return '';
}
$type = ModalType::tryFrom($context['fields']['type'] ?? '') ?? ModalType::BOX;

$module = $container->get(UIModal::class);

static $pre_content_filter = false;

if ($context['is_preview']) {
    if (!$pre_content_filter) {
        $pre_content_filter = true;
        add_filter(UIModal::hookName('pre-content'), fn($_, ModalData $modal) => "ID: #{$modal->id()}", 10, 2);
    }
    return $module->renderModalContent(new ModalData($post, $type, true));
}

$module->loadModal($post, $type);
return '';
