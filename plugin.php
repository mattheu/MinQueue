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

$admin = new MPH_Minify_Admin();

add_action( 'wp_enqueue_scripts', function() {
	
	$options = get_option( 'mph_minify_options', array( 'ignore-scripts' => array('admin-bar'), 'ignore-styles' => array('admin-bar') ) );

	// Scripts
	$minify_scripts = new MPH_Minify( 'WP_Scripts' ); 
	
	if ( ! empty( $options['force_scripts'] ) )
		$minify_scripts->force_list = $options['force_scripts'];
	
	if ( ! empty( $options['ignore_scripts'] ) )
		$minify_scripts->ignore_list = $options['ignore_scripts'];
	
	$minify_scripts->minify();

	// Styles
	$minify_styles = new MPH_Minify( 'WP_Styles' ); 

	if ( ! empty( $options['force_styles'] ) )
		$minify_scripts->force_list = $options['force_styles'];
	
	if ( ! empty( $options['ignore_styles'] ) )
		$minify_styles->ignore_list = $options['ignore_styles'];
	
	$minify_styles->minify();

}, 9999 );