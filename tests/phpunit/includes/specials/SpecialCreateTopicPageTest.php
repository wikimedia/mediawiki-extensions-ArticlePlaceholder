<?php

namespace ArticlePlaceholder\Tests\Specials;

use ArticlePlaceholder\Specials\SpecialCreateTopicPage;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWikiIntegrationTestCase;

/**
 * @covers \ArticlePlaceholder\Specials\SpecialCreateTopicPage
 *
 * @group ArticlePlaceholder
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Florian Schmidt
 */
class SpecialCreateTopicPageTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValue( MainConfigNames::Script, '/w/index.php' );
	}

	/**
	 * @dataProvider executeDataProvider
	 */
	public function testExecute( bool $useExistingPage, string $expected ) {
		$testTitle = $useExistingPage
			? $this->getExistingTestPage( 'ExistingPage' )->getTitle()->getText()
			: 'NonExistentPage';
		$context = new DerivativeContext( RequestContext::getMain() );
		$title = SpecialPage::getTitleFor( 'CreateTopicPage', $testTitle );
		$context->setTitle( $title );
		$instance = new SpecialCreateTopicPage();
		$instance->setContext( $context );

		$instance->execute( $testTitle );
		$output = $instance->getOutput();
		$this->assertEquals( $expected, $output->getRedirect() );
	}

	public static function executeDataProvider() {
		return [
			[ false, '/w/index.php?title=NonExistentPage&action=edit' ],
			[ true, '' ],
		];
	}

}
