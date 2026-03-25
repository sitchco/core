<?php

namespace Sitchco\Modules\UIModal;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;
use Sitchco\Modules\TimberModule;
use Sitchco\Modules\UIFramework\UIFramework;
use Sitchco\Utils\ArrayUtil;
use Sitchco\Utils\Str;
use Sitchco\Utils\TimberUtil;

class UIModal extends Module
{
    public const DEPENDENCIES = [TimberModule::class, UIFramework::class];

    const HOOK_SUFFIX = 'ui-modal';

    const DEFAULT_TYPE = 'box';

    /**
     * @var array<string, ModalData>
     */
    protected array $modalsLoaded = [];

    private array $types = [];

    public function init(): void
    {
        $this->registerType(static::DEFAULT_TYPE, ['label' => 'Box (default)']);
        $this->registerType('full', ['label' => 'Full Screen']);
        add_action('wp_footer', [$this, 'unloadModals']);
        add_filter('timber/locations', function ($paths) {
            $paths[] = [__DIR__ . '/templates'];
            return $paths;
        });
        add_filter('acf/prepare_field/key=field_698f2ded17b67', [$this, 'typeFieldChoices']);
        $this->registerAssets(function (ModuleAssets $assets) {
            $assets->registerScript(static::hookName(), 'main.js', [UIFramework::hookName()]);
            $assets->registerStyle(static::hookName(), 'main.css');
        });
    }

    public function registerType(string $key, array $options = []): void
    {
        $this->types[$key] = $options;
    }

    public function isRegistered(string $key): bool
    {
        return isset($this->types[$key]);
    }

    public function resolveType(string $type): string
    {
        return $this->isRegistered($type) ? $type : static::DEFAULT_TYPE;
    }

    public function isLoaded(string $id): bool
    {
        return isset($this->modalsLoaded[$id]);
    }

    public function typeFieldChoices(array $field): array
    {
        $field['choices'] = array_filter(array_map(fn(array $options) => $options['label'] ?? null, $this->types));
        return $field;
    }

    public function filterModalPostQuery(array|callable $query): void
    {
        add_filter('acf/fields/post_object/query/key=field_698f2dc017b66', function ($args) use ($query) {
            $resolved = is_callable($query) ? $query() : $query;
            return array_merge($args, $resolved);
        });
    }

    public function loadModal(ModalData $modal): ?ModalData
    {
        $id = $modal->id();
        if (!$id) {
            return null;
        }
        if (!isset($this->modalsLoaded[$id])) {
            $resolvedType = $this->resolveType($modal->type);
            if ($resolvedType !== $modal->type) {
                $modal = $modal->withType($resolvedType);
            }
            $this->modalsLoaded[$id] = $modal;
        }
        return $this->modalsLoaded[$id];
    }

    public function renderModalContent(ModalData $modalData): string
    {
        $contentAttrs = ['class' => 'sitchco-modal__content'];
        $contentAttrs = apply_filters(static::hookName('content-attributes'), $contentAttrs, $modalData);
        $context = [
            'modal' => $modalData,
            'content_attributes' => ArrayUtil::toAttributes($contentAttrs),
            'pre_content' => apply_filters(static::hookName('pre-content'), '', $modalData),
            'close' => apply_filters(static::hookName('close'), '&#10005;', $modalData),
        ];
        return TimberUtil::compileWithContext('modal.twig', $context);
    }

    public function unloadModals(): void
    {
        if (count($this->modalsLoaded)) {
            $this->assets()->enqueueScript(static::hookName());
            $this->assets()->enqueueStyle(static::hookName());
        }
        foreach ($this->modalsLoaded as $modal) {
            $attributes = apply_filters(
                static::hookName('attributes'),
                [
                    'id' => $modal->id(),
                    'class' => 'sitchco-modal sitchco-modal--' . $modal->type,
                    'tabindex' => '-1',
                ],
                $modal,
            );
            $modalContent = $this->renderModalContent($modal);
            echo Str::wrapElement($modalContent, 'dialog', $attributes);
        }
    }
}
