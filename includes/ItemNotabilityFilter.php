<?php

namespace ArticlePlaceholder;

use Database;
use ResultWrapper;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Lib\Store\SiteLinkLookup;
use Wikimedia\Rdbms\SessionConsistentConnectionManager;

/**
 * Filter a list of items by article placeholder notability.
 *
 * @author Lucie-Aimée Kaffee
 * @author Marius Hoch
 *
 * @license GPL-2.0+
 */
class ItemNotabilityFilter {

	/**
	 * Minimum number of statements for an item to be notable
	 */
	const MIN_STATEMENTS = 3;

	/**
	 * Minimum number of sitelinks for an item to be notable
	 */
	const MIN_SITELINKS = 2;

	/**
	 * @var SessionConsistentConnectionManager
	 */
	private $connectionManager;

	/**
	 * EntityNamespaceLookup
	 */
	private $entityNamespaceLookup;

	/**
	 * @var SiteLinkLookup
	 */
	private $siteLinkLookup;

	/**
	 * @var string
	 */
	private $siteGlobalId;

	/**
	 * @param SessionConsistentConnectionManager $connectionManager
	 * @param EntityNamespaceLookup $entityNamespaceLookup
	 * @param SiteLinkLookup $siteLinkLookup
	 * @param string $siteGlobalId
	 */
	public function __construct(
		SessionConsistentConnectionManager $connectionManager,
		EntityNamespaceLookup $entityNamespaceLookup,
		SiteLinkLookup $siteLinkLookup,
		$siteGlobalId
	) {
		$this->connectionManager = $connectionManager;
		$this->entityNamespaceLookup = $entityNamespaceLookup;
		$this->siteLinkLookup = $siteLinkLookup;
		$this->siteGlobalId = $siteGlobalId;

	}

	/**
	 * @param ItemId[] $itemIds
	 *
	 * @return ItemId[]
	 */
	public function getNotableEntityIds( array $itemIds ) {
		$numericItemIds = [];

		$statementClaimsCount = $this->getStatementClaimsCount( $itemIds );

		foreach ( $itemIds as $itemId ) {
			$itemIdSerialization = $itemId->getSerialization();

			if ( $statementClaimsCount[$itemIdSerialization]['wb-claims'] >= self::MIN_STATEMENTS
				&& $statementClaimsCount[$itemIdSerialization]['wb-sitelinks'] >= self::MIN_SITELINKS
			) {
				$numericItemIds[] = $itemId->getNumericId();
			}
		}

		return $this->getItemsWithoutArticle( $numericItemIds );
	}

	/**
	 * Get number of statements and claims for a list of ItemIds
	 *
	 * @param ItemId[] $itemIds
	 *
	 * @return array() int[page_title][propname] => value
	 */
	private function getStatementClaimsCount( array $itemIds ) {
		$statementsClaimsCount = [];

		$dbr = $this->connectionManager->getReadConnection();

		$res = $this->selectPagePropsPage( $dbr, $itemIds );

		$this->connectionManager->releaseConnection( $dbr );

		foreach ( $res as $row ) {
			$statementsClaimsCount[$row->page_title][$row->pp_propname] = $row->pp_value ?: 0;
		}

		return $statementsClaimsCount;
	}

	/**
	 * @param Database $dbr
	 * @param ItemId[] $itemIds
	 *
	 * @return ResultWrapper
	 */
	private function selectPagePropsPage( Database $dbr, array $itemIds ) {
		$entityNamespace = $this->entityNamespaceLookup->getEntityNamespace( 'item' );

		if ( !is_int( $entityNamespace ) ) {
			wfLogWarning( 'The ArticlePlaceholder extension requires an "item" namespace' );
			return [];
		}

		$itemIdSerializations = [];
		foreach ( $itemIds as $itemId ) {
			$itemIdSerializations[] = $itemId->getSerialization();
		}

		return $dbr->select(
			[ 'page_props', 'page' ],
			[ 'page_title', 'pp_propname', 'pp_value' ],
			[
				'page_namespace' => $entityNamespace,
				'page_title' => $itemIdSerializations,
				'pp_propname' => [ 'wb-sitelinks', 'wb-claims' ]
			],
			__METHOD__,
			[],
			[ 'page' => [ 'LEFT JOIN', 'page_id=pp_page' ] ]
		);
	}

	/**
	 * @param int[] $numericItemIds
	 *
	 * @return ItemId[]
	 */
	private function getItemsWithoutArticle( $numericItemIds ) {
		$itemIds = [];
		$links = $this->siteLinkLookup->getLinks( $numericItemIds, [ $this->siteGlobalId ] );

		if ( !empty( $links ) ) {
			foreach ( $links as $link ) {
				$key = array_search( $link[2], $numericItemIds );
				if ( $key !== false ) {
					unset( $numericItemIds[$key] );
				}
			}
		}

		foreach ( $numericItemIds as $itemId ) {
			$itemIds[] = ItemId::newFromNumber( $itemId );
		}

		return $itemIds;
	}

}
