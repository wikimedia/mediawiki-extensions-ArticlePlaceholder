<?php

namespace ArticlePlaceholder\Tests;

use ArticlePlaceholder\ItemNotabilityFilter;
use ArticlePlaceholder\SearchHookHandler;
use Config;
use Liuggio\StatsdClient\Factory\StatsdDataFactory;
use MediaWikiIntegrationTestCase;
use OutputPage;
use RequestContext;
use SpecialSearch;
use Title;
use Wikibase\DataAccess\NullPrefetchingTermLookup;
use Wikibase\DataAccess\Tests\FakePrefetchingTermLookup;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\Lib\Interactors\MatchingTermsLookupSearchInteractor;
use Wikibase\Lib\Interactors\TermSearchInteractor;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Lib\Store\MatchingTermsLookup;
use Wikibase\Lib\TermIndexEntry;
use Wikibase\Lib\Tests\Store\MockMatchingTermsLookup;
use Wikimedia\TestingAccessWrapper;

/**
 * @group ArticlePlaceholder
 *
 * @covers \ArticlePlaceholder\SearchHookHandler
 *
 * @license GPL-2.0-or-later
 * @author Lucie-Aimée Kaffee
 */
class SearchHookHandlerTest extends MediaWikiIntegrationTestCase {

	private function getMockMatchingTermLookup(): MatchingTermsLookup {
		$typeLabel = TermIndexEntry::TYPE_LABEL;
		$typeAlias = TermIndexEntry::TYPE_ALIAS;
		$typeDescription = TermIndexEntry::TYPE_DESCRIPTION;

		return new MockMatchingTermsLookup(
			[
				// Q7246 - Has label, description and alias all the same
				// (multiple sitelinks and statements in the ApiRequest.json)
				$this->getTermIndexEntry( 'Unicorn', 'en', $typeLabel, new ItemId( 'Q7246' ) ),
				$this->getTermIndexEntry( 'Unicorn', 'en', $typeDescription, new ItemId( 'Q7246' ) ),
				$this->getTermIndexEntry( 'Unicorn', 'en', $typeAlias, new ItemId( 'Q7246' ) ),
				$this->getTermIndexEntry( 'UNICORN', 'en', $typeAlias, new ItemId( 'Q7246' ) ),
				// Q111 - same label as Q7246
				$this->getTermIndexEntry( 'Unicorn', 'en', $typeLabel, new ItemId( 'Q111' ) ),
				// Q753853
				// (multiple sitelinks and statements ins ApiRequest.json)
				$this->getTermIndexEntry( 'Unicorns are great', 'en', $typeLabel, new ItemId( 'Q753853' ) ),
				// Q12345
				// (multiple statements and sitelinks)
				$this->getTermIndexEntry( 'Ta', 'en', $typeAlias, new ItemId( 'Q12345' ) ),
				$this->getTermIndexEntry( 'Taa', 'en', $typeAlias, new ItemId( 'Q12345' ) ),
				$this->getTermIndexEntry( 'TAAA', 'en-ca', $typeAlias, new ItemId( 'Q12345' ) ),
				$this->getTermIndexEntry( 'Taa', 'en-ca', $typeAlias, new ItemId( 'Q12345' ) ),
				// P22
				$this->getTermIndexEntry( 'Lama', 'en-ca', $typeLabel, new NumericPropertyId( 'P22' ) ),
				$this->getTermIndexEntry( 'La-description', 'en', $typeDescription, new NumericPropertyId( 'P22' ) ),
				// P44
				$this->getTermIndexEntry( 'Lama', 'en', $typeLabel, new NumericPropertyId( 'P44' ) ),
				$this->getTermIndexEntry( 'Lama-de-desc', 'de', $typeDescription, new NumericPropertyId( 'P44' ) ),
			]
		);
	}

	/**
	 * @param string $text
	 * @param string $languageCode
	 * @param string $termType
	 * @param EntityId|ItemId|NumericPropertyId $entityId
	 *
	 * @return TermIndexEntry
	 */
	private function getTermIndexEntry( $text, $languageCode, $termType, EntityId $entityId ): TermIndexEntry {
		return new TermIndexEntry( [
			'termText' => $text,
			'termLanguage' => $languageCode,
			'termType' => $termType,
			'entityId' => $entityId,
		] );
	}

	private function getMockedTermSearchInteractor( $language, $doNotReturnTerms = false ): TermSearchInteractor {
		return new MatchingTermsLookupSearchInteractor(
			$this->getMockMatchingTermLookup(),
			new LanguageFallbackChainFactory,
			$doNotReturnTerms
				? new NullPrefetchingTermLookup()
				: new FakePrefetchingTermLookup(),
			$language
		);
	}

	/**
	 * @param bool $doNotReturnTerms
	 * @param int &$hasResults
	 * @param int &$noResults
	 *
	 * @return SearchHookHandler
	 */
	protected function newSearchHookHandler(
		$doNotReturnTerms = false,
		&$hasResults = 0,
		&$noResults = 0
	) {
		$itemNotabilityFilter = $this->createMock( ItemNotabilityFilter::class );
		$itemNotabilityFilter->method( 'getNotableEntityIds' )
			->with( $this->isType( 'array' ) )
			->willReturnCallback( static function ( array $itemIds ) {
				// Q7246 is notable, nothing else is
				$Q7246 = new ItemId( 'Q7246' );
				if ( in_array( $Q7246, $itemIds ) ) {
					return [ $Q7246 ];
				}

				return [];
			} );

		$statsdDataFactory = $this->createMock( StatsdDataFactory::class );
		$statsdDataFactory->method( 'increment' )
			->willReturnCallback( function ( $key ) use ( &$hasResults, &$noResults ) {
				if ( $key === 'wikibase.articleplaceholder.search.has_results' ) {
					$hasResults++;
				} elseif ( $key === 'wikibase.articleplaceholder.search.no_results' ) {
					$noResults++;
				} else {
					$this->fail( "Unknown key: $key" );
				}
			} );

		return TestingAccessWrapper::newFromObject( new SearchHookHandler(
			$this->getMockedTermSearchInteractor( 'en', $doNotReturnTerms ),
			'en',
			$itemNotabilityFilter,
			$statsdDataFactory
		) );
	}

	public function testNewFromGlobalState() {
		$specialPage = $this->createMock( SpecialSearch::class );
		$specialPage->expects( $this->once() )
			->method( 'getConfig' )
			->willReturn( $this->createMock( Config::class ) );

		/** @var SearchHookHandler $handler */
		$handler = TestingAccessWrapper::newFromClass( SearchHookHandler::class );
		$instance = $handler->newFromGlobalState( $specialPage );

		$this->assertInstanceOf( SearchHookHandler::class, $instance );
	}

	public function provideAddToSearch() {
		return [
			[
				'get term, check if entity with right title is returned',
				'Unicorn',
				'>Q7246 en label</a>: Q7246 en description</div>'
			],
			[
				'search result with no label and no description',
				'Unicorn',
				'>Q7246</a></div>',
				true
			],
		];
	}

	/**
	 * @dataProvider provideAddToSearch
	 */
	public function testAddToSearch( $message, $term, $expected, $doNotReturnTerms = false ) {
		$output = new OutputPage( new RequestContext() );
		$output->setTitle( Title::makeTitle( -1, 'Search' ) );

		$hasResults = $noResults = 0;
		$searchHookHander = $this->newSearchHookHandler( $doNotReturnTerms, $hasResults, $noResults );
		$searchHookHander->addToSearch( $output, $term );
		$html = $output->getHTML();

		$this->assertStringNotContainsString( 'Q111', $html );
		$this->assertStringNotContainsString( 'Q222', $html );
		$this->assertStringContainsString( $expected, $html, $message );
		$this->assertSame( 1, $hasResults );
		$this->assertSame( 0, $noResults );
	}

	public function testAddToSearch_nothingFound() {
		$output = new OutputPage( new RequestContext() );
		$output->setTitle( Title::makeTitle( -1, 'Search' ) );

		$hasResults = $noResults = 0;
		$searchHookHander = $this->newSearchHookHandler( false, $hasResults, $noResults );
		$searchHookHander->addToSearch( $output, 'blah blah blah' );
		$html = $output->getHTML();

		$this->assertSame( '', $html );
		$this->assertSame( 0, $hasResults );
		$this->assertSame( 1, $noResults );
	}

}
