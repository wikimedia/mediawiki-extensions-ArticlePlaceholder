<?php

namespace ArticlePlaceholder\Tests\Lua;

use ArticlePlaceholder\Lua\ArticlePlaceholderLibrary;
use MediaWiki\Config\ConfigException;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaEngine;
use MediaWikiIntegrationTestCase;

/**
 * @covers \ArticlePlaceholder\Lua\ArticlePlaceholderLibrary
 *
 * @group ArticlePlaceholder
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class ArticlePlaceholderLibraryTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValues( [
			'ArticlePlaceholderImageProperty' => 'P2001',
			'ArticlePlaceholderReferencesBlacklist' => 'P2002',
		] );
	}

	public function testGetImageProperty() {
		$instance = $this->newInstance();

		$actual = $instance->getImageProperty();
		$this->assertSame( [ 'P2001' ], $actual );
	}

	public function testGetImageProperty_throwsException() {
		$this->overrideConfigValue( 'ArticlePlaceholderImageProperty', '' );
		$instance = $this->newInstance();

		$this->expectException( ConfigException::class );
		$instance->getImageProperty();
	}

	public function testGetReferencePropertyToHide() {
		$instance = $this->newInstance();

		$actual = $instance->getReferencePropertyToHide();
		$this->assertSame( [ 'P2002' ], $actual );
	}

	public function testGetReferencePropertyToHide_returnsNull() {
		$this->overrideConfigValue( 'ArticlePlaceholderReferencesBlacklist', '' );
		$instance = $this->newInstance();

		$actual = $instance->getReferencePropertyToHide();
		$this->assertNull( $actual );
	}

	public function testRegister() {
		$engine = $this->createMock( LuaEngine::class );
		$engine->expects( $this->once() )
			->method( 'registerInterface' )
			->willReturnCallback( function (
				$moduleFileName,
				array $interfaceFuncs,
				array $setupOptions
			) {
				$this->assertFileExists( $moduleFileName );
				$this->assertIsCallable( $interfaceFuncs['getImageProperty'] );
				$this->assertIsCallable( $interfaceFuncs['getReferencePropertyToHide'] );

				return 'dummyReturnValue';
			} );
		$instance = new ArticlePlaceholderLibrary( $engine );

		$actual = $instance->register();
		$this->assertSame( 'dummyReturnValue', $actual );
	}

	private function newInstance(): ArticlePlaceholderLibrary {
		$engine = $this->createMock( LuaEngine::class );
		return new ArticlePlaceholderLibrary( $engine );
	}

}
