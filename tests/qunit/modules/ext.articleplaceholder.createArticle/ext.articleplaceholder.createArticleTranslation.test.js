/**
 * @license GPL-2.0+
 * @author Jonas Kress
 */
( function ( $, QUnit, sinon, mw ) {
	'use strict';

	/*
	 * Constants
	 */
	var ARTICLE_URL = '[ARTICLE_URL]',
		PAGE_NAMES = '[PAGE_NAMES]',
		CONTENT_LANGUAGE = '[CONTENT_LANGUAGE]';

	/*
	 * Stubs
	 */
	module.exports = {};

	mw.track = sinon.stub();

	mw.loader = {};
	mw.loader.using = sinon.stub().returns( $.Deferred().resolve().promise() );

	mw.config.get.withArgs( 'apPageNames' ).returns( PAGE_NAMES );
	mw.config.get.withArgs( 'wgContentLanguage' ).returns( CONTENT_LANGUAGE );

	mw.cx = sinon.stub().returns( {
		using: sinon.stub()
	} );

	mw.cx = {
		SiteMapper: sinon.stub().returns( {
			getCXUrl: sinon.stub().returns( ARTICLE_URL )
		} )
	};

	/*
	 * Helper functions
	 */

	function createAndShowDialog() {
		var windowManager = new OO.ui.WindowManager(),
			dialog = new module.exports.CreateArticleTranslationDialog();

		$( 'body' ).append( windowManager.$element );
		windowManager.addWindows( [
			dialog
		] );
		windowManager.openWindow( dialog );

		return dialog;
	}

	/*
	 * Tests
	 */
	$.getScript( '../../modules/ext.articleplaceholder.createArticle/ext.articleplaceholder.createArticle.js' );
	$.getScript( '../../modules/ext.articleplaceholder.createArticle/ext.articleplaceholder.createArticleTranslation.js' );

	QUnit.module( 'createArticleTranslation' );

	QUnit.test( 'When calling the constructor', function ( assert ) {
		assert.expect( 1 );
		assert.ok( new module.exports.CreateArticleDialog() instanceof module.exports.CreateArticleDialog, 'it should return a valid object' );
	} );

	QUnit.test( 'When submit translate article', function ( assert ) {
		var dialog = null;
		assert.expect( 1 );

		dialog = createAndShowDialog();
		dialog.forwardTo = sinon.spy();

		return dialog.onSubmit().done( function () {
			assert.equal( dialog.forwardTo.getCall( 0 ).args[ 0 ],
					ARTICLE_URL, 'it should redirect to translate article URL' );
		} );

	} );

	QUnit.test( 'When submit and translate selected translate article', function ( assert ) {
		var dialog = null;
		assert.expect( 1 );

		dialog = createAndShowDialog();
		dialog.forwardTo = sinon.spy();
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
		dialog.forwardTo = sinon.spy();
		dialog.translateOption.setSelected( false );

		stub = sinon.stub().returns( $.Deferred().resolve().promise() );
		dialog.__proto__.onSubmit =  stub;// jshint ignore:line

		return dialog.onSubmit().done( function () {
			assert.ok( stub.called, 'it should call parent method' );
		} );

	} );

}( jQuery, QUnit, sinon, mediaWiki ) );
