<?php

abstract class MPH_Minify {

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

	// Reference to global record of everything minified
	private $minified_deps;

	// Reference to MPH_Admin_Notices class
	private $admin_notices;

	// Reference to WP_Scripts or WP_Styles. Must be a sub class of WP_Dependencies.
	protected $class;

	// File extension used for minified files.
	protected $file_extension;

	// Internal queue of assets to be minified. By group.
	private $process_queue = array();


	// Internal cache of group handles as they are slow to generate (hashes)
	private $group_handles = array();

	// Internal cache of handle src paths.
	private $asset_paths = array();

	/**
	 * Set things up.
	 *
	 * @param string $class Minify assets for this class.
	 */
	function __construct( $queue = array() ) {

		do_action( 'start_operation', '__construct' );

		global $minified_deps;

		$this->prefix        = apply_filters( 'mph_minify_prefix', $this->prefix );

		$wp_dir              = str_replace( home_url(), '', site_url() );
		$this->site_root     = str_replace( $wp_dir . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, ABSPATH );
		$this->site_root     = apply_filters( 'mph_minify_site_root', $this->site_root );

		$this->plugin_url    = apply_filters( 'mph_minify_plugin_url', plugins_url( '', __FILE__ ) );

		$uploads             = wp_upload_dir();
		$this->cache_dir     = trailingslashit( str_replace( $this->site_root, '', $uploads['basedir'] ) ) . $this->prefix . '-cache';
		$this->cache_dir     = apply_filters( 'mph_minify_cache_dir', $this->cache_dir );

		// Global record of everything minified.
		$this->minified_deps = &$minified_deps;

		$this->queue         = $queue;

		if ( empty( $this->class ) || ! empty( $this->class ) && ! is_subclass_of( $this->class, 'WP_Dependencies' ) )
			die( get_class( $this->class ) . ' does not extend WP_Dependencies' );

		do_action( 'end_operation', '__construct' );

	}

	/**
	 * Action! Run the minifier.
	 *
	 * @return null
	 */
	public function minify() {

		if ( empty( $this->class ) )
			return;

		// Get the queue of assets & Enqueue each group.
		foreach ( (array) $this->get_process_queue() as $group => $assets  ) {

			do_action( 'start_operation', 'enqueue_minified_group ' . $group );

			$this->enqueue_minified_group( $group );

			do_action( 'end_operation', 'enqueue_minified_group ' . $group );

		}

	}

	/**
	 * Get the queue of assets to be minified & concatenated
	 * Handles dependencies etc.
	 *
	 * @return array process_queue. An array of file handles.
	 */
	protected function get_process_queue() {

		do_action( 'start_operation', 'get_process_queue' );

		if ( empty( $this->process_queue ) ) {

			// Use a clone of the current class to avoid conflicts
			$_class = wp_clone( $this->class );

			// Remove from queue if not a registered asset.
			foreach ( $this->queue as $key => $handle )
				if ( ! array_key_exists( $handle, $_class->registered ) )
					unset( $this->queue[$key] );

			// If no scripts in the queue have been enqueued, don't proccess queue at all.
			$_class->all_deps( $_class->queue );
			$intersect = array_intersect( $_class->to_do, $this->queue );
			if ( empty( $intersect ) )
				return array();

			// Set up the todos according to our queue - do this to handle dependencies.
			$_class->to_do = array();
			$_class->all_deps( $this->queue );

			do_action( 'start_operation', 'build queue' );
	  		foreach ( $_class->to_do as $key => $handle ) {

				// If not in queue - skip (eg if is in queue because it is a dependency of another file)
				// Skip if no asset path (eg is remote.)
				if ( ! in_array( $handle, $this->queue ) || ! $this->get_asset_path( $handle ) )
					continue;

				$group = $this->get_handle_group( $handle );

				$this->process_queue[$group][] = $handle;

			}
			do_action( 'end_operation', 'build queue' );

		}

		do_action( 'end_operation', 'get_process_queue' );

		return $this->process_queue;

	}

	/**
	 * Process Group.
	 *
	 * Enqueue cached minified file or create one and enqueue that.
	 *
	 * @param  int $group Group identifier
	 * @return null
	 */
	private function enqueue_minified_group( $group ) {

		// Unique handle used as filename. (hash of the current group & version info)
		$group_handle = $this->get_group_handle( $group );
		$group_filename = $group_handle . $this->file_extension;

		$min_path     = trailingslashit( $this->site_root . $this->cache_dir ) . $group_filename;
		$min_src      = trailingslashit( home_url( '/' ) . $this->cache_dir ) . $group_filename;

		// If no cached file - generate minified asset src.
		if ( ! file_exists( $min_path ) ) {

			if ( $this->cache ) {
				do_action( 'start_operation', 'get_cache_file' );
				$min_src = $this->get_cache_file( $group, $group_handle );
				do_action( 'end_operation', 'get_cache_file' );
			} else {
				$min_src = $this->get_group_minify_src( $group );
			}

		}

		// If no $min_src - eg generating minified file, fall back to default.
		if ( empty ( $min_src ) )
			return;

		// Mark the minified assets as done so they are not done again.
		// Keep a global record of all minified assets
		foreach ( $this->process_queue[$group] as $handle ) {

			$this->class->to_do = array_diff( $this->class->to_do, array( $handle ) );
			$this->class->done[] = $handle;

			$this->minified_deps[ get_class( $this->class ) ][ $handle ] = $group_handle;

		}

		// Get dependencies of this group.
		$deps = $this->get_group_deps( $group );

		// Enqueue the minified file
		$this->enqueue( $group_handle, $min_src, $deps, null, $group );

		// Set up dependencies for this group.
		$this->setup_all_deps( $group );

	}

	/**
	 * Enqueue file.
	 *
	 * @param  string  $group_handle
	 * @param  string  $min_src
	 * @param  array   $deps
	 * @param  string  $ver
	 * @param  string  $group
	 * @return null
	 */
	function enqueue( $group_handle, $min_src, $deps = array(), $ver = null, $group = null ) {

		$this->class->add( $group_handle, $min_src, $deps, $ver );
		$this->class->add_data( $group_handle, 'group', $group );
		$this->class->enqueue( $group_handle );

	}

	/**
	 * Get Group
	 *
	 * Return the group for a given item handle
	 *
	 * @param string handle
	 * @return string group
	 */
	function get_handle_group( $handle ) {

		return (string) isset( $this->class->registered[$handle]->extra['group'] ) ? $this->class->registered[$handle]->extra['group'] : '0';

	}

	/**
	 * Get Dependencies of this group.
	 *
	 * All dependencies of files contained within this file.
	 *
	 * @param  int $group the group of handles currently being processed.
	 * @return arary of handles that are dependencies of the current minify group.
	 */
	private function get_group_deps( $group ) {

		do_action( 'start_operation', 'get_group_deps' );

		// Add any deps of assets in queue that are not themselves part of this queue as a dependency of the minified/concatenated file.
		$deps = array();
		foreach ( $this->process_queue[$group] as $handle )
			foreach ( $this->class->registered[$handle]->deps as $dep )
				if ( ! in_array( $dep, $this->process_queue[$group] ) && ! in_array( $dep, $deps ) )
					$deps[] = $dep;

		do_action( 'end_operation', 'get_group_deps' );

		return $deps;

	}

	/**
	 * Set up all dependencies for a group.
	 *
	 * Minifying and concatenating removes items from the queue.
	 * We need to modify dependencies of registered assets that have a file within this group.
	 *
	 * @param  int $group the group of handles currently being processed.
	 * @return arary of handles that are dependencies of the current minify group.
	 */
	private function setup_all_deps( $group ) {

		// Debug (Timestack)
		do_action( 'start_operation', 'setup_all_deps' );

		$group_handle = $this->get_group_handle( $group );

		// If any deps of this file are themselves part of another minified file, remove it and add that min file as a dep of this one.
		foreach ( $this->class->registered as &$dependency ) {

			// If any of the assets in this file are dependencies of any other registered files, we need to add the minified file as a dependancy.
			if ( ! empty( $dependency->deps ) )
				if ( array_intersect( $dependency->deps, $this->process_queue[$group] ) )
					$dependency->deps[] = $group_handle;

			foreach ( $dependency->deps as $key => $dep )
				if ( array_key_exists( $dep, (array) $this->minified_deps[ get_class( $this->class ) ] ) ) {
					unset( $dependency->deps[$key] );
					if ( ! in_array( $this->minified_deps[ get_class( $this->class ) ][$dep], $dependency->deps ) )
						$dependency->deps[] = $this->minified_deps[ get_class( $this->class ) ][$dep];
				}

		}

		// Debug (Timestack)
		do_action( 'end_operation', 'setup_all_deps' );

	}

	/**
	 * Get Unique Group Handle
	 *
	 * Handle is a crc32b hash of all handles & version numbers.
	 * If $this->checks_last_modified, also checks last modified times of files.
	 *
	 * @param  [type] $group [description]
	 * @return [type]        [description]
	 */
	private function get_group_handle( $group ) {

		// Debug (Timestack)
		do_action( 'start_operation', 'get_group_handle' );

		if ( empty( $this->group_handles[$group] ) ) {

			$data = array();
			foreach( $this->process_queue[$group] as $handle ) {

				$data[$handle] = array( 'version' => $this->class->registered[$handle]->ver );

				do_action( 'start_operation', 'check file modified time' );
				if ( $this->checks_last_modified )
					$data[$handle]['modified'] = filemtime( $this->site_root .  $this->get_asset_path( $handle ) );
				do_action( 'end_operation', 'check file modified time' );

			}

			do_action( 'start_operation', 'do hashes' );
			$this->group_handles[$group] = $this->prefix . '-' . hash( 'crc32', serialize( $this->process_queue[$group] ) ) . '-' . hash( 'crc32', serialize( $data ) );
			do_action( 'end_operation', 'do hashes' );

		}

		// Debug (Timestack)
		do_action( 'end_operation', 'get_group_handle' );

		return $this->group_handles[$group];

	}

	/**
	 * Get the on the fly minify generator src for current group
	 *
	 * Returns the URL used to generate the minifyied & concatenated file for a given group
	 *
	 * @param  int $group Group
	 * @return string SRC of on the fly minfy file
	 */
	private function get_group_minify_src( $group ) {

		// Debug (Timestack)
		do_action( 'start_operation', 'get_group_minify_src' );

		// Get array of srcs.
		$_srcs = array();
		foreach ( $this->process_queue[$group] as $handle )
			if ( $_src = $this->get_asset_path( $handle ) )
				$_srcs[] = $_src;

		// If no srcs to be minified, just stop all this right now.
		if ( empty( $_srcs ) )
			$r = null;

		else
			$r = trailingslashit( $this->plugin_url ) . 'php-minify/min/' . '?f=' . implode( ',', array_filter( $_srcs ) );

		// Debug (Timestack)
		do_action( 'end_operation', 'get_group_minify_src' );

		return $r;

	}

	/**
	 * Return the path to an asset relative to the site root
	 *
	 * @todo this can be a little slow.
	 *
	 * @param  string $handle handle of the item
	 * @return string - root relative path of the item src.
	 */
	private function get_asset_path( $handle ) {

		// If the path has already been calculated, return that.
		if ( array_key_exists( $handle, $this->asset_paths ) )
			return $this->asset_paths[ $handle ];

		// Don't try and process unregistered files.
		if ( empty( $this->class->registered[$handle] ) )
			return;

		$src = $this->class->registered[$handle]->src;

		// Maybe prepend base url.
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

		$this->asset_paths[ $handle ] = str_replace( home_url(), '', esc_url( $src ) );

		return $this->asset_paths[ $handle ];

	}

	/**
	 * Create Cache file.
	 *
	 * @param  int $group id of group
	 * @param  string $group_handle group handle. Only passed to avoid having to hash again.
	 * @return string           src of cache file.
	 */
	private function get_cache_file( $group, $group_handle ) {

		if ( ! $min_src = $this->get_group_minify_src( $group ) )
			return;

		$this->delete_cache_by_group( $group );

		// Create Directory.
		if ( ! is_dir( $this->site_root . $this->cache_dir ) )
			if ( false === wp_mkdir_p( $this->site_root . $this->cache_dir ) ) {
				$this->add_admin_notice( 'MPH Minify was unable to create the cache directory: ' . $this->site_root . $this->cache_dir, false, 'error' );
				return;
			}

		$data = @file_get_contents( $min_src );

		if ( false === $data ) {

			// If error, display admin error notice.
			$this->add_admin_notice( 'There was an error generating the minified file. Failed processing handles: ' . implode( ', ', $this->process_queue[$group] ), false, 'error' );
			return;

		}

		$data = '/*' . implode( ', ', $this->process_queue[$group] ) . '*/ ' . $data;
		$file = trailingslashit( $this->site_root . $this->cache_dir ) . $group_handle . ( ( 'WP_Styles' === get_class( $this->class ) ) ? '.css' : '.js' );

		if ( false === @file_put_contents( $file , $data ) ) {

			$this->add_admin_notice( 'MPH Minify was unable to create the file: ' . $file . ' for handles ' . implode( ', ', $this->process_queue[$group] ), false, 'error' );
			return;

		}

		return home_url( '/' ) . trailingslashit( $this->cache_dir ) . $group_handle . ( ( 'WP_Styles' === get_class( $this->class ) ) ? '.css' : '.js' );

	}

	/**
	 * Delete all cached files.
	 *
	 * @return null
	 * @todo This recursive iterator thing is PHP 5.3 only
	 */
	public function delete_cache() {

		$cache_dir_path = $this->site_root . $this->cache_dir;

		if ( ! is_dir( $cache_dir_path ) ) {
			$this->add_admin_notice( 'Cache empty.', true );
			return;
		}

		$files = new RecursiveIteratorIterator(
    		new RecursiveDirectoryIterator( $cache_dir_path, RecursiveDirectoryIterator::SKIP_DOTS ),
    			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($files as $fileinfo) {
    		$todo = ( $fileinfo->isDir() ? 'rmdir' : 'unlink' );
    		$todo( $fileinfo->getRealPath() );
		}

		rmdir( $cache_dir_path );

		$this->add_admin_notice( 'Cache cleared.', true );

	}

	private function delete_cache_by_group( $group ) {

		$group_handle = $this->get_group_handle( $group );

		$group_handle_hash = reset( explode( '-', str_replace( $this->prefix . '-', '', $group_handle ) ) );

		$cache_path = $this->site_root . $this->cache_dir;

		if ( is_dir( $cache_path ) ) {
	 		foreach( scandir( $cache_path ) as $cached_file ) {

	 			if ( strlen( $this->prefix . '-' ) === strpos( $cached_file, $group_handle_hash ) )
	 				unlink( $cache_path . DIRECTORY_SEPARATOR . $cached_file );

	 		}
	 	}

	}

	/**
	 * Get number of cached files.
	 *
	 * @return int number of cached files.
	 */
	public function get_cached_files_count() {

		$dir = $this->site_root . $this->cache_dir;

		if ( is_dir( $dir ) )
	 		return count( array_filter( scandir( $dir ), create_function( '$value', 'return ( \'.\' === $value || \'..\' === $value ) ? false : true;' ) ) );

	}

	/**
	 * Creates an admin notice - saved in options to be shown in the admin, until dismissed.
	 *
	 * @param string $new_notice Message content
	 * @param string $type Message type - added as a class to the message when displayed. Reccommended to use: updated, error.
	 * @param bool $display_once Display message once, or require manual dismissal.
	 */
	private function add_admin_notice( $new_notice, $display_once = false, $type = 'updated' ) {

		if ( ! $this->admin_notices )
			$this->admin_notices = new MPH_Admin_Notices( $this->prefix );

		$this->admin_notices->add_notice( $new_notice, $display_once, $type );

	}


}

/**
 * Minify Scripts
 *
 * Handle script localization.
 */
class MPH_Minify_Scripts extends MPH_Minify {

	// Array of script Localization data.
	public $script_localization = array();

	function __construct( $queue = array() ) {

		global $wp_scripts;

		$this->class = &$wp_scripts;
		$this->file_extension = '.js';

		parent::__construct( $queue );

		// Add the localization data to the head. Do it as early as possible.
		add_action( 'wp_print_scripts', array( $this, 'script_localization' ), 1000 );

	}

	function get_process_queue () {

		$this->process_queue = parent::get_process_queue();

		// Get localized script data.
		foreach( $this->process_queue as $group => $script_handles )
			foreach( $script_handles as $handle )
				if ( ! empty( $this->class->registered[$handle]->extra['data'] ) )
					$this->script_localization[ $handle ] = $this->class->registered[$handle]->extra['data'];

		return $this->process_queue;

	}

	/**
	 * Localize the minified scripts. Echo script tags in the head.
	 *
	 * @return null
	 * @todo - Unfortunately we cannot just localize the minified file using this data but could maybe add this using the wp_scripts class sett print_inline_style().
	 */
	public function script_localization() {

		foreach ( $this->script_localization as $handle => $data )
			echo '<script>' . $data . '</script>';

	}

}

/**
 * Minify Styles
 *
 * Groups are slightly different from scripts as we use media attributes as a group identifier.
 */
class MPH_Minify_Styles extends MPH_Minify {

	function __construct( $queue = array() ) {

		global $wp_styles;

		$this->class = &$wp_styles;
		$this->file_extension = '.css';

		parent::__construct( $queue );

	}

	/**
	 * Get Group
	 *
	 * For styles, return the media arg.
	 *
	 * @param string handle
	 * @return string group
	 */
	function get_handle_group( $handle ) {

		return (string) ! empty( $this->class->registered[$handle]->args ) ? $this->class->registered[$handle]->args : '0';

	}

	/**
	 * Enqueue style.
	 *
	 * Use wp_enqueue_style as groups is used to handle media attribute.
	 *
	 * @param  string $group_handle
	 * @param  string $min_src      [description]
	 * @param  array  $deps         [description]
	 * @param  string $ver          [description]
	 * @param  string $group        [description]
	 * @return null
	 */
	function enqueue( $group_handle, $min_src, $deps = array(), $ver = null, $group = null ) {

		wp_enqueue_style( $group_handle, $min_src, $deps, null, $group );

	}

}
