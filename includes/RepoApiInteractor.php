<?php

namespace ArticlePlaceholder;

use FormatJson;
use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;
use MWHttpRequest;

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

	public function __construct( $repoApiUrl, StatsdDataFactoryInterface $statsdDataFactory ) {
		$this->repoApiUrl = $repoApiUrl;
		$this->statsdDataFactory = $statsdDataFactory;
	}

	public function request( array $params ) {
		$url = wfAppendQuery( $this->repoApiUrl, $params );
		$req = MWHttpRequest::factory(
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
