<?php

namespace ArticlePlaceholder;

use Exception;
use MediaWiki\MediaWikiServices;
use Skin;
use Wikibase\Client\RepoLinker;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;

/**
 * Add Wikibase item link in toolbox for placeholders: Handler for the "SidebarBeforeOutput" hook.
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch < hoo@online.de >
 */
class SidebarBeforeOutputHookHandler {

	/**
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	/**
	 * @var RepoLinker
	 */
	private $repoLinker;

	/**
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @return self
	 */
	private static function newFromGlobalState() {
		return new self(
			WikibaseClient::getEntityIdParser(),
			WikibaseClient::getRepoLinker(),
			WikibaseClient::getStore()->getEntityLookup()
		);
	}

	/**
	 * @param EntityIdParser $entityIdParser
	 * @param RepoLinker $repoLinker
	 * @param EntityLookup $entityLookup
	 */
	public function __construct(
		EntityIdParser $entityIdParser,
		RepoLinker $repoLinker,
		EntityLookup $entityLookup
	) {
		$this->entityIdParser = $entityIdParser;
		$this->repoLinker = $repoLinker;
		$this->entityLookup = $entityLookup;
	}

	/**
	 * @param Skin $skin
	 * @param array[] &$sidebar
	 *
	 * @return void
	 */
	public static function onSidebarBeforeOutput( Skin $skin, &$sidebar ): void {
		$self = self::newFromGlobalState();
		$sidebarLink = $self->buildSidebarLink( $skin );

		if ( !$sidebarLink ) {
			return;
		}

		// Append link
		$sidebar['TOOLBOX']['wikibase'] = $sidebarLink;
	}

	/**
	 * Do checks. Build link array, if possible.
	 *
	 * @param Skin $skin
	 *
	 * @return bool|string[] Array of link elements or False if link cannot be craated
	 */
	public function buildSidebarLink( Skin $skin ) {
		// Return early (for performance reasons) in case we're not on
		// Special:AboutTopic (even before calling newFromGlobalState)
		$title = $skin->getTitle();

		if ( !$title->inNamespace( NS_SPECIAL ) ) {
			return false;
		}

		$factory = MediaWikiServices::getInstance()->getSpecialPageFactory();
		$canonicalSpecialPageName = $factory->resolveAlias( $title->getText() )[0];

		if ( $canonicalSpecialPageName !== 'AboutTopic' ) {
			return false;
		}

		$itemId = $this->getItemId( $skin );

		if ( $itemId === null || !$this->entityLookup->hasEntity( $itemId ) ) {
			return false;
		}
		// Duplicated from Wikibase\ClientHooks::buildWikidataItemLink
		return [
			'id' => 't-wikibase',
			'text' => $skin->msg( 'wikibase-dataitem' )->text(),
			'href' => $this->repoLinker->getEntityUrl( $itemId )
		];
	}

	/**
	 * @param Skin $skin
	 *
	 * @return ItemId|null
	 */
	private function getItemId( Skin $skin ): ?ItemId {
		$title = $skin->getTitle();
		$request = $skin->getRequest();

		$factory = MediaWikiServices::getInstance()->getSpecialPageFactory();
		$idSerialization = $request->getText(
			'entityid',
			$factory->resolveAlias( $title->getText() )[1]
		);

		if ( !$idSerialization ) {
			return null;
		}

		try {
			// @phan-suppress-next-line PhanTypeMismatchReturn
			return $this->entityIdParser->parse( $idSerialization );
		} catch ( Exception $ex ) {
			// Ignore
		}

		return null;
	}

}
