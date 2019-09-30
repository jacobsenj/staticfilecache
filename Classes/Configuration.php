<?php

/**
 * Configuration.
 */

declare(strict_types = 1);

namespace SFC\Staticfilecache;

use SFC\Staticfilecache\Cache\RemoteFileBackend;
use SFC\Staticfilecache\Cache\Rule\Enable;
use SFC\Staticfilecache\Cache\Rule\ForceStaticCache;
use SFC\Staticfilecache\Cache\Rule\LoginDeniedConfiguration;
use SFC\Staticfilecache\Cache\Rule\NoBackendUser;
use SFC\Staticfilecache\Cache\Rule\NoFakeFrontend;
use SFC\Staticfilecache\Cache\Rule\NoIntScripts;
use SFC\Staticfilecache\Cache\Rule\NoLongPathSegment;
use SFC\Staticfilecache\Cache\Rule\NoNoCache;
use SFC\Staticfilecache\Cache\Rule\NoUserOrGroupSet;
use SFC\Staticfilecache\Cache\Rule\NoWorkspacePreview;
use SFC\Staticfilecache\Cache\Rule\PageCacheable;
use SFC\Staticfilecache\Cache\Rule\SiteCacheable;
use SFC\Staticfilecache\Cache\Rule\StaticCacheable;
use SFC\Staticfilecache\Cache\Rule\ValidDoktype;
use SFC\Staticfilecache\Cache\Rule\ValidPageInformation;
use SFC\Staticfilecache\Cache\Rule\ValidRequestMethod;
use SFC\Staticfilecache\Cache\Rule\ValidUri;
use SFC\Staticfilecache\Cache\StaticFileBackend;
use SFC\Staticfilecache\Cache\UriFrontend;
use SFC\Staticfilecache\Hook\InitFrontendUser;
use SFC\Staticfilecache\Hook\LogoffFrontendUser;
use SFC\Staticfilecache\Hook\UninstallProcess;
use TYPO3\CMS\Core\Cache\Backend\NullBackend;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Imaging\IconProvider\FontawesomeIconProvider;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use TYPO3\CMS\Extensionmanager\Utility\InstallUtility;

/**
 * Configuration.
 */
class Configuration
{
    /**
     * Add Web>Info module:.
     */
    public static function registerBackendModule()
    {
        ExtensionUtility::registerModule(
            'SFC.Staticfilecache',
            'web',
            'staticfilecache',
            '',
            [
                'Backend' => 'list,boost,support',
            ],
            [
                'access' => 'user,group',
                'icon' => 'EXT:staticfilecache/Resources/Public/Icons/Extension.svg',
                'labels' => 'LLL:EXT:staticfilecache/Resources/Private/Language/locallang_mod.xlf',
            ]
        );
    }

    /**
     * Register hooks.
     */
    public static function registerHooks()
    {
        // Set cookie when User logs in
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['initFEuser']['staticfilecache'] = InitFrontendUser::class . '->setFeUserCookie';
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_userauth.php']['logoff_post_processing']['staticfilecache'] = LogoffFrontendUser::class . '->logoff';
    }

    /**
     * Register slots.
     */
    public static function registerSlots()
    {
        $ruleClasses = [
            StaticCacheable::class,
            ValidUri::class,
            SiteCacheable::class,
            ValidDoktype::class,
            NoWorkspacePreview::class,
            NoUserOrGroupSet::class,
            NoIntScripts::class,
            LoginDeniedConfiguration::class,
            PageCacheable::class,
            NoNoCache::class,
            NoBackendUser::class,
            Enable::class,
            ValidRequestMethod::class,
            ValidPageInformation::class,
            ForceStaticCache::class,
            NoFakeFrontend::class,
            NoLongPathSegment::class,
        ];

        /** @var Dispatcher $signalSlotDispatcher */
        $signalSlotDispatcher = GeneralUtility::makeInstance(Dispatcher::class);
        foreach ($ruleClasses as $class) {
            $signalSlotDispatcher->connect(StaticFileCache::class, 'cacheRule', $class, 'check');
        }

        $signalSlotDispatcher->connect(InstallUtility::class, 'afterExtensionUninstall', UninstallProcess::class, 'afterExtensionUninstall');
    }

    /**
     * Register caching framework.
     */
    public static function registerCachingFramework()
    {
        $configuration = self::getConfiguration();
        $useNullBackend = isset($configuration['disableInDevelopment']) && $configuration['disableInDevelopment'] && GeneralUtility::getApplicationContext()->isDevelopment();

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['staticfilecache'] = [
            'frontend' => UriFrontend::class,
            'backend' => $useNullBackend ? NullBackend::class : StaticFileBackend::class,
            'groups' => [
                'pages',
                'all',
            ],
        ];

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['remote_file'] = [
            'frontend' => UriFrontend::class,
            'backend' => RemoteFileBackend::class,
            'groups' => [
                'all',
            ],
            'options' => [
                // 'defaultLifetime' => 3600,
                // 'hashLength' => 10,
            ],
        ];
    }

    /**
     * Add fluid namespaces.
     */
    public static function registerFluidNamespace()
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['sfc'] = ['SFC\\Staticfilecache\\ViewHelpers'];
    }

    /**
     * Register eID scripts
     */
    public static function registerEid()
    {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['sfc_manifest'] = 'EXT:staticfilecache/Resources/Private/Php/Manifest.php';
    }

    /**
     * Register icons.
     */
    public static function registerIcons()
    {
        $iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);
        $iconRegistry->registerIcon(
            'brand-amazon',
            FontawesomeIconProvider::class,
            ['name' => 'amazon']
        );
        $iconRegistry->registerIcon(
            'brand-paypal',
            FontawesomeIconProvider::class,
            ['name' => 'paypal']
        );
        $iconRegistry->registerIcon(
            'documentation-book',
            FontawesomeIconProvider::class,
            ['name' => 'book']
        );
    }

    /**
     * Get the current extension configuration.
     *
     * @return array
     */
    public static function getConfiguration(): array
    {
        static $configuration;
        if (\is_array($configuration)) {
            return $configuration;
        }
        $configuration = (array)GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('staticfilecache');
        return $configuration;
    }
}
