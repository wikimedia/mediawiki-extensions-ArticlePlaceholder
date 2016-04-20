<?php

namespace ArticlePlaceholder\Tests;

use ArticlePlaceholder\SearchHookHandler;
use MediaWikiTestCase;
use OutputPage;
use RequestContext;
use Title;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Lib\Interactors\TermIndexSearchInteractor;
use Wikibase\Store\BufferingTermLookup;
use Wikibase\TermIndexEntry;
use Wikibase\Test\MockTermIndex;

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
			array(
				// Q111 - Has label, description and alias all the same
				$this->getTermIndexEntry( 'Unicorn', 'en', $typeLabel, new ItemId( 'Q111' ) ),
				$this->getTermIndexEntry( 'Unicorn', 'en', $typeDescription, new ItemId( 'Q111' ) ),
				$this->getTermIndexEntry( 'Unicorn', 'en', $typeAlias, new ItemId( 'Q111' ) ),
				$this->getTermIndexEntry( 'UNICORN', 'en', $typeAlias, new ItemId( 'Q111' ) ),
				// Q333
				$this->getTermIndexEntry( 'Unicorns are great', 'en', $typeLabel, new ItemId( 'Q333' ) ),
				// Q555
				$this->getTermIndexEntry( 'Ta', 'en', $typeAlias, new ItemId( 'Q555' ) ),
				$this->getTermIndexEntry( 'Taa', 'en', $typeAlias, new ItemId( 'Q555' ) ),
				$this->getTermIndexEntry( 'TAAA', 'en-ca', $typeAlias, new ItemId( 'Q555' ) ),
				$this->getTermIndexEntry( 'Taa', 'en-ca', $typeAlias, new ItemId( 'Q555' ) ),
				// P22
				$this->getTermIndexEntry( 'Lama', 'en-ca', $typeLabel, new PropertyId( 'P22' ) ),
				$this->getTermIndexEntry( 'La-description', 'en', $typeDescription, new PropertyId( 'P22' ) ),
				// P44
				$this->getTermIndexEntry( 'Lama', 'en', $typeLabel, new PropertyId( 'P44' ) ),
				$this->getTermIndexEntry( 'Lama-de-desc', 'de', $typeDescription, new PropertyId( 'P44' ) ),
			)
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
		return new TermIndexEntry( array(
			'termText' => $text,
			'termLanguage' => $languageCode,
			'termType' => $termType,
			'entityId' => $entityId->getNumericId(),
			'entityType' => $entityId->getEntityType(),
		) );
	}

	private function getMockedTermSearchInteractor( $language, $doNotReturnTerms = false ) {
		$termLookupIndex = $doNotReturnTerms
			? new MockTermIndex( array() )
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

	protected function newSearchHookHandler( $doNotReturnTerms = false ) {
		$language = $this->getLanguageCode();
		$page = new SearchHookHandler(
			$this->getMockTermIndex(),
			$this->getMockedTermSearchInteractor( $language, $doNotReturnTerms ),
			$language
		);
		return $page;
	}

	public function provideAddToSearch() {
		return array(
			array(
				'get term, check if entity with right title is returned',
				'Unicorn',
				'>Unicorn</a>: Unicorn</div>'
			),
			array(
				'search result with no label and no description',
				'Unicorn',
				'>Q111</a></div>',
				true
			),
		);
	}

	/**
	 * @dataProvider provideAddToSearch
	 */
	public function testAddToSearch( $message, $term, $expected, $doNotReturnTerms = false ) {
		$specialSearch = $this->getSpecialSearch();
		$output = new OutputPage( new RequestContext() );
		$output->setTitle( Title::makeTitle( 0, 'testOutputSearch' ) );

		$searchHookHander = $this->newSearchHookHandler( $doNotReturnTerms );
		$searchHookHander->addToSearch( $specialSearch, $output, $term );
		$html = $output->getHTML();
		$this->assertContains( $expected, $html, $message );
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
