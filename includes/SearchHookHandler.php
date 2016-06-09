<?php

namespace ArticlePlaceholder;

use DatabaseBase;
use OutputPage;
use SpecialSearch;
use SpecialPage;
use Wikibase\Client\WikibaseClient;
use Wikibase\Client\Store\Sql\ConsistentReadConnectionManager;
use Wikibase\Lib\Interactors\TermIndexSearchInteractor;
use Wikibase\Lib\Interactors\TermSearchResult;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\TermIndex;
use Wikibase\TermIndexEntry;

/**
 * Adding results from ArticlePlaceholder to search
 *
 * @author Lucie-Aimée Kaffee
 * @license GNU General Public Licence 2.0 or later
 */
class SearchHookHandler {

	/**
	 * minimum number of statements for an item to be notable
	 * @var int
	 */
	const MIN_STATEMENTS = 3;

	/**
	 * minimum number of sitelinks for an item to be notable
	 * @var int
	 */
	const MIN_SITELINKS = 3;

	/**
	 * @var TermIndex
	 */
	private $termIndex;

	/**
	 * @var TermIndexSearchInteractor
	 */
	private $termSearchInteractor;

	/**
	 * @var string
	 */
	private $languageCode;

	/**
	 * @var string
	 */
	private $repoScriptPath;

	/**
	 * @var string
	 */
	private $repoUrl;

	/**
	 * @var ConsistentReadConnectionManager
	 */
	private $connectionManager;

	/**
	 * EntityNamespaceLookup
	 */
	private $entityNamespaceLookup;

	/**
	 * @param SpecialPage $specialPage
	 *
	 * @return self
	 */
	private static function newFromGlobalState( SpecialPage $specialPage ) {
		$wikibaseClient = WikibaseClient::getDefaultInstance();
		$repoDB = $wikibaseClient->getSettings()->getSetting( 'repoDatabase' );

		return new self(
			$wikibaseClient->getStore()->getTermIndex(),
			$wikibaseClient->newTermSearchInteractor( $specialPage->getLanguage()->getCode() ),
			$specialPage->getConfig()->get( 'LanguageCode' ),
			$wikibaseClient->getSettings()->getSetting( 'repoScriptPath' ),
			$wikibaseClient->getSettings()->getSetting( 'repoUrl' ),
			new ConsistentReadConnectionManager( wfGetLB( $repoDB ), $repoDB ),
			$wikibaseClient->getEntityNamespaceLookup()
		);
	}

	/**
	 * @param TermIndex $termIndex
	 * @param TermIndexSearchInteractor $termSearchInteractor
	 * @param string $languageCode content language
	 * @param string $repoScriptPath
	 * @param string $repoUrl
	 * @param ConsistentReadConnectionManager $connectionManager
	 * @param EntityNamespaceLookup $entityNamespaceLookup
	 */
	public function __construct(
		TermIndex $termIndex,
		TermIndexSearchInteractor $termSearchInteractor,
		$languageCode,
		$repoScriptPath,
		$repoUrl,
		ConsistentReadConnectionManager $connectionManager,
		EntityNamespaceLookup $entityNamespaceLookup
	) {
		$this->termIndex = $termIndex;
		$this->termSearchInteractor = $termSearchInteractor;
		$this->languageCode = $languageCode;
		$this->repoScriptPath = $repoScriptPath;
		$this->repoUrl = $repoUrl;
		$this->connectionManager = $connectionManager;
		$this->entityNamespaceLookup = $entityNamespaceLookup;
	}

	/**
	 * @param SpecialSearch $specialSearch
	 * @param OutputPage $output
	 * @param string $term
	 */
	public static function onSpecialSearchResultsAppend(
		SpecialSearch $specialSearch,
		OutputPage $output,
		$term
	) {
		if ( trim( $term ) === '' ) {
			return;
		}

		$instance = self::newFromGlobalState( $specialSearch );
		$instance->addToSearch( $specialSearch, $output, $term );
	}

	/**
	 * @param SpecialSearch $specialSearch
	 * @param OutputPage $output
	 * @param string $term
	 */
	public function addToSearch( SpecialSearch $specialSearch, OutputPage $output, $term ) {
		$termSearchResults = $this->getTermSearchResults( $term );

		if ( !empty( $termSearchResults ) ) {
			$renderedTermSearchResults = $this->renderTermSearchResults( $termSearchResults );

			if ( $renderedTermSearchResults !== '' ) {
				$output->addWikiText(
					'==' .
					$output->msg( 'articleplaceholder-search-header' )->text() .
					'=='
				);

				$output->addWikiText( $renderedTermSearchResults );
			}
		}
	}

	/**
	 * @param string $term
	 *
	 * @return TermSearchResult[]
	 */
	private function getTermSearchResults( $term ) {
		$termSearchResults = [];

		foreach ( $this->searchEntities( $term ) as $searchResult ) {
			$entityId = $searchResult->getEntityId()->getSerialization();

			$termSearchResults[ $entityId ] = $searchResult;
		}

		return $termSearchResults;
	}

	/**
	 * Render search results, filtered for notability.
	 *
	 * @param TermSearchResult[] $termSearchResults
	 *
	 * @return string Wikitext
	 */
	private function renderTermSearchResults( array $termSearchResults ) {
		$wikitext = '';
		$notableEntityIds = $this->getNotableEntityIds( array_keys( $termSearchResults ) );

		foreach ( $notableEntityIds as $entityId ) {
			$result = $this->renderTermSearchResult( $termSearchResults[ $entityId ] );

			$wikitext .= '<div class="article-placeholder-searchResult">'
						. $result
						. '</div>';
		}

		return $wikitext;
	}

	/**
	 * @param TermSearchResult $searchResult
	 *
	 * @return string Wikitext
	 */
	private function renderTermSearchResult( TermSearchResult $searchResult ) {
		$entityId = $searchResult->getEntityId();

		$displayLabel = $searchResult->getDisplayLabel();
		$displayDescription = $searchResult->getDisplayDescription();

		$label = $displayLabel ? $displayLabel->getText() : $entityId->getSerialization();

		// TODO: Properly construct the page name of the special page.
		$wikitext = '[[Special:AboutTopic/' . wfEscapeWikiText( $entityId ) . '|'
			. wfEscapeWikiText( $label ) . ']]';

		if ( $displayDescription ) {
			$wikitext .= ': ' . wfEscapeWikiText( $displayDescription->getText() );
		}

		return $wikitext;
	}

	/**
	 * @param string $term
	 *
	 * @return TermSearchResult[]
	 */
	private function searchEntities( $term ) {
		$searchResults = $this->termSearchInteractor->searchForEntities(
			$term,
			$this->languageCode,
			'item',
			[ TermIndexEntry::TYPE_LABEL, TermIndexEntry::TYPE_ALIAS ]
		);
		return $searchResults;
	}

	/**
	 * TODO: instead of api request database?
	 * @param string[] $entityIds
	 *
	 * @return string[] $notableEntityIds
	 */
	private function getNotableEntityIds( $entityIds ) {
		$notableEntityIds = [];

		$statementClaimsCount = $this->getStatementClaimsCount( $entityIds );

		foreach ( $entityIds as $entityId ) {
			if ( $statementClaimsCount[ $entityId ][ 'wb-claims' ] >= self::MIN_STATEMENTS
				&& $statementClaimsCount[ $entityId ][ 'wb-sitelinks' ] >= self::MIN_SITELINKS ) {

				$notableEntityIds[] = $entityId;
			}
		}
		return $notableEntityIds;
	}

	/**
	 * Get number of statements and claims for a list of entityIds
	 * @param string[] $entityIds
	 * @return array() int[page_title][propname] => value
	 */
	private function getStatementClaimsCount( $entityIds ) {
		$statementsClaimsCount = [];

		$db = $this->connectionManager->getReadConnection();

		$res = $this->selectPagePropsPage( $db, $entityIds );

		$this->connectionManager->releaseConnection( $db );

		foreach ( $res as $row ) {
			if ( $row !== false ) {
				if ( !$row->pp_value ) {
					$statementsClaimsCount[ $row->page_title ][ $row->pp_propname ] = 0;
				} else {
					$statementsClaimsCount[ $row->page_title ][ $row->pp_propname ] = $row->pp_value;
				}
			}
		}

		return $statementsClaimsCount;
	}

	/**
	 * @param DatabaseBase $db
	 * @param string[] $entityIds
	 * @return type
	 */
	private function selectPagePropsPage( DatabaseBase $db, $entityIds ) {
		$entityNamespace = $this->entityNamespaceLookup->getEntityNamespace( 'item' );

		if ( $entityNamespace === false ) {
			wfLogWarning( 'EntityNamespaceLookup returns false' );
			return [];
		}

		return $db->select(
			[ 'page_props', 'page' ],
			[ 'page_title', 'pp_propname', 'pp_value' ],
			[
				'page_namespace' => $entityNamespace,
				'page_title' => $entityIds,
				'pp_propname' => [ 'wb-sitelinks', 'wb-claims' ]
			],
			__METHOD__,
			[],
			[ 'page' => [ 'LEFT JOIN', 'page_id=pp_page' ] ]
		);
	}

}
