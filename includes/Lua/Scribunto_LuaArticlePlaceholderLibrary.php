<?php

namespace ArticlePlaceholder\Lua;

use MediaWiki\Config\ConfigException;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LibraryBase;
use MediaWiki\MediaWikiServices;

/**
 * Registers and defines functions needed by the Lua modules
 *
 * @license GPL-2.0-or-later
 * @author Lucie-Aimée Kaffee
 */
class Scribunto_LuaArticlePlaceholderLibrary extends LibraryBase {

	/**
	 * @return string[]
	 */
	public function getImageProperty() {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$imageProperty = $config->get( 'ArticlePlaceholderImageProperty' );
		if (
			!is_string( $imageProperty ) ||
			$imageProperty === ''
		) {
			throw new ConfigException( 'Bad value in $wgArticlePlaceholderImageProperty' );
		}
		return [ $imageProperty ];
	}

	/**
	 * Returns an array containing the serialization of a single reference property id to hide
	 *
	 * @return string[]|null Null if $wgArticlePlaceholderReferencesBlacklist empty or not string
	 */
	public function getReferencePropertyToHide() {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$referencesBlacklist = $config->get( 'ArticlePlaceholderReferencesBlacklist' );
		if (
			!is_string( $referencesBlacklist ) ||
			$referencesBlacklist === ''
		) {
			return null;
		}
		return [ $referencesBlacklist ];
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
			'getReferencePropertyToHide' => [ $this, 'getReferencePropertyToHide' ],
		];

		return $this->getEngine()->registerInterface(
			__DIR__ . '/mw.ext.articlePlaceholder.entityRenderer.lua', $lib, []
		);
	}

}
