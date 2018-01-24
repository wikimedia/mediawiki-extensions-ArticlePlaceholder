<?php

namespace ArticlePlaceholder;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Lib\Store\SiteLinkLookup;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ResultWrapper;
use Wikimedia\Rdbms\SessionConsistentConnectionManager;

/**
 * Filter a list of items by article placeholder notability.
 *
 * @author Lucie-Aimée Kaffee
 * @author Marius Hoch
 *
 * @license GPL-2.0-or-later
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
		if ( $itemIds === [] ) {
			return [];
		}

		$byNumericId = [];

		$pagePropsByItem = $this->getPagePropsByItem( $itemIds );

		foreach ( $itemIds as $itemId ) {
			$itemIdSerialization = $itemId->getSerialization();
			$pageProps = $pagePropsByItem[$itemIdSerialization];

			if (
				isset( $pageProps['wb-claims'] ) &&
				isset( $pageProps['wb-sitelinks'] ) &&
				$pageProps['wb-claims'] >= self::MIN_STATEMENTS &&
				$pageProps['wb-sitelinks'] >= self::MIN_SITELINKS
			) {
				$byNumericId[$itemId->getNumericId()] = $itemId;
			}
		}

		return $this->getItemsWithoutArticle( $byNumericId );
	}

	/**
	 * @param ItemId[] $itemIds
	 *
	 * @return int[][] Map of page_title => propname => numeric value
	 */
	private function getPagePropsByItem( array $itemIds ) {
		$values = [];

		$dbr = $this->connectionManager->getReadConnection();

		$res = $this->selectPagePropsPage( $dbr, $itemIds );

		$this->connectionManager->releaseConnection( $dbr );

		foreach ( $res as $row ) {
			$values[$row->page_title][$row->pp_propname] = intval( $row->pp_value ?: 0 );
		}

		return $values;
	}

	/**
	 * @param IDatabase $dbr
	 * @param ItemId[] $itemIds
	 *
	 * @return ResultWrapper
	 */
	private function selectPagePropsPage( IDatabase $dbr, array $itemIds ) {
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
	 * @param ItemId[] $itemIds expected to be indexed by numeric item ID
	 *
	 * @return ItemId[]
	 */
	private function getItemsWithoutArticle( array $itemIds ) {
		if ( $itemIds === [] ) {
			return [];
		}

		$links = $this->siteLinkLookup->getLinks( array_keys( $itemIds ), [ $this->siteGlobalId ] );

		foreach ( $links as $link ) {
			list( , , $numericId ) = $link;
			unset( $itemIds[$numericId] );
		}

		return array_values( $itemIds );
	}

}
