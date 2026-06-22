<?php

namespace Sitchco\Modules\TagManager;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;
use Sitchco\Model\PostBase;
use Sitchco\Modules\TimberModule;
use Sitchco\Modules\UIFramework\UIFramework;
use Timber\Timber;

class TagManager extends Module
{
    public const HOOK_SUFFIX = 'tag-manager';

    public const DEPENDENCIES = [UIFramework::class, TimberModule::class];

    public function __construct(protected TagManagerSettings $settings) {}

    public function init(): void
    {
        add_action('acf/init', [$this, 'registerOptionsPage'], 5);
        $this->registerAssets(function (ModuleAssets $assets) {
            $assets->registerScript(static::hookName(), 'main.js', [UIFramework::hookName()]);
        });
        $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
            $assets->enqueueScript(static::hookName());
            $domains = OutboundDomainsResolver::fromSettings($this->settings);
            if (!$domains->isEmpty()) {
                $assets->inlineScriptData(static::hookName(), 'tagManager', [
                    'outboundDecorator' => $domains->toInlineData(),
                ]);
            }
        });
        add_action('wp_head', fn() => $this->renderDataLayerInit(), 4);
        add_action('wp_head', fn() => $this->renderContainerSnippets('headSnippet'), 5);
        add_action('wp_body_open', fn() => $this->renderContainerSnippets('bodySnippet'), 1);
        add_filter('timber/twig/functions', [$this, 'registerTwigFunctions'], 20);
        add_filter(
            'acf/validate_value/key=' . ExtraParamsField::FIELD_KEY,
            [ExtraParamsField::class, 'validateExtraParams'],
            10,
            2,
        );
    }

    public function registerOptionsPage(): void
    {
        acf_add_options_page([
            'page_title' => 'Tag Manager Settings',
            'menu_title' => 'Tag Manager',
            'menu_slug' => 'tag-manager',
            'capability' => 'manage_options',
            'position' => 61,
            'icon_url' => 'dashicons-tag',
            'redirect' => false,
            'autoload' => false,
            'update_button' => 'Update',
            'updated_message' => 'Options Updated',
        ]);
        add_action(
            'admin_menu',
            function () {
                global $submenu;
                if (isset($submenu['tag-manager'])) {
                    $submenu['tag-manager'][0][0] = 'Settings';
                }
            },
            999,
        );
    }

    public function registerTwigFunctions(array $functions): array
    {
        $functions['gtm_attr'] = [
            'callable' => static::renderGtmAttribute(...),
            'is_safe' => ['html'],
        ];
        return $functions;
    }

    public static function renderGtmAttribute(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if ($value === false || $value === 0) {
            return ' data-gtm="0"';
        }
        if (is_array($value) || is_object($value)) {
            $value = wp_json_encode($value);
        }
        return sprintf(' data-gtm="%s"', esc_attr((string) $value));
    }

    protected function getContainerIds(): array
    {
        if (!apply_filters(static::hookName('enable-gtm'), true)) {
            return [];
        }
        $ids = $this->settings->gtm_container_ids;
        return array_unique(array_filter(array_map('trim', array_column($ids ?: [], 'container_id'))));
    }

    protected function renderContainerSnippets(string $method): void
    {
        foreach ($this->getContainerIds() as $id) {
            echo $this->$method($id);
        }
    }

    protected function getPageMetadata(): array
    {
        $obj = get_queried_object();
        return match (true) {
            $obj instanceof \WP_Post => [
                'wp_post_type' => $obj->post_type,
                'wp_post_id' => $obj->ID,
                'wp_slug' => $obj->post_name,
                'wp_title' => $obj->post_title,
            ],
            $obj instanceof \WP_Term => [
                'wp_taxonomy' => $obj->taxonomy,
                'wp_term_id' => $obj->term_id,
                'wp_slug' => $obj->slug,
                'wp_title' => $obj->name,
            ],
            $obj instanceof \WP_Post_Type => [
                'wp_post_type' => $obj->name,
                'wp_slug' => $obj->name,
                'wp_title' => $obj->labels->name,
            ],
            default => [],
        };
    }

    /**
     * Merge the queried post model's data-layer context into the base metadata.
     *
     * No-op for terms, post-type archives, classmap-less posts (plain Timber\Post),
     * and any PostBase subclass that hasn't overridden buildDataLayerContext().
     * Each model's dataLayerContext() is the single source of truth for its keys.
     *
     * @param array<string, mixed> $data Base page metadata from getPageMetadata().
     * @return array<string, mixed>
     */
    protected function mergeQueriedModelContext(array $data): array
    {
        $obj = get_queried_object();
        if ($obj instanceof \WP_Post) {
            $post = Timber::get_post($obj->ID);
            if ($post instanceof PostBase) {
                return array_merge($data, $post->dataLayerContext());
            }
        }
        return $data;
    }

    protected function renderDataLayerInit(): void
    {
        $metadata = $this->mergeQueriedModelContext($this->getPageMetadata());
        $data = apply_filters(static::hookName('current-state'), $metadata);
        // TEMP (SP-579 M1): observe the resolved current-state push payload. Remove in M5.
        error_log('[SP-579] current-state push: ' . wp_json_encode($data));
        $push = !empty($data) ? "\nwindow.dataLayer.push(" . wp_json_encode($data) . ');' : '';
        echo "<script>\nwindow.dataLayer=window.dataLayer||[];{$push}\n</script>\n";
    }

    protected function headSnippet(string $id): string
    {
        $id = esc_js($id);
        return <<<HTML
        <!-- Google Tag Manager -->
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','{$id}');</script>
        <!-- End Google Tag Manager -->

        HTML;
    }

    protected function bodySnippet(string $id): string
    {
        $id = esc_attr($id);
        return <<<HTML
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={$id}"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->

        HTML;
    }
}
