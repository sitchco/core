<?php

namespace Sitchco\Modules\UIModal;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;
use Sitchco\Utils\ArrayUtil;
use Sitchco\Utils\TimberUtil;

class UIModal extends Module
{
    const ASSET_HANDLE = 'ui-modal';

    /**
     * @var array<string, ModalData>
     */
    protected array $modalsLoaded = [];

    public function init(): void
    {
        add_action('wp_footer', [$this, 'unloadModals']);
        add_filter('timber/locations', function ($paths) {
            $paths[] = __DIR__ . '/templates';
            return $paths;
        });
        $this->registerAssets(function (ModuleAssets $assets) {
            $assets->registerScript(static::ASSET_HANDLE, 'main.js', ['sitchco/ui-framework']);
            $assets->registerStyle(static::ASSET_HANDLE, 'main.css');
        });
    }

    public function loadModal(
        string $id,
        string $content,
        ModalType $type = ModalType::BOX,
        $format_content = false,
        $label = '',
    ): ModalData {
        $id = sanitize_html_class($id);
        if (!in_array($id, $this->modalsLoaded)) {
            $this->modalsLoaded[$id] = new ModalData($id, $content, $type, $format_content, $label);
        }
        return $this->modalsLoaded[$id];
    }

    public function unloadModals(): void
    {
        foreach ($this->modalsLoaded as $modal) {
            $content = $modal->format_content ? apply_filters('the_content', $modal->content) : $modal->content;
            $class = 'modal modal--' . $modal->type->value;
            $attributes = apply_filters(static::hookName('attributes'), ['class' => $class], $modal);
            $context = [
                'modal' => $modal,
                'attributes' => ArrayUtil::toAttributes($attributes),
                'content' => $content,
                'close' => apply_filters(static::hookName('close'), '&#10005;', $modal),
            ];
            echo TimberUtil::compileWithContext('modal.twig', $context);
        }
    }
}
