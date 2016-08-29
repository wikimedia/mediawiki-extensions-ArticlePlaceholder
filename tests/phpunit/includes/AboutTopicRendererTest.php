<?php

namespace ArticlePlaceholder\Tests;

use ArticlePlaceholder\AboutTopicRenderer;
use DerivativeContext;
use MediaWikiTestCase;
use RequestContext;
use Site;
use SiteLookup;
use SpecialPage;
use Language;
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
 *
 * @licence GNU GPL v2+
 * @author Lucie-Aimée Kaffee
 */
class AboutTopicRendererTest extends MediaWikiTestCase {

	/**
	 * @param ItemId $itemId
	 *
	 * @return OutputPage
	 */
	private function getInstanceOutput( ItemId $itemId ) {
		$context = new DerivativeContext( RequestContext::getMain() );
		$title = SpecialPage::getTitleFor( 'AboutTopic' );
		$context->getOutput()->setTitle( $title );

		$otherProjectsSidebarGenerator = $this->getMockBuilder( OtherProjectsSidebarGenerator::class )
			->disableOriginalConstructor()
			->getMock();

		$otherProjectsSidebarGeneratorFactory = $this->getMockBuilder(
			OtherProjectsSidebarGeneratorFactory::class
		)->disableOriginalConstructor()
		->getMock();

		$otherProjectsSidebarGeneratorFactory->expects( $this->any() )
			->method( 'getOtherProjectsSidebarGenerator' )
			->will( $this->returnValue( $otherProjectsSidebarGenerator ) );

		$instance = new AboutTopicRenderer(
			$this->getTermLookupFactory(),
			$this->getSiteLinkLookup(),
			$this->getSiteLookup(),
			'wikipedia',
			new TitleFactory(),
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
			->with( ItemId::newFromNumber( '123' ) )
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
