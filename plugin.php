<?php

/*
Plugin Name: MPH Minify
Plugin URI: http://matth.eu
Description: Mega Simple Minify. Minifies enqueued scripts & styles.
Author: Matthew Haines-Young
Version: 0.1
Author URI: http://www.matth.eu
*/


add_action( 'wp_enqueue_scripts', function() {

	$minify = new MPH_Minify(); 

	$minify->minify();

}, 100 );


class MPH_Minify {

	// Useful stuff.
	var $plugin_url;
	var $minify_url;
	var $cache_url;

	// Store all assets (handles, and versions), in correct order of processing.
	var $asset_queue = array();

	function __construct() {

		global $wp_scripts, $wp_styles;

		$this->wp_scripts = $wp_scripts;
		$this->wp_styles = $wp_styles;

		$this->plugin_url = plugins_url( basename( __DIR__ ) );
		$this->minify_url = trailingslashit( $this->plugin_url ) . 'php-minify/min/';
		
		$this->cache_url = trailingslashit( trailingslashit( WP_CONTENT_URL ) . 'mph_minify_cache' );

	}

	function minify() {

		$classes = array( 'wp_scripts', 'wp_styles' );

		// Set up the queue of assets.
		foreach( $classes as $class )
			$this->get_asset_queue( $class );

		// Process the queue.
		foreach( $classes as $class )
			foreach ( $this->asset_queue[ $class ] as $group => $assets  )
				$this->process_assets( $class, $group );	

	}

	function get_asset_queue( $class ) {

		// Do this to set up the todos - in the correct order.	
		$this->$class->all_deps( $this->$class->queue );
		
		foreach ( $this->$class->to_do as $key => $handle ) {

			$this->asset_queue[$class][ $this->$class->groups[$handle] ][] = array( 
				'handle' => $handle,
				'version' => $this->$class->registered[$handle]->ver
			);

		}

		return $this->asset_queue;

	}

	function process_assets( $class, $group ) {

		$file = trailingslashit( $this->cache_url ) . crc32( serialize( $this->asset_queue[$class][$group] ) );

		if ( ! file_exists( $file ) ) {

			$srcs = array();
			foreach ( $this->asset_queue[$class][$group] as $asset ) {
				
				$srcs[] = $this->get_asset_path( $class, $asset['handle'] );

				$this->$class->dequeue( $asset['handle'] );
				$this->$class->remove( $asset['handle'] );

			}	

			$file = $this->minify_url . '/?f=' . implode( ',', array_filter( $srcs ) );

		}

		// Enqueue the assets
		$this->$class->add( 'mph-minify-' . $class . '-' . $group, $file );
		$this->$class->add_data( 'mph-minify-' . $class . '-' . $group, 'group', $file );		
		$this->$class->enqueue( 'mph-minify-' . $class . '-' . $file );

	}

	function get_asset_path( $class, $handle ) {

		if ( empty( $this->$class->registered[$handle]->src ) )
			return;

		$src = $this->$class->registered[$handle]->src;

		if ( ! preg_match('|^(https?:)?//|', $src) && ! ( $this->$class->content_url && 0 === strpos( $src, $this->$class->content_url ) ) )
			$src = $this->$class->base_url . $src;

		// Don't handle remote urls. For now...
		if ( 0 !== strpos( $src, home_url() ) )
			return;

		return str_replace( home_url(), '', $src );

	}

}
