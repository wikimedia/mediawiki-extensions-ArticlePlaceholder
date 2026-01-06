<?php

namespace ArticlePlaceholder;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Lib\Store\SiteLinkLookup;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * Filter a list of items by article placeholder notability.
 *
 * @license GPL-2.0-or-later
 * @author Lucie-Aimée Kaffee
 * @author Marius Hoch
 */
class ItemNotabilityFilter {

	/**
	 * Minimum number of statements for an item to be notable
	 */
	private const MIN_STATEMENTS = 3;

	/**
	 * Minimum number of sitelinks for an item to be notable
	 */
	private const MIN_SITELINKS = 2;

	public function __construct(
		private readonly IReadableDatabase $dbr,
		private readonly EntityNamespaceLookup $entityNamespaceLookup,
		private readonly SiteLinkLookup $siteLinkLookup,
		private readonly string $siteGlobalId,
	) {
	}

	/**
	 * @param ItemId[] $itemIds
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

			if ( !isset( $pagePropsByItem[$itemIdSerialization] ) ) {
				continue;
			}
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
	 * @return int[][] Map of page_title => propname => numeric value
	 */
	private function getPagePropsByItem( array $itemIds ) {
		$values = [];

		$res = $this->selectPagePropsPage( $itemIds );

		foreach ( $res as $row ) {
			$values[$row->page_title][$row->pp_propname] = intval( $row->pp_value ?: 0 );
		}

		return $values;
	}

	/**
	 * @param ItemId[] $itemIds
	 * @return IResultWrapper
	 */
	private function selectPagePropsPage( array $itemIds ) {
		$entityNamespace = $this->entityNamespaceLookup->getEntityNamespace( 'item' );

		if ( !is_int( $entityNamespace ) ) {
			wfLogWarning( 'The ArticlePlaceholder extension requires an "item" namespace' );
			return new FakeResultWrapper( [] );
		}

		$itemIdSerializations = [];
		foreach ( $itemIds as $itemId ) {
			$itemIdSerializations[] = $itemId->getSerialization();
		}

		return $this->dbr->newSelectQueryBuilder()
			->select( [ 'page_title', 'pp_propname', 'pp_value' ] )
			->from( 'page' )
			->join( 'page_props', null, 'page_id=pp_page' )
			->where( [
				'page_namespace' => $entityNamespace,
				'page_title' => $itemIdSerializations,
				'pp_propname' => [ 'wb-sitelinks', 'wb-claims' ]
			] )
			->caller( __METHOD__ )->fetchResultSet();
	}

	/**
	 * @param ItemId[] $itemIds expected to be indexed by numeric item ID
	 * @return ItemId[]
	 */
	private function getItemsWithoutArticle( array $itemIds ) {
		$links = $this->siteLinkLookup->getLinks( array_keys( $itemIds ), [ $this->siteGlobalId ] );

		foreach ( $links as [ , , $numericId ] ) {
			unset( $itemIds[$numericId] );
		}

		return array_values( $itemIds );
	}

}
