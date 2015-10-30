<?php

/**
 * AticlePlaceholder extension
 *
 * @ingroup Extensions
 *
 * @author Lucie-Aimée Kaffee
 *
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

$dir = dirname( __FILE__ );
$dirbasename = basename( $dir );

$wgAutoloadClasses['ArticlePlaceholder\Specials\SpecialAboutTopic'] = $dir . '/Specials/SpecialAboutTopic.php';
$wgAutoloadClasses['ArticlePlaceholder\Hooks'] = __DIR__ . '/includes/Hooks.php';
$wgAutoloadClasses['ArticlePlaceholder\SearchHookHandler'] = __DIR__ . '/includes/SearchHookHandler.php';
$wgHooks['ScribuntoExternalLibraryPaths'][] = '\ArticlePlaceholder\Hooks::registerScribuntoExternalLibraryPaths';
$wgHooks['SpecialSearchResultsAppend'][] = '\ArticlePlaceholder\SearchHookHandler::onSpecialSearchResultsAppend';

$wgMessagesDirs['ArticlePlaceholder'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['ArticlePlaceholderAliases'] = __DIR__ . '/ArticlePlaceholder.alias.php';

$wgSpecialPages['AboutTopic'] = array(
	'ArticlePlaceholder\Specials\SpecialAboutTopic',
	'newFromGlobalState'
);

preg_match( '+' . preg_quote( DIRECTORY_SEPARATOR ) . '(?:vendor|extensions)'
	. preg_quote( DIRECTORY_SEPARATOR ) . '.*+', __DIR__, $remoteExtPath );

$wgResourceModules['ext.articleplaceholder.createArticle'] = array(
	'position' => 'bottom',
	'scripts' => 'ext.articleplaceholder.createArticle.js',
	'dependencies' => array(
		'oojs-ui',
		'mediawiki.api',
		'mediawiki.Title',
		'mediawiki.util'
	),
	'localBasePath' => __DIR__ . '/modules',
	'remoteExtPath' => '..' . $remoteExtPath[0],
	'messages' => array(
		'articleplaceholder-abouttopic-create-article',
		'articleplaceholder-abouttopic-article-exists-error',
		'articleplaceholder-abouttopic-create-article-submit-button'
	)
);
