module.exports = function( grunt ) {

	'use strict';
	var banner = '/**\n * <%= pkg.homepage %>\n * Copyright (c) <%= grunt.template.today("yyyy") %>\n * This file is generated automatically. Do not edit.\n */\n';
	// Project configuration
	grunt.initConfig( {

		pkg: grunt.file.readJSON( 'package.json' ),

		// Generate '-rtl.css' files from the LTR source CSS.
		rtlcss: {
			options: {
				config: {
					preserveComments: true,
					greedy: true,
				},
				map: false,
			},
			dist: {
				files: [
					{
						expand: true,
						cwd: 'assets/css/unminified/',
						src: [ '*.css', '!*-rtl.css' ],
						dest: 'assets/css/unminified',
						ext: '-rtl.css',
					},
				],
			},
		},

		// Minify JS: 'unminified/{name}.js' to 'minified/{name}.min.js'.
		uglify: {
			js: {
				files: [
					{
						expand: true,
						cwd: 'assets/js/unminified',
						src: [ '*.js' ],
						dest: 'assets/js/minified',
						ext: '.min.js',
					},
				],
			},
		},

		// Minify CSS: '{name}.css' to '{name}.min.css' and '{name}-rtl.css' to '{name}.min-rtl.css'.
		cssmin: {
			options: {
				keepSpecialComments: 0,
			},
			css: {
				files: [
					{
						expand: true,
						cwd: 'assets/css/unminified',
						src: [ '*.css', '!*-rtl.css' ],
						dest: 'assets/css/minified',
						ext: '.min.css',
					},
					{
						expand: true,
						cwd: 'assets/css/unminified',
						src: [ '*-rtl.css' ],
						dest: 'assets/css/minified',
						rename: function( dest, src ) {
							return dest + '/' + src.replace( /-rtl\.css$/, '.min-rtl.css' );
						},
					},
				],
			},
		},

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

	grunt.loadNpmTasks( 'grunt-rtlcss' );
	grunt.loadNpmTasks( 'grunt-contrib-uglify' );
	grunt.loadNpmTasks( 'grunt-contrib-cssmin' );
	grunt.loadNpmTasks( 'grunt-wp-i18n' );
	grunt.loadNpmTasks( 'grunt-wp-readme-to-markdown' );
	grunt.loadNpmTasks( 'grunt-bumpup' );
	grunt.loadNpmTasks( 'grunt-text-replace' );

	grunt.registerTask( 'i18n', ['addtextdomain', 'makepot'] );
	grunt.registerTask( 'readme', ['wp_readme_to_markdown'] );

	// RTL CSS: `grunt rtl`.
	grunt.registerTask( 'rtl', [ 'rtlcss' ] );

	// Build assets: `grunt minify` (RTL CSS, then minified JS and CSS).
	grunt.registerTask( 'minify', [ 'rtl', 'uglify:js', 'cssmin:css' ] );

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
