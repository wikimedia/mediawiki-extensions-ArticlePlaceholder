<?php

namespace ArticlePlaceholder\Tests\Specials;

use ArticlePlaceholder\Specials\SpecialCreateTopicPage;
use DerivativeContext;
use MediaWikiTestCase;
use RequestContext;
use SpecialPage;

/**
 * @covers ArticlePlaceholder\Specials\SpecialCreateTopicPage
 *
 * @group ArticlePlaceholder
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Florian Schmidt
 */
class SpecialCreateTopicPageTest extends MediaWikiTestCase {

	protected function setUp() : void {
		parent::setUp();
		$this->setMwGlobals( [
			'wgScript' => '/w/index.php'
		] );
	}

	/**
	 * @dataProvider executeDataProvider
	 */
	public function testExecute( $testTitle, $expected ) {
		$context = new DerivativeContext( RequestContext::getMain() );
		$title = SpecialPage::getTitleFor( 'CreateTopicPage', $testTitle );
		$context->setTitle( $title );
		$instance = new SpecialCreateTopicPage();
		$instance->setContext( $context );

		$instance->execute( $testTitle );
		$output = $instance->getOutput();
		$this->assertEquals( $expected, $output->getRedirect() );
	}

	public function executeDataProvider() {
		return [
			[ 'TestPage', '/w/index.php?title=TestPage&action=edit' ],
			[ 'UTPage', '' ],
		];
	}

}
