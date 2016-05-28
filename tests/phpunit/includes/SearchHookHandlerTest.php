<?php

namespace ArticlePlaceholder\Tests;

use ArticlePlaceholder\SearchHookHandler;
use DatabaseBase;
use MediaWikiTestCase;
use OutputPage;
use RequestContext;
use Title;
use Wikibase\Client\WikibaseClient;
use Wikibase\Client\Store\Sql\ConsistentReadConnectionManager;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\LanguageFallbackChainFactory;
use Wikibase\Lib\Interactors\TermIndexSearchInteractor;
use Wikibase\Lib\Tests\Store\MockTermIndex;
use Wikibase\Store\BufferingTermLookup;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\TermIndexEntry;

/**
 * @group Database
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
		ConsistentReadConnectionManager $connectionManager,
		EntityNamespaceLookup $entityNsLookup
	) {
		$language = $this->getLanguageCode();
		$page = new SearchHookHandler(
			$this->getMockTermIndex(),
			$this->getMockedTermSearchInteractor( $language, $doNotReturnTerms ),
			$language,
			'repo-script-path',
			'repo-url',
			$connectionManager,
			$entityNsLookup
		);
		return $page;
	}

	/**
	 * Insert necessary data in the page_props and page table
	 * @param DatabaseBase $db
	 * @param int $pageId
	 * @param string $entityId
	 * @param int $numSitelinks
	 * @param int $numClaims
	 */
	private function insertPageProps(
		DatabaseBase $db, $pageId, $entityIdSer, $numSitelinks, $numClaims, $itemNamespace
	) {
		$this->tablesUsed[] = 'page_props';
		$this->tablesUsed[] = 'page';
		$db->insert(
			'page_props',
			[
				[
					'pp_page' => $pageId,
					'pp_propname' => 'wb-sitelinks',
					'pp_value' => $numSitelinks,
				],
				[
					'pp_page' => $pageId,
					'pp_propname' => 'wb-claims',
					'pp_value' => $numClaims,
				]
			]
		);
		$this->insertPage( $db, $pageId, $entityIdSer, $itemNamespace );
	}

	protected function insertPage( DatabaseBase $db, $pageId, $entityIdSer, $itemNamespace ) {
		$this->tablesUsed[] = 'page';
		$db->insert(
			'page',
			[
				'page_namespace' => $itemNamespace,
				'page_title' => $entityIdSer,
				'page_id' => $pageId,
			]
		);
	}

	public function provideAddToSearch() {
		return [
			[
				'get term, check if entity with right title is returned',
				'Unicorn',
				'Q7246',
				'7246',
				'5',
				'7',
				'>Unicorn</a>: Unicorn</div>'
			],
			[
				'search result with no label and no description',
				'Unicorn',
				'Q7246',
				'7246',
				'5',
				'7',
				'>Q7246</a></div>',
				true
			],
		];
	}

	/**
	 * @dataProvider provideAddToSearch
	 */
	public function testAddToSearch(
		$message, $term, $entityIdSer, $pageId, $numSitelinks, $numClaims, $expected,
		$doNotReturnTerms = false
	) {
		$wikibaseClient = WikibaseClient::getDefaultInstance();
		$repoDb = $wikibaseClient->getSettings()->getSetting( 'repoDatabase' );
		$entityNsLookup = $wikibaseClient->getEntityNamespaceLookup();
		$itemNamespace = $entityNsLookup->getEntityNamespace( 'item' );

		if ( $repoDb !== false ) {
			$this->markTestSkipped( 'Test skipped if repo database is not same as client' );
		}

		$connectionManager = new ConsistentReadConnectionManager( wfGetLB( $repoDb ), $repoDb );

		$db = $connectionManager->getWriteConnection();

		$this->insertPageProps(
			$db, $pageId, $entityIdSer, $numSitelinks, $numClaims, $itemNamespace
		);
		// test an item with only 2 sitelinks and 2 claims
		$this->insertPageProps( $db, 111, 'Q111', 2, 2, $itemNamespace );
		// test an item only in page table, not in pageprops
		$this->insertPage( $db, 222, 'Q222', $itemNamespace );

		$specialSearch = $this->getSpecialSearch();
		$output = new OutputPage( new RequestContext() );
		$output->setTitle( Title::makeTitle( 0, 'testOutputSearch' ) );

		$searchHookHander = $this->newSearchHookHandler(
			$doNotReturnTerms, $connectionManager, $entityNsLookup
		);
		$searchHookHander->addToSearch( $specialSearch, $output, $term );
		$html = $output->getHTML();

		$this->assertNotContains( 'Q111', $html );
		$this->assertNotContains( 'Q222', $html );
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
