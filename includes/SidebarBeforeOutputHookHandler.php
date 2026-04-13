<?php

namespace ArticlePlaceholder;

use MediaWiki\Skin\Hook\SidebarBeforeOutputHook;
use MediaWiki\Skin\Skin;
use MediaWiki\SpecialPage\SpecialPageFactory;
use Wikibase\Client\RepoLinker;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;

/**
 * Add Wikibase item link in toolbox for placeholders: Handler for the "SidebarBeforeOutput" hook.
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch < hoo@online.de >
 */
class SidebarBeforeOutputHookHandler implements SidebarBeforeOutputHook {

	public static function newFromGlobalState( SpecialPageFactory $specialPageFactory ): self {
		return new self(
			WikibaseClient::getEntityIdParser(),
			WikibaseClient::getRepoLinker(),
			WikibaseClient::getStore()->getEntityLookup(),
			$specialPageFactory
		);
	}

	public function __construct(
		private readonly EntityIdParser $entityIdParser,
		private readonly RepoLinker $repoLinker,
		private readonly EntityLookup $entityLookup,
		private readonly SpecialPageFactory $specialPageFactory,
	) {
	}

	/**
	 * @param Skin $skin
	 * @param array[] &$sidebar
	 *
	 * @return void
	 */
	public function onSidebarBeforeOutput( $skin, &$sidebar ): void {
		$sidebarLink = $this->buildSidebarLink( $skin );
		if ( $sidebarLink ) {
			// Append link
			$sidebar['TOOLBOX']['wikibase'] = $sidebarLink;
		}
	}

	/**
	 * Do checks. Build link array, if possible.
	 *
	 * @param Skin $skin
	 *
	 * @return bool|string[] Array of link elements or False if link cannot be created
	 */
	public function buildSidebarLink( Skin $skin ) {
		// Return early (for performance reasons) in case we're not on
		// Special:AboutTopic (even before calling newFromGlobalState)
		if ( !$skin->getTitle()->isSpecial( 'AboutTopic' ) ) {
			return false;
		}

		$itemId = $this->getItemId( $skin );
		if ( !$itemId || !$this->entityLookup->hasEntity( $itemId ) ) {
			return false;
		}

		// Duplicated from Wikibase\ClientHooks::buildWikidataItemLink
		return [
			'id' => 't-wikibase',
			'text' => $skin->msg( 'wikibase-dataitem' )->text(),
			'href' => $this->repoLinker->getEntityUrl( $itemId )
		];
	}

	private function getItemId( Skin $skin ): ?ItemId {
		$title = $skin->getTitle();

		if ( !$title ) {
			return null;
		}

		$request = $skin->getRequest();

		$idSerialization = $request->getVal(
			'entityid',
			$this->specialPageFactory->resolveAlias( $title->getText() )[1]
		);

		if ( !$idSerialization ) {
			return null;
		}

		try {
			// @phan-suppress-next-line PhanTypeMismatchReturn
			return $this->entityIdParser->parse( $idSerialization );
		} catch ( EntityIdParsingException ) {
			// Ignore
		}

		return null;
	}

}
