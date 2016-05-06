<?php

namespace ArticlePlaceholder\Lua;

use RuntimeException;
use Scribunto_LuaLibraryBase;

/**
 * Registers and defines functions needed by the Lua modules
 *
 * @licence GNU GPL v2+
 * @author Lucie-Aimée Kaffee
 */
class Scribunto_LuaArticlePlaceholderLibrary extends Scribunto_LuaLibraryBase {

	/**
	 * @return string[]
	 */
	public function getImageProperty() {
		global $wgArticlePlaceholderImageProperty;
		if (
			!is_string( $wgArticlePlaceholderImageProperty ) ||
			$wgArticlePlaceholderImageProperty === ''
		) {
			throw new RuntimeException( 'Bad value in $wgArticlePlaceholderImageProperty' );
		}
		return [ $wgArticlePlaceholderImageProperty ];
	}

	/**
	 * Returns an array containing the serialization of a blacklisted reference property id
	 * @return string[]|null Null if $wgArticlePlaceholderReferencesBlacklist empty or not string
	 */
	public function getReferencesBlacklist() {
		global $wgArticlePlaceholderReferencesBlacklist;
		if (
			!is_string( $wgArticlePlaceholderReferencesBlacklist ) ||
			$wgArticlePlaceholderReferencesBlacklist === ''
		) {
			return null;
		}
		return [ $wgArticlePlaceholderReferencesBlacklist ];
	}

	/**
	 * @return array
	 */
	public function register() {
		// These functions will be exposed to the Lua module.
		// They are member functions on a Lua table which is private to the module, thus
		// these can't be called from user code, unless explicitly exposed in Lua.
		$lib = [
			'getImageProperty' => [ $this, 'getImageProperty' ],
			'getReferencesBlacklist' => [ $this, 'getReferencesBlacklist' ],
		];

		return $this->getEngine()->registerInterface(
			__DIR__ . '/mw.ext.articlePlaceholder.entityRenderer.lua', $lib, []
		);
	}
}
