/**
 * @licence GNU GPL v2+
 *
 * @author Lucie-Aimée Kaffee
 * @author Florian Schmidt
 */

( function ( $, mw, OO, module ) {
	'use strict';

	function onWikipageContent() {
		var CreateArticleDialog = mw.config.get( 'apContentTranslation' ) ?
					module.exports.CreateArticleTranslationDialog :
					module.exports.CreateArticleDialog,
			windowManager = new OO.ui.WindowManager(),
			dialog = new CreateArticleDialog();

		$( 'body' ).append( windowManager.$element );
		windowManager.addWindows( [ dialog ] );

		OO.ui.infuse( 'new-article-button' ).on( 'click', function () {
			mw.track( 'counter.MediaWiki.wikibase.articleplaceholder.button.createArticle' );
			windowManager.openWindow( dialog );
		} );
	}

	mw.hook( 'wikipage.content' ).add( onWikipageContent );

} )( jQuery, mediaWiki, OO, module );
