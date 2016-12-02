/**
 * @licence GNU GPL v2+
 * @author Florian Schmidt
 * @author Jonas M. Kress
 */

( function ( $, mw, OO, module ) {
	'use strict';

	/**
	 * @class
	 */
	function CreateArticleDialog() {
		CreateArticleDialog.super.call( this, {
			size: 'medium'
		} );
		this.createContentLayout();
	}

	OO.inheritClass( CreateArticleDialog, OO.ui.ProcessDialog );

	/**
	 * @property {string}
	 * @protected
	 */
	CreateArticleDialog.static.title =
		mw.msg( 'articleplaceholder-abouttopic-create-article-title' );

	CreateArticleDialog.static.actions = [
		{
			action: 'save',
			label: mw.msg( 'articleplaceholder-abouttopic-create-article-submit-button' ),
			flags: [ 'primary', 'progressive' ]
		},
		{
			label: mw.msg( 'cancel' ),
			flags: 'safe'
		}
	];

	/**
	 * @property {OO.ui.Layout}
	 * @protected
	 */
	CreateArticleDialog.prototype.dialogContentLayout = null;

	/**
	 * @property {OO.ui.TextInputWidget}
	 * @protected
	 */
	CreateArticleDialog.prototype.titleInput = null;

	/**
	 * @override
	 */
	CreateArticleDialog.prototype.initialize = function () {
		CreateArticleDialog.parent.prototype.initialize.call( this ); // jshint:ignore
		this.createContentElements();
		this.$body.append( this.dialogContent );
	};

	/**
	 * @override
	 */
	CreateArticleDialog.prototype.getActionProcess = function ( action ) {
		var self = this;

		if ( action ) {
			return new OO.ui.Process( function () {
				return self.process();
			}, this );
		}

		return CreateArticleDialog.parent.prototype.getActionProcess.call( this, action );
	};

	/**
	 * @private
	 */
	CreateArticleDialog.prototype.process = function () {
		var self = this,
			deferred = $.Deferred();

		$.when(
			this.onValidate(),
			this.onSubmit()
		).done( function () {
			self.close();
			deferred.resolve();
		} ).fail( function ( message ) {
			deferred.reject( new OO.ui.Error( message ) );
		} );

		return deferred.promise();
	};

	/**
	 * @protected
	 */
	CreateArticleDialog.prototype.createContentElements = function () {
		this.addElement( this.createTitleInput().$element );
	};

	/**
	 * @protected
	 */
	CreateArticleDialog.prototype.addElement = function ( $element, index ) {
		var item = new OO.ui.PanelLayout( {
			$content: $element,
			padded: true
		} );

		this.dialogContentLayout.addItems( [ item ], index );
	};

	/**
	 * @protected
	 */
	CreateArticleDialog.prototype.createContentLayout = function () {
		this.dialogContentLayout = new OO.ui.StackLayout( {
			continuous: true
		} );

		this.dialogContent = this.dialogContentLayout.$element;
	};

	/**
	 * @protected
	 * @return {OO.ui.TextInputWidget}
	 */
	CreateArticleDialog.prototype.createTitleInput = function () {
		var self = this;

		this.titleInput = new OO.ui.TextInputWidget( {
			value: mw.config.get( 'apLabel' ),
			label: mw.msg( 'articleplaceholder-abouttopic-create-article-label' ),
			multiline: false,
			required: true,
			autosize: true
		} );

		this.titleInput.on( 'enter', function () {
			self.executeAction( 'save' );
		} );

		return this.titleInput;
	};

	/**
	 * @protected
	 * @return {jQuery.Promise}
	 */
	CreateArticleDialog.prototype.onValidate = function () {
		if ( this.titleInput.getValue().trim() === '' ) {
			return $.Deferred().reject(
				mw.msg( 'articleplaceholder-abouttopic-create-article-mandatory' )
			).promise();
		}

		return $.Deferred().resolve().promise();
	};

	/**
	 * @protected
	 * @return {jQuery.Promise}
	 */
	CreateArticleDialog.prototype.onSubmit = function () {
		var self = this,
			title = this.titleInput.getValue(),
			deferred = $.Deferred(),
			url = null;

		new mw.Api().get( {
			formatversion: 2,
			action: 'query',
			titles: title
		} ).done( function ( data ) {
			var query = data.query;

			if ( !query || !query.pages ) {
				deferred.reject();
				return;
			}

			if ( query.pages[ 0 ].missing ) {
				title = mw.Title.newFromUserInput( title, 0 );
				url = mw.config.get( 'wgServer' ) + title.getUrl( {
					action: 'edit'
				} );
				self.forwardTo( url );
				deferred.resolve();
			} else {
				deferred.reject( mw.msg( 'articleplaceholder-abouttopic-article-exists-error' ) );
			}
		} );

		return deferred;
	};

	/**
	 * @protected
	 */
	CreateArticleDialog.prototype.forwardTo = function ( url ) {
		document.location.href = url;
	};

	module.exports.CreateArticleDialog = CreateArticleDialog;

} )( jQuery, mediaWiki, OO, module );
