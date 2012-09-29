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
	public $plugin_url;
	public $minify_url;
	public $cache_url;

	public $cache = true;

	// Store all assets (handles, and versions), in correct order of processing.
	private $asset_queue = array();
	
	// Do not minify these assets - array of handles.
	private $ignore_list = array();

	// Array of script Localization data. 
	private $script_localization = array();
	
	function __construct() {

		global $wp_scripts, $wp_styles;

		$this->wp_scripts    = $wp_scripts;
		$this->wp_styles     = $wp_styles;

		//$this->site_root();
		$this->plugin_url    = plugins_url( basename( __DIR__ ) );
		$this->minify_url    = trailingslashit( $this->plugin_url ) . 'php-minify/min/';
		
		$this->cache_dirname = trailingslashit( 'mph_minify_cache' );
		$this->cache_url     = trailingslashit( WP_CONTENT_URL ) . $this->cache_dirname;
		$this->cache_dir     = trailingslashit( WP_CONTENT_DIR ) . $this->cache_dirname;

		$this->ignore_list = array(
			'wp_scripts' => array( 'admin-bar', 'wc-single-product' ),
			'wp_styles' => array( 'admin-bar' )
		);

	}


	/**
	 * Action! Run the minifier.
	 * 
	 * @return null
	 */
	function minify() {

		$classes = array( 'wp_scripts', 'wp_styles' );

		// Set up the queue of assets.
		foreach( $classes as $class )
			$this->get_asset_queue( $class );

		// Process the queue.
		foreach( $classes as $class )
			foreach ( (array) $this->asset_queue[ $class ] as $group => $assets  )
				$this->enqueue_minified_assets( $class, $group );	
		
		// Foreach classes, if there is localization data, then we hook in and add a script tag to the head.
		// @todo - look into properly adding this using the wp_scripts class. Can we actually localize the minified script? or use print_inline_style.
		// @todo - can we do this without a closure for php 5.2 support.
		foreach ( $classes as $class ) {
			if ( ! empty( $this->script_localization[$class] ) )
				foreach( $this->script_localization[$class] as $group => $data ) {
					if ( $data = implode( ' ', $data ) )
						add_action( 'wp_head', function() use ( $data ) {
							echo '<script>' . $data . '</script>';
						} );
				}
		}
		

	}


	/**
	 * Get the queue of assets for a given class.
	 * 
	 * @param  class $class  type of asset (wp_scripts of wp_styles)
	 * @return array asset queue. An array of classes. Contains array of groups. contains array of asset handles.
	 */
	function get_asset_queue( $class ) {

		// Do this to set up the todos - in the correct order.	
		$this->$class->all_deps( $this->$class->queue );
		
  		foreach ( $this->$class->to_do as $key => $handle ) {

			// If this script is ignored, skip it.
			if ( in_array( $handle, $this->ignore_list[$class] ) )
				continue;

			// Add this asset to the queue.
			$this->asset_queue[$class][ $this->$class->groups[$handle] ][] = array( 
				'handle' => $handle,
				'version' => $this->$class->registered[$handle]->ver
			);

			if ( ! empty( $this->$class->registered[$handle]->extra['data'] ) )
				$this->script_localization[$class][ $this->$class->groups[$handle] ][] = $this->$class->registered[$handle]->extra['data'];

		}

		return $this->asset_queue;

	}

	/**
	 * Process Assets. 
	 *
	 * Enqueue cached minified file or create one and enqueue that.
	 * 
	 * @param  $class [description]
	 * @param  [type] $group [description]
	 * @return [type]        [description]
	 */
	function enqueue_minified_assets( $class, $group ) {

		// Filename is a crc32 hash of the current group asset queue (contains version numbers)
		$filename = crc32( serialize( $this->asset_queue[$class][$group] ) ) . ( ( 'wp_styles' === $class ) ? '.css' : '.js' );		
		$src = trailingslashit( $this->cache_url ) . $filename;

		// If no cached file - generate minified asset src.
		if ( ! file_exists( $this->cache_dir . $filename ) ) {

			$_srcs = array();
			foreach ( $this->asset_queue[$class][$group] as $asset )
				$_srcs[] = $this->get_asset_path( $class, $asset['handle'] );

			// On the fly minify url - used to generate the cache.
			$src = $this->minify_url . '/?f=' . implode( ',', array_filter( $_srcs ) );

			if ( $this->cache )
				$src = $this->cache_file( $filename, $src );

		}

		// Remove & Dequeue original files.
		foreach ( $this->asset_queue[$class][$group] as $asset ) {
			
			$this->$class->dequeue( $asset['handle'] );
			$this->$class->remove( $asset['handle'] );
		
		}

		// Enqueue the minified file
		$this->$class->add( 'mph-minify-' . $class . '-' . $group, $src, null, null );
		$this->$class->add_data( 'mph-minify-' . $class . '-' . $group, 'group', $group );
		$this->$class->enqueue( 'mph-minify-' . $class . '-' . $group );

	}

	/**
	 * Return the path to an asset relative to the site root, Uses $wp_scripts->registered.
	 * 
	 * @param  string $class  The Class to be used. wp_scripts or wp_styles.
	 * @param  string $handle handle of the asset
	 * @return string         string, path of the asset, relative to site root.
	 */
	function get_asset_path( $class, $handle ) {

		if ( empty( $this->$class->registered[$handle] ) )
			return;

		$src = $this->$class->registered[$handle]->src;

		if ( ! preg_match('|^(https?:)?//|', $src) && ! ( $this->$class->content_url && 0 === strpos( $src, $this->$class->content_url ) ) )
			$src = $this->$class->base_url . $src;

		// Don't handle remote urls. For now...
		if ( 0 !== strpos( $src, home_url() ) )
			return;

		return str_replace( home_url(), '', $src );

	}

	/**
	 * Create Cache file. 
	 * 
	 * @param  string $filename name used to create file. A hash of args.
	 * @param  array  $srcs     srcs of assets.
	 * @return string           src of cache file.
	 */
	function cache_file( $filename, $minify_src ) {

		// Create Directory.
		if ( ! is_dir( $this->cache_dir ) )
			mkdir( $this->cache_dir );

		$data = file_get_contents( $minify_src );
		
		if ( $data ) {
			
			file_put_contents( $this->cache_dir . $filename, $data );	
		
			return $this->cache_url . $filename;

		}
		
	}

	/**
	 * Delete all cached files.
	 * 
	 * @return null
	 */
	function delete_cache() { 

		// @todo

	}

}
