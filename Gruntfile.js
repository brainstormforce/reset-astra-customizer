module.exports = function( grunt ) {

	'use strict';
	var banner = '/**\n * <%= pkg.homepage %>\n * Copyright (c) <%= grunt.template.today("yyyy") %>\n * This file is generated automatically. Do not edit.\n */\n';
	// Project configuration
	grunt.initConfig( {

		pkg: grunt.file.readJSON( 'package.json' ),

		addtextdomain: {
			options: {
				textdomain: 'reset-astra-customizer',
			},
			target: {
				files: {
					src: [ '*.php', '**/*.php', '!node_modules/**', '!php-tests/**', '!bin/**' ]
				}
			}
		},

		wp_readme_to_markdown: {
			your_target: {
				files: {
					'README.md': 'readme.txt'
				}
			},
		},

		bumpup: {
			options: {
				updateProps: {
					pkg: 'package.json',
				},
			},
			file: 'package.json',
		},

		replace: {
			plugin_main: {
				src: [ 'class-astra-theme-customizer-reset.php' ],
				overwrite: true,
				replacements: [
					{
						from: /Version: \d{1,1}\.\d{1,2}\.\d{1,2}/g,
						to: 'Version: <%= pkg.version %>',
					},
				],
			},

			plugin_const: {
				src: [ 'class-astra-theme-customizer-reset.php' ],
				overwrite: true,
				replacements: [
					{
						from: /ASTRA_CUSTOMIZER_VERSION', '.*?'/g,
						to: 'ASTRA_CUSTOMIZER_VERSION\', \'<%= pkg.version %>\'',
					},
				],
			},

			plugin_stable_tag: {
				src: [ 'readme.txt' ],
				overwrite: true,
				replacements: [
					{
						from: /Stable tag: \d{1,1}\.\d{1,2}\.\d{1,2}/g,
						to: 'Stable tag: <%= pkg.version %>',
					},
				],
			},

			plugin_function_comment: {
				src: [
					'*.php',
					'**/*.php',
					'!node_modules/**',
					'!php-tests/**',
					'!bin/**',
					'!vendor/**',
				],
				overwrite: true,
				replacements: [
					{
						from: 'x.x.x',
						to: '<%= pkg.version %>',
					},
				],
			},
		},

		makepot: {
			target: {
				options: {
					domainPath: '/languages',
					mainFile: 'reset-astra-customizer.php',
					potFilename: 'reset-astra-customizer.pot',
					potHeaders: {
						poedit: true,
						'x-poedit-keywordslist': true
					},
					type: 'wp-plugin',
					updateTimestamp: true
				}
			}
		},
	} );

	grunt.loadNpmTasks( 'grunt-wp-i18n' );
	grunt.loadNpmTasks( 'grunt-wp-readme-to-markdown' );
	grunt.loadNpmTasks( 'grunt-bumpup' );
	grunt.loadNpmTasks( 'grunt-text-replace' );

	grunt.registerTask( 'i18n', ['addtextdomain', 'makepot'] );
	grunt.registerTask( 'readme', ['wp_readme_to_markdown'] );

	// Version Bump `grunt version-bump --ver=<version-number>`
	grunt.registerTask( 'version-bump', function() {
		var newVersion = grunt.option( 'ver' );

		if ( newVersion ) {
			grunt.task.run( 'bumpup:' + newVersion );
			grunt.task.run( 'replace' );
		}
	} );

	grunt.util.linefeed = '\n';

};
