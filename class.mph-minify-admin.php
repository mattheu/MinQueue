<?php

class MPH_Minify_Admin {

	// Plugin unique prefix. Used for options, filenames etc.
	private $prefix = 'mph-min';

	// Plugin options
	private $options;

	// Admin Notices.
	private $admin_notices;

	function __construct() {

		$this->prefix = apply_filters( 'mph_minify_prefix', $this->prefix );
		$this->options = mph_minify_get_options();

		$this->admin_notices = new MPH_Admin_Notices( $this->prefix );

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

		add_options_page( 'MPH Minify Plugin Page', 'MPH Minify', 'manage_options', 'mph_minify', array( $this, 'options_page' ) );

	}

	/**
	 * Register plugin settings
	 *
	 * @return null
	 */
	function admin_init(){

		// Maybe clear cache
		if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'mph_minify_clear_cache' ) )
			$this->clear_cache();

		register_setting( 'mph_minify_options', 'mph_minify_options', array( $this, 'validate_options' ) );

		add_settings_section( 'plugin_main', 'General Options', '__return_true', 'general_minify_options' );
		add_settings_section( 'plugin_main', 'Script Minification', '__return_true', 'script_minify_options' );
		add_settings_section( 'plugin_main', 'Style Minification', '__return_true', 'style_minify_options' );

		add_settings_field( 'mph_minify_debugger', 'Enable debugger', array( $this, 'field_debugger' ), 'general_minify_options', 'plugin_main' );
		add_settings_field( 'mph_minify_clear_cache', 'Delete all cached files', array( $this, 'field_clear_cache' ), 'general_minify_options', 'plugin_main' );

		add_settings_field( 'mph_minify_styles_method', 'Script minification method', array( $this, 'field_method_scripts' ), 'script_minify_options', 'plugin_main' );
		add_settings_field( 'mph_minify_scripts', 'Script minification queue', array( $this, 'field_scripts' ), 'script_minify_options', 'plugin_main' );

		add_settings_field( 'mph_minify_styles_method', 'Style minification method', array( $this, 'field_method_styles' ), 'style_minify_options', 'plugin_main' );
		add_settings_field( 'mph_minify_styles', 'Style minification queue', array( $this, 'field_styles' ), 'style_minify_options', 'plugin_main' );

	}

	/**
	 * Output the main options page content.
	 *
	 * @return null
	 */
	function options_page() { ?>

		<div class="wrap">

			<h2>MPH Minify Plugin Settings</h2>

			<form action="options.php" method="post">

				<?php

				settings_fields('mph_minify_options');
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

		<a href="<?php echo wp_nonce_url( 'options-general.php?page=mph_minify', 'mph_minify_clear_cache' ); ?>" class="button" style="margin-right: 10px;">Clear Cache</a>

		<?php if ( $cached_files_count = $this->get_cached_files_count() ) : ?>
			<?php echo $cached_files_count; ?> files cached.
		<?php endif; ?>

	<?php }

	/**
	 * Output debugger setting field.
	 *
	 * @return null
	 */
	function field_debugger() {	?>

		<input type="checkbox" id="mph_minify_options_debugger" name="mph_minify_options[debugger]" <?php checked( true, ( ! ( ! isset( $this->options['debugger'] ) || isset( $this->options['debugger'] ) && $this->options['debugger']  === false ) ) ); ?>/>
		<label for="mph_minify_options_debugger">Enable the debugger in the front end of the site. Note: visible for logged out users.</label>

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

		<input type="radio" id="mph_minify_options_scripts_method_manual" name="mph_minify_options[scripts_method]" value="manual" <?php checked( 'manual', $this->options['scripts_method'] ); ?>/> <label for="mph_minify_options_scripts_method_manual">Manual minification</label><br/>
		<input type="radio" id="mph_minify_options_scripts_method_disabled" name="mph_minify_options[scripts_method]" value="disabled" <?php checked( 'disabled', $this->options['scripts_method'] ); ?>/> <label for="mph_minify_options_scripts_method_disabled">Disable minification</label>

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

			<label for="mph_minify_field_manual_scripts">
				<p><span class="description">List of script handles to minify and concatenate into one file. Comma separated or on a new line</span></p>
				<p><span class="description">Multiple queues will be processed separately, creating multiple processed files.</span></p>
			</label>

			<textarea id="mph_minify_field_manual_scripts_hidden" name="mph_minify_options[scripts_manual][]" class="large-text code input-template" style="display:none;"></textarea>

			<?php for ( $i = 0; $i < ( ( count( $values ) > 0 ) ? count( $values ) : 1 ); $i++ ) : ?>
				<?php if ( $i > 0 && empty( $values[$i]) ) continue; ?>
				<textarea id="mph_minify_field_manual_scripts_<?php echo $i; ?>" name="mph_minify_options[scripts_manual][]" class="large-text code"><?php echo ( ! empty( $values[$i] ) ) ? esc_attr( implode( ', ', $values[$i] ) ) : null; ?></textarea>
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

		<input type="radio" id="mph_minify_options_styles_method_manual" name="mph_minify_options[styles_method]" value="manual" <?php checked( 'manual', $this->options['styles_method'] ); ?>/><label for="mph_minify_options_styles_method_manual"> Manual minification</label><br/>
		<input type="radio" id="mph_minify_options_styles_method_disabled" name="mph_minify_options[styles_method]" value="disabled" <?php checked( 'disabled', $this->options['styles_method'] ); ?>/> <label for="mph_minify_options_styles_method_disabled">Disable minification</label>

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

			<label for="mph_minify_field_manual_styles">
				<p><span class="description">List of style handles to minify and concatenate into one file. Comma separated or on a new line</span></p>
				<p><span class="description">Multiple queues will be processed separately, creating multiple processed files.</span></p>
			</label>

			<textarea id="mph_minify_field_manual_styles_template" name="mph_minify_options[styles_manual][]" class="large-text code input-template" style="display:none;"></textarea>

			<?php for ( $i = 0; $i < ( ( count( $values ) > 0 ) ? count( $values ) : 1 ); $i++ ) : ?>
				<?php if ( $i > 0 && empty( $values[$i]) ) continue; ?>
					<textarea id="mph_minify_field_manual_styles_<?php echo $i; ?>" name="mph_minify_options[styles_manual][]" class="large-text code"><?php echo ( ! empty( $values[$i] ) ) ? esc_attr( implode( ', ', $values[$i] ) ) : null; ?></textarea>
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

		foreach ( $input['scripts_manual'] as $key => $queue )
			$input['scripts_manual'][$key] = $this->validate_handle_list( $queue );

		foreach ( $input['styles_manual'] as $key => $queue )
			$input['styles_manual'][$key] = $this->validate_handle_list( $queue );

		// Remove empty & reset array keys.
		$input['scripts_manual'] = array_merge( array_filter( $input['scripts_manual'] ) );
		$input['styles_manual'] = array_merge( array_filter( $input['styles_manual'] ) );

		$input['debugger'] = ( empty( $input['debugger'] ) ) ? false : true;

		// If method is manual, and no manual handles are set, disable minification.
		if ( 'manual' == $input['styles_method'] && empty( $input['styles_manual'] ) )
			unset( $input['styles_method'] );
		if ( 'manual' == $input['scripts_method'] && empty( $input['scripts_manual'] ) )
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

		if ( 'settings_page_mph_minify' !== $hook )
			return;

		wp_enqueue_script( 'mph-admin', trailingslashit( plugins_url( basename( __DIR__ ) ) ) . 'admin.js' );

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

		if ( isset( $this->options['debugger'] ) && $this->options['debugger'] === true )
			$this->admin_notices->add_notice( 'MPH Minify debugger is currently active', true );


		if ( 'settings_page_mph_minify' == $current_screen->id ) {
			$this->admin_notices->delete_notice( 'mph_min_activation_notice' );
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
		$minify = new MPH_Minify_Scripts();
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

		$minify = new MPH_Minify_Scripts();
		return $minify->get_cached_files_count();

	}

}