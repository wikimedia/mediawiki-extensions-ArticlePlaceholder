<?php

namespace ArticlePlaceholder\Specials;

use ArticlePlaceholder\AboutTopicRenderer;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\TitleFactory;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\Lib\Store\SiteLinkLookup;

/**
 * The AboutTopic SpecialPage for the ArticlePlaceholder extension
 *
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 * @author Lucie-Aimée Kaffee
 */
class SpecialAboutTopic extends SpecialPage {

	public static function newFromGlobalState(): self {
		// TODO inject services
		$mwServices = MediaWikiServices::getInstance();
		$config = $mwServices->getMainConfig();
		$settings = WikibaseClient::getSettings( $mwServices );
		$store = WikibaseClient::getStore( $mwServices );

		return new self(
			new AboutTopicRenderer(
				WikibaseClient::getFallbackLabelDescriptionLookupFactory( $mwServices ),
				$store->getSiteLinkLookup(),
				$mwServices->getSiteLookup(),
				WikibaseClient::getLangLinkSiteGroup( $mwServices ),
				$mwServices->getTitleFactory(),
				WikibaseClient::getOtherProjectsSidebarGeneratorFactory( $mwServices ),
				$mwServices->getPermissionManager(),
				WikibaseClient::getRepoLinker( $mwServices )
			),
			WikibaseClient::getEntityIdParser( $mwServices ),
			$store->getSiteLinkLookup(),
			$mwServices->getTitleFactory(),
			$settings->getSetting( 'siteGlobalID' ),
			$store->getEntityLookup(),
			$config->get( 'ArticlePlaceholderSearchEngineIndexed' )
		);
	}

	public function __construct(
		private readonly AboutTopicRenderer $aboutTopicRenderer,
		private readonly EntityIdParser $idParser,
		private readonly SiteLinkLookup $siteLinkLookup,
		private readonly TitleFactory $titleFactory,
		private readonly string $siteGlobalID,
		private readonly EntityLookup $entityLookup,
		private readonly bool|string $searchEngineIndexed,
	) {
		parent::__construct( 'AboutTopic' );
	}

	/**
	 * @param string|null $sub
	 */
	public function execute( $sub ) {
		$this->showContent( $sub );
	}

	private function showContent( ?string $itemIdString ): void {
		$out = $this->getOutput();
		$itemId = $this->getItemIdParam( $itemIdString );

		if ( $itemId !== null ) {
			$out->setProperty( 'wikibase_item', $itemId->getSerialization() );

			$out->setCanonicalUrl(
				SpecialPage::getTitleFor( $this->getName(), $itemId->getSerialization() )->getCanonicalURL()
			);
		}
		$this->setHeaders();

		// Unconditionally cache the special page for a day, see T109458
		$out->setCdnMaxage( 86400 );

		if ( $itemId === null ) {
			$this->createForm();
			return;
		}

		if ( !$this->entityLookup->hasEntity( $itemId ) ) {
			$this->createForm();
			$out->addWikiMsg( 'articleplaceholder-abouttopic-no-entity-error' );
			return;
		}

		$articleOnWiki = $this->getArticleUrl( $itemId );

		if ( $articleOnWiki !== null ) {
			$out->redirect( $articleOnWiki );
		} else {
			$this->aboutTopicRenderer->showPlaceholder(
				$itemId,
				$this->getLanguage(),
				$this->getUser(),
				$out
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'articleplaceholder-abouttopic' );
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'other';
	}

	/**
	 * Create html elements
	 */
	protected function createForm() {
		$form = HTMLForm::factory( 'ooui', [
			'text' => [
				'type' => 'text',
				'name' => 'entityid',
				'id' => 'ap-abouttopic-entityid',
				'required' => true,
				'cssclass' => 'ap-input',
				'label-message' => 'articleplaceholder-abouttopic-entityid',
				'default' => $this->getRequest()->getVal( 'entityid' ),
			]
		], $this->getContext() );

		$form
			->setMethod( 'get' )
			->setId( 'ap-abouttopic-form1' )
			->setHeaderHtml( $this->msg( 'articleplaceholder-abouttopic-intro' )->parse() )
			->setWrapperLegend( '' )
			->setSubmitTextMsg( 'articleplaceholder-abouttopic-submit' )
			->prepareForm()
			->displayForm( false );
	}

	private function getItemIdParam( ?string $fallback ): ?ItemId {
		$rawId = trim( $this->getRequest()->getText( 'entityid', $fallback ?? '' ) );

		if ( $rawId === '' ) {
			return null;
		}

		try {
			$id = $this->idParser->parse( $rawId );
			if ( !( $id instanceof ItemId ) ) {
				throw new EntityIdParsingException();
			}

			return $id;
		} catch ( EntityIdParsingException ) {
			$this->getOutput()->addWikiMsg( 'articleplaceholder-abouttopic-no-entity-error' );
		}

		return null;
	}

	private function getArticleUrl( ItemId $entityId ): ?string {
		$sitelinkTitles = $this->siteLinkLookup->getLinks(
			[ $entityId->getNumericId() ],
			[ $this->siteGlobalID ]
		);

		if ( isset( $sitelinkTitles[0][1] ) ) {
			$sitelinkTitle = $sitelinkTitles[0][1];
			return $this->titleFactory->newFromText( $sitelinkTitle )->getLinkURL();
		}

		return null;
	}

	protected function getRobotPolicy(): string {
		$wikibaseItem = $this->getOutput()->getProperty( 'wikibase_item' );
		if ( $wikibaseItem === null ) {
			// No item id set: We're showing the form, not an actual placeholder.
			return parent::getRobotPolicy();
		}

		if ( $this->searchEngineIndexed === true ) {
			return 'index,follow';
		}

		if ( is_string( $this->searchEngineIndexed ) ) {
			$entityId = new ItemId( $wikibaseItem );

			$maxEntityId = new ItemId( $this->searchEngineIndexed );

			if ( $entityId->getNumericId() <= $maxEntityId->getNumericId() ) {
				return 'index,follow';
			}
		}

		return parent::getRobotPolicy();
	}

}
