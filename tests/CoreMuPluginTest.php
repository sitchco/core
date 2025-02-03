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
use WPTest\Test\TestCase;

class CoreMuPluginTest extends TestCase
{
    function test_registers_and_activates_core_modules()
    {
        global $SitchcoContainer;
        $Loader = $SitchcoContainer->get(ModuleConfigLoader::class);
        $this->assertEquals([
            Cleanup::class => true,
            SearchRewrite::class => true,
            BackgroundEventManager::class => true,
            PostModel::class => true,
            TermModel::class => true
        ], $Loader->load());
        $full_feature_list = $SitchcoContainer->get(Registry::class)->getModuleFeatures();
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
        global $SitchcoContainer;
        $active_modules = $SitchcoContainer->get(Registry::class)->getActiveModules();
        $this->assertEquals([
            Cleanup::class => $SitchcoContainer->get(Cleanup::class),
            SearchRewrite::class => $SitchcoContainer->get(SearchRewrite::class),
            BackgroundEventManager::class => $SitchcoContainer->get(BackgroundEventManager::class),
            Timber::class => $SitchcoContainer->get(Timber::class),
            PostModel::class => $SitchcoContainer->get(PostModel::class),
            TermModel::class => $SitchcoContainer->get(TermModel::class)
        ], $active_modules);
        $this->assertHasFilter('body_class', $SitchcoContainer->get(Cleanup::class), 'bodyClass');
        $this->assertHasFilter('wpseo_json_ld_search_url', $SitchcoContainer->get(SearchRewrite::class), 'rewriteUrl');
        $this->assertHasAction('current_screen', $SitchcoContainer->get(SavePermalinksAsyncHook::class), 'onSavePermalinks');
        $this->assertTrue(TIMBER_LOADED);
    }

}