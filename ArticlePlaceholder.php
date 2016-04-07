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

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'ArticlePlaceholder', __DIR__ . '/extension.json' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['ArticlePlaceholder'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['ArticlePlaceholder'] = __DIR__ . '/ArticlePlaceholder.alias.php';
	/* wfWarn(
		'Deprecated PHP entry point used for ArticlePlaceholder extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return true;
} else {
	die( 'This version of the ArticlePlaceholder extension requires MediaWiki 1.25+' );
}
