<?php

namespace ArticlePlaceholder;

use MediaWiki\Config\Config;
use MediaWiki\Hook\SpecialSearchResultsAppendHook;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Specials\SpecialSearch;
use Wikibase\Client\WikibaseClient;
use Wikibase\Lib\Interactors\TermSearchInteractor;
use Wikibase\Lib\Interactors\TermSearchResult;
use Wikibase\Lib\TermIndexEntry;
use Wikimedia\Rdbms\SessionConsistentConnectionManager;
use Wikimedia\Stats\Metrics\CounterMetric;

/**
 * Adding results from ArticlePlaceholder to search
 *
 * @license GPL-2.0-or-later
 * @author Lucie-Aimée Kaffee
 */
class SearchHookHandler implements SpecialSearchResultsAppendHook {

	/**
	 * @var TermSearchInteractor
	 */
	private $termSearchInteractor;

	/**
	 * @var string
	 */
	private $languageCode;

	/**
	 * @var ItemNotabilityFilter
	 */
	private $itemNotabilityFilter;

	/**
	 * @var CounterMetric
	 */
	private $searchesMetric;

	/**
	 * @param Config $config
	 *
	 * @return self
	 */
	public static function newFromGlobalState( Config $config ) {
		// TODO inject services into hook handler instance
		$mwServices = MediaWikiServices::getInstance();
		$repoDB = WikibaseClient::getItemAndPropertySource()->getDatabaseName();
		$lbFactory = $mwServices->getDBLoadBalancerFactory();
		$clientSettings = WikibaseClient::getSettings( $mwServices );

		$itemNotabilityFilter = new ItemNotabilityFilter(
			new SessionConsistentConnectionManager( $lbFactory->getMainLB( $repoDB ), $repoDB ),
			WikibaseClient::getEntityNamespaceLookup( $mwServices ),
			WikibaseClient::getStore()->getSiteLinkLookup(),
			$clientSettings->getSetting( 'siteGlobalID' )
		);

		$statsFactory = $mwServices->getStatsFactory();

		$termSearchInteractor = new TermSearchApiInteractor(
			new RepoApiInteractor(
				$config->get( 'ArticlePlaceholderRepoApiUrl' ),
				$statsFactory,
				$mwServices->getHttpRequestFactory()
			),
			WikibaseClient::getEntityIdParser()
		);

		return new self(
			$termSearchInteractor,
			$config->get( MainConfigNames::LanguageCode ),
			$itemNotabilityFilter,
			$statsFactory->getCounter( 'ArticlePlaceholder_search_total' )
		);
	}

	/**
	 * @param TermSearchInteractor $termSearchInteractor
	 * @param string $languageCode content language
	 * @param ItemNotabilityFilter $itemNotabilityFilter
	 * @param CounterMetric $searchesMetric
	 */
	public function __construct(
		TermSearchInteractor $termSearchInteractor,
		$languageCode,
		ItemNotabilityFilter $itemNotabilityFilter,
		CounterMetric $searchesMetric
	) {
		$this->termSearchInteractor = $termSearchInteractor;
		$this->languageCode = $languageCode;
		$this->itemNotabilityFilter = $itemNotabilityFilter;
		$this->searchesMetric = $searchesMetric;
	}

	/**
	 * @param SpecialSearch $specialSearch
	 * @param OutputPage $output
	 * @param string $term
	 */
	public function onSpecialSearchResultsAppend(
		$specialSearch,
		$output,
		$term
	) {
		if ( trim( $term ) === '' ) {
			return;
		}

		$config = $specialSearch->getConfig();
		if ( !$config->get( 'ArticlePlaceholderSearchIntegrationEnabled' ) ) {
			return;
		}

		$this->addToSearch( $output, $term );
	}

	/**
	 * @param OutputPage $output
	 * @param string $term
	 */
	private function addToSearch( OutputPage $output, $term ): void {
		$termSearchResults = $this->getTermSearchResults( $term );

		if ( $termSearchResults ) {
			$renderedTermSearchResults = $this->renderTermSearchResults( $termSearchResults );

			if ( $renderedTermSearchResults !== '' ) {
				$output->addWikiTextAsInterface(
					'==' .
					$output->msg( 'articleplaceholder-search-header' )->plain() .
					'=='
				);

				$output->addWikiTextAsInterface( $renderedTermSearchResults );

				$this->searchesMetric
					->setLabel( 'results', 'yes' )
					->copyToStatsdAt( 'wikibase.articleplaceholder.search.has_results' )
					->increment();

				return;
			}
		}
		$this->searchesMetric
			->setLabel( 'results', 'no' )
			->copyToStatsdAt( 'wikibase.articleplaceholder.search.no_results' )
			->increment();
	}

	/**
	 * @param string $term
	 *
	 * @return TermSearchResult[]
	 */
	private function getTermSearchResults( $term ) {
		$termSearchResults = [];

		foreach ( $this->searchEntities( $term ) as $searchResult ) {
			$termSearchResults[ $searchResult->getEntityIdSerialization() ] = $searchResult;
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
		$entityIdString = $searchResult->getEntityIdSerialization();

		$displayLabel = $searchResult->getDisplayLabel();
		$displayDescription = $searchResult->getDisplayDescription();

		$label = $displayLabel ? $displayLabel->getText() : $entityIdString;

		// TODO: Properly construct the page name of the special page.
		$wikitext = '[[Special:AboutTopic/' . wfEscapeWikiText( $entityIdString ) . '|'
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
		return $this->termSearchInteractor->searchForEntities(
			$term,
			$this->languageCode,
			'item',
			[ TermIndexEntry::TYPE_LABEL, TermIndexEntry::TYPE_ALIAS ]
		);
	}

}
