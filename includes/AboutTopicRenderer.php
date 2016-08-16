<?php

namespace ArticlePlaceholder;

use OOUI;
use SpecialPage;
use Title;
use Wikibase\Client\Store\TitleFactory;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookupFactory;
use Wikibase\Lib\Store\SiteLinkLookup;
use OutputPage;
use SiteLookup;
use Language;
use User;

/**
 * The AboutTopic SpecialPage for the ArticlePlaceholder extension
 *
 * @ingroup Extensions
 * @author Lucie-Aimée Kaffee
 * @license GNU General Public Licence 2.0 or later
 */
class AboutTopicRenderer {

	/**
	 * @var LanguageFallbackLabelDescriptionLookupFactory
	 */
	private $termLookupFactory;

	/**
	 * @var SiteLinkLookup
	 */
	private $siteLinkLookup;

	/**
	 * @var SiteLookup
	 */
	private $siteLookup;

	/**
	 * @var string
	 */
	private $langLinkSiteGroup;

	/**
	 * @var TitleFactory
	 */
	private $titleFactory;

	/**
	 * @param LanguageFallbackLabelDescriptionLookupFactory $termLookupFactory
	 * @param SiteLinkLookup $siteLinkLookup
	 * @param SiteLookup $siteLookup
	 * @param string $langLinkSiteGroup
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		LanguageFallbackLabelDescriptionLookupFactory $termLookupFactory,
		SiteLinkLookup $siteLinkLookup,
		SiteLookup $siteLookup,
		$langLinkSiteGroup,
		TitleFactory $titleFactory
	) {
		$this->termLookupFactory = $termLookupFactory;
		$this->siteLinkLookup = $siteLinkLookup;
		$this->siteLookup = $siteLookup;
		$this->langLinkSiteGroup = $langLinkSiteGroup;
		$this->titleFactory = $titleFactory;
	}

	/**
	 * Show content of the ArticlePlaceholder
	 * @param ItemId $entityId
	 * @param Language $language
	 * @param User $user
	 * @param OutputPage $output
	 */
	public function showPlaceholder(
			ItemId $entityId,
			Language $language,
			User $user,
			OutputPage $output
		) {
		$output->addWikiText( '{{aboutTopic|' . $entityId->getSerialization() . '}}' );
		$label = $this->getLabel( $entityId, $language );
		$labelTitle = null;
		if ( $label ) {
			$this->showTitle( $label, $output );
			$labelTitle = $this->titleFactory->newFromText( $label );
		}
		if ( $labelTitle && $labelTitle->quickUserCan( 'createpage', $user ) ) {
			$this->showCreateArticle( $labelTitle, $output );
		}
		$this->showLanguageLinks( $entityId, $output );
	}

	/**
	 * Adds a button to create an article
	 * @param Title $labelTitle
	 * @param OutputPage $output
	 */
	private function showCreateArticle( Title $labelTitle, OutputPage $output ) {
		$output->enableOOUI();
		$output->addModuleStyles( 'ext.articleplaceholder.defaultDisplay' );
		$output->addModules( 'ext.articleplaceholder.createArticle' );
		$output->addJsConfigVars( 'apLabel', $labelTitle->getPrefixedText() );

		$button = new OOUI\ButtonWidget( [
			'id' => 'new-empty-article-button',
			'infusable' => true,
			'label' => wfMessage( 'articleplaceholder-abouttopic-create-article-button' )->text(),
			'href' => SpecialPage::getTitleFor( 'CreateTopicPage', $labelTitle->getPrefixedText() )
				->getLocalURL( [ 'ref' => 'button' ] ),
			'target' => 'blank'
		] );

		$output->addHTML( $button );
	}

	/**
	 * @param ItemId $entityId
	 * @param Language $language
	 * @return string|null null if the item doesn't have a label
	 */
	private function getLabel( ItemId $entityId, Language $language ) {
		$label = $this->termLookupFactory->newLabelDescriptionLookup( $language )
			->getLabel( $entityId );

		if ( $label !== null ) {
			return $label->getText();
		}

		return null;
	}

	/**
	 * Show label as page title
	 * @param string $label
	 * @param OutputPage $output
	 */
	private function showTitle( $label, OutputPage $output ) {
		$output->setPageTitle( htmlspecialchars( $label ) );
	}

	/**
	 * Set language links
	 * @param ItemId $entityId
	 * @param OutputPage $output
	 */
	private function showLanguageLinks( ItemId $entityId, OutputPage $output ) {
		$siteLinks = $this->siteLinkLookup->getSiteLinksForItem( $entityId );
		$languageLinks = [];

		foreach ( $siteLinks as $siteLink ) {
			$site = $this->siteLookup->getSite( $siteLink->getSiteId() );
			$languageCode = $site->getLanguageCode();
			$group = $site->getGroup();
			if ( $languageCode !== null && $group === $this->langLinkSiteGroup ) {
				$languageLinks[$languageCode] = $languageCode . ':' . $siteLink->getPageName();
			}
		}

		$output->setLanguageLinks( $languageLinks );
	}

}
