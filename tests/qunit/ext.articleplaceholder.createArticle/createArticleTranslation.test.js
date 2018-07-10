/**
 * @license GPL-2.0-or-later
 * @author Jonas Kress
 */
( function () {
	'use strict';
	/*
	 * Constants
	 */
	var ARTICLE_URL = '[ARTICLE_URL]',
		PAGE_NAMES = '[PAGE_NAMES]',
		CONTENT_LANGUAGE = '[CONTENT_LANGUAGE]',
		sandbox,
		setupStubs,
		CreateArticleTranslationDialog;

	/*
	 * Stubs
	 */
	setupStubs = function () {
		sandbox.stub( mw, 'track' );

		sandbox.stub( mw.loader, 'using' )
			.returns( $.Deferred().resolve().promise() );

		sandbox.stub( mw.config, 'get' )
			.withArgs( 'apPageNames' ).returns( PAGE_NAMES )
			.withArgs( 'wgContentLanguage' ).returns( CONTENT_LANGUAGE );

		mw.cx = sandbox.stub().returns( {
			using: sandbox.stub()
		} );

		mw.cx = {
			SiteMapper: sandbox.stub().returns( {
				getCXUrl: sandbox.stub().returns( ARTICLE_URL )
			} )
		};
	};

	/*
	 * Helper functions
	 */
	CreateArticleTranslationDialog = require( 'ext.articleplaceholder.createArticle' ).CreateArticleTranslationDialog;

	function createAndShowDialog() {
		var windowManager = new OO.ui.WindowManager(),
			dialog = new CreateArticleTranslationDialog();

		$( '#qunit-fixture' ).append( windowManager.$element );
		windowManager.addWindows( [
			dialog
		] );
		windowManager.openWindow( dialog );

		return dialog;
	}

	/*
	 * Tests
	 */
	QUnit.module( 'ext.ArticlePlaceHolder.createArticleTranslation', {
		beforeEach: function () {
			sandbox = sinon.sandbox.create();
			setupStubs();
		},
		afterEach: function () {
			sandbox.restore();
		}
	} );

	QUnit.test( 'When calling the constructor', function ( assert ) {
		assert.expect( 1 );
		assert.ok( new CreateArticleTranslationDialog() instanceof CreateArticleTranslationDialog, 'it should return a valid object' );
	} );

	QUnit.test( 'When submit translate article', function ( assert ) {
		var dialog = null;

		assert.expect( 1 );

		dialog = createAndShowDialog();
		dialog.forwardTo = sandbox.spy();

		return dialog.onSubmit().done( function () {
			assert.equal( dialog.forwardTo.getCall( 0 ).args[ 0 ],
				ARTICLE_URL, 'it should redirect to translate article URL' );
		} );
	} );

	QUnit.test( 'When submit and translate selected translate article', function ( assert ) {
		var dialog = null;

		assert.expect( 1 );

		dialog = createAndShowDialog();
		dialog.forwardTo = sandbox.spy();
		dialog.translateOption.setSelected( true );

		return dialog.onSubmit().done( function () {
			assert.equal( dialog.forwardTo.getCall( 0 ).args[ 0 ],
				ARTICLE_URL, 'it should redirect to translate article URL' );
		} );
	} );

	QUnit.test( 'When submit and translate is not selected create article', function ( assert ) {
		var dialog = null,
			stub = null;
		assert.expect( 1 );

		dialog = createAndShowDialog();
		dialog.forwardTo = sandbox.spy();
		dialog.translateOption.setSelected( false );

		stub = sandbox.stub().returns( $.Deferred().resolve().promise() );
		dialog.__proto__.onSubmit = stub;

		return dialog.onSubmit().done( function () {
			assert.ok( stub.called, 'it should call parent method' );
		} );
	} );

}() );
