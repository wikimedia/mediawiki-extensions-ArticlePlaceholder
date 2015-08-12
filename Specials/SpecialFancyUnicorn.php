<?php
/**
 * The FancyUnicorn SpecialPage for the ArticlePlaceholder extension
 *
 * @ingroup Extensions
 * @author Lucie-Aimée Kaffee
 * @license GNU General Public Licence 2.0 or later
 * 
 */
namespace ArticlePlaceholder\Specials;

use Html;
use Exception;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Entity\ItemId;
use SpecialPage;

class SpecialFancyUnicorn extends SpecialPage {

	public static function newFromGlobalState() {
		$wikibaseClient = WikibaseClient::getDefaultInstance();

		return new self(
				$wikibaseClient->getEntityIdParser()
		);
	}

	/**
	 * @var EntityIdParser
	 */
	private $idParser;

	/**
	 * Initialize the special page.
	 */
	public function __construct( EntityIdParser $idParser ) {
		$this->idParser = $idParser;
		parent::__construct( 'FancyUnicorn' );
	}

	/**
	 * @param string $sub
	 *
	 */
	public function execute( $sub ) {
		$out = $this->getOutput();

		$out->setPageTitle( $this->msg( 'articleplaceholder-fancyunicorn' ) );
		if ( $this->getItemIdParam( 'entityid', $sub ) != null ) {
			$out->addWikiText( 'Testing test test' );
		} else {
			//create the html elements
			$this->createForm();
		}
	}

	/**
	 *
	 * @todo add wikibase group?
	 */
	protected function getGroupName() {
		return 'other';
	}

	/**
	 * first function to add a form to enter an entity id and a submit button
	 */
	protected function createForm() {
		//add css style thing (in Wikibase OutputPage#addModuleStyles

		// Form header
		$this->getOutput()->addHTML(
				Html::openElement(
					'form', array(
					'method' => 'get',
					'action' => $this->getPageTitle()->getFullUrl(),
					'name' => 'ap-fancyunicorn',
					'id' => 'ap-fancyunicorn-form1',
					'class' => 'ap-form'
					)
				)
		);

		// Form elements
		$this->getOutput()->addHTML( $this->getFormElements() );

		// Form body
		$this->getOutput()->addHTML(
				Html::input(
						'submit', $this->msg( 'articleplaceholder-fancyunicorn-submit' )->text(), 'submit', array(
					'id' => 'submit'
					)
				)
				. Html::closeElement( 'fieldset' )
				. Html::closeElement( 'form' )
		);
	}

	/**
	 * Returns the form elements.
	 *
	 * @return string
	 * @todo exchange all those . Html::element( 'br' ) with something pretty
	 */
	protected function getFormElements() {
		return Html::rawElement(
					'p', array(),
					$this->msg( 'articleplaceholder-fancyunicorn-intro' )->parse()
				)
				. Html::element( 'br' )
				. Html::element(
					'label', array(
					'for' => 'ap-fancyunicorn-entityid',
					'class' => 'ap-label'
					), $this->msg( 'articleplaceholder-fancyunicorn-entityid' )->text()
				)
				. Html::element( 'br' )
				. Html::input(
					'entityid', $this->getRequest()->getVal( 'entityid' ), 'text', array(
					'class' => 'ap-input',
					'id' => 'ap-fancyunicorn-entityid'
						)
				)
				. Html::element( 'br' );
	}

	private function getTextParam( $name, $fallback ) {
		$value = $this->getRequest()->getText( $name, $fallback );
		return trim( $value );
	}

	/**
	 * @param string $name
	 * @param string $fallback
	 *
	 * @return ItemId|null
	 * @throws @todo UserInputException
	 */
	private function getItemIdParam( $name, $fallback ) {
		$rawId = $this->getTextParam( $name, $fallback );

		if ( $rawId === '' ) {
			return null;
		}

		try {
			$id = $this->idParser->parse( $rawId );
			if ( !( $id instanceof ItemId ) ) {
					throw new Exception();
			}

			return $id;
		} catch ( Exception $ex ) {
			// @todo proper Exception Handling
			$this->getOutput()->addWikiText( $ex->getMessage() );
		}
	}

}
