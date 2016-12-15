/**
 * @license GPL-2.0+
 * @author Jonas Kress
 */
( function ( $, QUnit, sinon, mw ) {
	'use strict';

	/*
	 * Constants
	 */
	var DEFAULT_TITLE = '[DEFAULT TITLE]',
		EXISTING_ARTICLE_TITLE = '[EXISTING TITLE]',
		NON_EXISTING_ARTICLE_TITLE = '[NON EXISTING TITLE]',
		API_EXISTING_RESPONSE = {
			query: {
				pages: [
					{}
				]
			}
		},
		API_NON_EXISTING_RESPONSE = {
			query: {
				pages: [
					{
						missing: true
					}
				]
			}
		},
		SERVER = '[SERVER]',
		ARTICLE_URL = '[ARTICLE_URL]';

	/*
	 * Stubs
	 */
	module.exports = {};

	mw.msg = sinon.stub().returnsArg( 1 );

	mw.config = {
		get: sinon.stub()
	};
	mw.config.get.withArgs( 'apLabel' ).returns( DEFAULT_TITLE );
	mw.config.get.withArgs( 'wgServer' ).returns( SERVER );

	mw.Api = sinon.stub().returns( {
		get: sinon.stub()
	} );
	mw.Api().get.withArgs( {
		formatversion: 2,
		action: 'query',
		titles: EXISTING_ARTICLE_TITLE
	} ).returns(  $.Deferred().resolve( API_EXISTING_RESPONSE ).promise() );
	mw.Api().get.withArgs( {
		formatversion: 2,
		action: 'query',
		titles: NON_EXISTING_ARTICLE_TITLE
	} ).returns(  $.Deferred().resolve( API_NON_EXISTING_RESPONSE ).promise() );

	mw.Title = {
		newFromUserInput: sinon.stub().returns( {
			getUrl: sinon.stub().returns( ARTICLE_URL )
		} )
	};

	/*
	 * Helper functions
	 */

	function createAndShowDialog() {
		var windowManager = new OO.ui.WindowManager(),
			dialog =  new module.exports.CreateArticleDialog();

		$( 'body' ).append( windowManager.$element );
		windowManager.addWindows( [ dialog ] );
		windowManager.openWindow( dialog );

		return dialog;
	}

	/*
	 * Tests
	 */
	$.getScript( '../../modules/ext.articleplaceholder.createArticle/ext.articleplaceholder.createArticle.js' );
	QUnit.module( 'createArticle' );

	QUnit.test( 'When calling the constructor', function ( assert ) {
		assert.expect( 1 );
		assert.ok( new module.exports.CreateArticleDialog()	instanceof
				module.exports.CreateArticleDialog, 'it should return a valid object' );
	} );

	QUnit.test( 'When opening dialog', function ( assert ) {
		var dialog = null;
		assert.expect( 1 );

		dialog = createAndShowDialog();
		assert.equal( dialog.titleInput.getValue(), DEFAULT_TITLE, 'input value should be default title' );
	} );

	QUnit.test( 'When submit creating existing article', function ( assert ) {
		var dialog = null;
		assert.expect( 1 );

		dialog = createAndShowDialog();
		dialog.forwardTo = sinon.spy();

		dialog.titleInput.setValue( EXISTING_ARTICLE_TITLE );
		dialog.onSubmit().fail( function () {
			assert.ok( true, 'it should throw an error' );
		} );

	} );

	QUnit.test( 'When submit creating non existing article', function ( assert ) {
		var dialog = null;
		assert.expect( 1 );

		dialog = createAndShowDialog();
		dialog.forwardTo = sinon.spy();

		dialog.titleInput.setValue( NON_EXISTING_ARTICLE_TITLE );
		dialog.onSubmit().done( function () {
			assert.equal( dialog.forwardTo.getCall( 0 ).args[ 0 ],
					SERVER + ARTICLE_URL, 'it should redirect to new create article URL' );
		} );
	} );

}( jQuery, QUnit, sinon, mediaWiki ) );
