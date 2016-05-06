<?php

namespace ArticlePlaceholder;

use Http;
use OutputPage;
use SpecialSearch;
use SpecialPage;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\Lib\Interactors\TermIndexSearchInteractor;
use Wikibase\Lib\Interactors\TermSearchResult;
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
	 * @var callable Override for the Http::get function
	 */
	private $http_get = 'Http::get';

	/**
	 * @param SpecialPage $specialPage
	 *
	 * @return self
	 */
	private static function newFromGlobalState( SpecialPage $specialPage ) {
		$wikibaseClient = WikibaseClient::getDefaultInstance();

		return new self(
			$wikibaseClient->getStore()->getTermIndex(),
			$wikibaseClient->newTermSearchInteractor( $specialPage->getLanguage()->getCode() ),
			$specialPage->getConfig()->get( 'LanguageCode' ),
			$wikibaseClient->getSettings()->getSetting( 'repoScriptPath' ),
			$wikibaseClient->getSettings()->getSetting( 'repoUrl' )
		);
	}

	/**
	 * @param TermIndex $termIndex
	 * @param TermIndexSearchInteractor $termSearchInteractor
	 * @param string $languageCode content language
	 * @param string $repoScriptPath
	 * @param string $repoUrl
	 */
	public function __construct(
		TermIndex $termIndex,
		TermIndexSearchInteractor $termSearchInteractor,
		$languageCode,
		$repoScriptPath,
		$repoUrl
	) {
		$this->termIndex = $termIndex;
		$this->termSearchInteractor = $termSearchInteractor;
		$this->languageCode = $languageCode;
		$this->repoScriptPath = $repoScriptPath;
		$this->repoUrl = $repoUrl;
	}

	/**
	 * @param SpecialSearch $specialSearch
	 * @param OutputPage $output
	 * @param string|null $term
	 *
	 * @return bool|null
	 */
	public static function onSpecialSearchResultsAppend(
		SpecialSearch $specialSearch,
		OutputPage $output,
		$term
	) {
		if ( $term === null || $term === '' ) {
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
		$searchResult = $this->getSearchResults( $term );

		if ( $searchResult !== '' ) {
			$output->addWikiText(
				'==' .
				$output->msg( 'articleplaceholder-search-header' )->text() .
				'=='
			);

			$output->addWikiText( $searchResult );
		}
	}

	/**
	 * @param string $term
	 *
	 * @return string Wikitext
	 */
	private function getSearchResults( $term ) {
		$wikitext = '';
		$entityIdSearchResult = array();

		foreach ( $this->searchEntities( $term ) as $searchResult ) {
			$entityId = $searchResult->getEntityId()->getSerialization();

			$entityIdSearchResult[ $entityId ] = $searchResult;
		}

		$notableEntityIds = $this->getNotableEntityIds( array_keys( $entityIdSearchResult ) );

		foreach ( $notableEntityIds as $entityId ) {
			$result = $this->createResult( $entityIdSearchResult[ $entityId ] );

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
	private function createResult( TermSearchResult $searchResult ) {
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
			array( TermIndexEntry::TYPE_LABEL, TermIndexEntry::TYPE_ALIAS )
		);
		return $searchResults;
	}

	/**
	 * TODO: instead of api request database?
	 * @param string[] $entityIds
	 *
	 * @return string[]|null $notableEntityIds
	 */
	private function getNotableEntityIds( $entityIds ) {
		$notableEntityIds = array();
		$data = $this->loadEntityData( $entityIds );

		if ( $data === null ) {
			return null;
		}

		foreach ( $entityIds as $entityId ) {
			if ( count( $data[$entityId][ 'claims' ] ) > self::MIN_STATEMENTS
				&& count( $data[$entityId][ 'sitelinks' ] ) > self::MIN_SITELINKS ) {
					array_push( $notableEntityIds, $entityId );
			}
		}
		return $notableEntityIds;
	}

	/**
	 * Request to the repo's wbgetentities api, in order to find out which Items are notable.
	 *
	 * @param string[] $entityIds
	 *
	 * @return array[]|null $data Null if empty, entity serialization otherwise
	 */
	private function loadEntityData( $entityIds ) {
		$url = $this->getEntitiesApiRequestUrl( $entityIds );
		$json = call_user_func( $this->http_get, $url, [ 'timeout' => 3 ] );
		// $json will be false if the request fails, json_decode can handle that.
		$data = json_decode( $json, true );

		if ( is_array( $data ) ) {
			return $data[ 'entities' ];
		} else {
			return null;
		}
	}

	/**
	 * @param string[] $entityIds
	 *
	 * @return string $url
	 */
	private function getEntitiesApiRequestUrl( $entityIds ) {
		$apiUrl = $this->repoUrl . '/' . $this->repoScriptPath;
		$apiUrl .= '/api.php';

		// due to limitation of the API
		$entityIds = array_splice( $entityIds, 0, 50 );

		$params = wfArrayToCgi( array(
			'action' => 'wbgetentities',
			'props' => ['sitelinks', 'claims'],
			'format' => 'json',
			'ids' => implode( '|', $entityIds )
		) );
		$url = $apiUrl . '?' . $params;
		return $url;
	}

	/**
	 * Set override for Http::get(), for testing.
	 *
	 * @param callable $http_get
	 */
	public function setHttpGetOverride( $http_get ) {
		$this->http_get = $http_get;
	}
}
