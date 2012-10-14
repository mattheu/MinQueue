<?php

class MPH_Minify {

	// Useful stuff.
	public $prefix = 'mph-min-';
	public $plugin_url;
	public $minify_url;
	public $cache_url;

	// Reference to global record of everything minified
	public $minified_deps;

	// Array of handles to process.
	// If empty, all enqueued assets are used.
	public $queue = array();

	// Reference to WP_Scripts or WP_Styles. Must be a sub class of WP_Dependencies. 
	private $class;

	// Cache minified files or do it on the fly. 
	private $cache = true;

	// Internal queue of assets to be minified. By group.
	private $asset_queue = array();
	
	// Array of script Localization data.
	private $script_localization = array();

	/**
	 * Set things up.
	 * 
	 * @param string $class Minify assets for this class.
	 */
	function __construct( $class_name ) {

		$this->plugin_url    = plugins_url( basename( __DIR__ ) );
		$this->minify_url    = trailingslashit( $this->plugin_url ) . 'php-minify/min/';
		
		$this->cache_dirname = trailingslashit( apply_filters( 'mph_minify_cache_dir', 'mph_minify_cache' ) );
		$this->cache_url     = trailingslashit( WP_CONTENT_URL ) . $this->cache_dirname;
		$this->cache_dir     = trailingslashit( WP_CONTENT_DIR ) . $this->cache_dirname;

		// Global record of everything minified.
		global $minified_deps;
		$this->minified_deps = &$minified_deps; 

		// Set up which WP_Dependencies sub-class to use. 
		if ( 'WP_Scripts' ==  $class_name ) {
			
			global $wp_scripts;
			$this->class = &$wp_scripts;

		} elseif ( 'WP_Styles' == $class_name ) {
		
			global $wp_styles;
			$this->class = &$wp_styles;
		
		}

		if ( ! empty( $this->class ) && ! is_subclass_of( $this->class, 'WP_Dependencies' ) )
			die( get_class( $this->class ) . ' does not extend WP_Dependencies' );

	}


	/**
	 * Action! Run the minifier.
	 * 
	 * @return null
	 */
	function minify() {

		if ( empty( $this->class ) )
			return;

		// Get the queue of assets & Enqueue each group.
		foreach ( (array) $this->get_asset_queue() as $group => $assets  )
			$this->enqueue_minified_assets( $group );	

		// Add the localization data to the head. Do it as early as possible.
		if ( ! empty( $this->script_localization ) )
			add_action( 'wp_head', array( $this, 'script_localization' ), 2 );

	}

	/**
	 * Get the queue of assets to be minified & concatenated
	 * 
	 * @param  class $class  type of asset (wp_scripts of wp_styles)
	 * @return array asset queue. An array of classes. Contains array of groups. contains array of asset handles.
	 */
	function get_asset_queue() {
		
		if ( empty( $this->asset_queue ) ) {

			// Remove from queue if not a registered asset.
			foreach ( $this->queue as $key => $handle )
				if ( ! array_key_exists( $handle, $this->class->registered ) )
					unset( $this->queue[$key] );

			// Set up the todos - in correct order considering dependencies.
			$this->class->all_deps( $this->queue );

	  		foreach ( $this->class->to_do as $key => $handle ) {

				// If not in queue - skip
				// Skip if no asset path (eg is remote.)
				if ( ! in_array( $handle, $this->queue ) || ! $this->get_asset_path( $handle ) ) 
					continue;

				// Add this asset to the queue.
				$this->asset_queue[ $this->class->groups[$handle] ][$handle] = array( 
					'handle' => $handle,
					'version' => $this->class->registered[$handle]->ver
				);

				// If this asset is localized, store that data.
				if ( ! empty( $this->class->registered[$handle]->extra['data'] ) )
					$this->script_localization[ $handle ] = $this->class->registered[$handle]->extra['data'];

			}

		}		

		return $this->asset_queue;

	}

	/**
	 * Process Assets. 
	 *
	 * Enqueue cached minified file or create one and enqueue that.
	 * 
	 * @param  int $group Group identifier
	 * @return null
	 */
	function enqueue_minified_assets( $group ) {

		// Handle used as filename. It is a crc32 hash of the current group asset queue - contains version numbers
		$min_handle = $this->get_min_handle( $group );
		$min_src    = trailingslashit( $this->cache_url ) . $min_handle . ( ( 'WP_Styles' === get_class( $this->class ) ) ? '.css' : '.js' );
		$min_path   = trailingslashit( $this->cache_dir ) . $min_handle . ( ( 'WP_Styles' === get_class( $this->class ) ) ? '.css' : '.js' );
		
		// If no cached file - generate minified asset src.
		if ( ! file_exists( $min_path ) ) {

			// Get array of srcs.
			$_srcs = array();
			foreach ( $this->asset_queue[$group] as $asset )
				if ( $_src = $this->get_asset_path( $asset['handle'] ) )
					$_srcs[] = $_src;

			// If no srcs to be minified, just stop all this right now.
			if ( empty( $_srcs ) )
				return;

			// On the fly minify url - used to generate the cache.
			$min_src = $this->minify_url . '/?f=' . implode( ',', array_filter( $_srcs ) );

			// Generate cached file.
			if ( $this->cache )
				$min_src = $this->get_cache_file( $min_handle, $min_src, array_keys( $this->asset_queue[$group] ) );

		}

		// Mark the minified assets as done so they are not done again.
		// Keep a global record of all minified assets
		foreach ( $this->asset_queue[$group] as $asset ) {
			
			$this->class->to_do = array_diff( $this->class->to_do, array( $asset['handle'] ) );
			$this->class->done[] = $asset['handle'];
			$this->minified_deps[ get_class( $this->class ) ][ $asset['handle'] ] = $min_handle;

		}	

		$deps = $this->get_group_deps( $group );

		// Enqueue the minified file
		$this->class->add( $min_handle, $min_src, $deps, null );
		$this->class->add_data( $min_handle, 'group', $group );
		$this->class->enqueue( $min_handle );

		$this->setup_all_deps( $group );

	}


	/**
	 * Get Dependencies of this minify file.
	 *
	 * All dependencies of files contained within this file.
	 * 
	 * @param  int $group the group of handles currently being processed. 
	 * @return arary of handles that are dependencies of the current minify group.
	 */
	function get_group_deps( $group ) {

		// Add any deps of assets in queue that are not themselves part of this queue as a dependency of the minified/concatenated file.
		$deps = array();
		foreach ( $this->asset_queue[$group] as $asset )
			foreach ( $this->class->registered[$asset['handle'] ]->deps as $dep )
				if ( ! array_key_exists( $dep, $this->asset_queue[$group] ) && ! in_array( $dep, $deps ) )
					$deps[] = $dep;

		return $deps;

	}

	/**
	 * Set up all dependencies.
	 *
	 * Minifying and concatenating removes handles from the queue. Causes problems with dependencies.
	 * Set up dependencies of this file, and others to reflect the current state.
	 * 
	 * @param  int $group the group of handles currently being processed. 
	 * @return arary of handles that are dependencies of the current minify group.
	 */
	function setup_all_deps( $group ) {

		// If any of the assets in this file are dependencies of any other registered files, we need to add the minified file as a dependancy.
		foreach ( $this->class->registered as &$asset )
			if ( ! empty( $asset->deps ) ) 
				if ( array_intersect( $asset->deps, array_keys( $this->asset_queue[$group] ) ) ) 
					$asset->deps[] = $this->get_min_handle( $group );

		// If any deps of this file are themselves part of another minified file, remove it and add that min file as a dep of this one.
		foreach ( $this->class->registered as &$dependency )
			foreach ( $dependency->deps as $key => $dep )
				if ( array_key_exists( $dep, (array) $this->minified_deps[ get_class( $this->class ) ] ) ) {
					unset( $dependency->deps[$key] );
					if ( ! in_array( $this->minified_deps[ get_class( $this->class ) ][$dep], $dependency->deps ) )
						$dependency->deps[] = $this->minified_deps[ get_class( $this->class ) ][$dep];
				}

	}

	/**
	 * Localize the minified scripts. Echo script tags in the head.
	 *
	 * @return null
	 * @todo - Unfortunately we cannot just localize the minified file using this data but could maybe add this using the wp_scripts class sett print_inline_style().
	 */
	function script_localization() {

		foreach ( $this->script_localization as $handle => $data )
			echo '<script>' . $data . '</script>';

	}


	function get_min_handle( $group ) {

		return $this->prefix . hash( 'crc32b', serialize( $this->asset_queue[$group] ) );

	}

	/**
	 * Return the path to an asset relative to the site root, Uses $wp_scripts->registered.
	 * 
	 * @param  string $handle handle of the asset
	 * @return string         string, path of the asset, relative to site root.
	 */
	function get_asset_path( $handle ) {
		
		// Don't try and process unregistered files, or other minify.
		if ( empty( $this->class->registered[$handle] ) || ! $src = $this->class->registered[$handle]->src )
			return;

		if ( ! preg_match('|^(https?:)?//|', $src) && ! ( $this->class->content_url && 0 === strpos( $src, $this->class->content_url ) ) )
			$src = $this->class->base_url . $src;

		// Strip query args.
		$src = strtok( $src, '?' );

		// Don't handle remote urls.
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
	function get_cache_file( $min_handle, $min_src, $handles ) {

		// Create Directory.
		if ( ! is_dir( $this->cache_dir ) )
			wp_mkdir_p( $this->cache_dir );

		$data = @file_get_contents( $min_src );

		if ( $data ) {

			$data = '/*' . implode( ',', $handles ) . '*/' . $data; 
			file_put_contents( $this->cache_dir . $min_handle . ( ( 'WP_Styles' === get_class( $this->class ) ) ? '.css' : '.js' ), $data );	
			return $this->cache_url . $min_handle . ( ( 'WP_Styles' === get_class( $this->class ) ) ? '.css' : '.js' );

		} else {

			throw new Exception( 'Error generating minified & concatenated file. Handles:' . implode( ', ', $handles ) );

		}
		
	}

	/**
	 * Delete all cached files.
	 * 
	 * @return null
	 * @todo This recursive iterator thing is PHP 5.3 only
	 */
	function delete_cache() { 
		
		if ( ! is_dir( $this->cache_dir ) )
			return;

		$files = new RecursiveIteratorIterator(
    		new RecursiveDirectoryIterator( $this->cache_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
    			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($files as $fileinfo) {
    		$todo = ( $fileinfo->isDir() ? 'rmdir' : 'unlink' );
    		$todo( $fileinfo->getRealPath() );
		}

		rmdir( $this->cache_dir );
		
	}

}
