<?php

namespace ArticlePlaceholder\Tests;

use ArticlePlaceholder\AboutTopicRenderer;
use DerivativeContext;
use MalformedTitleException;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;
use MediaWikiIntegrationTestCase;
use OutputPage;
use RequestContext;
use Site;
use SiteLookup;
use SpecialPage;
use TitleFactory;
use User;
use Wikibase\Client\Hooks\OtherProjectsSidebarGenerator;
use Wikibase\Client\Hooks\OtherProjectsSidebarGeneratorFactory;
use Wikibase\Client\RepoLinker;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\SiteLink;
use Wikibase\DataModel\Term\Term;
use Wikibase\DataModel\Term\TermTypes;
use Wikibase\Lib\Store\FallbackLabelDescriptionLookup;
use Wikibase\Lib\Store\FallbackLabelDescriptionLookupFactory;
use Wikibase\Lib\Store\SiteLinkLookup;

/**
 * @covers \ArticlePlaceholder\AboutTopicRenderer
 *
 * @group ArticlePlaceholder
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Lucie-Aimée Kaffee
 */
class AboutTopicRendererTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->insertPage( 'Template:AboutTopic', '(aboutTopic: {{{1}}})' );
	}

	/**
	 * @param ItemId $itemId
	 * @param bool $canCreate
	 * @param TitleFactory|null $titleFactory
	 *
	 * @return OutputPage
	 */
	private function getInstanceOutput(
		ItemId $itemId,
		$canCreate = true,
		TitleFactory $titleFactory = null
	): OutputPage {
		$context = new DerivativeContext( RequestContext::getMain() );
		$outputPage = $context->getOutput();
		$title = SpecialPage::getTitleFor( 'AboutTopic' );
		$outputPage->setTitle( $title );

		// is set in SpecialAboutTopic
		$outputPage->setProperty( 'wikibase_item', $itemId->getSerialization() );

		$sidebarGenerator = $this->createMock( OtherProjectsSidebarGenerator::class );
		$sidebarGenerator->expects( $this->once() )
			->method( 'buildProjectLinkSidebarFromItemId' )
			->with( $itemId )
			->willReturn( 'other-projects-sidebar' );

		$sidebarGeneratorFactory = $this->createMock( OtherProjectsSidebarGeneratorFactory::class );
		$sidebarGeneratorFactory->expects( $this->once() )
			->method( 'getOtherProjectsSidebarGenerator' )
			->willReturn( $sidebarGenerator );

		$permMock = $this->createMock( PermissionManager::class );
		$permMock->method( 'quickUserCan' )
			->willReturn( $canCreate );

		$repoLinker = $this->createMock( RepoLinker::class );
		$repoLinker->method( 'getEntityUrl' )
			->willReturn( 'https://example.com/' );

		$instance = new AboutTopicRenderer(
			$this->getTermLookupFactory(),
			$this->getSiteLinkLookup(),
			$this->getSiteLookup(),
			'wikipedia',
			$titleFactory ?: MediaWikiServices::getInstance()->getTitleFactory(),
			$sidebarGeneratorFactory,
			$permMock,
			$repoLinker
		);

		$instance->showPlaceholder(
			$itemId,
			MediaWikiServices::getInstance()->getLanguageFactory()->getLanguage( 'eo' ),
			$this->createMock( User::class ),
			$outputPage
		);

		return $outputPage;
	}

	/**
	 * Test that the title is set correctly
	 */
	public function testTitle() {
		$output = $this->getInstanceOutput( new ItemId( 'Q123' ) );
		$title = $output->getPageTitle();
		$this->assertEquals( 'Label of Q123', $title );
	}

	/**
	 * Test that Language links are set correctly
	 */
	public function testLanguageLinks() {
		$output = $this->getInstanceOutput( new ItemId( 'Q123' ) );
		$this->assertSame( [ 'eo' => 'eo:Unicorn' ], $output->getLanguageLinks() );
	}

	/**
	 * Test that the AboutTopic template has been correctly parsed
	 */
	public function testTemplateUsed() {
		$output = $this->getInstanceOutput( new ItemId( 'Q123' ) );
		$this->assertStringContainsString( '(aboutTopic: Q123)', $output->getHTML() );
	}

	/**
	 * Test that the create article button has been inserted
	 */
	public function testCreateArticleButton() {
		$output = $this->getInstanceOutput( new ItemId( 'Q123' ) );
		$this->assertStringContainsString( 'new-article-button', $output->getHTML() );
	}

	public function testCreateArticleButton_ifLabelIsNotAValidTitle() {
		$titleFactory = $this->createMock( TitleFactory::class );
		$titleFactory->method( 'newFromTextThrow' )
			->willThrowException( new MalformedTitleException( '' ) );
		$html = $this->getInstanceOutput( new ItemId( 'Q123' ), true, $titleFactory )->getHTML();
		$this->assertStringContainsString( 'new-article-button', $html );
	}

	/**
	 * Test that the create article button is not inserted, if the user is not allowed
	 * to create the page.
	 */
	public function testNoCreateArticleButton_ifUserNotAllowedToCreatePage() {
		$output = $this->getInstanceOutput( new ItemId( 'Q123' ), false );
		$this->assertStringNotContainsString( 'new-article-button', $output->getHTML() );
	}

	/**
	 * Test that output properties for the other projects sidebar have been set.
	 */
	public function testOtherProjectsLinks() {
		$output = $this->getInstanceOutput( new ItemId( 'Q123' ) );
		$this->assertSame(
			'other-projects-sidebar',
			$output->getProperty( 'wikibase-otherprojects-sidebar' )
		);
		$this->assertSame( 'Q123', $output->getProperty( 'wikibase_item' ) );
	}

	public function testMetaTags() {
		$output = $this->getInstanceOutput( new ItemId( 'Q123' ) );
		$this->assertSame(
			[ [ 'description', 'Description of Q123' ] ],
			$output->getMetaTags()
		);
	}

	/**
	 * @return FallbackLabelDescriptionLookupFactory
	 */
	private function getTermLookupFactory(): FallbackLabelDescriptionLookupFactory {
		$factory = $this->createMock( FallbackLabelDescriptionLookupFactory::class );
		$factory->expects( $this->once() )
			->method( 'newLabelDescriptionLookup' )
			->with(
				MediaWikiServices::getInstance()->getLanguageFactory()->getLanguage( 'eo' ),
				$this->anything(),
				[ TermTypes::TYPE_LABEL, TermTypes::TYPE_DESCRIPTION ]
			)
			->willReturn( $this->getLabelDescriptionLookup() );

		return $factory;
	}

	/**
	 * @return FallbackLabelDescriptionLookup
	 */
	private function getLabelDescriptionLookup(): FallbackLabelDescriptionLookup {
		$labelDescriptionLookup = $this->createMock( FallbackLabelDescriptionLookup::class );
		$labelDescriptionLookup->method( 'getLabel' )
			->willReturnCallback( static function ( ItemId $id ) {
				return new Term( 'eo', 'Label of ' . $id->getSerialization() );
			} );

		$labelDescriptionLookup->method( 'getDescription' )
			->willReturnCallback( static function ( ItemId $id ) {
				return new Term( 'eo', 'Description of ' . $id->getSerialization() );
			} );

		return $labelDescriptionLookup;
	}

	/**
	 * @return SiteLinkLookup
	 */
	private function getSiteLinkLookup(): SiteLinkLookup {
		$siteLinkLookup = $this->createMock( SiteLinkLookup::class );

		$siteLinkLookup->method( 'getSiteLinksForItem' )
			->with( new ItemId( 'Q123' ) )
			->willReturn( [
				new SiteLink( 'eowiki', 'Unicorn' ),
				new SiteLink( 'qwertz', 'Unicorn' ),
				new SiteLink( 'eowikivoyage', 'TravelUnicorn' ),
				new SiteLink( 'null', 'A very nully page' )
			] );

		return $siteLinkLookup;
	}

	private function getSiteLookup(): SiteLookup {
		$siteLookup = $this->createMock( SiteLookup::class );
		$siteLookup->method( 'getSite' )
			->willReturnCallback( static function ( $siteId ) {
				$site = new Site();
				$site->setGlobalId( $siteId );

				switch ( $siteId ) {
					case 'eowiki':
						$site->setGroup( 'wikipedia' );
						$site->setLanguageCode( 'eo' );
						return $site;
					case 'qwertz':
						$site->setGroup( 'qwertz' );
						$site->setLanguageCode( 'qw' );
						return $site;
					case 'eowikivoyage':
						$site->setGroup( 'wikivoyage' );
						$site->setLanguageCode( 'eo' );
						return $site;
					default:
						return null;
				}
			} );

		return $siteLookup;
	}

}
