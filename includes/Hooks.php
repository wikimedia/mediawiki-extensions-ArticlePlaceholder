<?php

namespace ArticlePlaceholder;

use ArticlePlaceholder\Lua\Scribunto_LuaArticlePlaceholderLibrary;

/**
 * File defining the hook handlers for the ArticlePlaceholder extension.
 *
 * @license GPL-2.0-or-later
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
				'class' => Scribunto_LuaArticlePlaceholderLibrary::class,
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

}
