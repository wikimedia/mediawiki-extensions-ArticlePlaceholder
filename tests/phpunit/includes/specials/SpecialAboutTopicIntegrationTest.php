<?php

declare( strict_types = 1 );

namespace ArticlePlaceholder\Tests\Specials;

use ArticlePlaceholder\AboutTopicRenderer;
use ArticlePlaceholder\Specials\SpecialAboutTopic;
use HashSiteStore;
use MediaWiki\MediaWikiServices;
use Site;
use SiteLookup;
use SpecialPage;
use SpecialPageTestBase;
use Wikibase\Client\Hooks\OtherProjectsSidebarGeneratorFactory;
use Wikibase\Client\Tests\Mocks\MockClientStore;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\InMemoryEntityLookup;
use Wikibase\DataModel\SiteLink;
use Wikibase\DataModel\SiteLinkList;
use Wikibase\DataModel\Tests\NewItem;
use Wikibase\Lib\SettingsArray;

/**
 * @covers \ArticlePlaceholder\Specials\SpecialAboutTopic
 *
 * @group ArticlePlaceholder
 * @group Database
 *
 * @license GPL-2.0-or-later
 */
class SpecialAboutTopicIntegrationTest extends SpecialPageTestBase {

	/**
	 * @var SpecialPage
	 */
	private $page;

	/**
	 * @var MockClientStore
	 */
	private $store;

	/**
	 * @var InMemoryEntityLookup
	 */
	private $inMemoryLookup;

	protected function setUp(): void {
		parent::setUp();

		$this->insertPage( 'Template:AboutTopic', '(aboutTopic: {{{1}}})' );

		$this->store = new MockClientStore( 'sv' );

		$testItem = NewItem::withId( 'Q1' )->build();
		$testItem->setSiteLinkList( new SiteLinkList( [ new SiteLink( 'non-default', 'Wikipedia' ) ] ) );

		$this->inMemoryLookup = new InMemoryEntityLookup( $testItem );
	}

	private function newSettings(): SettingsArray {
		$defaults = [
			'siteGlobalID' => 'local-non-default',
			'otherProjectsLinks' => [ 'non-default' ],
		];

		return new SettingsArray( $defaults );
	}

	/**
	 * @return SiteLookup
	 */
	private function getSiteLookupMock(): SiteLookup {
		$sites = [];

		$site = new Site();
		$site->setGlobalId( 'non-default' );
		$site->setGroup( 'wikipedia' );
		$site->setLanguageCode( 'sv' );
		$site->setLinkPath( 'http://www.something-cool.se' );
		$sites[] = $site;

		return new HashSiteStore( $sites );
	}

	protected function newSpecialPage() {
		$services = MediaWikiServices::getInstance();
		$articlePlaceholderSearchEngineIndexed = $services->getMainConfig()->get(
			'ArticlePlaceholderSearchEngineIndexed'
		);

		$siteLookup = $this->getSiteLookupMock();
		$titleFactory = $services->getTitleFactory();
		$settings = $this->newSettings();

		$factory = new OtherProjectsSidebarGeneratorFactory(
			$settings,
			$this->store->getSiteLinkLookup(),
			$siteLookup,
			$this->inMemoryLookup,
			WikibaseClient::getSidebarLinkBadgeDisplay( $services ),
			$services->getHookContainer(),
			WikibaseClient::getLogger( $services )
		);

		$this->page = new SpecialAboutTopic(
			new AboutTopicRenderer(
				WikibaseClient::getFallbackLabelDescriptionLookupFactory( $services ),
				$this->store->getSiteLinkLookup(),
				$siteLookup,
				WikibaseClient::getLangLinkSiteGroup( $services ),
				$titleFactory,
				$factory,
				$services->getPermissionManager(),
				WikibaseClient::getRepoLinker( $services )
			),
			WikibaseClient::getEntityIdParser( $services ),
			$this->store->getSiteLinkLookup(),
			$titleFactory,
			$settings->getSetting( 'siteGlobalID' ),
			$this->inMemoryLookup,
			$articlePlaceholderSearchEngineIndexed
		);

		return $this->page;
	}

	public function testExecution() {
		list( $specialPageResult, ) = $this->executeSpecialPage( 'Q1' );
		$repoLinker = WikibaseClient::getRepoLinker();
		$itemID = new ItemId( 'Q1' );
		$entityUrl = $repoLinker->getEntityUrl( $itemID );

		$expected = '<div class="mw-articleplaceholder-topmessage-container">';
		$expected = $expected . '<div class="mw-articleplaceholder-topmessage-container-left">';
		$expected = $expected . '<span title=\'(articleplaceholder-abouttopic-icon-title)\' ';
		$expected = $expected . 'class=\'oo-ui-widget ';
		$expected = $expected . 'oo-ui-widget-enabled oo-ui-iconElement-icon oo-ui-icon-infoFilled oo-ui-iconElement ';
		$expected = $expected . 'oo-ui-labelElement-invisible oo-ui-iconWidget\'></span></div>';
		$expected = $expected . '<div class="mw-articleplaceholder-topmessage-container-right"></div>';
		$expected = $expected . '<div class="plainlinks mw-articleplaceholder-topmessage-container-center">';

		$expected = $expected . '<p>(articleplaceholder-abouttopic-topmessage-text: <a rel="nofollow" ';
		$expected = $expected . 'class="external free" href="';

		$expected = $expected . $entityUrl;
		$expected = $expected . '">';
		$expected = $expected . $entityUrl;
		$expected = $expected . '</a>)';
		$expected = $expected . '</p></div></div><p>(aboutTopic: Q1)';
		$expected = $expected . "\n" . '</p>';

		$output = $this->page->getOutput();
		$sidebar = $output->getProperty( 'wikibase-otherprojects-sidebar' );

		$this->assertSame( $expected, $specialPageResult );
		$this->assertSame( 'http://www.something-cool.se', $sidebar[0]['href'] );
		$this->assertSame( 'wb-otherproject-link wb-otherproject-wikipedia', $sidebar[0]['class'] );
		$this->assertSame( 'sv', $sidebar[0]['hreflang'] );
		$this->assertSame( 'wikibase-otherprojects-wikipedia', $sidebar[0]['msg'] );
	}
}
