<?php

class MPH_Minify {

	// Prefix
	private $prefix = 'mph-min';

	// Cache minified files or do it on the fly.
	public $cache = true;

	// Check file modified date also when generating new filename.
	public $checks_last_modified = true;

	// Array of handles to process.
	public $queue = array();

	// URL of the plugin directory.
	private $plugin_url;

	// Root relative path of the cache directory
	private $cache_dir;

	// Internal reference to global record of everything minified
	private $minified_deps;

	// Internal Reference to WP_Scripts or WP_Styles. Must be a sub class of WP_Dependencies.
	private $class;

	// Internal queue of assets to be minified. By group.
	private $process_queue = array();

	// Array of script Localization data.
	private $script_localization = array();

	/**
	 * Set things up.
	 *
	 * @param string $class Minify assets for this class.
	 */
	function __construct( $class_name ) {

		global $wp_scripts, $wp_styles, $minified_deps;

		$this->prefix        = apply_filters( 'mph_minify_prefix', $this->prefix );

		$wp_dir 		     = str_replace( home_url(), '', site_url() );
		$this->site_root     = str_replace( "$wp_dir" . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, ABSPATH );
		$this->site_root     = apply_filters( 'mph_minify_site_root', $this->site_root );

		$this->plugin_url    = apply_filters( 'mph_minify_plugin_url', trailingslashit( plugins_url( basename( __DIR__ ) ) ) );

		$uploads             = wp_upload_dir();
		$this->cache_dir     = trailingslashit( str_replace( $this->site_root, '', $uploads['basedir'] ) ) . $this->prefix . '-cache';
		$this->cache_dir     = apply_filters( 'mph_minify_cache_dir', $this->cache_dir );

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
			$this->enqueue_minified_group( $group );

		// Add the localization data to the head. Do it as early as possible.
		if ( ! empty( $this->script_localization ) )
			add_action( 'wp_head', array( $this, 'script_localization' ), 2 );

	}

	/**
	 * Get the queue of assets to be minified & concatenated
	 *
	 * @return array asset queue. An array of classes. Contains array of groups. contains array of asset handles.
	 */
	function get_asset_queue() {

		if ( empty( $this->asset_queue ) ) {

			// Use a clone of the current class to avoid conflicts
			$_class = wp_clone( $this->class );
			$_class->all_deps( $_class->queue );

			// Remove from queue if not a registered asset.
			foreach ( $this->queue as $key => $handle )
				if ( ! array_key_exists( $handle, $_class->registered ) )
					unset( $this->queue[$key] );

			// If no scripts in the queue have been enqueued, don't proccess queue at all.
			$intersect = array_intersect( $_class->to_do, $this->queue );
			if ( empty( $intersect ) )
				return array();

			// Set up the todos according to our queue - do this to handle dependencies.
			$_class->to_do = array();
			$_class->all_deps( $this->queue );

	  		foreach ( $_class->to_do as $key => $handle ) {

				// If not in queue - skip (eg if is in queue because it is a dependency of another file)
				// Skip if no asset path (eg is remote.)
				if ( ! in_array( $handle, $this->queue ) || ! $this->get_asset_path( $handle ) )
					continue;

				$this->asset_queue[$_class->groups[$handle]][] = $handle;

				// If this asset is localized, store that data.
				if ( ! empty( $_class->registered[$handle]->extra['data'] ) )
					$this->script_localization[ $handle ] = $_class->registered[$handle]->extra['data'];

			}

		}

		return $this->asset_queue;

	}

	/**
	 * Process Group.
	 *
	 * Enqueue cached minified file or create one and enqueue that.
	 *
	 * @param  int $group Group identifier
	 * @return null
	 */
	function enqueue_minified_group( $group ) {

		// Handle used as filename. It is a crc32 hash of the current group asset queue - contains version numbers
		$group_handle = $this->get_group_handle( $group );
		$min_src      = trailingslashit( $this->cache_url ) . $group_handle . ( ( 'WP_Styles' === get_class( $this->class ) ) ? '.css' : '.js' );
		$min_path     = trailingslashit( $this->cache_dir ) . $group_handle . ( ( 'WP_Styles' === get_class( $this->class ) ) ? '.css' : '.js' );

		// If no cached file - generate minified asset src.
		if ( ! file_exists( $min_path ) ) {

			if ( $this->cache )
				$min_src = $this->get_cache_file( $group, $group_handle );
			else
				$min_src = $this->get_group_minify_src( $group );

		}

		// If no $min_src - eg generating minified file, fall back to default.
		if ( empty ( $min_src ) )
			return;

		// Mark the minified assets as done so they are not done again.
		// Keep a global record of all minified assets
		foreach ( $this->asset_queue[$group] as $handle ) {

			$this->class->to_do = array_diff( $this->class->to_do, array( $handle ) );
			$this->class->done[] = $handle;

			$this->minified_deps[ get_class( $this->class ) ][ $handle ] = $group_handle;

		}

		// Get dependencies of this group.
		$deps = $this->get_group_deps( $group );

		// Enqueue the minified file
		$this->class->add( $group_handle, $min_src, $deps, null );
		$this->class->add_data( $group_handle, 'group', $group );
		$this->class->enqueue( $group_handle );

		// Set up dependencies for this group.
		$this->setup_all_deps( $group );

	}

	/**
	 * Get Dependencies of this group.
	 *
	 * All dependencies of files contained within this file.
	 *
	 * @param  int $group the group of handles currently being processed.
	 * @return arary of handles that are dependencies of the current minify group.
	 */
	function get_group_deps( $group ) {

		// Add any deps of assets in queue that are not themselves part of this queue as a dependency of the minified/concatenated file.
		$deps = array();
		foreach ( $this->asset_queue[$group] as $handle )
			foreach ( $this->class->registered[$handle]->deps as $dep )
				if ( ! in_array( $dep, $this->asset_queue[$group] ) && ! in_array( $dep, $deps ) )
					$deps[] = $dep;

		return $deps;

	}

	/**
	 * Set up all dependencies for a group.
	 *
	 * Minifying and concatenating removes handles from the queue. Causes problems with dependencies.
	 * Modify dependencies of registered assets that have a file within this group.
	 *
	 * @param  int $group the group of handles currently being processed.
	 * @return arary of handles that are dependencies of the current minify group.
	 */
	function setup_all_deps( $group ) {

		// If any of the assets in this file are dependencies of any other registered files, we need to add the minified file as a dependancy.
		foreach ( $this->class->registered as &$asset )
			if ( ! empty( $asset->deps ) )
				if ( array_intersect( $asset->deps, $this->asset_queue[$group] ) )
					$asset->deps[] = $this->get_group_handle( $group );

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

	function get_group_handle( $group ) {

		$data = array();
		foreach( $this->asset_queue[$group] as $handle ) {

			$data[$handle] = array( 'version' => $this->class->registered[$handle]->ver );

			if ( $this->checks_last_modified )
				$data[$handle]['modified'] = filemtime( $this->site_root .  $this->get_asset_path( $handle ) );

		}

		return $this->prefix . hash( 'crc32b', serialize( $data ) );

	}

	/**
	 * Get the on the fly minify generator src for current group
	 *
	 * Returns the URL used to generate the minifyied & concatenated file for a given group
	 *
	 * @param  int $group Group
	 * @return string SRC of on the fly minfy file
	 */
	function get_group_minify_src( $group ) {

		// Get array of srcs.
		$_srcs = array();
		foreach ( $this->asset_queue[$group] as $handle )
			if ( $_src = $this->get_asset_path( $handle ) )
				$_srcs[] = $_src;

		// If no srcs to be minified, just stop all this right now.
		if ( empty( $_srcs ) )
			return;

		return $this->minify_url . '/?f=' . implode( ',', array_filter( $_srcs ) );

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

		if ( 'WP_Scripts' == get_class( $this->class ) )
			$src = apply_filters( 'script_loader_src', $src, $handle );
		elseif ( 'WP_Styles' == get_class( $this->class ) )
			$src = apply_filters( 'style_loader_src', $src, $handle );

		// Strip query args.
		$src = strtok( $src, '?' );

		// Don't handle remote urls.
		if ( 0 !== strpos( $src, home_url() ) )
			return;

		return str_replace( home_url(), '', esc_url( $src ) );

	}

	/**
	 * Create Cache file.
	 *
	 * @param  string $filename name used to create file. A hash of args.
	 * @param  array  $srcs     srcs of assets.
	 * @return string           src of cache file.
	 */
	function get_cache_file( $group, $group_handle ) {

		if ( ! $min_src = $this->get_group_minify_src( $group ) )
			return;

		// Create Directory.
		if ( ! is_dir( $this->cache_dir ) )
			wp_mkdir_p( $this->cache_dir );

		$data = @file_get_contents( $min_src );

		if ( ! $data ) {

			// If error, display admin error notice.
			$this->add_admin_notice( 'There was an error generating the minified file. Failed processing handles: ' . implode( ', ', $this->asset_queue[$group] ), 'error' );

			return;

		}

		$data = '/*' . implode( ', ', $this->asset_queue[$group] ) . '*/ ' . $data;
		file_put_contents( $this->cache_dir . $group_handle . ( ( 'WP_Styles' === get_class( $this->class ) ) ? '.css' : '.js' ), $data );

		return $this->cache_url . $group_handle . ( ( 'WP_Styles' === get_class( $this->class ) ) ? '.css' : '.js' );

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


	/**
	 * Creates an admin notice - saved in options to be shown in the admin, until dismissed.
	 *
	 * @param string $new_notice Message content
	 * @param string $type Message type - added as a class to the message when displayed. Reccommended to use: updated, error.
	 * @param bool $display_once Display message once, or require manual dismissal.
	 */
	function add_admin_notice( $new_notice, $type = 'updated', $display_once = false ) {

		$admin_notices = get_option( 'mph_minify_notices', array() );

		if ( ! in_array( $notice = array( 'type' => $type, 'message' => $new_notice, 'display_once' => $display_once ), $admin_notices ) )
			$admin_notices[] = $notice;

		update_option( 'mph_minify_notices', $admin_notices );

	}

}
