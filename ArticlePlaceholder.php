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

$wgAutoloadClasses[ 'ArticlePlaceholder\Specials\SpecialFancyUnicorn' ] = $dir . '/Specials/SpecialFancyUnicorn.php';

$wgMessagesDirs[ 'ArticlePlaceholder' ] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['ArticlePlaceholderAliases'] = __DIR__ . '/ArticlePlaceholder.alias.php';

$wgSpecialPages[ 'FancyUnicorn' ] = array(
	'ArticlePlaceholder\Specials\SpecialFancyUnicorn',
	'newFromGlobalState'
);