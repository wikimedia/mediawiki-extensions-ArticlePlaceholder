<?php

namespace ArticlePlaceholder\Tests;

use ArticlePlaceholder\BaseTemplateToolboxHookHandler;
use BaseTemplate;
use MediaWikiTestCase;
use ReflectionMethod;
use Skin;
use Title;
use WebRequest;
use Wikibase\Client\Tests\MockClientStore;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;

/**
 * @group ArticlePlaceholder
 *
 * @covers ArticlePlaceholder\BaseTemplateToolboxHookHandler
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch < hoo@online.de >
 */
class BaseTemplateToolboxHookHandlerTest extends MediaWikiTestCase {

	public function setUp() {
		parent::setUp();

		$this->setUserLang( 'qqx' );

		$clientStore = new MockClientStore();
		$wikibaseClient = WikibaseClient::getDefaultInstance();
		$wikibaseClient->overrideStore( $clientStore );

		$mockRepository = $clientStore->getEntityRevisionLookup();
		$item = new Item( new ItemId( 'Q2013' ) );

		$mockRepository->putEntity( $item );
	}

	public function tearDown() {
		parent::tearDown();

		WikibaseClient::getDefaultInstance( 'reset' );
	}

	public function testNewFromGlobalState() {
		$reflectionMethod = new ReflectionMethod(
			BaseTemplateToolboxHookHandler::class,
			'newFromGlobalState'
		);
		$reflectionMethod->setAccessible( true );
		$handler = $reflectionMethod->invoke( null );

		$this->assertInstanceOf( BaseTemplateToolboxHookHandler::class, $handler );
	}

	public function testOnBaseTemplateToolbox_wrongNamespace() {
		$title = $this->createMock( Title::class );
		$title->expects( $this->once() )
			->method( 'getNamespace' )
			->will( $this->returnValue( 2 ) );

		// We abort early, before Title::getText is ever called
		$title->expects( $this->never() )
			->method( 'getText' );

		$baseTemplate = $this->getBaseTemplate( $title );

		$arr = [];
		BaseTemplateToolboxHookHandler::onBaseTemplateToolbox( $baseTemplate, $arr );

		$this->assertSame( [], $arr );
	}

	public function testOnBaseTemplateToolbox_wrongSpecialPageName() {
		$title = $this->createMock( Title::class );
		$title->expects( $this->once() )
			->method( 'getNamespace' )
			->will( $this->returnValue( NS_SPECIAL ) );

		// Title::getText is only called once, because we abort after checking the name
		$title->expects( $this->once() )
			->method( 'getText' )
			->will( $this->returnValue( 'Preferences' ) );

		$baseTemplate = $this->getBaseTemplate( $title );

		$arr = [];
		BaseTemplateToolboxHookHandler::onBaseTemplateToolbox( $baseTemplate, $arr );

		$this->assertSame( [], $arr );
	}

	public function testOnBaseTemplateToolbox_invalidItemId() {
		$title = $this->getAboutTopicTitle( 'not-an-item-id' );

		$baseTemplate = $this->getBaseTemplate( $title );

		$arr = [];
		BaseTemplateToolboxHookHandler::onBaseTemplateToolbox( $baseTemplate, $arr );

		$this->assertSame( [], $arr );
	}

	public function testOnBaseTemplateToolbox_unknownItemId() {
		$title = $this->getAboutTopicTitle( 'Q42' );

		$baseTemplate = $this->getBaseTemplate( $title );

		$arr = [];
		BaseTemplateToolboxHookHandler::onBaseTemplateToolbox( $baseTemplate, $arr );

		$this->assertSame( [], $arr );
	}

	public function testOnBaseTemplateToolbox_subPage() {
		$title = $this->getAboutTopicTitle( 'Q2013' );

		$baseTemplate = $this->getBaseTemplate( $title );

		$toolbox = [];
		BaseTemplateToolboxHookHandler::onBaseTemplateToolbox( $baseTemplate, $toolbox );

		$this->assertAmendedToolbox( $toolbox );
	}

	public function testOnBaseTemplateToolbox_entityidParam() {
		$title = $this->getAboutTopicTitle();

		$baseTemplate = $this->getBaseTemplate( $title, 'Q2013' );

		$toolbox = [];
		BaseTemplateToolboxHookHandler::onBaseTemplateToolbox( $baseTemplate, $toolbox );

		$this->assertAmendedToolbox( $toolbox );
	}

	public function testOnBaseTemplateToolbox_entityidParamOverridesSubPage() {
		// This matches the special page's behaviour.
		$title = $this->getAboutTopicTitle( 'Q123' );

		$baseTemplate = $this->getBaseTemplate( $title, 'Q2013' );

		$toolbox = [];
		BaseTemplateToolboxHookHandler::onBaseTemplateToolbox( $baseTemplate, $toolbox );

		$this->assertAmendedToolbox( $toolbox );
	}

	private function assertAmendedToolbox( $toolbox ) {
		$itemId = new ItemId( 'Q2013' );
		$href = WikibaseClient::getDefaultInstance()->newRepoLinker()->getEntityUrl( $itemId );

		$this->assertSame(
			[
				'wikibase' => [
					'text' => '(wikibase-dataitem)',
					'href' => $href,
					'id' => 't-wikibase'
				]
			],
			$toolbox
		);
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
			->method( 'getNamespace' )
			->will( $this->returnValue( NS_SPECIAL ) );

		$title->expects( $this->exactly( 2 ) )
			->method( 'getText' )
			->will( $this->returnValue( $titleText ) );

		return $title;
	}

	/**
	 * @param Title $title
	 * @param string|null $itemIdParam
	 *
	 * @return BaseTemplate
	 */
	private function getBaseTemplate( Title $title, $itemIdParam = null ) {
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

		$baseTemplate = $this->getMockBuilder( BaseTemplate::class )
			->setMethods( [ 'getSkin' ] )
			->getMockForAbstractClass();

		$baseTemplate->expects( $this->any() )
			->method( 'getSkin' )
			->will( $this->returnValue( $skin ) );

		return $baseTemplate;
	}

}
