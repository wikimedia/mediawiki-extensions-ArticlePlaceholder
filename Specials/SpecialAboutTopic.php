<?php

namespace ArticlePlaceholder\Specials;

use Html;
use OOUI;
use SiteStore;
use SpecialPage;
use Title;
use Wikibase\Client\Store\TitleFactory;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookupFactory;
use Wikibase\Lib\Store\SiteLinkLookup;

/**
 * The AboutTopic SpecialPage for the ArticlePlaceholder extension
 *
 * @ingroup Extensions
 * @author Lucie-Aimée Kaffee
 * @license GNU General Public Licence 2.0 or later
 */
class SpecialAboutTopic extends SpecialPage {

	public static function newFromGlobalState() {
		$wikibaseClient = WikibaseClient::getDefaultInstance();
		return new self(
			$wikibaseClient->getEntityIdParser(),
			$wikibaseClient->getLanguageFallbackLabelDescriptionLookupFactory(),
			$wikibaseClient->getStore()->getSiteLinkLookup(),
			$wikibaseClient->getSiteStore(),
			new TitleFactory(),
			$wikibaseClient->getSettings()->getSetting( 'siteGlobalID' ),
			$wikibaseClient->getStore()->getEntityLookup()
		);
	}

	/**
	 * @var EntityIdParser
	 */
	private $idParser;

	/**
	 * @var LanguageFallbackLabelDescriptionLookupFactory
	 */
	private $termLookupFactory;

	/**
	 * @var SitelinkLookup
	 */
	private $sitelinkLookup;

	/**
	 * @var SiteStore
	 */
	private $siteStore;

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
	 * Initialize the special page.
	 */
	public function __construct(
		EntityIdParser $idParser,
		LanguageFallbackLabelDescriptionLookupFactory $termLookupFactory,
		SiteLinkLookup $sitelinkLookup,
		SiteStore $siteStore,
		TitleFactory $titleFactory,
		$siteGlobalID,
		EntityLookup $entityLookup
	) {
		$this->idParser = $idParser;
		$this->termLookupFactory = $termLookupFactory;
		$this->sitelinkLookup = $sitelinkLookup;
		$this->siteStore = $siteStore;
		$this->titleFactory = $titleFactory;
		$this->siteGlobalID = $siteGlobalID;
		$this->entityLookup = $entityLookup;

		parent::__construct( 'AboutTopic' );
	}

	/**
	 * @param string|null $sub
	 */
	public function execute( $sub ) {
		$this->setHeaders();
		$this->showContent( $sub );
	}

	/**
	 * @param string|null $entityIdString
	 */
	private function showContent( $entityIdString ) {
		$entityId = $this->getItemIdParam( 'entityid', $entityIdString );

		if ( $entityId === null ) {
			$this->createForm();
			return;
		}
		if ( !$this->entityLookup->hasEntity( $entityId ) ) {
			$this->createForm();
			$message = $this->msg( 'articleplaceholder-abouttopic-no-entity-error' );
			$this->getOutput()->addWikiText( $message->text() );
			return;
		}

		$articleOnWiki = $this->getArticleOnWiki( $entityId );

		if ( $articleOnWiki !== null ) {
			$this->getOutput()->redirect( $articleOnWiki );
		} else {
			$this->showPlaceholder( $entityId );
		}
	}

	/**
	 * @see SpecialPage::getDescription
	 *
	 * @return string
	 */
	public function getDescription() {
		return $this->msg( 'articleplaceholder-abouttopic' )->text();
	}

	protected function getGroupName() {
		return 'other';
	}

	/**
	 * Create html elements
	 */
	protected function createForm() {
		// Form header
		$this->getOutput()->addHTML(
			Html::openElement(
				'form',
				array(
					'method' => 'get',
					'action' => $this->getPageTitle()->getFullUrl(),
					'name' => 'ap-abouttopic',
					'id' => 'ap-abouttopic-form1',
					'class' => 'ap-form'
				)
			)
		);

		// Form elements
		$this->getOutput()->addHTML( $this->getFormElements() );

		// Form body
		$this->getOutput()->addHTML(
			Html::input(
				'submit',
				$this->msg( 'articleplaceholder-abouttopic-submit' )->text(),
				'submit',
				array( 'id' => 'submit' )
			)
			. Html::closeElement( 'fieldset' )
			. Html::closeElement( 'form' )
		);
	}

	/**
	 * Returns the form elements.
	 *
	 * @return string
	 * @todo exchange all those . Html::element( 'br' ) with something pretty
	 */
	protected function getFormElements() {
		return Html::rawElement(
			'p',
			array(),
			$this->msg( 'articleplaceholder-abouttopic-intro' )->parse()
		)
		. Html::element( 'br' )
		. Html::element(
			'label',
			array(
				'for' => 'ap-abouttopic-entityid',
				'class' => 'ap-label'
			),
			$this->msg( 'articleplaceholder-abouttopic-entityid' )->text()
		)
		. Html::element( 'br' )
		. Html::input(
			'entityid',
			$this->getRequest()->getVal( 'entityid' ),
			'text', array(
				'class' => 'ap-input',
				'id' => 'ap-abouttopic-entityid'
			)
		)
		. Html::element( 'br' );
	}

	private function getTextParam( $name, $fallback ) {
		$value = $this->getRequest()->getText( $name, $fallback );
		return trim( $value );
	}

	/**
	 * @param string $name
	 * @param string $fallback
	 *
	 * @return ItemId|null
	 * @throws @todo UserInputException
	 */
	private function getItemIdParam( $name, $fallback ) {
		$rawId = $this->getTextParam( $name, $fallback );

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
			// @todo proper Exception Handling
			$this->getOutput()->addWikiText( $ex->getMessage() );
		}

		return null;
	}

	/**
	 * Show placeholder and include template to call lua module
	 * @param ItemId $entityId
	 */
	private function showPlaceholder( ItemId $entityId ) {
		$this->getOutput()->addWikiText( '{{aboutTopic|' . $entityId->getSerialization() . '}}' );
		$label = $this->getLabel( $entityId );
		$this->showTitle( $label );
		$this->showCreateArticle( $label );
		$this->showLanguageLinks( $entityId );
	}

	private function showCreateArticle( $label ) {
		$output = $this->getOutput();

		$output->enableOOUI();
		$modules = array(
			'ext.articleplaceholder.createArticle',
			'ext.articleplaceholder.defaultDisplay'
		);
		$output->addModules( $modules );
		$output->addJsConfigVars( 'apLabel', $label );

		$button = new OOUI\ButtonWidget( array(
			'id' => 'create-article-button',
			'infusable' => true,
			'label' => $this->msg( 'articleplaceholder-abouttopic-create-article-button' )->text(),
			'target' => 'blank'
		) );

		$output->addHTML( $button );
	}

	/**
	 * @param ItemId $entityId
	 * @return string|null label
	 */
	private function getLabel( ItemId $entityId ) {
		$label = $this->termLookupFactory->newLabelDescriptionLookup( $this->getLanguage() )
			->getLabel( $entityId );

		if ( $label !== null ) {
			return $label->getText();
		}

		return null;
	}

	/**
	 * Show label as page title
	 * @param string|null $label
	 */
	private function showTitle( $label ) {
		if ( $label !== null ) {
			$this->getOutput()->setPageTitle( htmlspecialchars( $label ) );
		}
	}

	/**
	 * Set language links
	 * @param ItemId $entityId
	 * @todo set links to other projects in sidebar, too!
	 */
	private function showLanguageLinks( ItemId $entityId ) {
		$siteLinks = $this->sitelinkLookup->getSiteLinksForItem( $entityId );
		$languageLinks = array();

		foreach ( $siteLinks as $siteLink ) {
			$languageCode = $this->siteStore->getSite( $siteLink->getSiteId() )->getLanguageCode();

			if ( $languageCode !== null ) {
				$languageLinks[$languageCode] = $languageCode . ':' . $siteLink->getPageName();
			}
		}

		$this->getOutput()->setLanguageLinks( $languageLinks );
	}

	/**
	 * @param ItemId $entityId
	 * @return Title
	 */
	private function getArticleOnWiki( ItemId $entityId ) {
		$sitelinkTitles = $this->sitelinkLookup->getLinks(
			array( $entityId->getNumericId() ),
			array( $this->siteGlobalID )
		);

		if ( isset( $sitelinkTitles[0][1] ) ) {
			$sitelinkTitle = $sitelinkTitles[0][1];
			return $this->titleFactory->newFromText( $sitelinkTitle )->getLinkURL();
		}

		return null;
	}

}
