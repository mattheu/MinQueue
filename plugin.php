<?php

/*
Plugin Name: MPH Minify
Plugin URI: http://matth.eu
Description: Mega Simple Minify. Minifies enqueued scripts & styles.
Author: Matthew Haines-Young
Version: 1.0-beta1
Author URI: http://www.matth.eu
*/

// The core minify class.
require_once( 'class.mph-minify.php' );

// Minify Admin Page.
require_once( 'class.mph-minify-admin.php' );

// Admin Notices Abstraction to handle displaying of admin notices.
require_once( 'class.mph-minify-notices.php' );

// Front end debugger tool for showing what is enqueued on each page.
require_once( 'debugger.php' );

define( 'MPH_MINIFY_VERSION', '1.0-beta1' );

$minified_deps = array( 'WP_Scripts' => array(), 'WP_Styles' => array() );
global $minified_deps;

add_action( 'init', 'mph_minify_init' );

/**
 * Init
 *
 * @return null
 */
function mph_minify_init () {

	// Save current version no.
	if ( ! $version = get_option( 'mph_minify_version' ) )
		update_option( 'mph_minify_version', MPH_MINIFY_VERSION );

	// Update hook.
	if ( 0 !== version_compare( $version, MPH_MINIFY_VERSION ) ) {
		do_action( 'mph_minify_update', $version, MPH_MINIFY_VERSION );
		update_option( 'mph_minify_version', MPH_MINIFY_VERSION );
	}

	add_action( 'wp_enqueue_scripts', 'mph_minify', 9999 );

	// Load the admin - unless settings are not defined.
	if ( ! defined( 'MPH_MINIFY_OPTIONS' ) )
		new MPH_Minify_Admin;

	register_deactivation_hook( basename( __DIR__ ) . DIRECTORY_SEPARATOR . basename( __FILE__ ), 'mph_minify_deactivation_hook' );

}

/**
 * Return the function options.
 *
 * Sets defaults & handles disabling the admin by defining settings instead.
 */
function mph_minify_get_options() {

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

	$options = mph_minify_get_options();

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

}

/**
 * Plugn deactivate callback
 *
 * Delete all options and files.
 *
 * @return null
 */
function mph_minify_deactivation_hook() {

	// Delete the cache if requested.
	$minify = new MPH_Minify( 'WP_Scripts' );
	$minify->delete_cache();

	delete_option( 'mph_minify_notices' );
	delete_option( 'mph_minify_options' );

}

