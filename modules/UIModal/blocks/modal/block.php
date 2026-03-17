<?php
/**
 * Expected:
 * @var array $context
 * @var ContainerInterface $container
 */

use Psr\Container\ContainerInterface;
use Sitchco\Modules\UIModal\ModalData;
use Sitchco\Modules\UIModal\UIModal;

if (empty($context['fields']['post'])) {
    return '';
}
$post = \Timber\Timber::get_post($context['fields']['post']);
if (!$post) {
    return '';
}
$module = $container->get(UIModal::class);
$rawType = $context['fields']['type'] ?? '';
$type = $module->isRegistered($rawType) ? $rawType : 'box';

static $pre_content_filter = false;

if ($context['is_preview']) {
    if (!$pre_content_filter) {
        $pre_content_filter = true;
        add_filter(UIModal::hookName('pre-content'), fn($_, ModalData $modal) => "ID: #{$modal->id()}", 10, 2);
    }
    return $module->renderModalContent(ModalData::fromPost($post, $type, true));
}

$modal = $module->loadModal(ModalData::fromPost($post, $type));

/**
 * Return HTML comment to avoid automatically removing any assets enqueued by modal's post content
 * when the block content itself is empty
 * @see WP_Block:::render()
 */
return sprintf('<!-- modal:%s -->', $modal ? $modal->id() : 'error');
