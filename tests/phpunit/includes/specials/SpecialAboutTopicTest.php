<?php

namespace ArticlePlaceholder\Tests\Specials;

use ArticlePlaceholder\Specials\SpecialAboutTopic;
use DerivativeContext;
use Language;
use MediaWikiTestCase;
use RequestContext;
use SpecialPage;
use Wikibase\Client\Store\TitleFactory;

/**
 * @covers ArticlePlaceholder\SpecialAboutTopic
 *
 * @group ArticlePlaceholder
 *
 * @licence GNU GPL v2+
 * @author Thiemo Mättig
 */
class SpecialAboutTopicTest extends MediaWikiTestCase {

	protected function setUp() {
		parent::setUp();

		$this->setMwGlobals( [
			'wgContLang' => Language::factory( 'qqx' )
		] );
	}

	public function testExecute() {
		$termLookupFactory = $this->getMockBuilder(
			'Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookupFactory' )
			->disableOriginalConstructor()
			->getMock();

		$context = new DerivativeContext( RequestContext::getMain() );
		$title = SpecialPage::getTitleFor( 'AboutTopic' );
		$context->setTitle( $title );
		$instance = new SpecialAboutTopic(
			$this->getMock( 'Wikibase\DataModel\Entity\EntityIdParser' ),
			$termLookupFactory,
			$this->getMock( 'Wikibase\Lib\Store\SiteLinkLookup' ),
			$this->getMock( 'SiteStore' ),
			new TitleFactory(),
			'',
			$this->getMock( 'Wikibase\DataModel\Services\Lookup\EntityLookup' )
		);
		$instance->setContext( $context );

		$instance->execute( '' );
		$output = $instance->getOutput();
		$this->assertSame( '(articleplaceholder-abouttopic)', $output->getPageTitle() );

		$html = $output->getHTML();
		$this->assertContains( 'id=\'ap-abouttopic-form1\'', $html );
		$this->assertContains( 'id=\'ap-abouttopic-entityid\'', $html );
		$this->assertContains( '(articleplaceholder-abouttopic-intro)', $html );
		$this->assertContains( '(articleplaceholder-abouttopic-entityid)', $html );
		$this->assertContains( '(articleplaceholder-abouttopic-submit)', $html );
	}

}
