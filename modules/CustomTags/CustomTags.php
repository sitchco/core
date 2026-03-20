<?php

namespace Sitchco\Modules\CustomTags;

use Sitchco\Framework\Module;
use Sitchco\Modules\ContentTargeting\ContentTargeting;
use Sitchco\Utils\Cache;

class CustomTags extends Module
{
    public const DEPENDENCIES = [ContentTargeting::class];

    public const HOOK_SUFFIX = 'custom-tags';

    public const POST_CLASSES = [CustomTag::class];

    private const CACHE_KEY = 'custom_tags_by_placement';

    public function __construct(
        protected CustomTagRepository $repository,
        protected ContentTargeting $contentTargeting,
    ) {}

    public function init(): void
    {
        add_action('admin_menu', [$this, 'registerAdminMenu'], 100);
        $this->enqueueAdminAssets([$this, 'initCodeEditor']);
        add_filter('acf/load_field/name=script_placement', [$this, 'loadPlacementChoices']);
        add_action('wp_head', fn() => $this->renderTags(ScriptPlacement::BeforeGtm), 3);
        add_action('wp_head', fn() => $this->renderTags(ScriptPlacement::AfterGtm), 6);
        add_action('wp_footer', fn() => $this->renderTags(ScriptPlacement::Footer));
    }

    public function loadPlacementChoices(array $field): array
    {
        $field['choices'] = ScriptPlacement::choices();
        $field['default_value'] = ScriptPlacement::AfterGtm->value;
        return $field;
    }

    protected function getTagsByPlacement(): array
    {
        return Cache::remember(self::CACHE_KEY, function () {
            $tags = array_fill_keys(array_column(ScriptPlacement::cases(), 'value'), []);
            foreach ($this->repository->findAll() as $tag) {
                $placement = ScriptPlacement::tryFrom($tag->script_placement) ?? ScriptPlacement::AfterGtm;
                $content = $tag->script_content ?: '';
                if ($content !== '') {
                    $tags[$placement->value][] = [
                        'content' => $content,
                        'post_id' => $tag->ID,
                        'targeting' => get_field('script_assignment', $tag->ID) ?: [],
                    ];
                }
            }
            return $tags;
        });
    }

    protected function renderTags(ScriptPlacement $placement): void
    {
        foreach ($this->getTagsByPlacement()[$placement->value] ?? [] as $tag) {
            if (!$this->contentTargeting->matchesCurrentRequest($tag['targeting'])) {
                continue;
            }
            $content = apply_filters(static::hookName('render'), $tag['content'], $tag['post_id'], $placement->value);
            if (!empty($content)) {
                echo $content . "\n";
            }
        }
    }

    public function registerAdminMenu(): void
    {
        global $menu;
        $hasTagManager = false;
        foreach ($menu as $item) {
            if (($item[2] ?? '') === 'tag-manager') {
                $hasTagManager = true;
                break;
            }
        }
        if ($hasTagManager) {
            add_submenu_page(
                'tag-manager',
                'Custom Tags',
                'Custom Tags',
                'edit_posts',
                'edit.php?post_type=' . CustomTag::POST_TYPE,
            );
        } else {
            add_menu_page(
                'Custom Tags',
                'Custom Tags',
                'edit_posts',
                'edit.php?post_type=' . CustomTag::POST_TYPE,
                '',
                'dashicons-code-standards',
                100,
            );
        }
    }

    public function initCodeEditor(): void
    {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== CustomTag::POST_TYPE) {
            return;
        }
        $settings = wp_enqueue_code_editor(['type' => 'text/html']);
        if ($settings === false) {
            return;
        }
        wp_add_inline_script(
            'code-editor',
            sprintf(
                'jQuery(function($){var $el=$(".acf-field[data-name=script_content] textarea");if($el.length){wp.codeEditor.initialize($el[0],%s);}});',
                wp_json_encode($settings),
            ),
        );
    }
}
