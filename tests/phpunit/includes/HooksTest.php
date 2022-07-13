<?php

namespace ArticlePlaceholder\Tests;

use ArticlePlaceholder\Hooks;

/**
 * @covers \ArticlePlaceholder\Hooks
 *
 * @group ArticlePlaceholder
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class HooksTest extends \PHPUnit\Framework\TestCase {

	public function testOnScribuntoExternalLibraries() {
		$instance = new Hooks();
		$extraLibraries = [];
		$instance->onScribuntoExternalLibraries( 'lua', $extraLibraries );
		$this->assertCount( 1, $extraLibraries );
		$this->assertContainsOnly( 'array', $extraLibraries );
		$this->assertArrayHasKey( 'class', reset( $extraLibraries ) );
	}

	public function testRegisterScribuntoExternalLibraryPaths() {
		$instance = new Hooks();
		$paths = [];
		$instance->registerScribuntoExternalLibraryPaths( 'lua', $paths );
		$this->assertArrayHasKey( 0, $paths );
		$this->assertStringEndsWith( '/includes/Lua', $paths[0] );
	}

}
