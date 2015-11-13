<?php

namespace ArticlePlaceholder;

use OutputPage;
use SpecialSearch;
use Wikibase\Client\WikibaseClient;
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
	 * @param string $language user language
	 *
	 * @return self
	 */
	private static function newFromGlobalState( $language ) {
		global $wgLanguageCode;

		$wikibaseClient = WikibaseClient::getDefaultInstance();

		return new self(
			$wikibaseClient->getStore()->getTermIndex(),
			$wikibaseClient->newTermSearchInteractor( $language ),
			$wgLanguageCode
		);
	}

	/**
	 * @param TermIndex $termIndex
	 * @param TermIndexSearchInteractor $termSearchInteractor
	 * @param string $languageCode content language
	 */
	public function __construct(
		TermIndex $termIndex,
		TermIndexSearchInteractor $termSearchInteractor,
		$languageCode
	) {
		$this->termIndex = $termIndex;
		$this->termSearchInteractor = $termSearchInteractor;
		$this->languageCode = $languageCode;
	}

	/**
	 * @param SpecialSearch $specialSearch
	 * @param OutputPage $output
	 * @param string|null $term
	 *
	 * @return bool
	 */
	public static function onSpecialSearchResultsAppend(
		SpecialSearch $specialSearch,
		OutputPage $output,
		$term
	) {
		if ( $term === null || $term === '' ) {
			return;
		}
		// user language, not content language
		$instance = self::newFromGlobalState( $specialSearch->getLanguage()->getCode() );
		$instance->addToSearch( $specialSearch, $output, $term );
		return true;
	}

	/**
	 * @param SpecialSearch $specialSearch
	 * @param OutputPage $output
	 * @param string $term
	 */
	public function addToSearch( SpecialSearch $specialSearch, OutputPage $output, $term ) {
		$searchResult = $this->getSearchResults( $term );
		if ( $searchResult !== null ) {
			$output->addWikiText( $this->getSearchHeader() );

			$output->addWikiText( $searchResult );
		}
	}

	/**
	 * @return string
	 */
	private function getSearchHeader() {
		$header = '==' . wfMessage( 'articleplaceholder-search-header' )->text() . '==';
		return $header;
	}

	/**
	 * @param string $term
	 *
	 * @return string
	 */
	private function getSearchResults( $term ) {
		$searchResults = $this->searchEntities( $term );
		$link = 'Special:AboutTopic/';
		$wikitext = null;
		foreach ( $searchResults as $searchResult ) {
			$wikitext .= '<div class="article-placeholder-searchResult">'
						. $this->createResult( $searchResult, $link )
						. '</div>';
		}
		return $wikitext;
	}

	/**
	 * @param TermSearchResult $searchResult
	 * @param string $link
	 *
	 * @return string
	 */
	private function createResult( TermSearchResult $searchResult, $link ) {
		$entityId = $searchResult->getEntityId();
		$label = $searchResult->getDisplayLabel()->getText();
		$description = $searchResult->getDisplayDescription()->getText();

		return '[[' . $link . wfEscapeWikiText( $entityId ) . '|' . wfEscapeWikiText( $label )
			.']]: ' . wfEscapeWikiText( $description );
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

}
