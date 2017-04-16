/*jshint node:true */
module.exports = function ( grunt ) {
	require( 'load-grunt-tasks' )( grunt );

	grunt.initConfig( {
		jshint: {
			options: {
				jshintrc: true
			},
			all: [
				'*.js',
				'modules/**/*.js',
				'tests/**/*.js'
			]
		},
		jscs: {
			src: '<%= jshint.all %>'
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

	grunt.registerTask( 'test', [ 'jshint', 'jscs', 'jsonlint', 'banana', 'qunit', 'stylelint' ] );
	grunt.registerTask( 'default', 'test' );
};
