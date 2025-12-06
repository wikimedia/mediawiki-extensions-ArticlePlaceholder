<?php declare( strict_types = 1 );

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

	public function __construct(
		private readonly RepoApiInteractor $repoApiInteractor,
		private readonly EntityIdParser $entityIdParser,
	) {
	}

	/** @inheritDoc */
	public function searchForEntities(
		string $text,
		string $languageCode,
		string $entityType,
		array $termTypes
	): array {
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

		$results = [];
		foreach ( $data['search'] as $datum ) {
			$result = $this->parseDatum( $datum, $languageCode );
			if ( $result ) {
				$results[] = $result;
			}
		}
		return $results;
	}

	private function parseDatum( array $datum, string $languageCode ): ?TermSearchResult {
		if ( !isset( $datum['title'] ) ||
			!isset( $datum['match']['text'] ) ||
			!isset( $datum['match']['type'] )
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
