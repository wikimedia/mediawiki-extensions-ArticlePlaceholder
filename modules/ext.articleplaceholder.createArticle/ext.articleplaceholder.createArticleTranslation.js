/**
 * @licence GNU GPL v2+
 * @author Florian Schmidt
 * @author Jonas M. Kress
 */

( function ( $, mw, OO, module ) {
	'use strict';

	var CreateArticleDialog = module.exports.CreateArticleDialog;

	/**
	 * @class
	 */
	function CreateArticleTranslationDialog() {
		CreateArticleTranslationDialog.super.call( this ); // jshint:ignore
	}
	OO.inheritClass( CreateArticleTranslationDialog, CreateArticleDialog );

	/**
	 * @property {OO.ui.DropdownInputWidget}
	 * @protected
	 */
	CreateArticleTranslationDialog.prototype.languageInput = null;

	/**
	 * @property {OO.ui.CheckboxInputWidget}
	 * @protected
	 */
	CreateArticleTranslationDialog.prototype.translateCheckbox = null;

	/**
	 * @override
	 * @return {jQuery.Promise}
	 */
	CreateArticleTranslationDialog.prototype.onSubmit = function () {
		var self = this,
			deferred = $.Deferred();

		if ( !this.translateCheckbox.isSelected() ) {
			return CreateArticleTranslationDialog.super.prototype.onSubmit.apply( this );
		}

		mw.track( 'counter.MediaWiki.wikibase.articleplaceholder.button.translate-article' );

		mw.loader.using( 'ext.cx.sitemapper' ).then( function () {
			document.location.href = mw.cx.SiteMapper.prototype.getCXUrl(
				mw.config.get( 'apPageNames' )[ self.languageInput.getValue() ],
				self.titleInput.getValue(),
				self.languageInput.getValue(),
				mw.config.get( 'wgContentLanguage' )
			);

			deferred.resolve();
		} );

		return deferred.promise();
	};

	/**
	 * @protected
	 * @return {OO.ui.DropdownInputWidget}
	 */
	CreateArticleTranslationDialog.prototype.createLanguageInput = function () {
		this.languageInput = new OO.ui.DropdownInputWidget( {
			text: mw.msg( 'articleplaceholder-abouttopic-translate-article-label' ),
			options: mw.config.get( 'apLanguages' ),
			disabled: true
		} );

		return this.languageInput;
	};

	/**
	 * @protected
	 * @return {OO.ui.CheckboxInputWidget}
	 */
	CreateArticleTranslationDialog.prototype.createTranslateCheckbox = function () {
		var self = this;

		this.translateCheckbox = new OO.ui.CheckboxInputWidget()
		.on( 'change', function ( selected ) {
			self.languageInput.setDisabled( !selected );
		} );

		return this.translateCheckbox;
	};

	/**
	 * @protected
	 * @return {jQuery}
	 */
	CreateArticleTranslationDialog.prototype.createTranslateSection = function () {
		var msg = mw.msg( 'articleplaceholder-abouttopic-translate-article-button' );

		return $( '<div>' ).append(
			new OO.ui.FieldLayout(
				this.createTranslateCheckbox(),
				{ label: msg, align: 'inline' }
			).$element,
			this.createLanguageInput().$element
		);
	};

	/**
	 * @protected
	 */
	CreateArticleTranslationDialog.prototype.createContentElements = function () {
		this.addElement( this.createTranslateSection() );
		this.addElement( this.createTitleInput().$element );
	};

	module.exports.CreateArticleTranslationDialog = CreateArticleTranslationDialog;

} )( jQuery, mediaWiki, OO, module );
