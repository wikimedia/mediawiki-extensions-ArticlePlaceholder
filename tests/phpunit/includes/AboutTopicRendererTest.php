<?php

namespace ArticlePlaceholder\Tests;

use ArticlePlaceholder\AboutTopicRenderer;
use DerivativeContext;
use Language;
use MediaWikiTestCase;
use RequestContext;
use Site;
use SiteLookup;
use SpecialPage;
use Title;
use User;
use OutputPage;
use Wikibase\Client\Store\TitleFactory;
use Wikibase\Client\Hooks\OtherProjectsSidebarGenerator;
use Wikibase\Client\Hooks\OtherProjectsSidebarGeneratorFactory;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\SiteLink;
use Wikibase\DataModel\Services\Lookup\LabelDescriptionLookup;
use Wikibase\DataModel\Term\Term;
use Wikibase\Lib\Store\SiteLinkLookup;
use Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookupFactory;

/**
 * @covers ArticlePlaceholder\AboutTopicRenderer
 *
 * @group ArticlePlaceholder
 * @group Database
 *
 * @licence GNU GPL v2+
 * @author Lucie-Aimée Kaffee
 */
class AboutTopicRendererTest extends MediaWikiTestCase {

	public function setUp() {
		parent::setUp();

		$this->insertPage( 'Template:AboutTopic', '(aboutTopic: {{{1}}})' );
	}

	/**
	 * @param ItemId $itemId
	 *
	 * @return OutputPage
	 */
	private function getInstanceOutput( ItemId $itemId, TitleFactory $titleFactory = null ) {
		$context = new DerivativeContext( RequestContext::getMain() );
		$title = SpecialPage::getTitleFor( 'AboutTopic' );
		$context->getOutput()->setTitle( $title );

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

		$instance = new AboutTopicRenderer(
			$this->getTermLookupFactory(),
			$this->getSiteLinkLookup(),
			$this->getSiteLookup(),
			'wikipedia',
			$titleFactory ?: $this->getTitleFactory(),
			$otherProjectsSidebarGeneratorFactory
		);

		$instance->showPlaceholder(
			$itemId,
			Language::factory( 'eo' ),
			$this->getMock( User::class ),
			$context->getOutput()
		);

		return $context->getOutput();
	}

	private function getTitleFactory( $canCreate = true ) {
		$titleFactory = $this->getMock( TitleFactory::class );
		$titleFactory->expects( $this->any() )
			->method( 'newFromText' )
			->with( $this->isType( 'string' ) )
			->will( $this->returnCallback( function( $text ) use ( $canCreate ) {
				$title = $this->getMockBuilder( Title::class )
					->disableOriginalConstructor()
					->getMock();

				$title->expects( $this->once() )
					->method( 'quickUserCan' )
					->with( 'createpage', $this->isInstanceOf( User::class ) )
					->will( $this->returnValue( $canCreate ) );

				return $title;
			} ) );

		return $titleFactory;
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
		$this->assertEquals( 1, count( $langLinks ) );
	}

	/**
	 * Test that the AboutTopic template has been correctly parsed
	 */
	public function testTemplateUsed() {
		$output = $this->getInstanceOutput( new ItemId( 'Q123' ) );
		$this->assertContains( '(aboutTopic: Q123)', $output->getHTML() );
	}

	/**
	 * Test that the create article button has been inserted
	 */
	public function testCreateArticleButton() {
		$output = $this->getInstanceOutput( new ItemId( 'Q123' ), $this->getTitleFactory( true ) );
		$this->assertContains( 'new-article-button', $output->getHTML() );
	}

	/**
	 * Test that the create article button is not inserted, if the user is not allowed
	 * to create the page.
	 */
	public function testNoCreateArticleButton_ifUserNotAllowedToCreatePage() {
		$output = $this->getInstanceOutput( new ItemId( 'Q123' ), $this->getTitleFactory( false ) );
		$this->assertNotContains( 'new-article-button', $output->getHTML() );
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

	/**
	 * @return LanguageFallbackLabelDescriptionLookupFactory
	 */
	private function getTermLookupFactory() {
		$labelDescriptionLookupFactory = $this->getMockBuilder(
				LanguageFallbackLabelDescriptionLookupFactory::class
			)
			->disableOriginalConstructor()
			->getMock();
		$labelDescriptionLookupFactory->expects( $this->once() )
			->method( 'newLabelDescriptionLookup' )
			->with( Language::factory( 'eo' ) )
			->will( $this->returnValue( $this->getLabelLookup() ) );

		return $labelDescriptionLookupFactory;
	}

	/**
	 * @return LabelDescriptionLookup
	 */
	private function getLabelLookup() {
		$labelLookup = $this->getMock( LabelDescriptionLookup::class );
		$labelLookup->expects( $this->any() )
			->method( 'getLabel' )
			->will( $this->returnCallback( function( ItemId $id ) {
				return new Term( 'eo', 'Label of ' . $id->getSerialization() );
			} ) );

		return $labelLookup;
	}

	/**
	 * @return SiteLinkLookup
	 */
	private function getSiteLinkLookup() {
		$siteLinkLookup = $this->getMockBuilder( SiteLinkLookup::class )
			->disableOriginalConstructor()
			->getMock();

		$siteLinkLookup->expects( $this->any() )
			->method( 'getSiteLinksForItem' )
			->with( new ItemId( 'Q123' ) )
			->will(
				$this->returnValue( [
					new SiteLink( 'eowiki', 'Unicorn' ),
					new SiteLink( 'qwertz', 'Unicorn' ),
					new SiteLink( 'eowikivoyage', 'TravelUnicorn' )
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
			->will( $this->returnCallback( function( $siteId ) {
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
				}
			} ) );

		return $siteLookup;
	}

}
