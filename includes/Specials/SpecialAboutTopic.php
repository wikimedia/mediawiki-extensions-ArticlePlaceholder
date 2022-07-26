<?php

namespace ArticlePlaceholder\Specials;

use ArticlePlaceholder\AboutTopicRenderer;
use HTMLForm;
use MediaWiki\MediaWikiServices;
use SpecialPage;
use TitleFactory;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\Lib\Store\SiteLinkLookup;
use Wikimedia\Assert\Assert;
use Wikimedia\Assert\ParameterTypeException;

/**
 * The AboutTopic SpecialPage for the ArticlePlaceholder extension
 *
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 * @author Lucie-Aimée Kaffee
 */
class SpecialAboutTopic extends SpecialPage {

	public static function newFromGlobalState() {
		// TODO inject services
		$mwServices = MediaWikiServices::getInstance();
		$settings = WikibaseClient::getSettings( $mwServices );
		$store = WikibaseClient::getStore( $mwServices );

		$articlePlaceholderSearchEngineIndexed = $mwServices->getMainConfig()->get(
			'ArticlePlaceholderSearchEngineIndexed'
		);

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
			$articlePlaceholderSearchEngineIndexed
		);
	}

	/**
	 * @var AboutTopicRenderer
	 */
	private $aboutTopicRenderer;

	/**
	 * @var EntityIdParser
	 */
	private $idParser;

	/**
	 * @var SiteLinkLookup
	 */
	private $siteLinkLookup;

	/**
	 * @var TitleFactory
	 */
	private $titleFactory;

	/**
	 * @var string
	 */
	private $siteGlobalID;

	/**
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var bool|string
	 */
	private $searchEngineIndexed;

	/**
	 * @param AboutTopicRenderer $aboutTopicRenderer
	 * @param EntityIdParser $idParser
	 * @param SiteLinkLookup $siteLinkLookup
	 * @param TitleFactory $titleFactory
	 * @param string $siteGlobalID
	 * @param EntityLookup $entityLookup
	 * @param bool|string $searchEngineIndexed
	 *
	 * @throws ParameterTypeException
	 */
	public function __construct(
		AboutTopicRenderer $aboutTopicRenderer,
		EntityIdParser $idParser,
		SiteLinkLookup $siteLinkLookup,
		TitleFactory $titleFactory,
		$siteGlobalID,
		EntityLookup $entityLookup,
		$searchEngineIndexed
	) {
		parent::__construct( 'AboutTopic' );

		Assert::parameterType(
			'boolean|string',
			$searchEngineIndexed,
			'$searchEngineIndexed'
		);

		$this->aboutTopicRenderer = $aboutTopicRenderer;
		$this->idParser = $idParser;
		$this->siteLinkLookup = $siteLinkLookup;
		$this->titleFactory = $titleFactory;
		$this->siteGlobalID = $siteGlobalID;
		$this->entityLookup = $entityLookup;
		$this->searchEngineIndexed = $searchEngineIndexed;
	}

	/**
	 * @param string|null $sub
	 */
	public function execute( $sub ) {
		$this->showContent( $sub );
	}

	/**
	 * @param string|null $itemIdString
	 */
	private function showContent( ?string $itemIdString ) {
		$out = $this->getOutput();
		$itemId = $this->getItemIdParam( $itemIdString );

		if ( $itemId !== null ) {
			$out->setProperty( 'wikibase_item', $itemId->getSerialization() );

			$out->setCanonicalUrl(
				$this->getTitleFor( $this->getName(), $itemId->getSerialization() )->getCanonicalURL()
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
		return $this->msg( 'articleplaceholder-abouttopic' )->text();
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
			->setHeaderText( $this->msg( 'articleplaceholder-abouttopic-intro' )->parse() )
			->setWrapperLegend( '' )
			->setSubmitTextMsg( 'articleplaceholder-abouttopic-submit' )
			->prepareForm()
			->displayForm( false );
	}

	/**
	 * @param string|null $fallback
	 *
	 * @return ItemId|null
	 */
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
		} catch ( EntityIdParsingException $ex ) {
			$this->getOutput()->addWikiMsg( 'articleplaceholder-abouttopic-no-entity-error' );
		}

		return null;
	}

	/**
	 * @param ItemId $entityId
	 *
	 * @return string|null
	 */
	private function getArticleUrl( ItemId $entityId ) {
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

	/**
	 * @return string
	 */
	protected function getRobotPolicy() {
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
