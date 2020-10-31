<?php

namespace ArticlePlaceholder\Tests;

use ArticlePlaceholder\AboutTopicRenderer;
use DerivativeContext;
use Language;
use MalformedTitleException;
use MediaWiki\Permissions\PermissionManager;
use MediaWikiTestCase;
use OutputPage;
use RequestContext;
use Site;
use SiteLookup;
use SpecialPage;
use TitleFactory;
use User;
use Wikibase\Client\Hooks\OtherProjectsSidebarGenerator;
use Wikibase\Client\Hooks\OtherProjectsSidebarGeneratorFactory;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\LabelDescriptionLookup;
use Wikibase\DataModel\SiteLink;
use Wikibase\DataModel\Term\Term;
use Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookupFactory;
use Wikibase\Lib\Store\SiteLinkLookup;

/**
 * @covers ArticlePlaceholder\AboutTopicRenderer
 *
 * @group ArticlePlaceholder
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Lucie-Aimée Kaffee
 */
class AboutTopicRendererTest extends MediaWikiTestCase {

	protected function setUp() : void {
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
	) {
		$context = new DerivativeContext( RequestContext::getMain() );
		$outputPage = $context->getOutput();
		$title = SpecialPage::getTitleFor( 'AboutTopic' );
		$outputPage->setTitle( $title );

		// is set in SpecialAboutTopic
		$outputPage->setProperty( 'wikibase_item', $itemId->getSerialization() );

		$otherProjectsSidebarGenerator = $this->getMockBuilder( OtherProjectsSidebarGenerator::class )
			->disableOriginalConstructor()
			->getMock();

		$otherProjectsSidebarGenerator->expects( $this->once() )
			->method( 'buildProjectLinkSidebarFromItemId' )
			->with( $itemId )
			->will( $this->returnValue( 'other-projects-sidebar' ) );

		$otherProjectsSidebarGeneratorFactory = $this->getMockBuilder(
			OtherProjectsSidebarGeneratorFactory::class
		)->disableOriginalConstructor()
		->getMock();

		$otherProjectsSidebarGeneratorFactory->expects( $this->once() )
			->method( 'getOtherProjectsSidebarGenerator' )
			->will( $this->returnValue( $otherProjectsSidebarGenerator ) );

		$permMock = $this->getMockBuilder( PermissionManager::class )
			->disableOriginalConstructor()
			->getMock();
		$permMock->expects( $this->any() )
			->method( 'quickUserCan' )
			->willReturn( $canCreate );

		$instance = new AboutTopicRenderer(
			$this->getTermLookupFactory(),
			$this->getSiteLinkLookup(),
			$this->getSiteLookup(),
			'wikipedia',
			$titleFactory ?: new TitleFactory(),
			$otherProjectsSidebarGeneratorFactory,
			$permMock
		);

		$instance->showPlaceholder(
			$itemId,
			Language::factory( 'eo' ),
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
		$langLinks = $output->getLanguageLinks();
		$this->assertArrayEquals( [ 'eo:Unicorn' ], $langLinks );
		$this->assertSame( 1, count( $langLinks ) );
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
	 * @return LanguageFallbackLabelDescriptionLookupFactory
	 */
	private function getTermLookupFactory() {
		$labelDescriptionLookupFactory = $this->getMockBuilder(
				LanguageFallbackLabelDescriptionLookupFactory::class
			)
			->disableOriginalConstructor()
			->getMock();
		$labelDescriptionLookupFactory->expects( $this->atLeastOnce() )
			->method( 'newLabelDescriptionLookup' )
			->with( Language::factory( 'eo' ) )
			->will( $this->returnValue( $this->getLabelDescriptionLookup() ) );

		return $labelDescriptionLookupFactory;
	}

	/**
	 * @return LabelDescriptionLookup
	 */
	private function getLabelDescriptionLookup() {
		$labelDescriptionLookup = $this->createMock( LabelDescriptionLookup::class );
		$labelDescriptionLookup->expects( $this->any() )
			->method( 'getLabel' )
			->will( $this->returnCallback( function ( ItemId $id ) {
				return new Term( 'eo', 'Label of ' . $id->getSerialization() );
			} ) );

		$labelDescriptionLookup->expects( $this->any() )
			->method( 'getDescription' )
			->will( $this->returnCallback( function ( ItemId $id ) {
				return new Term( 'eo', 'Description of ' . $id->getSerialization() );
			} ) );

		return $labelDescriptionLookup;
	}

	/**
	 * @return SiteLinkLookup
	 */
	private function getSiteLinkLookup() {
		$siteLinkLookup = $this->createMock( SiteLinkLookup::class );

		$siteLinkLookup->expects( $this->any() )
			->method( 'getSiteLinksForItem' )
			->with( new ItemId( 'Q123' ) )
			->will(
				$this->returnValue( [
					new SiteLink( 'eowiki', 'Unicorn' ),
					new SiteLink( 'qwertz', 'Unicorn' ),
					new SiteLink( 'eowikivoyage', 'TravelUnicorn' ),
					new SiteLink( 'null', 'A very nully page' )
				] )
			);

		return $siteLinkLookup;
	}

	private function getSiteLookup() {
		$siteLookup = $this->getMockBuilder( SiteLookup::class )
			->disableOriginalConstructor()
			->getMock();

		$siteLookup->expects( $this->any() )
			->method( 'getSite' )
			->will( $this->returnCallback( function ( $siteId ) {
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
					case 'null':
						return null;
				}
			} ) );

		return $siteLookup;
	}

}
