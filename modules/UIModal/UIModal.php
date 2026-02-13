<?php

namespace Sitchco\Modules\UIModal;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;
use Sitchco\Modules\TimberModule;
use Sitchco\Modules\UIFramework\UIFramework;
use Timber\Post;
use Sitchco\Utils\Str;
use Sitchco\Utils\TimberUtil;

class UIModal extends Module
{
    public const DEPENDENCIES = [TimberModule::class, UIFramework::class];

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

    public function filterModalPostQuery(array $query): void
    {
        add_filter('acf/fields/post_object/query/key=field_698f2dc017b66', function ($args) use ($query) {
            return array_merge($args, $query);
        });
    }

    public function loadModal(Post $post, ModalType $type = ModalType::BOX): ModalData
    {
        if (!in_array($post->slug, $this->modalsLoaded)) {
            $this->modalsLoaded[$post->slug] = new ModalData($post, $type);
        }
        return $this->modalsLoaded[$post->slug];
    }

    public function renderModalContent(ModalData $modalData): string
    {
        $context = [
            'modal' => $modalData,
            'pre_content' => apply_filters(static::hookName('pre-content'), '', $modalData),
            'close' => apply_filters(static::hookName('close'), '&#10005;', $modalData),
        ];
        return TimberUtil::compileWithContext('modal.twig', $context);
    }

    public function unloadModals(): void
    {
        if (count($this->modalsLoaded)) {
            $this->assets()->enqueueScript(static::ASSET_HANDLE);
            $this->assets()->enqueueStyle(static::ASSET_HANDLE);
        }
        foreach ($this->modalsLoaded as $modal) {
            $attributes = apply_filters(
                static::hookName('attributes'),
                [
                    'id' => $modal->id(),
                    'class' => 'sitchco-modal sitchco-modal--' . $modal->type->value,
                    'tabindex' => '-1',
                    'role' => 'dialog',
                    'aria-modal' => 'true',
                ],
                $modal,
            );
            $modalContent = $this->renderModalContent($modal);
            echo Str::wrapElement($modalContent, 'div', $attributes);
        }
    }
}
