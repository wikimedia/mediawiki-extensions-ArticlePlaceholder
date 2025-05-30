/**
 * @license GPL-2.0-or-later
 * @author Florian Schmidt
 * @author Jonas M. Kress
 */

( function () {
	'use strict';

	var CreateArticleDialog = module.exports.CreateArticleDialog;

	/**
	 * @class
	 */
	function CreateArticleTranslationDialog() {
		CreateArticleTranslationDialog.super.call( this );
	}
	OO.inheritClass( CreateArticleTranslationDialog, CreateArticleDialog );

	/**
	 * @property {OO.ui.DropdownInputWidget}
	 * @private
	 */
	CreateArticleTranslationDialog.prototype.languageInput = null;

	/**
	 * @property {OO.ui.RadioOptionWidget}
	 * @private
	 */
	CreateArticleTranslationDialog.prototype.translateOption = null;

	/**
	 * @property {OO.ui.RadioOptionWidget}
	 * @private
	 */
	CreateArticleTranslationDialog.prototype.emptyOption = null;

	/**
	 * @override
	 * @return {jQuery.Promise}
	 */
	CreateArticleTranslationDialog.prototype.onSubmit = function () {
		var self = this;

		if ( !this.translateOption.isSelected() ) {
			return CreateArticleTranslationDialog.super.prototype.onSubmit.apply( this );
		}

		mw.track( 'counter.MediaWiki.wikibase.articleplaceholder.button.translateArticle' );
		mw.track( 'stats.mediawiki_articleplaceholder_translatearticle_submit_total' );

		return mw.loader.using( 'mw.cx.SiteMapper' ).then( function () {
			var siteMapper = new mw.cx.SiteMapper(),
				url = siteMapper.getCXUrl(
					mw.config.get( 'apPageNames' )[ self.languageInput.getValue() ],
					self.titleInput.getValue(),
					self.languageInput.getValue(),
					mw.config.get( 'wgContentLanguage' ),
					{ campaign: 'articleplaceholder' }
				);

			self.forwardTo( url );
		} );
	};

	/**
	 * @private
	 * @return {OO.ui.DropdownInputWidget}
	 */
	CreateArticleTranslationDialog.prototype.createLanguageInput = function () {
		var self = this;

		this.languageInput = new OO.ui.DropdownInputWidget( {
			text: mw.msg( 'articleplaceholder-abouttopic-translate-article-label' ),
			options: mw.config.get( 'apLanguages' )
		} ).on( 'change', function () {
			self.toggleTranslateArticle( true );
		} );

		// Workaround to focus the translate option when clicking the dropdown.
		// TODO: Replace with a proper upstream solution when available.
		this.languageInput.$element.find( '.oo-ui-dropdownWidget-handle' ).click( function () {
			self.toggleTranslateArticle( true );
		} );

		return this.languageInput;
	};

	/**
	 * @private
	 * @param {boolean} translate
	 */
	CreateArticleTranslationDialog.prototype.toggleTranslateArticle = function ( translate ) {
		this.translateOption.setSelected( translate );
		this.emptyOption.setSelected( !translate );
	};

	/**
	 * @private
	 */
	CreateArticleTranslationDialog.prototype.createRadioOptions = function () {
		var self = this;

		this.translateOption = new OO.ui.RadioOptionWidget( {
			label: mw.msg( 'articleplaceholder-abouttopic-translate-article-button' )
		} );
		this.translateOption.setSelected( true );

		this.emptyOption = new OO.ui.RadioOptionWidget( {
			label: mw.msg( 'articleplaceholder-abouttopic-create-empty-article-button' )
		} );

		this.translateOption.$element.click( function () {
			self.toggleTranslateArticle( true );
		} );

		this.emptyOption.$element.click( function () {
			self.toggleTranslateArticle( false );
		} );
	};

	/**
	 * @private
	 * @return {OO.ui.StackLayout}
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

}() );
