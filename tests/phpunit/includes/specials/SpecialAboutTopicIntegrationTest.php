<?php

declare( strict_types = 1 );

namespace ArticlePlaceholder\Tests\Specials;

use ArticlePlaceholder\AboutTopicRenderer;
use ArticlePlaceholder\Specials\SpecialAboutTopic;
use HashSiteStore;
use MediaWiki\MediaWikiServices;
use Site;
use SiteLookup;
use SpecialPageTestBase;
use Wikibase\Client\Hooks\OtherProjectsSidebarGeneratorFactory;
use Wikibase\Client\Tests\Mocks\MockClientStore;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Services\Lookup\InMemoryEntityLookup;
use Wikibase\DataModel\SiteLink;
use Wikibase\DataModel\SiteLinkList;
use Wikibase\Lib\SettingsArray;
use Wikibase\Repo\Tests\NewItem;

/**
 * @covers ArticlePlaceholder\Specials\SpecialAboutTopic
 *
 * @group ArticlePlaceholder
 * @group Database
 *
 * @license GPL-2.0-or-later
 */
class SpecialAboutTopicIntegrationTest extends SpecialPageTestBase {

	/**
	 * @var OutputPage
	 */
	private $page;

	/**
	 * @var WikibaseClient
	 */
	private $wikibaseClient;

	/**
	 * @var MockClientStore
	 */
	private $store;

	/**
	 * @var InMemoryEntityLookup
	 */
	private $inMemoryLookup;

	protected function setUp() : void {
		parent::setUp();

		$this->insertPage( 'Template:AboutTopic', '(aboutTopic: {{{1}}})' );
		$this->wikibaseClient = WikibaseClient::getDefaultInstance( 'reset' );

		$this->store = new MockClientStore( 'sv' );

		$testItem = NewItem::withId( 'Q1' )->build();
		$testItem->setSiteLinkList( new SiteLinkList( [ new SiteLink( 'non-default', 'Wikipedia' ) ] ) );

		$this->inMemoryLookup = new InMemoryEntityLookup( $testItem );
	}

	protected function tearDown(): void {
		WikibaseClient::getDefaultInstance( 'reset' );
		parent::tearDown();
	}

	private function newSettings() {
		$defaults = [
			'siteGlobalID' => 'local-non-default',
			'otherProjectsLinks' => [ 'non-default' ],
		];

		return new SettingsArray( $defaults );
	}

	/**
	 * @return SiteLookup
	 */
	private function getSiteLookupMock() {
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
			$this->wikibaseClient->getSidebarLinkBadgeDisplay(),
			$services->getHookContainer(),
			WikibaseClient::getLogger( $services )
		);

		$this->page = new SpecialAboutTopic(
			new AboutTopicRenderer(
				$this->wikibaseClient->getLanguageFallbackLabelDescriptionLookupFactory(),
				$this->store->getSiteLinkLookup(),
				$siteLookup,
				$this->wikibaseClient->getLangLinkSiteGroup(),
				$titleFactory,
				$factory,
				$services->getPermissionManager()
			),
			WikibaseClient::getEntityIdParser(),
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
		$expected = '<p>(aboutTopic: Q1)' . "\n</p>";

		$output = $this->page->getOutput();
		$sidebar = $output->getProperty( 'wikibase-otherprojects-sidebar' );

		$this->assertSame( $expected, $specialPageResult );
		$this->assertSame( 'http://www.something-cool.se', $sidebar[0]['href'] );
		$this->assertSame( 'wb-otherproject-link wb-otherproject-wikipedia', $sidebar[0]['class'] );
		$this->assertSame( 'sv', $sidebar[0]['hreflang'] );
		$this->assertSame( 'wikibase-otherprojects-wikipedia', $sidebar[0]['msg'] );
	}
}
