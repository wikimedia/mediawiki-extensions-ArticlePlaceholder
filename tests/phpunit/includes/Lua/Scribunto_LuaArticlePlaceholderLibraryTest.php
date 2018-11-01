<?php

namespace ArticlePlaceholder\Tests\Lua;

use ArticlePlaceholder\Lua\Scribunto_LuaArticlePlaceholderLibrary;
use MediaWikiTestCase;
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
class Scribunto_LuaArticlePlaceholderLibraryTest extends MediaWikiTestCase {

	protected function setUp() {
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

		$this->setExpectedException( RuntimeException::class );
		$instance->getImageProperty();
	}

	public function testGetReferencesBlacklist() {
		$instance = $this->newInstance();

		$actual = $instance->getReferencesBlacklist();
		$this->assertSame( [ 'P2002' ], $actual );
	}

	public function testGetReferencesBlacklist_returnsNull() {
		$this->setMwGlobals( 'wgArticlePlaceholderReferencesBlacklist', '' );
		$instance = $this->newInstance();

		$actual = $instance->getReferencesBlacklist();
		$this->assertNull( $actual );
	}

	public function testRegister() {
		$engine = $this->getMockBuilder( Scribunto_LuaEngine::class )
			->disableOriginalConstructor()
			->getMock();
		$engine->expects( $this->once() )
			->method( 'registerInterface' )
			->willReturnCallback( function (
				$moduleFileName,
				array $interfaceFuncs,
				array $setupOptions
			) {
				$this->assertFileExists( $moduleFileName );
				$this->assertInternalType( 'callable', $interfaceFuncs['getImageProperty'] );
				$this->assertInternalType( 'callable', $interfaceFuncs['getReferencesBlacklist'] );

				return 'dummyReturnValue';
			} );
		$instance = new Scribunto_LuaArticlePlaceholderLibrary( $engine );

		$actual = $instance->register();
		$this->assertSame( 'dummyReturnValue', $actual );
	}

	private function newInstance() {
		$engine = $this->getMockBuilder( Scribunto_LuaEngine::class )
			->disableOriginalConstructor()
			->getMock();

		return new Scribunto_LuaArticlePlaceholderLibrary( $engine );
	}

}
