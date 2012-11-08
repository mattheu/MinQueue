<?php

class MPH_Minify_Admin {

	var $options = '';
	var $notices = array();

	function __construct() {

		$this->options = mph_minify_get_plugin_options();

		add_action( 'admin_init', array( $this, 'init' ) );

		add_action( 'admin_menu', array( $this, 'admin_add_page' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );

		add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );

	}

	/**
	 * Admin Init
	 *
	 * Everything that needs hooking in here, goes here!
	 *
	 */
	function init() {

		// Maybe clear cache
		if ( isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'mph_minify_clear_cache' ) )
			$this->clear_cache();

	}

	/**
	 * Add the options page
	 * @return null
	 */
	function admin_add_page() {

		// add the admin options page
		add_options_page( 'MPH Minify Plugin Page', 'MPH Minify', 'manage_options', 'mph_minify', array( $this, 'options_page' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );

	}

	/**
	 * Register plugin settings
	 * @return null
	 */
	function admin_init(){

		register_setting( 'mph_minify_options', 'mph_minify_options', array( $this, 'options_validate' ) );

		add_settings_section( 'plugin_main', 'General Options', array( $this, 'general_options_text' ), 'general_minify_options' );
		add_settings_section( 'plugin_main', 'Script Minification', array( $this, 'general_options_text' ), 'script_minify_options' );
		add_settings_section( 'plugin_main', 'Style Minification', array( $this, 'general_options_text' ), 'style_minify_options' );

		add_settings_field( 'mph_minify_debugger', 'Enable debugger', array( $this, 'field_debugger' ), 'general_minify_options', 'plugin_main' );
		add_settings_field( 'mph_minify_clear_cache', 'Delete all cached files', array( $this, 'field_clear_cache' ), 'general_minify_options', 'plugin_main' );

		add_settings_field( 'mph_minify_styles_method', 'Script minification method', array( $this, 'field_method_scripts' ), 'script_minify_options', 'plugin_main' );
		add_settings_field( 'mph_minify_scripts', 'Script minification settings', array( $this, 'field_scripts' ), 'script_minify_options', 'plugin_main' );

		add_settings_field( 'mph_minify_styles_method', 'Style minification method', array( $this, 'field_method_styles' ), 'style_minify_options', 'plugin_main' );
		add_settings_field( 'mph_minify_styles', 'Style minification settings', array( $this, 'field_styles' ), 'style_minify_options', 'plugin_main' );

	}

	/**
	 * Output the main options page content.
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
	 * Output general options description text
	 *
	 * @return null
	 */
	function general_options_text() {}

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
	 * Output script method inputs.
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
	 * Output settings section for scripts.
	 *
	 * @return null
	 */
	function field_scripts() {

		$values = ( ! empty( $this->options['scripts_manual'] ) ) ? $this->options['scripts_manual'] : array();

		?>

		<div id="field_manual_scripts">

			<label for="mph_minify_field_manual_scripts">
				<strong>Minfy & Concatenate Queue</strong>
				<span class="description">List of script handles to minify and concatenate into one file. Comma separated or on a new line</span>
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
	 * Output settings section for styles.
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

	function field_styles() {

		$values = ( ! empty( $this->options['styles_manual'] ) ) ? $this->options['styles_manual'] : array();

		?>

		<div id="field_manual_styles">

			<label for="mph_minify_field_manual_styles">
				<strong>Manual styles</strong>
				<span class="description">List of style handles to minify and concatenate into one file. Comma separated or on a new line</span>
			</label>

			<textarea id="mph_minify_field_manual_styles_template" name="mph_minify_options[scripts_manual][]" class="large-text code input-template" style="display:none;"></textarea>

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
	 * Validation
	 */
	function options_validate( $input ) {

		foreach ( $input['scripts_manual'] as $key => $queue )
			$input['scripts_manual'][$key] = $this->handle_list_filter( $queue );

		foreach ( $input['styles_manual'] as $key => $queue )
			$input['styles_manual'][$key] = $this->handle_list_filter( $queue );

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
	 * Filter inputs that contain a comma separated list of asset handles.
	 * Return an array ready for saving.
	 *
	 * @param  string $list string of comma separated handles
	 * @return array       array of handles
	 */
	function handle_list_filter( $list ) {

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
	function enqueue( $hook ) {

		if ( 'settings_page_mph_minify' !== $hook )
			return;

		wp_enqueue_script( 'mph-admin', trailingslashit( plugins_url( basename( __DIR__ ) ) ) . 'admin.js' );

	}

	/**
	 * Delete all cached files
	 *
	 * @param  boolean $redirect whether
	 * @return [type]            [description]
	 */
	function clear_cache( $redirect = true ) {

		// Delete the cache if requested.
		$minify = new MPH_Minify( 'WP_Scripts' );
		$minify->delete_cache();

		$this->add_admin_notice( 'Cache Cleared', 'updated', true );

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

		$dir = trailingslashit( WP_CONTENT_DIR ) . trailingslashit( apply_filters( 'mph_minify_cache_dir', 'mph_minify_cache' ) );

		if ( is_dir( $dir ) )
	 		return count( glob( $dir . "*" ) );

	}

	/**
	 * Display all notices in the admin.
	 *
	 * @return null
	 */
	function display_admin_notices() {

		// If delete admin notice request is set, delete admin notice.
		if ( isset( $_REQUEST['mph-minify-notice-dismiss'] ) && isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'mph-minify-notice-dismiss' ) )
			$this->delete_admin_notice( $_REQUEST['mph-minify-notice-dismiss'] );

		// Get admin notices.
		$admin_notices = get_option( 'mph_minify_notices', array() );

		if ( isset( $this->options['debugger'] ) && $this->options['debugger']  === true )
			$admin_notices[] = array( 'type' => 'updated', 'message' => 'MPH Minify debugger is currently active', 'display_once' => true );

		// Display admin notices
		foreach ( $admin_notices as $key => $notice ) {

			echo '<div class="' . $notice['type'] . ' fade"><p>';
			echo $notice['message'];

			if ( empty( $notice['display_once'] ) )
				echo '<a class="button" style="margin-left: 10px; color: inherit; text-decoration: none;" href="' . wp_nonce_url( add_query_arg( 'mph-minify-notice-dismiss', $key ), 'mph-minify-notice-dismiss' ) . '">Dismiss</a>';

			echo '</p></div>';

			if ( $notice['display_once'] )
				unset( $admin_notices[$key] );

		}

		if ( empty( $admin_notices ) )
			delete_option( 'mph_minify_notices' );
		else
			update_option( 'mph_minify_notices', $admin_notices );

	}

	/**
	 * Deletes an admin notice.
	 *
	 * @param string $key message unique identifier
	 */
	function delete_admin_notice( $key ) {

		$admin_notices = get_option( 'mph_minify_notices', array() );

		if ( isset( $admin_notices[$key] ) )
			unset( $admin_notices[$key] );

		if ( empty( $admin_notices ) )
			delete_option( 'mph_minify_notices' );
		else
			update_option( 'mph_minify_notices', $admin_notices );

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