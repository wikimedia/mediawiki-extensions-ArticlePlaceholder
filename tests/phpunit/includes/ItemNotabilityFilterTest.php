<?php

namespace ArticlePlaceholder\Tests;

use ArticlePlaceholder\ItemNotabilityFilter;
use DataValues\StringValue;
use MediaWikiTestCase;
use Wikibase\Client\Store\Sql\ConsistentReadConnectionManager;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\SiteLink;
use Wikibase\DataModel\SiteLinkList;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\Repo\WikibaseRepo;

/**
 * @group Database
 * @group ArticlePlaceholder
 *
 * @covers ArticlePlaceholder\ItemNotabilityFilter
 *
 * @license GPL-2.0+
 * @author Marius Hoch
 * @author Lucie-Aimée Kaffee
 */
class ItemNotabilityFilterTest extends MediaWikiTestCase {

	/**
	 * @var ItemId[]
	 */
	private $testItemIds = [];

	public function setUp() {
		parent::setUp();

		if ( !defined( 'WB_VERSION' ) ) {
			$this->markTestSkipped(
				'ItemNotabilityFilterTest needs the current wiki to be the repo.'
			);
		}

		static $setUp = false;
		if ( !$setUp ) {
			$this->createTestEntities();
			$setUp = true;
		}
	}

	/**
	 * @return ItemNotabilityFilter
	 */
	private function getInstance() {
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();

		return new ItemNotabilityFilter(
			new ConsistentReadConnectionManager( wfGetLB() ),
			$wikibaseRepo->getEntityNamespaceLookup(),
			$wikibaseRepo->getStore()->newSiteLinkStore(),
			'enwiki'
		);
	}

	private function createTestEntities() {
		global $wgUser;
		$entityStore = WikibaseRepo::getDefaultInstance()->getEntityStore();

		$snak = new PropertyValueSnak(
			new PropertyId( 'P123' ),
			new StringValue( 'foo' )
		);
		$statement = new Statement( $snak );

		$fingerprint = new Fingerprint();
		$statementListNotable = new StatementList( [ $statement, $statement, $statement ] );
		$statementListNotNotable = new StatementList( [ $statement, $statement ] );

		// Notable:
		$siteLinkList0 = new SiteLinkList( [
			new SiteLink( 'a', 'notable' ),
			new SiteLink( 'b', 'notable' ),
			new SiteLink( 'c', 'notable' )
		] );
		$item0 = new Item( null, $fingerprint, $siteLinkList0, $statementListNotable );

		// Not notable (to few sitelinks):
		$siteLinkList1 = new SiteLinkList( [
			new SiteLink( 'a', 'not notable 0' ),
			new SiteLink( 'b', 'not notable 0' )
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

		$entityStore->saveEntity( $item0, 'ItemNotabilityFilterTest', $wgUser, EDIT_NEW );
		$entityStore->saveEntity( $item1, 'ItemNotabilityFilterTest', $wgUser, EDIT_NEW );
		$entityStore->saveEntity( $item2, 'ItemNotabilityFilterTest', $wgUser, EDIT_NEW );
		$entityStore->saveEntity( $item3, 'ItemNotabilityFilterTest', $wgUser, EDIT_NEW );

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
