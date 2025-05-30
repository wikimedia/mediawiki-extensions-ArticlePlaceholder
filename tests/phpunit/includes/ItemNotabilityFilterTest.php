<?php

namespace ArticlePlaceholder\Tests;

use ArticlePlaceholder\ItemNotabilityFilter;
use DataValues\StringValue;
use MediaWikiIntegrationTestCase;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\SiteLink;
use Wikibase\DataModel\SiteLinkList;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\Repo\WikibaseRepo;
use Wikimedia\Rdbms\SessionConsistentConnectionManager;

/**
 * @group Database
 * @group ArticlePlaceholder
 *
 * @covers \ArticlePlaceholder\ItemNotabilityFilter
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch
 * @author Lucie-Aimée Kaffee
 */
class ItemNotabilityFilterTest extends MediaWikiIntegrationTestCase {

	/**
	 * @var ItemId[]
	 */
	private $testItemIds = [];

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseRepository' );

		static $setUp = false;
		if ( !$setUp ) {
			$this->createTestEntities();
			$setUp = true;
		}
	}

	private function getInstance(): ItemNotabilityFilter {
		$lbFactory = $this->getServiceContainer()->getDBLoadBalancerFactory();

		return new ItemNotabilityFilter(
			new SessionConsistentConnectionManager( $lbFactory->getMainLB() ),
			WikibaseRepo::getEntityNamespaceLookup(),
			WikibaseRepo::getStore()->newSiteLinkStore(),
			'enwiki'
		);
	}

	private function createTestEntities() {
		$user = $this->getTestSysop()->getUser();
		$entityStore = WikibaseRepo::getEntityStore();

		$snak = new PropertyValueSnak(
			new NumericPropertyId( 'P123' ),
			new StringValue( 'foo' )
		);
		$statement = new Statement( $snak );

		$fingerprint = new Fingerprint();
		$statementListNotable = new StatementList( $statement, $statement, $statement );
		$statementListNotNotable = new StatementList( $statement, $statement );

		// Notable:
		$siteLinkList0 = new SiteLinkList( [
			new SiteLink( 'a', 'notable' ),
			new SiteLink( 'b', 'notable' )
		] );
		$item0 = new Item( null, $fingerprint, $siteLinkList0, $statementListNotable );

		// Not notable (to few sitelinks):
		$siteLinkList1 = new SiteLinkList( [
			new SiteLink( 'a', 'not notable 0' )
		] );
		$item1 = new Item( null, $fingerprint, $siteLinkList1, $statementListNotable );

		// Not notable (to few statements):
		$siteLinkList2 = new SiteLinkList( [
			new SiteLink( 'a', 'not notable 1' ),
			new SiteLink( 'b', 'not notable 1' ),
			new SiteLink( 'c', 'not notable 1' )
		] );
		$item2 = new Item( null, $fingerprint, $siteLinkList2, $statementListNotNotable );

		// Not notable, has an article on wiki:
		$siteLinkList3 = new SiteLinkList( [
			new SiteLink( 'enwiki', 'has-an-article' ),
			new SiteLink( 'b', 'not notable 2' ),
			new SiteLink( 'c', 'not notable 2' )
		] );
		$item3 = new Item( null, $fingerprint, $siteLinkList3, $statementListNotable );

		$entityStore->saveEntity( $item0, 'ItemNotabilityFilterTest', $user, EDIT_NEW );
		$entityStore->saveEntity( $item1, 'ItemNotabilityFilterTest', $user, EDIT_NEW );
		$entityStore->saveEntity( $item2, 'ItemNotabilityFilterTest', $user, EDIT_NEW );
		$entityStore->saveEntity( $item3, 'ItemNotabilityFilterTest', $user, EDIT_NEW );

		$this->testItemIds[] = $item0->getId();
		$this->testItemIds[] = $item1->getId();
		$this->testItemIds[] = $item2->getId();
		$this->testItemIds[] = $item3->getId();
	}

	public function testGetNotableEntityIds() {
		$itemNotabilityFilter = $this->getInstance();

		$result = $itemNotabilityFilter->getNotableEntityIds( $this->testItemIds );

		$this->assertEquals(
			[ $this->testItemIds[0] ],
			$result
		);
		$this->assertCount( 1, $result );
	}

}
