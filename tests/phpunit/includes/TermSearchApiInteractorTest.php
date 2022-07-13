<?php

namespace ArticlePlaceholder\Tests;

use ArticlePlaceholder\RepoApiInteractor;
use ArticlePlaceholder\TermSearchApiInteractor;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Term\Term;

/**
 * @covers \ArticlePlaceholder\TermSearchApiInteractor
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 */
class TermSearchApiInteractorTest extends \PHPUnit\Framework\TestCase {

	private function mockApiInteractor(): RepoApiInteractor {
		$mock = $this->createMock( RepoApiInteractor::class );
		$mock->method( 'request' )
			->willReturnCallback( static function ( array $params ) {
				$result = [];
				$result['search'] = [
					[
						'repository' => '',
						'id' => 'Q294833',
						'concepturi' => 'http://www.wikidata.org/entity/Q294833',
						'title' => 'Q294833',
						'pageid' => 284790,
						'url' => '//www.wikidata.org/wiki/Q294833',
						'label' => $params['search'],
						'description' => 'male given name',
						'match' => [
							'type' => 'label',
							'language' => $params['language'],
							'text' => $params['search']
						]
					],
					[
						'repository' => '',
						'id' => 'Q1148538',
						'concepturi' => 'http://www.wikidata.org/entity/Q1148538',
						'title' => 'Q1148538',
						'pageid' => 1095040,
						'url' => '//www.wikidata.org/wiki/Q1148538',
						'label' => $params['search'] . ' More',
						'description' => 'commune in Haute-Garonne, France',
						'match' => [
							'type' => 'label',
							'language' => $params['language'],
							'text' => $params['search']
						]
					],
				];
				return $result;
			} );

		return $mock;
	}

	public function testApiTermSearch() {
		$interactor = new TermSearchApiInteractor(
			$this->mockApiInteractor(),
			WikibaseClient::getEntityIdParser()
		);
		$result = $interactor->searchForEntities( 'Alan', 'en', 'item', [] );

		$this->assertCount( 2, $result );

		$this->assertEquals( new Term( 'en', 'Alan' ), $result[0]->getDisplayLabel() );
		$this->assertEquals( new Term( 'en', 'male given name' ), $result[0]->getDisplayDescription() );
		$this->assertEquals( new ItemId( 'Q294833' ), $result[0]->getEntityId() );
		$this->assertEquals( new Term( 'en', 'Alan' ), $result[0]->getMatchedTerm() );
		$this->assertSame( 'label', $result[0]->getMatchedTermType() );

		$this->assertEquals( new Term( 'en', 'Alan More' ), $result[1]->getDisplayLabel() );
	}

}
