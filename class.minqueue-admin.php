<?php

class MinQueue_Admin {

	// Plugin unique prefix. Used for options, filenames etc.
	private $prefix = 'minqueue';

	// Plugin options
	private $options;

	// Admin Notices.
	private $admin_notices;

	function __construct() {

		$this->prefix = apply_filters( 'minqueue_prefix', $this->prefix );
		$this->options = minqueue_get_options();

		$this->admin_notices = new MinQueue_Admin_Notices( $this->prefix );

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_notices', array( $this, 'display_admin_notices' ), 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

	}

	/**
	 * Add the adminmenu item
	 *
	 * @return null
	 */
	function admin_menu() {

		add_options_page( 'MinQueue Plugin Page', 'MinQueue', 'manage_options', 'minqueue', array( $this, 'options_page' ) );

	}

	/**
	 * Register plugin settings
	 *
	 * @return null
	 */
	function admin_init(){

		// Maybe clear cache
		if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'minqueue_clear_cache' ) )
			$this->clear_cache();

		register_setting( 'minqueue_options', 'minqueue_options', array( $this, 'validate_options' ) );

		add_settings_section( 'plugin_main', 'General Options', '__return_true', 'general_minify_options' );
		add_settings_section( 'plugin_main', 'Script Minification', '__return_true', 'script_minify_options' );
		add_settings_section( 'plugin_main', 'Style Minification', '__return_true', 'style_minify_options' );

		add_settings_field( 'minqueue_helper', 'Enable helper', array( $this, 'field_helper' ), 'general_minify_options', 'plugin_main' );
		add_settings_field( 'minqueue_clear_cache', 'Delete all cached files', array( $this, 'field_clear_cache' ), 'general_minify_options', 'plugin_main' );

		add_settings_field( 'minqueue_styles_method', 'Script minification method', array( $this, 'field_method_scripts' ), 'script_minify_options', 'plugin_main' );
		add_settings_field( 'minqueue_scripts', 'Script minification queue(s)', array( $this, 'field_scripts' ), 'script_minify_options', 'plugin_main' );

		add_settings_field( 'minqueue_styles_method', 'Style minification method', array( $this, 'field_method_styles' ), 'style_minify_options', 'plugin_main' );
		add_settings_field( 'minqueue_styles', 'Style minification queue(s)', array( $this, 'field_styles' ), 'style_minify_options', 'plugin_main' );

	}

	/**
	 * Output the main options page content.
	 *
	 * @return null
	 */
	function options_page() { ?>

		<div class="wrap">

			<h2>MinQueue Plugin Settings</h2>

			<form action="options.php" method="post">

				<?php

				settings_fields('minqueue_options');
				do_settings_sections('general_minify_options');
				do_settings_sections('script_minify_options');
				do_settings_sections('style_minify_options');

				?>

				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
				</p>

			</form>

		</div>

		<?php

	}

	/**
	 * Output clear cache button
	 *
	 * @return null
	 */
	function field_clear_cache() { ?>

		<a href="<?php echo wp_nonce_url( 'options-general.php?page=minqueue', 'minqueue_clear_cache' ); ?>" class="button" style="margin-right: 10px;">Clear Cache</a>

		<?php if ( $cached_files_count = $this->get_cached_files_count() ) : ?>
			<?php echo intval( $cached_files_count ); ?> files cached.
		<?php endif; ?>

	<?php }

	/**
	 * Output helper setting field.
	 *
	 * @return null
	 */
	function field_helper() {	?>

		<input type="checkbox" id="minqueue_options_helper" name="minqueue_options[helper]" <?php checked( true, ( ! ( ! isset( $this->options['helper'] ) || isset( $this->options['helper'] ) && $this->options['helper']  === false ) ) ); ?>/>
		<label for="minqueue_options_helper">Enable the helper in the front end of the site (only visible for site admin users).</label>

	<?php }

	/**
	 * Output settings section for scripts enable/disable toggle.
	 *
	 * @return null
	 */
	function field_method_scripts() {

		if ( empty( $this->options['scripts_method'] ) )
			$this->options['scripts_method'] = 'disabled';

		?>

		<input type="radio" id="minqueue_options_scripts_method_manual" name="minqueue_options[scripts_method]" value="manual" <?php checked( 'manual', $this->options['scripts_method'] ); ?>/> <label for="minqueue_options_scripts_method_manual">Enable script minification</label><br/>
		<input type="radio" id="minqueue_options_scripts_method_disabled" name="minqueue_options[scripts_method]" value="disabled" <?php checked( 'disabled', $this->options['scripts_method'] ); ?>/> <label for="minqueue_options_scripts_method_disabled">Disable script minification</label>

	<?php }

	/**
	 * Output settings section for scripts queue textarea/s
	 *
	 * @return null
	 */
	function field_scripts() {

		$values = ( ! empty( $this->options['scripts_manual'] ) ) ? $this->options['scripts_manual'] : array();

		?>

		<div id="field_manual_scripts">

			<label for="minqueue_field_manual_scripts_0">
				<p><span class="description">For each queue, provide a list of script handles that will be concatenated into one file and minified. (Comma separated or on a new line)</span></p>
				<p><span class="description">Multiple queues will be processed separately, creating a minified file for each. Note that scripts can be placed in the header or footer. If header &amp; footer scripts are added to the same queue, they will still be proccessed separately.</span></p>
			</label>

			<textarea id="minqueue_field_manual_scripts_hidden" name="minqueue_options[scripts_manual][]" class="large-text code input-template" style="display:none;"></textarea>

			<?php for ( $i = 0; $i < ( ( count( $values ) > 0 ) ? count( $values ) : 1 ); $i++ ) : ?>
				<?php if ( $i > 0 && empty( $values[$i]) ) continue; ?>
				<textarea id="minqueue_field_manual_scripts_<?php echo $i; ?>" name="minqueue_options[scripts_manual][]" class="large-text code"><?php echo ( ! empty( $values[$i] ) ) ? esc_attr( implode( ', ', $values[$i] ) ) : null; ?></textarea>
			<?php endfor; ?>

		</div>

		<div id="field_disabled_scripts">
			<span class="description">Script minification is disabled</span>
		</div>

		<?php

	}

	/**
	 * Output settings section for styles enable/disable toggle.
	 *
	 * @return null
	 */
	function field_method_styles() {

		if ( empty( $this->options['styles_method'] ) )
			$this->options['styles_method'] = 'disabled';

		?>

		<input type="radio" id="minqueue_options_styles_method_manual" name="minqueue_options[styles_method]" value="manual" <?php checked( 'manual', $this->options['styles_method'] ); ?>/> <label for="minqueue_options_styles_method_manual">Enable style minification</label><br/>
		<input type="radio" id="minqueue_options_styles_method_disabled" name="minqueue_options[styles_method]" value="disabled" <?php checked( 'disabled', $this->options['styles_method'] ); ?>/> <label for="minqueue_options_styles_method_disabled">Disable style minification</label>

	<?php }

	/**
	 * Output settings section for styles queue textarea/s
	 *
	 * @return null
	 */
	function field_styles() {

		$values = ( ! empty( $this->options['styles_manual'] ) ) ? $this->options['styles_manual'] : array();

		?>

		<div id="field_manual_styles">

			<label for="minqueue_field_manual_styles_0">
				<p><span class="description">List of style handles to minify and concatenate into one file. Comma separated or on a new line</span></p>
				<p><span class="description">Multiple queues will be processed separately, creating a minified file for each. Note that enqueued CSS files targeting different media will always be minified separately even if they are part of the same queue.</span></p>
			</label>

			<textarea id="minqueue_field_manual_styles_template" name="minqueue_options[styles_manual][]" class="large-text code input-template" style="display:none;"></textarea>

			<?php for ( $i = 0; $i < ( ( count( $values ) > 0 ) ? count( $values ) : 1 ); $i++ ) : ?>
				<?php if ( $i > 0 && empty( $values[$i]) ) continue; ?>
					<textarea id="minqueue_field_manual_styles_<?php echo $i; ?>" name="minqueue_options[styles_manual][]" class="large-text code"><?php echo ( ! empty( $values[$i] ) ) ? esc_attr( implode( ', ', $values[$i] ) ) : null; ?></textarea>
			<?php endfor; ?>

		</div>

		<div id="field_disabled_styles">
			<span class="description">Style minification is disabled</span>
		</div>

		<?php

	}

	/**
	 * Settings validation.
	 *
	 * @return null
	 */
	function validate_options( $input ) {

		if ( ! empty( $input['scripts_manual'] ) ) {
		
			foreach ( $input['scripts_manual'] as $key => $queue )
				$input['scripts_manual'][$key] = $this->validate_handle_list( $queue );

			// Remove empty & reset array keys.
			$input['scripts_manual'] = array_merge( array_filter( $input['scripts_manual'] ) );

		}
		
		if ( ! empty( $input['styles_manual'] ) ) {
		
			foreach ( $input['styles_manual'] as $key => $queue )
				$input['styles_manual'][$key] = $this->validate_handle_list( $queue );
	
			// Remove empty & reset array keys.
			$input['styles_manual'] = array_merge( array_filter( $input['styles_manual'] ) );

		}
		
		$input['helper'] = ( empty( $input['helper'] ) ) ? false : true;

		// If method is manual, and no manual handles are set, disable minification.
		if ( isset( $input['styles_method'] ) && 'manual' == $input['styles_method'] && empty( $input['styles_manual'] ) )
			unset( $input['styles_method'] );
		if ( isset( $input['scripts_method'] ) && 'manual' == $input['scripts_method'] && empty( $input['scripts_manual'] ) )
			unset( $input['scripts_method'] );

		// Delete empty fields
		foreach( $input as $key => $field )
			if ( empty( $field ) )
				unset( $input[$key] );

		return $input;

	}

	/**
	 * Validate the list of handles from the scripts & style queue textareas.
	 *
	 * Deal with new lines, spaces, double commas & convert to array.
	 *
	 * Return an array ready for saving.
	 *
	 * @param  string $list string of comma separated handles
	 * @return array       array of handles
	 */
	function validate_handle_list( $list ) {

		$list = str_replace( array( "\n", "\r" ), ',', $list );

		$list = explode(',', $list );

		foreach ( $list as &$item )
			$item = trim( $item );

		return array_filter( $list );

	}

	/**
	 * Enqueue all scripts required by the admin page
	 *
	 * @return null
	 */
	function enqueue_scripts( $hook ) {

		if ( 'settings_page_minqueue' !== $hook )
			return;

		wp_enqueue_script( 'minqueue-admin', trailingslashit( plugins_url( basename( __DIR__ ) ) ) . 'admin.js' );

	}

	/**
	 * Display Admin notices.
	 *
	 * Hook in on display admin notices, otherwise this is called when saving, before the redirect, causing the notice to be displayed and extra time.
	 *
	 * @return null
	 */
	function display_admin_notices() {

		$current_screen = get_current_screen();

		if ( isset( $this->options['helper'] ) && $this->options['helper'] === true )
			$this->admin_notices->add_notice( 'MinQueue helper is currently active', true );


		if ( 'settings_page_minqueue' == $current_screen->id ) {
			$this->admin_notices->delete_notice( 'minqueue_min_activation_notice' );
		}

	}

	/**
	 * Delete all cached files
	 *
	 * @param  boolean $redirect whether
	 * @return [type]            [description]
	 */
	function clear_cache( $redirect = true ) {

		// Delete the cache if requested.
		$minify = new MinQueue_Scripts();
		$minify->delete_cache();

		// Redirect.
		if ( $redirect ) {
			wp_redirect( remove_query_arg( '_wpnonce' ) );
			exit;
		}

	}

	/**
	 * Get number of cached files.
	 *
	 * @return int number of cached files.
	 */
	function get_cached_files_count() {

		$minify = new MinQueue_Scripts();
		return $minify->get_cached_files_count();

	}

}