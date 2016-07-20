<?php

namespace ArticlePlaceholder;

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
	 * @var ItemNotabilityFilter
	 */
	private $itemNotabilityFilter;

	/**
	 * @param SpecialPage $specialPage
	 *
	 * @return self
	 */
	private static function newFromGlobalState( SpecialPage $specialPage ) {
		$wikibaseClient = WikibaseClient::getDefaultInstance();
		$repoDB = $wikibaseClient->getSettings()->getSetting( 'repoDatabase' );

		$itemNotabilityFilter = new ItemNotabilityFilter(
			new ConsistentReadConnectionManager( wfGetLB( $repoDB ), $repoDB ),
			$wikibaseClient->getEntityNamespaceLookup()
		);

		return new self(
			$wikibaseClient->getStore()->getTermIndex(),
			$wikibaseClient->newTermSearchInteractor( $specialPage->getLanguage()->getCode() ),
			$specialPage->getConfig()->get( 'LanguageCode' ),
			$wikibaseClient->getSettings()->getSetting( 'repoScriptPath' ),
			$wikibaseClient->getSettings()->getSetting( 'repoUrl' ),
			$itemNotabilityFilter
		);
	}

	/**
	 * @param TermIndex $termIndex
	 * @param TermIndexSearchInteractor $termSearchInteractor
	 * @param string $languageCode content language
	 * @param string $repoScriptPath
	 * @param string $repoUrl
	 * @param ItemNotabilityFilter $itemNotabilityFilter
	 */
	public function __construct(
		TermIndex $termIndex,
		TermIndexSearchInteractor $termSearchInteractor,
		$languageCode,
		$repoScriptPath,
		$repoUrl,
		ItemNotabilityFilter $itemNotabilityFilter
	) {
		$this->termIndex = $termIndex;
		$this->termSearchInteractor = $termSearchInteractor;
		$this->languageCode = $languageCode;
		$this->repoScriptPath = $repoScriptPath;
		$this->repoUrl = $repoUrl;
		$this->itemNotabilityFilter = $itemNotabilityFilter;
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

		$itemIds = [];
		foreach ( $termSearchResults as $termSearchResult ) {
			$itemIds[] = $termSearchResult->getEntityId();
		}
		$notableEntityIds = $this->itemNotabilityFilter->getNotableEntityIds( $itemIds );

		foreach ( $notableEntityIds as $entityId ) {
			$result = $this->renderTermSearchResult( $termSearchResults[ $entityId->getSerialization() ] );

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

}
