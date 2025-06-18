/**
 * @license GPL-2.0-or-later
 * @author Jonas Kress
 */
QUnit.module( 'ext.ArticlePlaceHolder.createArticleTranslation', ( hooks ) => {
	'use strict';

	const { CreateArticleTranslationDialog } = require( 'ext.articleplaceholder.createArticle' );

	var ARTICLE_URL = '[ARTICLE_URL]',
		PAGE_NAMES = '[PAGE_NAMES]',
		CONTENT_LANGUAGE = '[CONTENT_LANGUAGE]',
		savedMwCx,
		windowManager;

	function createAndShowDialog() {
		windowManager = new OO.ui.WindowManager();
		var dialog = new CreateArticleTranslationDialog();

		$( '#qunit-fixture' ).append( windowManager.$element );
		windowManager.addWindows( [
			dialog
		] );
		windowManager.openWindow( dialog );

		return dialog;
	}

	hooks.beforeEach( function () {
		this.sandbox.stub( mw, 'track' );

		// Stub lazy-loading of 'mw.cx.SiteMapper' module
		this.sandbox.stub( mw.loader, 'using' )
			.returns( Promise.resolve() );

		this.sandbox.stub( mw.config, 'get' )
			.withArgs( 'apPageNames' ).returns( PAGE_NAMES )
			.withArgs( 'wgContentLanguage' ).returns( CONTENT_LANGUAGE );

		// This may be undefined, so we can't use Sinon to stub
		savedMwCx = mw.cx;
		mw.cx = {
			SiteMapper: function () {
				return {
					getCXUrl: function () {
						return ARTICLE_URL;
					}
				};
			}
		};
	} );
	hooks.afterEach( function () {
		if ( savedMwCx ) {
			mw.cx = savedMwCx;
		} else {
			delete mw.cx;
		}
		if ( windowManager ) {
			windowManager.destroy();
			windowManager = undefined;
		}
	} );

	QUnit.test( 'When calling the constructor', function ( assert ) {
		assert.true( new CreateArticleTranslationDialog() instanceof CreateArticleTranslationDialog, 'it should return a valid object' );
	} );

	QUnit.test( 'When submit translate article', function ( assert ) {
		var dialog = createAndShowDialog();
		dialog.forwardTo = this.sandbox.spy();

		return dialog.onSubmit().then( function () {
			assert.equal( dialog.forwardTo.getCall( 0 ).args[ 0 ],
				ARTICLE_URL, 'it should redirect to translate article URL' );
		} );
	} );

	QUnit.test( 'When submit and translate selected translate article', function ( assert ) {
		var dialog = createAndShowDialog();
		dialog.forwardTo = this.sandbox.spy();
		dialog.translateOption.setSelected( true );

		return dialog.onSubmit().then( function () {
			assert.equal( dialog.forwardTo.getCall( 0 ).args[ 0 ],
				ARTICLE_URL, 'it should redirect to translate article URL' );
		} );
	} );

	QUnit.test( 'When submit and translate is not selected create article', function ( assert ) {
		var dialog = createAndShowDialog();
		dialog.forwardTo = this.sandbox.spy();
		dialog.translateOption.setSelected( false );

		var stub = this.sandbox.stub().returns( Promise.resolve() );
		dialog.__proto__.onSubmit = stub;

		return dialog.onSubmit().then( function () {
			assert.ok( stub.called, 'it should call parent method' );
		} );
	} );

} );
