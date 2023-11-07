<?php

namespace ArticlePlaceholder\Tests;

use ArticlePlaceholder\ScribuntoHooks;

/**
 * @covers \ArticlePlaceholder\ScribuntoHooks
 *
 * @group ArticlePlaceholder
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class ScribuntoHooksTest extends \PHPUnit\Framework\TestCase {

	public function testOnScribuntoExternalLibraries() {
		$instance = new ScribuntoHooks();
		$extraLibraries = [];
		$instance->onScribuntoExternalLibraries( 'lua', $extraLibraries );
		$this->assertCount( 1, $extraLibraries );
		$this->assertContainsOnly( 'array', $extraLibraries );
		$this->assertArrayHasKey( 'class', reset( $extraLibraries ) );
	}

	public function testRegisterScribuntoExternalLibraryPaths() {
		$instance = new ScribuntoHooks();
		$paths = [];
		$instance->onScribuntoExternalLibraryPaths( 'lua', $paths );
		$this->assertArrayHasKey( 0, $paths );
		$this->assertStringEndsWith( '/includes/Lua', $paths[0] );
	}

}
