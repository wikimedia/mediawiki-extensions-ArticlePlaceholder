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
	 * @property {OO.ui.RadioOptionWidget}
	 * @protected
	 */
	CreateArticleTranslationDialog.prototype.translateOption = null;

	/**
	 * @property {OO.ui.RadioOptionWidget}
	 * @protected
	 */
	CreateArticleTranslationDialog.prototype.emptyOption = null;

	/**
	 * @override
	 * @return {jQuery.Promise}
	 */
	CreateArticleTranslationDialog.prototype.onSubmit = function () {
		var self = this,
			deferred = $.Deferred();

		if ( !this.translateOption.isSelected() ) {
			return CreateArticleTranslationDialog.super.prototype.onSubmit.apply( this );
		}

		mw.track( 'counter.MediaWiki.wikibase.articleplaceholder.button.translateArticle' );

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
		var self = this;

		this.languageInput = new OO.ui.DropdownInputWidget( {
			text: mw.msg( 'articleplaceholder-abouttopic-translate-article-label' ),
			options: mw.config.get( 'apLanguages' )
		} );

		this.languageInput.$element.find( '*' ).on( 'click', function () {
			self.toggleTranslateArticle( true );
		} );

		return this.languageInput;
	};

	/**
	 * @protected
	 */
	CreateArticleTranslationDialog.prototype.toggleTranslateArticle = function ( translate ) {
		this.emptyOption.setSelected( !translate );
		this.translateOption.setSelected( translate );
	};

	/**
	 * @protected
	 */
	CreateArticleTranslationDialog.prototype.createRadioOptions = function () {
		var self = this;

		this.translateOption = new OO.ui.RadioOptionWidget( {
			label: mw.msg( 'articleplaceholder-abouttopic-translate-article-button' )
		} );
		self.translateOption.setSelected( true );

		this.emptyOption = new OO.ui.RadioOptionWidget( {
			label: mw.msg( 'articleplaceholder-abouttopic-create-emtpy-article-button' )
		} );

		this.translateOption.$element.click( function () {
			self.toggleTranslateArticle( true );
		} );

		this.emptyOption.$element.click( function () {
			self.toggleTranslateArticle( false );
		} );
	};

	/**
	 * @protected
	 * @return {jQuery}
	 */
	CreateArticleTranslationDialog.prototype.createRadioSelect = function () {
		this.createRadioOptions();
		this.createLanguageInput();

		return new OO.ui.StackLayout( {
			items: [
					this.translateOption,
					this.languageInput,
					this.emptyOption
			],
			continuous: true,
			scrollable: false,
			classes: [
				'create-options'
			]
		} );
	};

	/**
	 * @protected
	 */
	CreateArticleTranslationDialog.prototype.createContentElements = function () {
		this.addElement( this.createTitleInput().$element );
		this.addElement( this.createRadioSelect().$element );
	};

	module.exports.CreateArticleTranslationDialog = CreateArticleTranslationDialog;

} )( jQuery, mediaWiki, OO, module );
