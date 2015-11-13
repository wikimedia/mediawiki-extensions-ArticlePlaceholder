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
	 * External Lua library paths for Scribunto
	 *
	 * @param string $engine
	 * @param array &$extraLibraryPaths
	 *
	 * @return bool
	 */
	public static function registerScribuntoExternalLibraryPaths(
		$engine,
		array &$extraLibraryPaths
	) {
		if ( $engine !== 'lua' ) {
			return true;
		}

		// Path containing pure Lua libraries that don't need to interact with PHP
		$extraLibraryPaths[] = __DIR__ . '/Lua';

		return true;
	}

}
