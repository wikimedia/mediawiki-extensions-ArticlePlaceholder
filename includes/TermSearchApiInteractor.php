<?php

namespace ArticlePlaceholder;

use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Term\Term;
use Wikibase\Lib\Interactors\TermSearchInteractor;
use Wikibase\Lib\Interactors\TermSearchResult;

/**
 * Repository's API-based term search interactor
 *
 * @license GPL-2.0-or-later
 */
class TermSearchApiInteractor implements TermSearchInteractor {

	/**
	 * @var RepoApiInteractor
	 */
	private $repoApiInteractor;

	/**
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	/**
	 * @param RepoApiInteractor $repoApiInteractor
	 * @param EntityIdParser $entityIdParser
	 */
	public function __construct(
		RepoApiInteractor $repoApiInteractor,
		EntityIdParser $entityIdParser
	) {
		$this->repoApiInteractor = $repoApiInteractor;
		$this->entityIdParser = $entityIdParser;
	}

	/**
	 * @param string $text Term text to search for
	 * @param string $languageCode Language code to search in
	 * @param string $entityType Type of Entity to return
	 * @param string[] $termTypes Types of Term to return, array of Wikibase\Lib\TermIndexEntry::TYPE_*
	 *
	 * @return TermSearchResult[]
	 */
	public function searchForEntities( $text, $languageCode, $entityType, array $termTypes ) {
		$params = [
			'action' => 'wbsearchentities',
			'language' => $languageCode,
			'strictlanguage' => 1,
			'search' => $text,
			'format' => 'json',
			'type' => $entityType
		];

		$data = $this->repoApiInteractor->request( $params );
		if ( !isset( $data['search'] ) ) {
			return [];
		}

		$result = [];
		foreach ( $data['search'] as $datum ) {
			$result[] = $this->parseDatum( $datum, $languageCode );
		}

		return array_filter( $result );
	}

	/**
	 * @param array $datum
	 * @param string $languageCode
	 * @return null|TermSearchResult
	 */
	private function parseDatum( array $datum, $languageCode ) {
		if ( !isset( $datum['title'] ) || !isset( $datum['match'] ) ||
			!isset( $datum['match']['text'] ) || !isset( $datum['match']['type'] )
		) {
			return null;
		}

		return new TermSearchResult(
			new Term( $languageCode, $datum['match']['text'] ),
			$datum['match']['type'],
			$this->entityIdParser->parse( $datum['title'] ),
			isset( $datum['label'] ) ? new Term( $languageCode, $datum['label'] ) : null,
			isset( $datum['description'] ) ? new Term( $languageCode, $datum['description'] ) : null
		);
	}

}
