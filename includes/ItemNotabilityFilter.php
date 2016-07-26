<?php

namespace ArticlePlaceholder;

use DatabaseBase;
use Wikibase\Client\Store\Sql\ConsistentReadConnectionManager;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Store\EntityNamespaceLookup;

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
	const MIN_SITELINKS = 3;

	/**
	 * @var ConsistentReadConnectionManager
	 */
	private $connectionManager;

	/**
	 * EntityNamespaceLookup
	 */
	private $entityNamespaceLookup;

	/**
	 * @param ConsistentReadConnectionManager $connectionManager
	 * @param EntityNamespaceLookup $entityNamespaceLookup
	 */
	public function __construct(
		ConsistentReadConnectionManager $connectionManager,
		EntityNamespaceLookup $entityNamespaceLookup
	) {
		$this->connectionManager = $connectionManager;
		$this->entityNamespaceLookup = $entityNamespaceLookup;
	}

	/**
	 * @param ItemId[] $itemIds
	 *
	 * @return ItemId[]
	 */
	public function getNotableEntityIds( array $itemIds ) {
		$notableItemIds = [];

		$statementClaimsCount = $this->getStatementClaimsCount( $itemIds );

		foreach ( $itemIds as $itemId ) {
			$itemIdSerialization = $itemId->getSerialization();

			if ( $statementClaimsCount[$itemIdSerialization]['wb-claims'] >= self::MIN_STATEMENTS
				&& $statementClaimsCount[$itemIdSerialization]['wb-sitelinks'] >= self::MIN_SITELINKS ) {

				$notableItemIds[] = $itemId;
			}
		}

		return $notableItemIds;
	}

	/**
	 * Get number of statements and claims for a list of ItemIds
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
	 * @param DatabaseBase $dbr
	 * @param ItemId[] $itemIds
	 *
	 * @return ResultWrapper
	 */
	private function selectPagePropsPage( DatabaseBase $dbr, array $itemIds ) {
		$entityNamespace = $this->entityNamespaceLookup->getEntityNamespace( 'item' );

		if ( $entityNamespace === false ) {
			wfLogWarning( 'EntityNamespaceLookup returns false' );
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

}