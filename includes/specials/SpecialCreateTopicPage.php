<?php

namespace ArticlePlaceholder\Specials;

use MediaWiki\MediaWikiServices;
use UnlistedSpecialPage;
use Title;
use PermissionsError;
use HTMLForm;
use Message;
use MWException;

/**
 * The CreateTopicPage SpecialPage for the ArticlePlaceholder extension
 *
 * @ingroup Extensions
 * @author Florian Schmidt
 * @license GNU General Public Licence 2.0 or later
 */
class SpecialCreateTopicPage extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct( 'CreateTopicPage' );
	}

	public function execute( $par ) {
		$out = $this->getOutput();
		$this->setHeaders();
		if ( $this->getRequest()->getVal( 'ref' ) === 'button' ) {
			$statsd = MediaWikiServices::getInstance()->getStatsdDataFactory();
			$statsd->increment( 'wikibase.articleplaceholder.button.createArticle' );
		}
		$page = $this->getRequest()->getVal( 'wptitleinput', $par );
		if ( $page === '' || $page === null ) {
			$this->showNoOrInvalidTitleGivenMessage( 'valid' );
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

		$permissionErrors = $title->getUserPermissionsErrors( 'edit', $this->getUser() );
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
	 * @param string $invalid Whether the title is valid or invalid
	 */
	private function showNoOrInvalidTitleGivenMessage( $invalid = 'valid' ) {
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
		$form = HTMLForm::factory(
			'ooui',
			[
				'titleinput' => [
					'type' => 'text',
				],
			],
			$this->getContext()
		);

		$form
			->setMethod( 'get' )
			->setWrapperLegendMsg(
				$msg
			)
			->setSubmitTextMsg( 'create' )
			->prepareForm()
			->displayForm( false );
	}

}
