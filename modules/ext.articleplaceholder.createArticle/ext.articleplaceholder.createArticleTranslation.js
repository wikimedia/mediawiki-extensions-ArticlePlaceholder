/**
 * @licence GNU GPL v2+
 * @author Florian Schmidt
 * @author Jonas M. Kress
 */

( function ( $, mw, OO ) {
	'use strict';

	var windowManager,
		dialog,
		languageInput,
		titleInput,
		CreateArticleDialog = mw.loader.require( 'ext.articleplaceholder.createArticle' ).CreateArticleDialog;

	function onSubmit() {
		if ( titleInput.getValue().trim() === '' ) {
			return $.Deferred().reject(
				new OO.ui.Error( mw.msg( 'articleplaceholder-abouttopic-create-article-mandatory' ) )
			).promise();
		}

		mw.loader.using( 'ext.cx.sitemapper' ).then( function () {
			document.location.href = mw.cx.SiteMapper.prototype.getCXUrl(
				mw.config.get( 'apPageNames' )[ languageInput.getValue() ],
				titleInput.getValue(),
				languageInput.getValue(),
				mw.config.get( 'wgContentLanguage' )
			);
		} );

		// To not leave the dialog in a stale, unusable state.
		dialog.close();
		return $.Deferred();
	}

	function createDialogContent() {
		titleInput = new OO.ui.TextInputWidget( {
			value: mw.config.get( 'apLabel' ),
			label: mw.msg( 'articleplaceholder-abouttopic-create-article-label' ),
			multiline: false,
			required: true,
			autosize: true
		} );

		languageInput = new OO.ui.DropdownInputWidget( {
			text: mw.msg( 'articleplaceholder-abouttopic-translate-article-label' ),
			options: mw.config.get( 'apLanguages' ),
			required: true
		} );

		return new OO.ui.StackLayout( {
			items: [
					new OO.ui.PanelLayout( {
						$content: languageInput.$element,
						padded: true
					} ), new OO.ui.PanelLayout( {
						$content: titleInput.$element,
						padded: true
					} )
			],
			continuous: true
		} );
	}

	function createDialog() {
		dialog = new CreateArticleDialog( {
			size: 'medium'
		} );

		dialog.setContent( createDialogContent().$element );
		dialog.onSubmit = onSubmit;

		windowManager = new OO.ui.WindowManager();

		$( 'body' ).append( windowManager.$element );
		windowManager.addWindows( [ dialog ] );
	}

	function onWikipageContent() {
		createDialog();

		OO.ui.infuse( 'translate-article-button' ).on( 'click', function () {
			mw.track( 'counter.MediaWiki.wikibase.articleplaceholder.button.translate-article' );
			windowManager.openWindow( dialog );
		} );
	}

	mw.hook( 'wikipage.content' ).add( onWikipageContent );

} )( jQuery, mediaWiki, OO );
