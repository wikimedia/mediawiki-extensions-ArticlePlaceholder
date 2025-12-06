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
use Wikimedia\Stats\Metrics\CounterMetric;

/**
 * Add results from ArticlePlaceholder to search
 *
 * @license GPL-2.0-or-later
 * @author Lucie-Aimée Kaffee
 */
class SearchHookHandler implements SpecialSearchResultsAppendHook {

	public static function newFromGlobalState( Config $config ): self {
		// TODO inject services into hook handler instance
		$mwServices = MediaWikiServices::getInstance();
		$connProvider = $mwServices->getConnectionProvider();
		$statsFactory = $mwServices->getStatsFactory();

		$repoDB = WikibaseClient::getItemAndPropertySource()->getDatabaseName();
		$clientSettings = WikibaseClient::getSettings( $mwServices );

		$itemNotabilityFilter = new ItemNotabilityFilter(
			$connProvider->getReplicaDatabase( $repoDB ),
			WikibaseClient::getEntityNamespaceLookup( $mwServices ),
			WikibaseClient::getStore()->getSiteLinkLookup(),
			$clientSettings->getSetting( 'siteGlobalID' )
		);

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

	public function __construct(
		private readonly TermSearchInteractor $termSearchInteractor,
		private readonly string $languageCode,
		private readonly ItemNotabilityFilter $itemNotabilityFilter,
		private readonly CounterMetric $searchesMetric,
	) {
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

	private function addToSearch( OutputPage $output, string $term ): void {
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
	 * @return TermSearchResult[]
	 */
	private function getTermSearchResults( string $term ): array {
		$termSearchResults = [];

		foreach ( $this->searchEntities( $term ) as $searchResult ) {
			$termSearchResults[ $searchResult->getEntityIdSerialization() ] = $searchResult;
		}

		return $termSearchResults;
	}

	/**
	 * Render search results, filtered for notability.
	 *
	 * @return string Wikitext
	 */
	private function renderTermSearchResults( array $termSearchResults ): string {
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
	 * @return string Wikitext
	 */
	private function renderTermSearchResult( TermSearchResult $searchResult ): string {
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
	 * @return TermSearchResult[]
	 */
	private function searchEntities( string $term ): array {
		return $this->termSearchInteractor->searchForEntities(
			$term,
			$this->languageCode,
			'item',
			[ TermIndexEntry::TYPE_LABEL, TermIndexEntry::TYPE_ALIAS ]
		);
	}

}
