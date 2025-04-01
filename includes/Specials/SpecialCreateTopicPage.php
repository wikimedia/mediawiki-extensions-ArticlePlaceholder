<?php

namespace ArticlePlaceholder\Specials;

use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\UnlistedSpecialPage;
use MediaWiki\Title\Title;
use PermissionsError;
use Wikimedia\Stats\StatsFactory;

/**
 * The CreateTopicPage SpecialPage for the ArticlePlaceholder extension
 *
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 * @author Florian Schmidt
 */
class SpecialCreateTopicPage extends UnlistedSpecialPage {

	private PermissionManager $permissionManager;
	private StatsFactory $statsFactory;

	public function __construct(
		PermissionManager $permissionManager,
		StatsFactory $statsFactory
	) {
		parent::__construct( 'CreateTopicPage' );
		$this->permissionManager = $permissionManager;
		$this->statsFactory = $statsFactory;
	}

	/**
	 * @param string|null $par
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$this->setHeaders();
		if ( $this->getRequest()->getRawVal( 'ref' ) === 'button' ) {
			$this->statsFactory->getCounter( 'ArticlePlaceholder_button_createArticle_total' )
				->copyToStatsdAt( 'wikibase.articleplaceholder.button.createArticle' )
				->increment();
		}
		$page = $this->getRequest()->getVal( 'wptitleinput', $par );
		if ( $page === '' || $page === null ) {
			$this->showNoOrInvalidTitleGivenMessage();
			return;
		}
		$title = Title::newFromText( $page );
		if ( $title === null ) {
			$this->showNoOrInvalidTitleGivenMessage( 'invalid' );
			return;
		}
		$out->setPageTitleMsg( $this->msg( 'articleplaceholder-createpage-title', $title->getText() ) );
		if ( $title->exists() ) {
			$this->showAlreadyExistsMessage( $title );
			return;
		}

		$permissionErrors = $this->permissionManager
			->getPermissionErrors( 'edit', $this->getUser(), $title );
		if ( $permissionErrors ) {
			throw new PermissionsError( 'edit', $permissionErrors );
		}

		$out->redirect( $title->getLocalURL( [ 'action' => 'edit' ] ) );
	}

	/**
	 * Displays a form that gives the user the information, that the page (with
	 * the currently tried title) already exists and that he should
	 * choose another title.
	 */
	private function showAlreadyExistsMessage( Title $title ) {
		$this->showTitleInputWithMessage(
			$this->msg( 'articleplaceholder-createpage-alreadyexists', $title->getText() )
		);
	}

	/**
	 * Displays a form that gives the user the information, that the page title, he wants to
	 * create, is invalid or none is given and that he should provide one.
	 *
	 * @param string $invalid
	 */
	private function showNoOrInvalidTitleGivenMessage( $invalid = 'missing' ) {
		$this->showTitleInputWithMessage(
			$this->msg( $invalid === 'invalid' ?
				'articleplaceholder-createpage-invalidtitleprovided' :
				'articleplaceholder-createpage-notitleprovided'
			)
		);
	}

	/**
	 * Displays a form that with the information that he should
	 * choose another title. The given message key is used as a
	 * reason why he need to do this.
	 */
	private function showTitleInputWithMessage( Message $msg ) {
		HTMLForm::factory(
			'ooui',
			[ 'titleinput' => [ 'type' => 'text' ] ],
			$this->getContext()
		)->setMethod( 'get' )
			->setWrapperLegendMsg( $msg )
			->setSubmitTextMsg( 'create' )
			->prepareForm()
			->displayForm( false );
	}

}
