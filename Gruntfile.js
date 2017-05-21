/* eslint-env node */

module.exports = function ( grunt ) {
	require( 'load-grunt-tasks' )( grunt );

	grunt.initConfig( {
		eslint: {
			all: '.'
		},
		banana: {
			all: 'i18n/'
		},
		jsonlint: {
			all: [
				'*.json',
				'**/*.json',
				'.stylelintrc',
				'!node_modules/**'
			]
		},
		stylelint: {
			all: [
				'**/*.css',
				'!node_modules/**'
			]
		},
		qunit: {
			all: [
				'tests/qunit/*.html'
			]
		}
	} );

	grunt.registerTask( 'test', [ 'eslint', 'jsonlint', 'banana', 'qunit', 'stylelint' ] );
	grunt.registerTask( 'default', 'test' );
};
