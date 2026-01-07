<?php

namespace ArticlePlaceholder;

use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Json\FormatJson;
use Wikimedia\Stats\StatsFactory;

/**
 * Gateway to API of the repository
 *
 * @license GPL-2.0-or-later
 */
class RepoApiInteractor {

	public function __construct(
		private readonly string $repoApiUrl,
		private readonly StatsFactory $statsFactory,
		private readonly HttpRequestFactory $httpRequestFactory,
	) {
	}

	public function request( array $params ): array {
		$url = wfAppendQuery( $this->repoApiUrl, $params );
		$req = $this->httpRequestFactory->create(
			$url,
			[
				'userAgent' => 'ArticlePlaceholder ' . $this->httpRequestFactory->getUserAgent(),
			],
			__METHOD__
		);
		$metric = $this->statsFactory->getCounter( 'ArticlePlaceholder_apitermsearch_total' );

		$status = $req->execute();
		if ( !$status->isOK() ) {
			$metric->setLabel( 'status', 'errored' )
				->copyToStatsdAt( 'articleplaceholder.apitermsearch.errored' )
				->increment();
			return [];
		}

		$json = $req->getContent();
		$data = FormatJson::decode( $json, true );
		if ( !$data || !empty( $data['error'] ) ) {
			$metric->setLabel( 'status', 'invalid' )
				->copyToStatsdAt( 'articleplaceholder.apitermsearch.invalid' )
				->increment();
			return [];
		}

		$metric->setLabel( 'status', 'ok' )
			->copyToStatsdAt( 'articleplaceholder.apitermsearch.ok' )
			->increment();
		return $data;
	}

}
