<?php

namespace ArticlePlaceholder\Tests\Lua;

use ArticlePlaceholder\Lua\Scribunto_LuaArticlePlaceholderLibrary;
use MediaWikiIntegrationTestCase;
use RuntimeException;
use Scribunto_LuaEngine;

/**
 * @covers \ArticlePlaceholder\Lua\Scribunto_LuaArticlePlaceholderLibrary
 *
 * @group ArticlePlaceholder
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class Scribunto_LuaArticlePlaceholderLibraryTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( [
			'wgArticlePlaceholderImageProperty' => 'P2001',
			'wgArticlePlaceholderReferencesBlacklist' => 'P2002',
		] );
	}

	public function testGetImageProperty() {
		$instance = $this->newInstance();

		$actual = $instance->getImageProperty();
		$this->assertSame( [ 'P2001' ], $actual );
	}

	public function testGetImageProperty_throwsException() {
		$this->setMwGlobals( 'wgArticlePlaceholderImageProperty', '' );
		$instance = $this->newInstance();

		$this->expectException( RuntimeException::class );
		$instance->getImageProperty();
	}

	public function testGetReferencePropertyToHide() {
		$instance = $this->newInstance();

		$actual = $instance->getReferencePropertyToHide();
		$this->assertSame( [ 'P2002' ], $actual );
	}

	public function testGetReferencePropertyToHide_returnsNull() {
		$this->setMwGlobals( 'wgArticlePlaceholderReferencesBlacklist', '' );
		$instance = $this->newInstance();

		$actual = $instance->getReferencePropertyToHide();
		$this->assertNull( $actual );
	}

	public function testRegister() {
		$engine = $this->createMock( Scribunto_LuaEngine::class );
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
		$instance = new Scribunto_LuaArticlePlaceholderLibrary( $engine );

		$actual = $instance->register();
		$this->assertSame( 'dummyReturnValue', $actual );
	}

	private function newInstance(): Scribunto_LuaArticlePlaceholderLibrary {
		$engine = $this->createMock( Scribunto_LuaEngine::class );
		return new Scribunto_LuaArticlePlaceholderLibrary( $engine );
	}

}
