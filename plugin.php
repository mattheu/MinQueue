<?php

/*
Plugin Name: MPH Minify
Plugin URI: http://matth.eu
Description: Mega Simple Minify. Minifies enqueued scripts & styles.
Author: Matthew Haines-Young
Version: 0.1
Author URI: http://www.matth.eu
*/

require_once( 'class.mph-minify.php' );
require_once( 'class.mph-minify-admin.php' );
require_once( 'debugger.php' );

$admin = new MPH_Minify_Admin();

add_action( 'wp_enqueue_scripts', function() {

	$defaults = array( 
		'scripts_method' => 'auto', 
		'scripts_manual' => array(),
		'scripts_ignore' => array('admin-bar'),
		'scripts_force' => array(),
		'styles_method' => 'auto', 
		'styles_manual' => array(),
		'styles_ignore' => array('admin-bar'),
		'styles_force' => array(),
		'debugger' => true
	);

	if ( defined( 'MPH_MINIFY_OPTIONS' ) )
		$options = unserialize( MPH_MINIFY_OPTIONS );
	else
		$options = get_option( 'mph_minify_options', $defaults );
	
	// Scripts
	$minify_scripts = new MPH_Minify( 'WP_Scripts' ); 

	if ( 'manual' == $options['scripts_method'] ) {

		$minify_scripts->queue = $options['scripts_manual'];

	} else {

		$minify_scripts->ignore_list = $options['scripts_ignore'];
		$minify_scripts->force_list = $options['scripts_force'];

	}

	$minify_scripts->minify();

	// Styles
	$minify_styles = new MPH_Minify( 'WP_Styles' ); 

	if ( 'manual' == $options['styles_method'] ) {

		$minify_styles->queue = $options['styles_manual'];

	} else {

		$minify_styles->ignore_list = $options['styles_ignore'];
		$minify_styles->force_list = $options['styles_force'];

	}

	$minify_styles->minify();

	if ( true === $options['debugger'] ) {

		add_action( 'wp_head', 'mph_minify_debugger_style' );
		add_action( 'wp_footer', function() use ( $minify_scripts, $minify_styles ) {

			mph_minify_debugger( $minify_scripts, $minify_styles );			

		} );
	
	}


}, 9999 );