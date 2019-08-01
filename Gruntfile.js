/* eslint-env node */

module.exports = function ( grunt ) {
	require( 'load-grunt-tasks' )( grunt );

	grunt.initConfig( {
		eslint: {
			options: {
				cache: true,
				reportUnusedDisableDirectives: true
			},
			all: [
				'*.js',
				'**/*.js',
				'!node_modules/**',
				'!vendor/**'
			]
		},
		banana: {
			all: 'i18n/'
		},
		jsonlint: {
			all: [
				'*.json',
				'**/*.json',
				'!node_modules/**',
				'!vendor/**'
			]
		},
		stylelint: {
			all: [
				'**/*.css',
				'!node_modules/**',
				'!vendor/**'
			]
		}
	} );

	grunt.registerTask( 'test', [ 'eslint', 'stylelint', 'jsonlint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
