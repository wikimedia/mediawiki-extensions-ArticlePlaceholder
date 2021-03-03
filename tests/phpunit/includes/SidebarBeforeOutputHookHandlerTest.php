<?php

namespace ArticlePlaceholder\Tests;

use ArticlePlaceholder\SidebarBeforeOutputHookHandler;
use MediaWikiTestCase;
use ReflectionMethod;
use Skin;
use Title;
use WebRequest;
use Wikibase\Client\Tests\Mocks\MockClientStore;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;

/**
 * @group ArticlePlaceholder
 *
 * @covers ArticlePlaceholder\SidebarBeforeOutputHookHandler
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch < hoo@online.de >
 */
class SidebarBeforeOutputHookHandlerTest extends MediaWikiTestCase {

	protected function setUp() : void {
		parent::setUp();

		$this->setUserLang( 'qqx' );
	}

	protected function tearDown() : void {
		WikibaseClient::getDefaultInstance( 'reset' );

		parent::tearDown();
	}

	public function testNewFromGlobalState() {
		$reflectionMethod = new ReflectionMethod(
			SidebarBeforeOutputHookHandler::class,
			'newFromGlobalState'
		);
		$reflectionMethod->setAccessible( true );
		$handler = $reflectionMethod->invoke( null );

		$this->assertInstanceOf( SidebarBeforeOutputHookHandler::class, $handler );
	}

	public function testBuildSidebarLink_wrongNamespace() {
		$title = $this->createMock( Title::class );
		$title->expects( $this->once() )
			->method( 'inNamespace' )
			->willReturn( false );

		// We abort early, before Title::getText is ever called
		$title->expects( $this->never() )
			->method( 'getText' );

		$skin = $this->getSkin( $title );

		$result = $this->getHookHandler()->buildSidebarLink( $skin );

		$this->assertFalse( $result );
	}

	public function testBuildSidebarLink_wrongSpecialPageName() {
		$title = $this->createMock( Title::class );
		$title->expects( $this->once() )
			->method( 'inNamespace' )
			->willReturn( true );

		// Title::getText is only called once, because we abort after checking the name
		$title->expects( $this->once() )
			->method( 'getText' )
			->will( $this->returnValue( 'Preferences' ) );

		$skin = $this->getSkin( $title );

		$result = $this->getHookHandler()->buildSidebarLink( $skin );

		$this->assertFalse( $result );
	}

	public function testBuildSidebarLink_invalidItemId() {
		$title = $this->getAboutTopicTitle( 'not-an-item-id' );

		$skin = $this->getSkin( $title );

		$result = $this->getHookHandler()->buildSidebarLink( $skin );

		$this->assertFalse( $result );
	}

	public function testBuildSidebarLink_unknownItemId() {
		$title = $this->getAboutTopicTitle( 'Q42' );

		$skin = $this->getSkin( $title );

		$result = $this->getHookHandler()->buildSidebarLink( $skin );

		$this->assertFalse( $result );
	}

	public function testBuildSidebarLink_subPage() {
		$title = $this->getAboutTopicTitle( 'Q2013' );

		$skin = $this->getSkin( $title );

		$result = $this->getHookHandler()->buildSidebarLink( $skin );

		$this->assertLinkArray( $result );
	}

	public function testBuildSidebarLink_entityidParam() {
		$title = $this->getAboutTopicTitle();

		$skin = $this->getSkin( $title, 'Q2013' );

		$result = $this->getHookHandler()->buildSidebarLink( $skin );

		$this->assertLinkArray( $result );
	}

	public function testBuildSidebarLink_entityidParamOverridesSubPage() {
		// This matches the special page's behaviour.
		$title = $this->getAboutTopicTitle( 'Q123' );

		$skin = $this->getSkin( $title, 'Q2013' );

		$result = $this->getHookHandler()->buildSidebarLink( $skin );

		$this->assertLinkArray( $result );
	}

	private function assertLinkArray( $result ) {
		$itemId = new ItemId( 'Q2013' );
		$href = WikibaseClient::getRepoLinker()->getEntityUrl( $itemId );

		$link = [
			'id' => 't-wikibase',
			'text' => '(wikibase-dataitem)',
			'href' => $href
		];

		$this->assertSame( $link, $result );
	}

	/**
	 * @param string|null $subPage
	 *
	 * @return Title
	 */
	private function getAboutTopicTitle( $subPage = null ) {
		$titleText = 'AboutTopic';

		if ( $subPage ) {
			$titleText .= '/' . $subPage;
		}

		$title = $this->createMock( Title::class );
		$title->expects( $this->once() )
			->method( 'inNamespace' )
			->willReturn( true );

		$title->expects( $this->exactly( 2 ) )
			->method( 'getText' )
			->will( $this->returnValue( $titleText ) );

		return $title;
	}

	/**
	 * @param Title $title
	 * @param string|null $itemIdParam
	 *
	 * @return Skin
	 */
	private function getSkin( Title $title, $itemIdParam = null ) {
		$request = $this->createMock( WebRequest::class );
		$request->expects( $this->any() )
			->method( 'getText' )
			->will( $this->returnCallback( function ( $name, $default ) use ( $itemIdParam ) {
				$this->assertSame( 'entityid', $name );

				return $itemIdParam ?: $default;
			} ) );

		$skin = $this->getMockBuilder( Skin::class )
			->setMethods( [ 'getTitle', 'getRequest' ] )
			->getMockForAbstractClass();

		$skin->expects( $this->any() )
			->method( 'getTitle' )
			->will( $this->returnValue( $title ) );

		$skin->expects( $this->any() )
			->method( 'getRequest' )
			->will( $this->returnValue( $request ) );

		return $skin;
	}

	private function getHookHandler() {
		$clientStore = new MockClientStore();
		$wbClient = WikibaseClient::getDefaultInstance();
		$wbClient->overrideStore( $clientStore );

		$mockRepository = $clientStore->getEntityRevisionLookup();
		$item = new Item( new ItemId( 'Q2013' ) );

		$mockRepository->putEntity( $item );

		return new SidebarBeforeOutputHookHandler(
			WikibaseClient::getEntityIdParser(),
			WikibaseClient::getRepoLinker(),
			$wbClient->getStore()->getEntityLookup()
		);
	}

}
