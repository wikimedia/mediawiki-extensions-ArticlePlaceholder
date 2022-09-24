<?php

namespace ArticlePlaceholder;

use ExtensionRegistry;
use Html;
use Language;
use MalformedTitleException;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;
use OOUI;
use OutputPage;
use SiteLookup;
use SpecialPage;
use TitleFactory;
use User;
use Wikibase\Client\Hooks\OtherProjectsSidebarGeneratorFactory;
use Wikibase\Client\RepoLinker;
use Wikibase\Client\Usage\HashUsageAccumulator;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Term\Term;
use Wikibase\DataModel\Term\TermTypes;
use Wikibase\Lib\Store\FallbackLabelDescriptionLookupFactory;
use Wikibase\Lib\Store\SiteLinkLookup;

/**
 * The AboutTopic SpecialPage for the ArticlePlaceholder extension
 * The AboutTopicRenderer assumes the 'wikibase_item' OutputPage property
 * is set in SpecialAboutTopic
 *
 * @ingroup Extensions
 * @author Lucie-Aimée Kaffee
 * @license GPL-2.0-or-later
 */
class AboutTopicRenderer {

	/**
	 * @var FallbackLabelDescriptionLookupFactory
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
	 * @var OtherProjectsSidebarGeneratorFactory
	 */
	private $otherProjectsSidebarGeneratorFactory;

	/**
	 * @var PermissionManager
	 */
	private $permissionManager;

	/** @var RepoLinker */
	private $repoLinker;

	/**
	 * @param FallbackLabelDescriptionLookupFactory $termLookupFactory
	 * @param SiteLinkLookup $siteLinkLookup
	 * @param SiteLookup $siteLookup
	 * @param string $langLinkSiteGroup
	 * @param TitleFactory $titleFactory
	 * @param OtherProjectsSidebarGeneratorFactory $otherProjectsSidebarGeneratorFactory
	 * @param PermissionManager $permissionManager
	 * @param RepoLinker $repoLinker
	 */
	public function __construct(
		FallbackLabelDescriptionLookupFactory $termLookupFactory,
		SiteLinkLookup $siteLinkLookup,
		SiteLookup $siteLookup,
		string $langLinkSiteGroup,
		TitleFactory $titleFactory,
		OtherProjectsSidebarGeneratorFactory $otherProjectsSidebarGeneratorFactory,
		PermissionManager $permissionManager,
		RepoLinker $repoLinker
	) {
		$this->termLookupFactory = $termLookupFactory;
		$this->siteLinkLookup = $siteLinkLookup;
		$this->siteLookup = $siteLookup;
		$this->langLinkSiteGroup = $langLinkSiteGroup;
		$this->titleFactory = $titleFactory;
		$this->otherProjectsSidebarGeneratorFactory = $otherProjectsSidebarGeneratorFactory;
		$this->permissionManager = $permissionManager;
		$this->repoLinker = $repoLinker;
	}

	/**
	 * Show content of the ArticlePlaceholder
	 *
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
		$termLookup = $this->termLookupFactory->newLabelDescriptionLookup(
			$language,
			[ $entityId ],
			[ TermTypes::TYPE_LABEL, TermTypes::TYPE_DESCRIPTION ]
		);
		$label = $termLookup->getLabel( $entityId );
		$description = $termLookup->getDescription( $entityId );
		$canEdit = false;

		if ( $label !== null ) {
			$label = $label->getText();
			$this->showTitle( $label, $output );

			try {
				$title = $this->titleFactory->newFromTextThrow( $label );
				if ( $this->permissionManager->quickUserCan( 'createpage', $user, $title ) ) {
					$canEdit = true;
				}
			} catch ( MalformedTitleException $ex ) {
				// When the entity's label contains characters not allowed in page titles
				$label = '';
				$canEdit = true;
			}
		}

		$this->showTopMessage( $entityId, $label, $output, $canEdit );
		$output->addModuleStyles( 'ext.articleplaceholder.defaultDisplay' );
		$output->addWikiTextAsInterface( '{{aboutTopic|' . $entityId->getSerialization() . '}}' );

		$this->showLanguageLinks( $entityId, $output );
		$this->setOtherProjectsLinks( $entityId, $output );
		$this->addMetaTags( $output, $description );
	}

	/**
	 * Adds the top message bar
	 *
	 * @param ItemId $entityId
	 * @param string|null $label
	 * @param OutputPage $output
	 * @param bool $canEdit
	 */
	private function showTopMessage( ItemId $entityId, ?string $label, OutputPage $output, bool $canEdit ) {
		$infoIcon = new OOUI\IconWidget( [
			'icon' => 'infoFilled',
			'title' => $output->msg( 'articleplaceholder-abouttopic-icon-title' )->text()
		] );

		$output->enableOOUI();

		$leftDIV = Html::rawElement( 'div',
			[ 'class' => 'mw-articleplaceholder-topmessage-container-left' ],
			$infoIcon
		);

		$buttonCode = '';
		if ( $label !== null && $canEdit ) {
			$buttonCode = $this->showCreateArticle( $entityId, $label, $output );
		}

		$this->repoLinker = WikibaseClient::getRepoLinker();
		$messageP = Html::rawElement( 'p', [], $output->msg(
			'articleplaceholder-abouttopic-topmessage-text',
			$this->repoLinker->getEntityUrl( $entityId )
			)->parse()
		);
		$centerDIV = Html::rawElement( 'div',
			[ 'class' => [ 'plainlinks', 'mw-articleplaceholder-topmessage-container-center' ] ],
			$messageP
		);

		$rightDIV = Html::rawElement( 'div',
			[ 'class' => 'mw-articleplaceholder-topmessage-container-right' ],
			$buttonCode
		);

		$output->addHTML( Html::rawElement( 'div',
			[ 'class' => 'mw-articleplaceholder-topmessage-container' ],
			$leftDIV . $rightDIV . $centerDIV )
		);
	}

	/**
	 * Adds a button to create an article
	 *
	 * @param ItemId $itemId
	 * @param string $label
	 * @param OutputPage $output
	 *
	 * @return string HTML
	 */
	private function showCreateArticle( ItemId $itemId, $label, OutputPage $output ) {
		$siteLinks = $this->siteLinkLookup->getSiteLinksForItem( $itemId );

		$output->enableOOUI();
		$output->addModules( 'ext.articleplaceholder.createArticle' );
		$output->addJsConfigVars( 'apLabel', $label );

		$contents = new OOUI\ButtonWidget( [
			'id' => 'new-article-button',
			'flags' => [ 'primary', 'progressive' ],
			'infusable' => true,
			'label' => wfMessage( 'articleplaceholder-abouttopic-create-article-button' )->text(),
			'href' => SpecialPage::getTitleFor( 'CreateTopicPage', $label )
				->getLocalURL( [ 'ref' => 'button' ] ),
			'target' => 'blank'
		] );

		// TODO: Button should be hidden if the only sitelink links to the current wiki.
		// $wikibaseClient->getSettings()->getSetting( 'siteGlobalID' ) should be injected here!
		if ( ExtensionRegistry::getInstance()->isLoaded( 'ContentTranslation' ) && $siteLinks ) {
			$output->addJsConfigVars( 'apContentTranslation', true );
		}

		return $contents;
	}

	/**
	 * Show label as page title
	 *
	 * @param string $label
	 * @param OutputPage $output
	 */
	private function showTitle( $label, OutputPage $output ) {
		$output->setPageTitle( htmlspecialchars( $label ) );
	}

	/**
	 * Set language links
	 *
	 * @param ItemId $entityId
	 * @param OutputPage $output
	 */
	private function showLanguageLinks( ItemId $entityId, OutputPage $output ) {
		$siteLinks = $this->siteLinkLookup->getSiteLinksForItem( $entityId );
		$languageLinks = [];
		$languageNames = [];
		$pageNames = [];

		foreach ( $siteLinks as $siteLink ) {
			$site = $this->siteLookup->getSite( $siteLink->getSiteId() );
			if ( $site === null ) {
				continue;
			}
			$languageCode = $site->getLanguageCode();
			$group = $site->getGroup();
			// TODO: This should not contain the current wiki.
			// $wikibaseClient->getSettings()->getSetting( 'siteGlobalID' ) should be injected here!
			if ( $languageCode !== null && $group === $this->langLinkSiteGroup ) {
				$languageLinks[$languageCode] = $languageCode . ':' . $siteLink->getPageName();

				// TODO: We may want to filter with user languages
				$languageNames[] = [
					'data' => $languageCode,
					'label' => MediaWikiServices::getInstance()->getLanguageNameUtils()
						->getLanguageName( $languageCode ),
				];
				$pageNames[ $languageCode ] = $siteLink->getPageName();
			}
		}

		$output->setLanguageLinks( $languageLinks );
		$output->addJsConfigVars( 'apLanguages', $languageNames );
		$output->addJsConfigVars( 'apPageNames', $pageNames );
	}

	/**
	 * @param ItemId $itemId
	 * @param OutputPage $output
	 */
	private function setOtherProjectsLinks( ItemId $itemId, OutputPage $output ) {
		$otherProjectsSidebarGenerator = $this->otherProjectsSidebarGeneratorFactory
			->getOtherProjectsSidebarGenerator( new HashUsageAccumulator() );

		$otherProjects = $otherProjectsSidebarGenerator->buildProjectLinkSidebarFromItemId( $itemId );
		$output->setProperty( 'wikibase-otherprojects-sidebar', $otherProjects );
	}

	/**
	 * @param OutputPage $output
	 * @param Term|null $description
	 */
	private function addMetaTags( OutputPage $output, ?Term $description ) {
		if ( $description !== null ) {
			$output->addMeta( 'description', trim( $description->getText() ) );
		}
	}

}
