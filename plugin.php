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

add_action( 'wp_enqueue_scripts', function() {

	$minify_scripts = new MPH_Minify( 'WP_Scripts' ); 
	$minify_scripts->force_list = array();
	$minify_scripts->ignore_list = array( 'admin-bar', 'wc-single-product' );
	$minify_scripts->minify();
	
	$minify_styles = new MPH_Minify( 'WP_Styles' ); 
	$minify_scripts->force_list = array();
	$minify_styles->ignore_list = array( 'admin-bar' );
	$minify_styles->minify();


}, 100 );

