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

define( 'MPH_MINIFY_VERSION', '0.0.1' );

$admin = new MPH_Minify_Admin();

function mph_minify_get_plugin_options() {

	$defaults = array( 
		'debugger' => false,
		'cache_dir' => 'mph_minify_cache',
		'scripts_method' => 'disabled',
		'styles_method' => 'disabled'
	);

	if ( defined( 'MPH_MINIFY_OPTIONS' ) )
		$options = unserialize( MPH_MINIFY_OPTIONS );
	else
		$options = get_option( 'mph_minify_options', $defaults );

	$options = wp_parse_args( $options, $defaults );

	return $options;

}

add_action( 'wp_enqueue_scripts', function() {

	$options = mph_minify_get_plugin_options();

	// Scripts	
	if ( 'auto' == $options['scripts_method'] ) {

		$minify_scripts = new MPH_Minify( 'WP_Scripts' ); 

		if ( isset( $options['scripts_ignore'] ) )
			$minify_scripts->ignore_list = $options['scripts_ignore'];
		if ( isset( $options['scripts_force'] ) )
			$minify_scripts->force_list = $options['scripts_force'];

		$minify_scripts->minify();

	} elseif ( 'manual' == $options['scripts_method'] ) {

		$minify_scripts = new MPH_Minify( 'WP_Scripts' ); 

		if ( isset( $options['scripts_manual'] ) )
			$minify_scripts->queue = (array) $options['scripts_manual'];

		$minify_scripts->minify();

	}	

	// Styles
	if ( 'auto' == $options['styles_method'] ) {

		$minify_styles = new MPH_Minify( 'WP_Styles' ); 

		if ( isset( $options['styles_ignore'] ) )
			$minify_styles->ignore_list = $options['styles_ignore'];
		if ( isset( $options['styles_force'] ) )
		$minify_styles->force_list = $options['styles_force'];

	} elseif ( 'manual' == $options['styles_method'] ) {

		$minify_styles = new MPH_Minify( 'WP_Styles' ); 

		if ( isset( $options['styles_manual'] ) )
			$minify_styles->queue = (array) $options['styles_manual'];

		$minify_styles->minify();

	}

	// Debugger...
	if ( isset( $options['debugger'] ) && true === $options['debugger'] && current_user_can( 'manage_options' ) ) {

		$minifiers = array();

		if ( isset( $minify_scripts ) ) 
			$minifiers['minify_scripts'] = $minify_scripts;

		if ( isset( $minify_styles ) ) 
			$minifiers['minify_styles'] = $minify_styles;

		add_action( 'wp_head', 'mph_minify_debugger_style' );
		add_action( 'wp_footer', function() use ( $minifiers ) {

			mph_minify_debugger( $minifiers );			

		} );
	
	}


}, 9999 );