<?php

namespace ArticlePlaceholder\Tests\Specials;

use ArticlePlaceholder\AboutTopicRenderer;
use ArticlePlaceholder\Specials\SpecialAboutTopic;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\Lib\Store\SiteLinkLookup;
use Wikibase\Lib\Tests\MockRepository;

/**
 * @covers \ArticlePlaceholder\Specials\SpecialAboutTopic
 *
 * @group ArticlePlaceholder
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 * @author Lucie-Aimée Kaffee
 */
class SpecialAboutTopicTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->setContentLang( 'qqx' );
	}

	public function testNewFromGlobalState() {
		$settings = WikibaseClient::getSettings();

		$siteGroup = $settings->getSetting( 'siteGroup' );
		$settings->setSetting( 'siteGroup', 'wikipedia' );

		$this->assertInstanceOf(
			SpecialAboutTopic::class,
			SpecialAboutTopic::newFromGlobalState()
		);

		$settings->setSetting( 'siteGroup', $siteGroup );
	}

	public static function provideSearchEngineIndexed() {
		return [
			[ true ],
			[ false ],
			[ 'Q123' ]
		];
	}

	/**
	 * @dataProvider provideSearchEngineIndexed
	 */
	public function testHTML( $searchEngineIndexed ) {
		$output = $this->getInstanceOutput( '', $searchEngineIndexed );
		$this->assertSame( '(articleplaceholder-abouttopic)', $output->getPageTitle() );

		$html = $output->getHTML();
		$this->assertStringContainsString( 'id=\'ap-abouttopic-form1\'', $html );
		$this->assertStringContainsString( 'id=\'ap-abouttopic-entityid\'', $html );
		$this->assertStringContainsString( '(articleplaceholder-abouttopic-intro)', $html );
		$this->assertStringContainsString( '(articleplaceholder-abouttopic-entityid)', $html );
		$this->assertStringContainsString( '(articleplaceholder-abouttopic-submit)', $html );
	}

	public function testRedirect() {
		$redirect = $this->getInstanceOutput( 'Q1234' )->getRedirect();

		$this->assertSame( Title::newFromText( 'Beer' )->getLinkURL(), $redirect );
	}

	/**
	 * @param string $itemIdSerialization
	 * @param bool|string $searchEngineIndexed
	 *
	 * @return OutputPage
	 */
	private function getInstanceOutput( string $itemIdSerialization, $searchEngineIndexed = true ): OutputPage {
		$context = new DerivativeContext( RequestContext::getMain() );
		$title = SpecialPage::getTitleFor( 'AboutTopic' );
		$context->setTitle( $title );
		$outputPage = new OutputPage( $context );

		$context->setOutput( $outputPage );

		$instance = new SpecialAboutTopic(
			$this->createMock( AboutTopicRenderer::class ),
			$this->getEntityIdParser(),
			$this->getSiteLinkLookup(),
			$this->getServiceContainer()->getTitleFactory(),
			'enwiki',
			$this->getEntityLookup(),
			$searchEngineIndexed
		);
		$instance->setContext( $context );

		$instance->execute( $itemIdSerialization );
		return $instance->getOutput();
	}

	private function getSiteLinkLookup(): SiteLinkLookup {
		$siteLinkLookup = $this->createMock( SiteLinkLookup::class );

		$siteLinkLookup->method( 'getLinks' )
			->with( [ 1234 ], [ 'enwiki' ] )
			->willReturn( [ [ 'enwiki', 'Beer', 1234 ] ] );

		return $siteLinkLookup;
	}

	private function getEntityIdParser(): EntityIdParser {
		$idParser = $this->createMock( EntityIdParser::class );

		$idParser->method( 'parse' )
			->with( 'Q1234' )
			->willReturn( new ItemId( 'Q1234' ) );

		return $idParser;
	}

	private function getEntityLookup(): EntityLookup {
		$item = new Item( new ItemId( 'Q1234' ) );
		$item->setLabel( 'en', 'Beer' );
		$item->setDescription( 'en', 'yummy beverage' );
		$item->getSiteLinkList()->addNewSiteLink( 'enwiki', 'Beer' );

		$entityLookup = new MockRepository();
		$entityLookup->putEntity( $item );

		return $entityLookup;
	}

	public static function provideRobotPolicy() {
		return [
			[ true, true ],
			[ false, false ],
			[ 'Q1', false ],
			[ 'Q2000', true ]
		];
	}

	/**
	 * @dataProvider provideRobotPolicy
	 */
	public function testRobotPolicy( $searchEngineIndexed, bool $expected ) {
		$output = $this->getInstanceOutput( 'Q1234', $searchEngineIndexed );

		if ( $expected === true ) {
			$this->assertSame( 'index', $output->getMetadata()->getIndexPolicy() );
			$this->assertSame( 'follow', $output->getFollowPolicy() );
		} else {
			$this->assertSame( 'noindex', $output->getMetadata()->getIndexPolicy() );
			$this->assertSame( 'nofollow', $output->getFollowPolicy() );
		}
	}

	public function testCanonicalUrl() {
		$output = $this->getInstanceOutput( 'Q1234' );

		$this->assertIsString( $output->getCanonicalUrl() );
		$this->assertSame(
			SpecialPage::getTitleFor( 'AboutTopic', 'Q1234' )->getCanonicalURL(),
			$output->getCanonicalUrl()
		);
		// Should use the sub page syntax
		$this->assertStringContainsString( '/Q1234', $output->getCanonicalUrl() );
	}

}
