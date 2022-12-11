<?php

namespace ArticlePlaceholder;

use FormatJson;
use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;
use MediaWiki\Http\HttpRequestFactory;

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
	 * @var StatsdDataFactoryInterface
	 */
	private $statsdDataFactory;

	/**
	 * @var HttpRequestFactory
	 */
	private $httpRequestFactory;

	/**
	 * @param string $repoApiUrl
	 * @param StatsdDataFactoryInterface $statsdDataFactory
	 * @param HttpRequestFactory $httpRequestFactory
	 */
	public function __construct(
		$repoApiUrl,
		StatsdDataFactoryInterface $statsdDataFactory,
		HttpRequestFactory $httpRequestFactory
	) {
		$this->repoApiUrl = $repoApiUrl;
		$this->statsdDataFactory = $statsdDataFactory;
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
			[],
			__METHOD__
		);

		$status = $req->execute();
		if ( !$status->isOK() ) {
			$this->statsdDataFactory->increment( 'articleplaceholder.apitermsearch.errored' );
			return [];
		}

		$json = $req->getContent();
		$data = FormatJson::decode( $json, true );
		if ( !$data || !empty( $data['error'] ) ) {
			$this->statsdDataFactory->increment( 'articleplaceholder.apitermsearch.invalid' );
			return [];
		}

		$this->statsdDataFactory->increment( 'articleplaceholder.apitermsearch.ok' );
		return $data;
	}

}
