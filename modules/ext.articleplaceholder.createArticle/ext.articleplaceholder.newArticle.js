/**
 * @license GPL-2.0-or-later
 * @author Lucie-Aimée Kaffee
 * @author Florian Schmidt
 */
( function ( $, mw, OO, module ) {
	'use strict';

	function init() {
		var CreateArticleDialog = mw.config.get( 'apContentTranslation' ) ?
				module.exports.CreateArticleTranslationDialog :
				module.exports.CreateArticleDialog,
			windowManager = new OO.ui.WindowManager(),
			dialog = new CreateArticleDialog();

		$( 'body' ).append( windowManager.$element );
		windowManager.addWindows( [ dialog ] );

		OO.ui.infuse( $( '#new-article-button' ) ).on( 'click', function () {
			mw.track( 'counter.MediaWiki.wikibase.articleplaceholder.button.createArticle' );
			windowManager.openWindow( dialog );
		} );
	}

	// Don't init while testing. This file is not supposed to run.
	// The tests instead invoke individual methods directly.
	if ( typeof QUnit === 'undefined' ) {
		$( init );
	}

}( jQuery, mediaWiki, OO, module ) );
