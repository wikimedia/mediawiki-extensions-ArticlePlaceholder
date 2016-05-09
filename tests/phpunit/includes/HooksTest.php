<?php

namespace ArticlePlaceholder\Tests;

use ArticlePlaceholder\Hooks;
use PHPUnit_Framework_TestCase;

/**
 * @covers ArticlePlaceholder\Hooks
 *
 * @group ArticlePlaceholder
 *
 * @licence GNU GPL v2+
 * @author Thiemo Mättig
 */
class HooksTest extends PHPUnit_Framework_TestCase {

	public function testRegisterScribuntoExternalLibraryPaths() {
		$instance = new Hooks();
		$paths = [];
		$this->assertTrue( $instance->registerScribuntoExternalLibraryPaths( 'lua', $paths ) );
		$this->assertArrayHasKey( 0, $paths );
		$this->assertStringEndsWith( '/includes/Lua', $paths[0] );
	}

}
