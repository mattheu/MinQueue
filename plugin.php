<?php

/*
Plugin Name: MinQueue
Plugin URI: https://github.com/mattheu/MPH-Minify
Description: Minify & concatenate enqueued scripts & styles. For developers who want complete control.
Author: Matthew Haines-Young
Version: 1.1.2
Author URI: http://www.matth.eu
*/

// The core minify class.
require_once( 'class.minqueue.php' );

// Minify Admin Page.
require_once( 'class.minqueue-admin.php' );

// Admin Notices Abstraction to handle displaying of admin notices.
require_once( 'class.minqueue-notices.php' );

// Front end helper tool for showing what is enqueued on each page.
require_once( 'minqueue-helper-tool.php' );

define( 'MINQUEUE_VERSION', '1.0-beta1' );

$minified_deps = array( 'WP_Scripts' => array(), 'WP_Styles' => array() );
global $minified_deps;

add_action( 'init', 'minqueue_init', 1 );

register_activation_hook( __FILE__, 'minqueue_activation_hook' );
register_deactivation_hook( __FILE__, 'minqueue_deactivation_hook' );

/**
 * Init
 *
 * @return null
 */
function minqueue_init () {

	if ( defined( 'WP_INSTALLING' ) )
		return;

	// Save current version no.
	if ( ! $version = get_option( 'minqueue_version' ) )
		update_option( 'minqueue_version', MINQUEUE_VERSION );

	// Update hook.
	if ( 0 !== version_compare( $version, MINQUEUE_VERSION ) ) {
		do_action( 'minqueue_update', $version, MINQUEUE_VERSION );
		update_option( 'minqueue_version', MINQUEUE_VERSION );
	}

	// Run the minifier
	add_action( 'wp_print_scripts', 'minqueue_scripts', 100 );
	add_action( 'wp_footer', 'minqueue_scripts', 5 );
	add_action( 'wp_print_styles', 'minqueue_styles', 100 );

	// Load the admin - unless settings are not defined.
	if ( ! defined( 'MINQUEUE_OPTIONS' ) )
		new MinQueue_Admin;

}

/**
 * Return the function options.
 *
 * Sets defaults & handles disabling the admin by defining settings instead.
 */
function minqueue_get_options() {

	$defaults = array(
		'helper' => false,
		'cache_dir' => 'minqueue_cache',
		'scripts_method' => 'disabled',
		'styles_method' => 'disabled',
		'scripts_manual' => array(),
		'styles_manual' => array()
	);

	if ( defined( 'MINQUEUE_OPTIONS' ) )
		$options = unserialize( MINQUEUE_OPTIONS );
	else
		$options = get_option( 'minqueue_options', $defaults );

	$options = wp_parse_args( $options, $defaults );

	return $options;

}

/**
 * Process Scripts
 *
 * @return null
 */
function minqueue_scripts() {

	if ( is_admin() )
		return;

	$options = minqueue_get_options();

	// Scripts
	if ( isset( $options['scripts_method'] ) && 'disabled' !== $options['scripts_method'] ) {

		foreach ( $options['scripts_manual'] as $key => $queue ) {

			if ( ! empty( $queue ) ) {
				$scripts[$key] = new MinQueue_Scripts( (array) $queue );
				$scripts[$key]->minify();
			}

		}

	}

}

/**
 * Process Styles
 *
 * @return null
 */
function minqueue_styles() {

	if ( is_admin() )
		return;

	$options = minqueue_get_options();

	// Styles
	if ( isset( $options['styles_method'] ) && 'disabled' !== $options['styles_method'] ) {

		foreach ( $options['styles_manual'] as $key => $queue ) {

			if ( ! empty( $queue ) ) {
				$styles[$key] = new MinQueue_Styles( (array) $queue );
				$styles[$key]->minify();
			}

		}

	}

}

/**
 * Plugn activation callback
 *
 * Display admin notice with instructions.
 *
 * @return null
 */
function minqueue_activation_hook() {

	// Require PHP 5.3
	if ( version_compare( PHP_VERSION, '5.3', '<' ) ) {
		deactivate_plugins(basename(__FILE__)); // Deactivate ourself
		wp_die( 'MinQueue requires PHP 5.3. Current PHP version is ' . esc_html( phpversion() ) );
	}

	$admin_notices = new MinQueue_Admin_Notices( apply_filters( 'minqueue_prefix', 'minqueue' ) );
	$admin_notices->add_notice( 'MinQueue activated. Go to the <a href="options-general.php?page=minqueue">settings page</a> to configure the plugin.', false, 'updated', 'minqueue_min_activation_notice' );

}

/**
 * Plugn deactivate callback
 *
 * Delete all options and files.
 *
 * @return null
 */
function minqueue_deactivation_hook() {

	$minify = new MinQueue_Scripts();
	$minify->delete_cache();

	delete_option( 'minqueue_options' );

	$admin_notices = new MinQueue_Admin_Notices( apply_filters( 'minqueue_prefix', 'minqueue' ) );
	$admin_notices->clean_up();

}