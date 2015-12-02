<?php

/**
 * AticlePlaceholder extension
 *
 * @ingroup Extensions
 *
 * @author Lucie-Aimée Kaffee
 *
 * The license governing the extension code:
 * @license GNU General Public Licence 2.0 or later
 */
$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'ArticlePlaceholder',
	'author' => array(
		'Lucie-Aimée Kaffee',
	),
	'url' => 'https://www.mediawiki.org/wiki/Extension:ArticlePlaceholder',
	'descriptionmsg' => 'articleplaceholder-desc',
);

$wgArticlePlaceholderImageProperty = 'P18';

$wgAutoloadClasses['ArticlePlaceholder\Specials\SpecialAboutTopic']
	= __DIR__ . '/Specials/SpecialAboutTopic.php';
$wgAutoloadClasses['ArticlePlaceholder\Lua\Scribunto_LuaArticlePlaceholderLibrary']
	= __DIR__ . '/includes/Lua/Scribunto_LuaArticlePlaceholderLibrary.php';
$wgAutoloadClasses['ArticlePlaceholder\Hooks'] = __DIR__ . '/includes/Hooks.php';
$wgAutoloadClasses['ArticlePlaceholder\SearchHookHandler']
	= __DIR__ . '/includes/SearchHookHandler.php';

$wgHooks['ScribuntoExternalLibraries'][]
	= '\ArticlePlaceholder\Hooks::onScribuntoExternalLibraries';
$wgHooks['ScribuntoExternalLibraryPaths'][]
	= '\ArticlePlaceholder\Hooks::registerScribuntoExternalLibraryPaths';
$wgHooks['SpecialSearchResultsAppend'][]
	= '\ArticlePlaceholder\SearchHookHandler::onSpecialSearchResultsAppend';

$wgHooks['UnitTestsList'][] = 'ArticlePlaceholder\Hooks::onUnitTestsList';

$wgMessagesDirs['ArticlePlaceholder'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['ArticlePlaceholderAliases'] = __DIR__ . '/ArticlePlaceholder.alias.php';

$wgSpecialPages['AboutTopic'] = array(
	'ArticlePlaceholder\Specials\SpecialAboutTopic',
	'newFromGlobalState'
);

preg_match( '+' . preg_quote( DIRECTORY_SEPARATOR ) . '(?:vendor|extensions)'
	. preg_quote( DIRECTORY_SEPARATOR ) . '.*+', __DIR__, $remoteExtPath );

$commonModuleInfo = array(
	'localBasePath' => __DIR__ . '/modules',
	'remoteExtPath' => '..' . $remoteExtPath[0] . '/modules',
);

$wgResourceModules['ext.articleplaceholder.createArticle'] = array(
	'position' => 'bottom',
	'scripts' => 'ext.articleplaceholder.createArticle.js',
	'dependencies' => array(
		'oojs-ui',
		'mediawiki.api',
		'mediawiki.Title',
		'mediawiki.util'
	),
	'messages' => array(
		'articleplaceholder-abouttopic-create-article',
		'articleplaceholder-abouttopic-article-exists-error',
		'articleplaceholder-abouttopic-create-article-submit-button'
	),
) + $commonModuleInfo;

$wgResourceModules['ext.articleplaceholder.defaultDisplay'] = array(
	'styles' => array(
		'ext.articleplaceholder.defaultDisplay.css',
		'ext.articleplaceholder.defaultDisplaySmall.css' => array( 'media' => '(max-width: 880px)' ),
	),
	'position' => 'top',
) + $commonModuleInfo;
