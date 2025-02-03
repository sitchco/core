<?php

namespace Test\Sitchco;

use DI\DependencyException;
use DI\NotFoundException;
use Sitchco\Events\SavePermalinksAsyncHook;
use Sitchco\Framework\Config\ModuleConfigLoader;
use Sitchco\Framework\Core\Registry;
use Sitchco\Integration\BackgroundEventManager;
use Sitchco\Integration\Timber;
use Sitchco\Integration\Wordpress\Cleanup;
use Sitchco\Integration\Wordpress\SearchRewrite;
use Sitchco\Model\PostModel;
use Sitchco\Model\TermModel;
use Sitchco\Tests\Support\TestCase;

class CoreMuPluginTest extends TestCase
{
    function test_registers_and_activates_core_modules()
    {
        $Loader = $this->container->get(ModuleConfigLoader::class);
        $this->assertEquals([
            Cleanup::class => true,
            SearchRewrite::class => true,
            BackgroundEventManager::class => true,
            PostModel::class => true,
            TermModel::class => true
        ], $Loader->load());
        $full_feature_list = $this->container->get(Registry::class)->getModuleFeatures();
        $this->assertEquals(
            [
                Cleanup::class => [
                    'obscurity' => true,
                    'cleanHtmlMarkup' => true,
                    'disableEmojis' => true,
                    'disableGutenbergBlockCss' => true,
                    'disableExtraRss' => true,
                    'disableRecentCommentsCss' => true,
                    'disableGalleryCss' => true,
                    'disableXmlRpc' => true,
                    'disableFeeds' => true,
                    'disableDefaultPosts' => true,
                    'disableComments' => true,
                    'removeLanguageDropdown' => true,
                    'removeWordPressVersion' => true,
                    'disableRestEndpoints' => true,
                    'removeJpegCompression' => true,
                    'updateLoginPage' => true,
                    'removeGutenbergStyles' => true,
                    'removeScriptVersion' => true
                ],
                SearchRewrite::class => [
                    'redirect' => true,
                    'compatibility' => true
                ],
                BackgroundEventManager::class => [
                    'savePermalinks' => true
                ],
                Timber::class => true,
                PostModel::class => true,
                TermModel::class => true
            ],
            $full_feature_list
        );
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    function test_active_modules_initialized()
    {
        $active_modules = $this->container->get(Registry::class)->getActiveModules();
        $this->assertEquals([
            Cleanup::class => $this->container->get(Cleanup::class),
            SearchRewrite::class => $this->container->get(SearchRewrite::class),
            BackgroundEventManager::class => $this->container->get(BackgroundEventManager::class),
            Timber::class => $this->container->get(Timber::class),
            PostModel::class => $this->container->get(PostModel::class),
            TermModel::class => $this->container->get(TermModel::class)
        ], $active_modules);
        $this->assertHasFilter('body_class', $this->container->get(Cleanup::class), 'bodyClass');
        $this->assertHasFilter('wpseo_json_ld_search_url', $this->container->get(SearchRewrite::class), 'rewriteUrl');
        $this->assertHasAction('current_screen', $this->container->get(SavePermalinksAsyncHook::class), 'onSavePermalinks');
        $this->assertTrue(TIMBER_LOADED);
    }

}