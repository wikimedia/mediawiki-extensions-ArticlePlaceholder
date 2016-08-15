<?php

namespace ArticlePlaceholder;

use BaseTemplate;
use Exception;
use SpecialPageFactory;
use Title;
use WebRequest;
use Wikibase\Client\RepoLinker;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;

/**
 * Add Wikibase item link in toolbox for placeholders: Handler for the "BaseTemplateToolbox" hook.
 *
 * @license GPL-2.0+
 * @author Marius Hoch < hoo@online.de >
 */
class BaseTemplateToolboxHookHandler {

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
		$wikibaseClient = WikibaseClient::getDefaultInstance();

		return new self(
			$wikibaseClient->getEntityIdParser(),
			$wikibaseClient->newRepoLinker(),
			$wikibaseClient->getStore()->getEntityLookup()
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
	 * @param BaseTemplate $baseTemplate
	 * @param array &$toolbox
	 */
	public static function onBaseTemplateToolbox( BaseTemplate $baseTemplate, array &$toolbox ) {
		// Return early (for performance reasons) in case we're not on
		// Special:AboutTopic (even before calling newFromGlobalState)
		$title = $baseTemplate->getSkin()->getTitle();

		if ( $title->getNamespace() !== NS_SPECIAL ) {
			return;
		}

		$canonicalSpecialPageName = SpecialPageFactory::resolveAlias( $title->getText() )[0];
		if ( $canonicalSpecialPageName !== 'AboutTopic' ) {
			return;
		}

		$self = self::newFromGlobalState();
		$self->doBaseTemplateToolbox( $baseTemplate, $toolbox );
	}

	/**
	 * @param BaseTemplate $baseTemplate
	 * @param array &$toolbox
	 */
	public function doBaseTemplateToolbox( BaseTemplate $baseTemplate, array &$toolbox ) {
		$itemId = $this->getItemId(
			$baseTemplate->getSkin()->getTitle(),
			$baseTemplate->getSkin()->getRequest()
		);

		if ( $itemId && $this->entityLookup->hasEntity( $itemId ) ) {
			// Duplicated from Wikibase\ClientHooks::onBaseTemplateToolbox
			$toolbox['wikibase'] = [
				'text' => $baseTemplate->getMsg( 'wikibase-dataitem' )->text(),
				'href' => $this->repoLinker->getEntityUrl( $itemId ),
				'id' => 't-wikibase'
			];
		}
	}

	/**
	 * @param Title $title
	 * @param WebRequest $webRequest
	 *
	 * @return ItemId|null
	 */
	private function getItemId( Title $title, WebRequest $webRequest ) {
		$idSerialization = $webRequest->getText(
			'entityid',
			SpecialPageFactory::resolveAlias( $title->getText() )[1]
		);

		if ( !$idSerialization ) {
			return null;
		}

		try {
			return $this->entityIdParser->parse( $idSerialization );
		} catch ( Exception $ex ) {
			// Ignore
		}

		return null;
	}

}
