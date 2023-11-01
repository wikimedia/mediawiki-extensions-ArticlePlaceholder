<?php

namespace ArticlePlaceholder;

use ArticlePlaceholder\Lua\Scribunto_LuaArticlePlaceholderLibrary;
use MediaWiki\Extension\Scribunto\Hooks\ScribuntoExternalLibrariesHook;
use MediaWiki\Extension\Scribunto\Hooks\ScribuntoExternalLibraryPathsHook;

/**
 * File defining the hook handlers for the ArticlePlaceholder extension.
 * All hooks from the Scribunto extension which is optional to use with this extension.
 *
 * @license GPL-2.0-or-later
 * @author Lucie-Aimée Kaffee
 */
class ScribuntoHooks implements
	ScribuntoExternalLibrariesHook,
	ScribuntoExternalLibraryPathsHook
{

	/**
	 * External Lua libraries for Scribunto
	 *
	 * @param string $engine
	 * @param array[] &$extraLibraries
	 */
	public function onScribuntoExternalLibraries( string $engine, array &$extraLibraries ): void {
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
	public function onScribuntoExternalLibraryPaths(
		string $engine,
		array &$extraLibraryPaths
	): void {
		if ( $engine === 'lua' ) {
			$extraLibraryPaths[] = __DIR__ . '/Lua';
		}
	}

}
