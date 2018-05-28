<?php

namespace ArticlePlaceholder;

use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;
use OutputPage;
use SpecialSearch;
use SpecialPage;
use MediaWiki\MediaWikiServices;
use Wikibase\Client\WikibaseClient;
use Wikibase\Lib\Interactors\TermSearchInteractor;
use Wikibase\Lib\Interactors\TermSearchResult;
use Wikibase\TermIndexEntry;
use Wikimedia\Rdbms\SessionConsistentConnectionManager;

/**
 * Adding results from ArticlePlaceholder to search
 *
 * @author Lucie-Aimée Kaffee
 * @license GNU General Public Licence 2.0 or later
 */
class SearchHookHandler {

	/**
	 * @var TermSearchInteractor
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
	 * @var StatsdDataFactoryInterface
	 */
	private $statsdDataFactory;

	/**
	 * @param SpecialPage $specialPage
	 *
	 * @return self
	 */
	private static function newFromGlobalState( SpecialPage $specialPage ) {
		$wikibaseClient = WikibaseClient::getDefaultInstance();
		$repoDB = $wikibaseClient->getRepositoryDefinitions()->getDatabaseNames()[''];
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

		$itemNotabilityFilter = new ItemNotabilityFilter(
			new SessionConsistentConnectionManager( $lbFactory->getMainLB( $repoDB ), $repoDB ),
			$wikibaseClient->getEntityNamespaceLookup(),
			$wikibaseClient->getStore()->getSiteLinkLookup(),
			$wikibaseClient->getSettings()->getSetting( 'siteGlobalID' )
		);

		return new self(
			$wikibaseClient->newTermSearchInteractor( $specialPage->getLanguage()->getCode() ),
			$specialPage->getConfig()->get( 'LanguageCode' ),
			$wikibaseClient->getSettings()->getSetting( 'repoScriptPath' ),
			$wikibaseClient->getSettings()->getSetting( 'repoUrl' ),
			$itemNotabilityFilter,
			MediaWikiServices::getInstance()->getStatsdDataFactory()
		);
	}

	/**
	 * @param TermSearchInteractor $termSearchInteractor
	 * @param string $languageCode content language
	 * @param string $repoScriptPath
	 * @param string $repoUrl
	 * @param ItemNotabilityFilter $itemNotabilityFilter
	 * @param StatsdDataFactoryInterface $statsdDataFactory
	 */
	public function __construct(
		TermSearchInteractor $termSearchInteractor,
		$languageCode,
		$repoScriptPath,
		$repoUrl,
		ItemNotabilityFilter $itemNotabilityFilter,
		StatsdDataFactoryInterface $statsdDataFactory
	) {
		$this->termSearchInteractor = $termSearchInteractor;
		$this->languageCode = $languageCode;
		$this->repoScriptPath = $repoScriptPath;
		$this->repoUrl = $repoUrl;
		$this->itemNotabilityFilter = $itemNotabilityFilter;
		$this->statsdDataFactory = $statsdDataFactory;
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

		$articlePlaceholderSearchEnabled = MediaWikiServices::getInstance()->getMainConfig()->get(
			'ArticlePlaceholderSearchIntegrationEnabled'
		);
		if ( $articlePlaceholderSearchEnabled !== true ) {
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

				$this->statsdDataFactory->increment(
					'wikibase.articleplaceholder.search.has_results'
				);

				return;
			}
		}

		$this->statsdDataFactory->increment(
			'wikibase.articleplaceholder.search.no_results'
		);
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
