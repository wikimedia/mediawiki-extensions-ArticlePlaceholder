<?php

namespace ArticlePlaceholder\Tests;

use ArticlePlaceholder\ItemNotabilityFilter;
use ArticlePlaceholder\SearchHookHandler;
use Config;
use Language;
use Liuggio\StatsdClient\Factory\StatsdDataFactory;
use MediaWikiTestCase;
use OutputPage;
use ReflectionMethod;
use RequestContext;
use Title;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Lib\Interactors\TermIndexSearchInteractor;
use Wikibase\Lib\Tests\Store\MockTermIndex;
use Wikibase\Store\BufferingTermLookup;
use Wikibase\TermIndexEntry;

/**
 * @group ArticlePlaceholder
 *
 * @covers ArticlePlaceholder\SearchHookHandler
 *
 * @licence GNU GPL v2+
 * @author Lucie-Aimée Kaffee
 */
class SearchHookHandlerTest extends MediaWikiTestCase {

	private function getMockTermIndex() {
		$typeLabel = TermIndexEntry::TYPE_LABEL;
		$typeAlias = TermIndexEntry::TYPE_ALIAS;
		$typeDescription = TermIndexEntry::TYPE_DESCRIPTION;

		return new MockTermIndex(
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
				$this->getTermIndexEntry( 'Lama', 'en-ca', $typeLabel, new PropertyId( 'P22' ) ),
				$this->getTermIndexEntry( 'La-description', 'en', $typeDescription, new PropertyId( 'P22' ) ),
				// P44
				$this->getTermIndexEntry( 'Lama', 'en', $typeLabel, new PropertyId( 'P44' ) ),
				$this->getTermIndexEntry( 'Lama-de-desc', 'de', $typeDescription, new PropertyId( 'P44' ) ),
			]
		);
	}

	/**
	 * @param string $text
	 * @param string $languageCode
	 * @param string $termType
	 * @param EntityId|ItemId|PropertyId $entityId
	 *
	 * @returns TermIndexEntry
	 */
	private function getTermIndexEntry( $text, $languageCode, $termType, EntityId $entityId ) {
		return new TermIndexEntry( [
			'termText' => $text,
			'termLanguage' => $languageCode,
			'termType' => $termType,
			'entityId' => $entityId->getNumericId(),
			'entityType' => $entityId->getEntityType(),
		] );
	}

	private function getMockedTermSearchInteractor( $language, $doNotReturnTerms = false ) {
		$termLookupIndex = $doNotReturnTerms
			? new MockTermIndex( [] )
			: $this->getMockTermIndex();

		$termSearchInteractor = new TermIndexSearchInteractor(
			$this->getMockTermIndex(),
			new LanguageFallbackChainFactory,
			new BufferingTermLookup( $termLookupIndex ),
			$language
		);
		return $termSearchInteractor;
	}

	private function getSpecialSearch() {
		$mock = $this->getMockBuilder( 'SpecialSearch' )
			->disableOriginalConstructor()
			->getMock();
		return $mock;
	}

	private function getLanguageCode() {
		return 'en';
	}

	protected function newSearchHookHandler(
		$doNotReturnTerms = false,
		&$hasResults = 0,
		&$noResults = 0
	) {
		$itemNotabilityFilter = $this->getMockBuilder( ItemNotabilityFilter::class )
			->disableOriginalConstructor()
			->getMock();

		$itemNotabilityFilter->expects( $this->any() )
			->method( 'getNotableEntityIds' )
			->with( $this->isType( 'array' ) )
			->will( $this->returnCallback( function( array $itemIds ) {
				// Q7246 is notable, nothing else is
				$Q7246 = new ItemId( 'Q7246' );
				if ( in_array( $Q7246, $itemIds ) ) {
					return [ $Q7246 ];
				}

				return [];
			} ) );

		$statsdDataFactory = $this->getMock( StatsdDataFactory::class );
		$statsdDataFactory->expects( $this->any() )
			->method( 'increment' )
			->will( $this->returnCallback( function( $key ) use ( &$hasResults, &$noResults ) {
				if ( $key === 'wikibase.articleplaceholder.search.has_results' ) {
					$hasResults++;
				} elseif ( $key === 'wikibase.articleplaceholder.search.no_results' ) {
					$noResults++;
				} else {
					$this->fail( "Unknown key: $key" );
				}
			} ) );

		$language = $this->getLanguageCode();

		return new SearchHookHandler(
			$this->getMockTermIndex(),
			$this->getMockedTermSearchInteractor( $language, $doNotReturnTerms ),
			$language,
			'repo-script-path',
			'repo-url',
			$itemNotabilityFilter,
			$statsdDataFactory
		);
	}

	public function testNewFromGlobalState() {
		$specialPage = $this->getSpecialSearch();
		$specialPage->expects( $this->once() )
			->method( 'getConfig' )
			->will( $this->returnValue( $this->getMock( Config::class ) ) );

		$specialPage->expects( $this->once() )
			->method( 'getLanguage' )
			->will( $this->returnValue( Language::factory( 'en' ) ) );

		$reflectionMethod = new ReflectionMethod( SearchHookHandler::class, 'newFromGlobalState' );
		$reflectionMethod->setAccessible( true );
		$handler = $reflectionMethod->invoke( null, $specialPage );

		$this->assertInstanceOf( SearchHookHandler::class, $handler );
	}

	public function provideAddToSearch() {
		return [
			[
				'get term, check if entity with right title is returned',
				'Unicorn',
				'>Unicorn</a>: Unicorn</div>'
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
		$specialSearch = $this->getSpecialSearch();
		$output = new OutputPage( new RequestContext() );
		$output->setTitle( Title::makeTitle( -1, 'Search' ) );

		$hasResults = $noResults = 0;
		$searchHookHander = $this->newSearchHookHandler( $doNotReturnTerms, $hasResults, $noResults );
		$searchHookHander->addToSearch( $specialSearch, $output, $term );
		$html = $output->getHTML();

		$this->assertNotContains( 'Q111', $html );
		$this->assertNotContains( 'Q222', $html );
		$this->assertContains( $expected, $html, $message );
		$this->assertSame( 1, $hasResults );
		$this->assertSame( 0, $noResults );
	}

	public function testAddToSearch_nothingFound() {
		$specialSearch = $this->getSpecialSearch();
		$output = new OutputPage( new RequestContext() );
		$output->setTitle( Title::makeTitle( -1, 'Search' ) );

		$hasResults = $noResults = 0;
		$searchHookHander = $this->newSearchHookHandler( false, $hasResults, $noResults );
		$searchHookHander->addToSearch( $specialSearch, $output, 'blah blah blah' );
		$html = $output->getHTML();

		$this->assertSame( '', $html );
		$this->assertSame( 0, $hasResults );
		$this->assertSame( 1, $noResults );
	}

	public function testOnSpecialSearchResultsAppend() {
		$specialSearch = $this->getSpecialSearch();
		$output = new OutputPage( new RequestContext() );

		$result = SearchHookHandler::onSpecialSearchResultsAppend(
			$specialSearch,
			$output,
			''
		);

		$this->assertNull( $result );
	}

}
