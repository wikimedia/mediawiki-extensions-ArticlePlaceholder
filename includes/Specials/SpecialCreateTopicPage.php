<?php

namespace ArticlePlaceholder\Specials;

use HTMLForm;
use MediaWiki\MediaWikiServices;
use Message;
use MWException;
use PermissionsError;
use Title;
use UnlistedSpecialPage;

/**
 * The CreateTopicPage SpecialPage for the ArticlePlaceholder extension
 *
 * @ingroup Extensions
 * @license GPL-2.0-or-later
 * @author Florian Schmidt
 */
class SpecialCreateTopicPage extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct( 'CreateTopicPage' );
	}

	/**
	 * @param string|null $par
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$this->setHeaders();
		if ( $this->getRequest()->getVal( 'ref' ) === 'button' ) {
			$statsd = MediaWikiServices::getInstance()->getStatsdDataFactory();
			$statsd->increment( 'wikibase.articleplaceholder.button.createArticle' );
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
		$out->setPageTitle( $this->msg( 'articleplaceholder-createpage-title', $title->getText() ) );
		if ( $title->exists() ) {
			$this->showAlreadyExistsMessage( $title );
			return;
		}

		$permissionErrors = MediaWikiServices::getInstance()->getPermissionManager()
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
	 *
	 * @param Title $title
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
	 *
	 * @param Message $msg
	 *
	 * @throws MWException
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
