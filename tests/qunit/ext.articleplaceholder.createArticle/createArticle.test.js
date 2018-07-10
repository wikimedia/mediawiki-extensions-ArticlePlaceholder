/**
 * @license GPL-2.0-or-later
 * @author Jonas Kress
 */
( function () {
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
		ARTICLE_URL = '[ARTICLE_URL]',
		sandbox,
		setupStubs,
		CreateArticleDialog;

	/*
	 * Stubs
	 */
	setupStubs = function () {
		sandbox.stub( mw, 'msg' ).returnsArg( 1 );

		sandbox.stub( mw.config, 'get' );
		mw.config.get.withArgs( 'apLabel' ).returns( DEFAULT_TITLE );
		mw.config.get.withArgs( 'wgServer' ).returns( SERVER );

		sandbox.stub( mw, 'Api' ).returns( {
			get: sandbox.stub()
		} );

		mw.Api().get.withArgs( {
			formatversion: 2,
			action: 'query',
			titles: EXISTING_ARTICLE_TITLE
		} ).returns( $.Deferred().resolve( API_EXISTING_RESPONSE ).promise() );
		mw.Api().get.withArgs( {
			formatversion: 2,
			action: 'query',
			titles: NON_EXISTING_ARTICLE_TITLE
		} ).returns( $.Deferred().resolve( API_NON_EXISTING_RESPONSE ).promise() );

		sandbox.stub( mw.Title, 'newFromUserInput' ).returns( {
			getUrl: sandbox.stub().returns( ARTICLE_URL )
		} );
	};

	/*
	 * Helper functions
	 */

	CreateArticleDialog = require( 'ext.articleplaceholder.createArticle' ).CreateArticleDialog;

	function createAndShowDialog() {
		var windowManager = new OO.ui.WindowManager(),
			dialog = new CreateArticleDialog();

		$( '#qunit-fixture' ).append( windowManager.$element );
		windowManager.addWindows( [ dialog ] );
		windowManager.openWindow( dialog );

		return dialog;
	}

	/*
	 * Tests
	 */
	QUnit.module( 'ext.ArticlePlaceHolder.createArticle', {
		beforeEach: function () {
			sandbox = sinon.sandbox.create();
			setupStubs();
		},
		afterEach: function () {
			sandbox.restore();
		}
	} );

	QUnit.test( 'When calling the constructor', function ( assert ) {
		assert.ok( new CreateArticleDialog() instanceof
				CreateArticleDialog, 'it should return a valid object' );
	} );

	QUnit.test( 'When opening dialog', function ( assert ) {
		var dialog = createAndShowDialog();
		assert.equal( dialog.titleInput.getValue(), DEFAULT_TITLE, 'input value should be default title' );
	} );

	QUnit.test( 'When submit creating existing article', function ( assert ) {
		var done = assert.async(),
			dialog = createAndShowDialog();
		dialog.forwardTo = sandbox.spy();

		dialog.titleInput.setValue( EXISTING_ARTICLE_TITLE );
		// assert.rejects( dialog.onSubmit(), 'it should throw an error' );
		dialog.onSubmit().fail( function () {
			assert.ok( true, 'it should throw an error' );
			done();
		} );
	} );

	QUnit.test( 'When submit creating non existing article', function ( assert ) {
		var done = assert.async(),
			dialog = createAndShowDialog();
		dialog.forwardTo = sandbox.spy();

		dialog.titleInput.setValue( NON_EXISTING_ARTICLE_TITLE );
		dialog.onSubmit().done( function () {
			assert.equal( dialog.forwardTo.getCall( 0 ).args[ 0 ],
				SERVER + ARTICLE_URL, 'it should redirect to new create article URL' );
			done();
		} );
	} );

}() );
