/**
 * @licence GNU GPL v2+
 * @author Florian Schmidt
 */

( function ( $, mw, OO, module ) {
	'use strict';

	/**
	 * @class
	 */
	function CreateArticleDialog( config ) {
		CreateArticleDialog.super.call( this, config ); // jshint:ignore
	}

	OO.inheritClass( CreateArticleDialog, OO.ui.ProcessDialog );

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

	// Customize the initialize() function: This is where to add content to the dialog body and set up event handlers.
	CreateArticleDialog.prototype.initialize = function () {
		CreateArticleDialog.parent.prototype.initialize.call( this ); // jshint:ignore
		this.$body.append( this.dialogContent );
	};

	CreateArticleDialog.prototype.setContent = function ( dialogContent ) {
		this.dialogContent = dialogContent;
	};

	CreateArticleDialog.prototype.getActionProcess = function ( action ) {
		var self = this;

		if ( action ) {
			return new OO.ui.Process( function () {
				return self.onSubmit();
			}, this );
		}

		return CreateArticleDialog.parent.prototype.getActionProcess.call( this, action );
	};

	CreateArticleDialog.prototype.onSubmit = function () {
		return true;
	};

	module.exports.CreateArticleDialog = CreateArticleDialog;

} )( jQuery, mediaWiki, OO, module );
