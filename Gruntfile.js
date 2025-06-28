/* eslint-env node */

module.exports = function ( grunt ) {
	var conf = grunt.file.readJSON( 'extension.json' );

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
		banana: conf.MessagesDirs,
		stylelint: {
			options: {
				cache: true
			},
			all: [
				'**/*.{css,less}',
				'!node_modules/**',
				'!vendor/**'
			]
		}
	} );

	grunt.registerTask( 'test', [ 'eslint', 'stylelint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
