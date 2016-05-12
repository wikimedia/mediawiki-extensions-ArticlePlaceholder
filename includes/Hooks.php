<?php

namespace ArticlePlaceholder;

/**
 * File defining the hook handlers for the ArticlePlaceholder extension.
 *
 * @license GNU GPL v2+
 * @author Lucie-Aimée Kaffee
 */
class Hooks {

	/**
	 * External Lua libraries for Scribunto
	 *
	 * @param string $engine
	 * @param array[] &$extraLibraries
	 */
	public static function onScribuntoExternalLibraries( $engine, array &$extraLibraries ) {
		if ( $engine === 'lua' ) {
			$extraLibraries['mw.ext.articlePlaceholder.entityRenderer'] = [
				'class' => 'ArticlePlaceholder\Lua\Scribunto_LuaArticlePlaceholderLibrary',
				'deferLoad' => true,
			];
		}
	}

	/**
	 * External Lua library paths for Scribunto
	 *
	 * @param string $engine
	 * @param string[] &$extraLibraryPaths
	 */
	public static function registerScribuntoExternalLibraryPaths(
		$engine,
		array &$extraLibraryPaths
	) {
		if ( $engine === 'lua' ) {
			$extraLibraryPaths[] = __DIR__ . '/Lua';
		}
	}

	/**
	 * @param string[] $files
	 */
	public static function onUnitTestsList( array &$files ) {
		$files[] = __DIR__ . '/../tests/phpunit/';
	}

}
