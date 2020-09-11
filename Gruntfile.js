/* eslint-env node */

module.exports = function ( grunt ) {
	require( 'load-grunt-tasks' )( grunt );

	grunt.initConfig( {
		eslint: {
			options: {
				cache: true
			},
			all: [
				'*.js',
				'**/*.{js,json}',
				'!node_modules/**',
				'!vendor/**'
			]
		},
		banana: {
			all: 'i18n/'
		},
		stylelint: {
			all: [
				'**/*.css',
				'!node_modules/**',
				'!vendor/**'
			]
		}
	} );

	grunt.registerTask( 'test', [ 'eslint', 'stylelint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
