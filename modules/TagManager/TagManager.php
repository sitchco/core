<?php

namespace Sitchco\Modules\TagManager;

use Sitchco\Framework\ConfigRegistry;
use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;
use Sitchco\Modules\UIFramework\UIFramework;

class TagManager extends Module
{
    public const HOOK_SUFFIX = 'tag-manager';

    public const DEPENDENCIES = [UIFramework::class];

    public function __construct(
        protected TagManagerSettings $settings,
        private readonly ConfigRegistry $configRegistry,
    ) {}

    public function init(): void
    {
        $this->registerAssets(function (ModuleAssets $assets) {
            $assets->registerScript(static::hookName(), 'main.js', [UIFramework::hookName()]);
            $outboundDomains = $this->getOutboundDomains();
            if (!empty($outboundDomains)) {
                $assets->inlineScriptData(static::hookName(), 'tagManager', [
                    'outboundDomains' => $outboundDomains,
                ]);
            }
        });

        add_action('wp_head', fn() => $this->renderDataLayerInit(), 4);
        add_action('wp_head', fn() => $this->renderContainerSnippets('headSnippet'), 5);
        add_action('wp_body_open', fn() => $this->renderContainerSnippets('bodySnippet'), 1);
        add_filter('timber/twig/functions', function ($functions) {
            $functions['gtm_attr'] = [
                'callable' => static::renderGtmAttribute(...),
                'is_safe' => ['html'],
            ];
            return $functions;
        }, 20);
    }

    public static function renderGtmAttribute(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            $value = wp_json_encode($value);
        }
        return sprintf(' data-gtm="%s"', esc_attr((string) $value));
    }

    protected function getOutboundDomains(): array
    {
        if (!$this->settings->gtm_decorate_outbound) {
            return [];
        }
        $acfDomains = array_filter(array_column($this->settings->gtm_outbound_domains ?: [], 'domain'));
        $tagManagerConfig = $this->configRegistry->load('tagManager');
        $configDomains = array_keys($tagManagerConfig['outboundDomains'] ?? []);
        $merged = array_unique(array_merge($acfDomains, $configDomains));
        $domains = apply_filters(static::hookName('outbound-domains'), $merged);

        return array_fill_keys(array_values(array_filter($domains)), true);
    }

    protected function getContainerIds(): array
    {
        if (!apply_filters(static::hookName('enable-gtm'), true)) {
            return [];
        }
        $ids = $this->settings->gtm_container_ids;
        return array_filter(array_column($ids ?: [], 'container_id'));
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
        [$type, $id, $slug] = match (true) {
            $obj instanceof \WP_Post => [$obj->post_type, $obj->ID, $obj->post_name],
            $obj instanceof \WP_Term => [$obj->taxonomy, $obj->term_id, $obj->slug],
            $obj instanceof \WP_Post_Type => [$obj->name, 0, $obj->name],
            default => [null, null, null],
        };
        return $type !== null ? [
            'wp_post_type' => $type,
            'wp_post_id' => $id,
            'wp_slug' => $slug,
        ] : [];
    }

    protected function renderDataLayerInit(): void
    {
        $data = apply_filters(static::hookName('current-state'), $this->getPageMetadata());
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
