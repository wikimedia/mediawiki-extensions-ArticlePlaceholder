/**
 * @licence GNU GPL v2+
 *
 * @author Lucie-Aimée Kaffee
 */

( function ( $, mw, OO ) {

	var titleInput;

	function onSubmit() {
		var titleRaw = titleInput.getValue(),
			api = new mw.Api();

		api.get( { action: 'query', titles: titleRaw } ).done( function ( data ) {
			var query = data.query,
				pageKeys = Object.keys( query.pages ),
				title,
				link;

			if ( titleRaw !== titleInput.getValue() ) {
				return;
			}

			if ( pageKeys[ 0 ] === '-1' ) {
				title = mw.Title.newFromUserInput( titleRaw, 0 );
				link = '?title=' + encodeURIComponent( title.getNameText() ) + '&action=edit';
				link = mw.util.wikiScript() + link;
				document.location.href = link;
			} else {
				$( '#mw-article-placeholder-error' ).append(
					'<p>' + mw.message( 'articleplaceholder-abouttopic-article-exists-error' ).escaped() + '</p>'
				);
			}
		} );

		return false;
	}

	function onWikipageContent() {
		var dialog,
			windowManager,
			button,
			submitButton,
			dialogContent;

		titleInput = new OO.ui.TextInputWidget( {
			value: mw.config.get( 'apLabel' ),
			label: mw.msg( 'articleplaceholder-abouttopic-create-article-label' ),
			multiline: false,
			autosize: true
		} );

		dialogContent = titleInput.$element;
		dialogContent.append( '<div id="mw-article-placeholder-error"></div>' );

		titleInput.on( 'change', function () {
			$( '#mw-article-placeholder-error' ).empty();
		} );

		titleInput.on( 'enter', function () {
			submitButton.emit( 'click' );
		} );

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
			return this.content.$element.outerHeight( true );
		};

		CreateArticleDialog.prototype.getActionProcess = function ( action ) {
			if ( action ) {
				return new OO.ui.Process( function () {
					var saveDeferred = $.Deferred();
					onSubmit();
					return saveDeferred.promise();
				}, this );
			}
			return CreateArticleDialog.parent.prototype.getActionProcess.call( this, action );
		};

		dialog = new CreateArticleDialog( {
			size: 'medium'
		} );

		windowManager = new OO.ui.WindowManager();

		$( 'body' ).append( windowManager.$element );
		// Add the window to the window manager using the addWindows() method.
		windowManager.addWindows( [ dialog ] );

		button = OO.ui.infuse( 'create-article-button' );
		button.on( 'click', function () {
			windowManager.openWindow( dialog );
		} );
	}

	mw.hook( 'wikipage.content' ).add( onWikipageContent );

} )( jQuery, mediaWiki, OO );
