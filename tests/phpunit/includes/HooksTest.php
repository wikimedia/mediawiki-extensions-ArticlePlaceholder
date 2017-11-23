<?php

namespace ArticlePlaceholder\Tests;

use ArticlePlaceholder\Hooks;
use PHPUnit_Framework_TestCase;

/**
 * @covers ArticlePlaceholder\Hooks
 *
 * @group ArticlePlaceholder
 *
 * @license GNU GPL v2+
 * @author Thiemo Kreuz
 */
class HooksTest extends PHPUnit_Framework_TestCase {

	public function testRegisterScribuntoExternalLibraryPaths() {
		$instance = new Hooks();
		$paths = [];
		$instance->registerScribuntoExternalLibraryPaths( 'lua', $paths );
		$this->assertArrayHasKey( 0, $paths );
		$this->assertStringEndsWith( '/includes/Lua', $paths[0] );
	}

}
