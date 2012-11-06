<?php

/*
Plugin Name: MPH Minify
Plugin URI: http://matth.eu
Description: Mega Simple Minify. Minifies enqueued scripts & styles.
Author: Matthew Haines-Young
Version: 0.2
Author URI: http://www.matth.eu
*/

require_once( 'class.mph-minify.php' );
require_once( 'class.mph-minify-admin.php' );
require_once( 'debugger.php' );

define( 'MPH_MINIFY_VERSION', '0.0.2' );

$minified_deps = array( 'WP_Scripts' => array(), 'WP_Styles' => array() );
global $minified_deps;

if ( ! defined( 'MPH_MINIFY_OPTIONS' ) )
	$admin = new MPH_Minify_Admin();

add_action( 'wp_enqueue_scripts', 'mph_minify', 9999 );
add_filter( 'mph_minify_cache_dir', 'mph_minify_cache_dir_override' );

/**
 * Return the function options.
 *
 * Handles defaults & overriding with define.
 */
function mph_minify_get_plugin_options() {

	$defaults = array(
		'debugger' => false,
		'cache_dir' => 'mph_minify_cache',
		'scripts_method' => 'disabled',
		'styles_method' => 'disabled',
		'scripts_manual' => array(),
		'styles_manual' => array()
	);

	if ( defined( 'MPH_MINIFY_OPTIONS' ) )
		$options = unserialize( MPH_MINIFY_OPTIONS );
	else
		$options = get_option( 'mph_minify_options', $defaults );

	$options = wp_parse_args( $options, $defaults );

	return $options;

}

/**
 * Main Plugin Functionality.
 *
 * @return null
 */
function mph_minify() {

	$options = mph_minify_get_plugin_options();

	$instances = array(
		'scripts' => array(),
		'styles' => array()
	);

	// Scripts
	if ( isset( $options['scripts_method'] ) && 'disabled' !== $options['scripts_method'] ) {

		foreach ( $options['scripts_manual'] as $key => $queue ) {

			if ( ! empty( $queue ) ) {
				$instances['scripts'][$key] = new MPH_Minify( 'WP_Scripts' );
				$instances['scripts'][$key]->queue = (array) $queue;
				$instances['scripts'][$key]->minify();
			}

		}

	}

	// Styles
	if ( isset( $options['styles_method'] ) && 'disabled' !== $options['styles_method'] ) {

		foreach ( $options['styles_manual'] as $key => $queue ) {

			if ( ! empty( $queue ) ) {
				$instances['styles'][$key] = new MPH_Minify( 'WP_Styles' );
				$instances['styles'][$key]->queue = (array) $queue;
				$instances['styles'][$key]->minify();
			}

		}

	}

	// Debugger
	if ( isset( $options['debugger'] ) && true === $options['debugger'] ) {

		add_action( 'wp_head', 'mph_minify_debugger_style' );
		add_action( 'wp_footer', 'mph_minify_debugger', 9999 );

	}


}


/**
 * Filter the cache directory to allow setting your own.
 *
 * If settings or defined
 *
 * @param  [type] $cache_dir [description]
 * @return [type]            [description]
 */
function mph_minify_cache_dir_override( $cache_dir ) {

	$options = mph_minify_get_plugin_options();

	if ( ! empty( $options[ 'cache_dir' ] ) )
		return $options[ 'cache_dir' ];

	return $cache_dir;

}