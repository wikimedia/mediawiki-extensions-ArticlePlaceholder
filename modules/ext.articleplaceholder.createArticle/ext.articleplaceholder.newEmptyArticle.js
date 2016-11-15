/**
 * @licence GNU GPL v2+
 * @author Lucie-Aimée Kaffee
 * @author Florian Schmidt
 */

( function ( $, mw, OO, module ) {
	'use strict';

	var titleInput,
		CreateArticleDialog = module.exports.CreateArticleDialog;

	/**
	 * @return {jQuery.Promise}
	 */
	function onSubmit() {
		var titleRaw = titleInput.getValue(),
			api = new mw.Api(),
			deferred = $.Deferred();

		if ( titleRaw.trim() === '' ) {
			return deferred.reject( new OO.ui.Error(
				mw.msg( 'articleplaceholder-abouttopic-create-article-mandatory' )
			) ).promise();
		}

		api.get( {
			formatversion: 2,
			action: 'query',
			titles: titleRaw
		} ).done( function ( data ) {
			var query = data.query,
				title;

			if ( titleRaw !== titleInput.getValue() || !query || !query.pages ) {
				deferred.reject();
				return;
			}

			if ( query.pages[ 0 ].missing ) {
				title = mw.Title.newFromUserInput( titleRaw, 0 );
				document.location.href = mw.config.get( 'wgServer' ) + title.getUrl( { action: 'edit' } );
				deferred.resolve();
			} else {
				deferred.reject( new OO.ui.Error(
					mw.msg( 'articleplaceholder-abouttopic-article-exists-error' )
				) );
			}
		} );

		return deferred.promise();
	}

	function onWikipageContent() {
		var dialog,
			windowManager,
			button,
			dialogContent;

		titleInput = new OO.ui.TextInputWidget( {
			value: mw.config.get( 'apLabel' ),
			label: mw.msg( 'articleplaceholder-abouttopic-create-article-label' ),
			multiline: false,
			required: true,
			autosize: true
		} );

		dialogContent = new OO.ui.PanelLayout( { $: $, padded: true, expanded: false } );
		dialogContent.$element.append( titleInput.$element );

		titleInput.on( 'change', function () {
			$( '#mw-article-placeholder-error' ).empty();
		} );

		dialog = new CreateArticleDialog( {
			size: 'medium'
		} );
		dialog.setContent( dialogContent.$element );
		dialog.onSubmit = onSubmit;

		titleInput.on( 'enter', function () {
			dialog.executeAction( 'save' );
		} );

		windowManager = new OO.ui.WindowManager();

		$( 'body' ).append( windowManager.$element );
		// Add the window to the window manager using the addWindows() method.
		windowManager.addWindows( [ dialog ] );

		button = OO.ui.infuse( 'new-empty-article-button' );
		button.on( 'click', function () {
			mw.track( 'counter.MediaWiki.wikibase.articleplaceholder.button.create-article' );
			windowManager.openWindow( dialog );
		} );
	}

	mw.hook( 'wikipage.content' ).add( onWikipageContent );

} )( jQuery, mediaWiki, OO, module );
