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

	/**
	 * @var string
	 */
	private $repoApiUrl;

	/**
	 * @var StatsFactory
	 */
	private $statsFactory;

	/**
	 * @var HttpRequestFactory
	 */
	private $httpRequestFactory;

	/**
	 * @param string $repoApiUrl
	 * @param StatsFactory $statsFactory
	 * @param HttpRequestFactory $httpRequestFactory
	 */
	public function __construct(
		$repoApiUrl,
		StatsFactory $statsFactory,
		HttpRequestFactory $httpRequestFactory
	) {
		$this->repoApiUrl = $repoApiUrl;
		$this->statsFactory = $statsFactory;
		$this->httpRequestFactory = $httpRequestFactory;
	}

	/**
	 * @param array $params
	 *
	 * @return array
	 */
	public function request( array $params ) {
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
