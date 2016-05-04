/**
 * @licence GNU GPL v2+
 *
 * @author Lucie-Aimée Kaffee
 */

( function ( $, mw, OO ) {

	var titleInput;

	function onSubmit( deferred ) {
		var titleRaw = titleInput.getValue(),
			api = new mw.Api();

		if ( titleRaw.trim() === '' ) {
			deferred.reject( new OO.ui.Error(
				mw.msg( 'articleplaceholder-abouttopic-create-article-mandatory' )
			) );
		} else {
			api.get( {
				formatversion: 2,
				action: 'query',
				titles: titleRaw
			} ).done( function ( data ) {
				var query = data.query,
					title;

				if ( query && query.hasOwnProperty( 'pages' ) ) {
					if ( titleRaw !== titleInput.getValue() ) {
						return;
					}

					if ( query.pages[ 0 ].missing ) {
						title = mw.Title.newFromUserInput( titleRaw, 0 );
						document.location.href = title.getUrl( { action: 'edit' } );
					} else {
						deferred.reject( new OO.ui.Error(
							mw.msg( 'articleplaceholder-abouttopic-article-exists-error' )
						) );
					}
				}
			} );
		}

		return false;
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

		dialogContent = titleInput.$element;

		function CreateArticleDialog( config ) {
			CreateArticleDialog.super.call( this, config ); // jshint:ignore
		}
		OO.inheritClass( CreateArticleDialog, OO.ui.ProcessDialog );

		CreateArticleDialog.static.title = mw.message( 'articleplaceholder-abouttopic-create-article' ).text();
		CreateArticleDialog.static.actions = [
			{
				action: 'save',
				label: mw.message( 'articleplaceholder-abouttopic-create-article-submit-button' ).text(),
				flags: [ 'primary', 'progressive' ]
			},
			{ label: mw.message( 'cancel' ).text(), flags: 'safe' }
		];

		// Customize the initialize() function: This is where to add content to the dialog body and set up event handlers.
		CreateArticleDialog.prototype.initialize = function () {
			CreateArticleDialog.super.prototype.initialize.call( this ); // jshint:ignore
			this.content = new OO.ui.PanelLayout( { $: this.$, padded: true, expanded: false } );
			this.content.$element.append( dialogContent );
			this.$body.append( this.content.$element );
		};

		CreateArticleDialog.prototype.getBodyHeight = function () {
			return this.content.$element.outerHeight( true ) * 2;
		};

		CreateArticleDialog.prototype.getActionProcess = function ( action ) {
			if ( action ) {
				return new OO.ui.Process( function () {
					var saveDeferred = $.Deferred();
					onSubmit( saveDeferred );

					return saveDeferred.promise();
				}, this );
			}
			return CreateArticleDialog.parent.prototype.getActionProcess.call( this, action );
		};

		dialog = new CreateArticleDialog( {
			size: 'medium'
		} );

		titleInput.on( 'enter', function () {
			dialog.executeAction( 'save' );
		} );

		windowManager = new OO.ui.WindowManager();

		$( 'body' ).append( windowManager.$element );
		// Add the window to the window manager using the addWindows() method.
		windowManager.addWindows( [ dialog ] );

		button = OO.ui.infuse( 'create-article-button' );
		button.on( 'click', function () {
			mw.track( 'counter.MediaWiki.wikibase.articleplaceholder.button.create-article' );
			windowManager.openWindow( dialog );
		} );
	}

	mw.hook( 'wikipage.content' ).add( onWikipageContent );

} )( jQuery, mediaWiki, OO );
