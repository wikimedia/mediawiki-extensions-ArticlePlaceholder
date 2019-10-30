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
		savedMwCx,
		CreateArticleTranslationDialog;

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
			sandbox.stub( mw, 'track' );

			// Stub lazy-loading of 'mw.cx.SiteMapper' module
			sandbox.stub( mw.loader, 'using' )
				.returns( $.Deferred().resolve() );

			sandbox.stub( mw.config, 'get' )
				.withArgs( 'apPageNames' ).returns( PAGE_NAMES )
				.withArgs( 'wgContentLanguage' ).returns( CONTENT_LANGUAGE );

			// This may be undefined, so we can't use Sinon to stub
			savedMwCx = mw.cx;
			mw.cx = {
				SiteMapper: function () {
					return {
						getCXUrl: function () { return ARTICLE_URL; }
					};
				}
			};
		},
		afterEach: function () {
			sandbox.restore();
			if ( savedMwCx ) {
				mw.cx = savedMwCx;
			} else {
				delete mw.cx;
			}
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

		return dialog.onSubmit().then( function () {
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

		return dialog.onSubmit().then( function () {
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

		stub = sandbox.stub().returns( $.Deferred().resolve() );
		dialog.__proto__.onSubmit = stub;

		return dialog.onSubmit().then( function () {
			assert.ok( stub.called, 'it should call parent method' );
		} );
	} );

}() );
