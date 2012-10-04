<?php

class MPH_Minify_Admin {

	var $options = '';
	var $notices = array();

	function __construct() {
		
		add_action( 'admin_menu', array( $this, 'admin_add_page' ) );

		$this->options = mph_minify_get_plugin_options();

		if ( isset( $_GET['mph_minify_action'] ) && 'clear_cache' == $_GET['mph_minify_action'] )
			add_action( 'admin_init', array( $this, 'clear_cache' ) );

	}

	/**
	 * Delete all cached files
	 * 
	 * @param  boolean $redirect whether
	 * @return [type]            [description]
	 */
	function clear_cache ( $redirect = true ) {

		// Delete the cache if requested.
		$minify = new MPH_Minify( 'WP_Scripts' );
		$minify->delete_cache();
		
		// Redirect.
		if ( $redirect ) {
			wp_redirect( add_query_arg( 'mph_minify_action', 'cache_cleared', remove_query_arg( 'mph_minify_action', wp_get_referer() ) ) );	
			exit;
		}

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
	
		add_settings_field( 'mph_minify_cache_dir', 'Cache directory name', array( $this, 'field_cache_dir' ), 'general_minify_options', 'plugin_main' );
		add_settings_field( 'mph_minify_debugger', 'Enable debugger', array( $this, 'field_debugger' ), 'general_minify_options', 'plugin_main' );
		add_settings_field( 'mph_minify_clear_cache', 'Delete all cached files', array( $this, 'field_clear_cache' ), 'general_minify_options', 'plugin_main' );

		add_settings_field( 'mph_minify_select_method', 'Script minification method', array( $this, 'field_method_scripts' ), 'script_minify_options', 'plugin_main' );
		add_settings_field( 'mph_minify_manual_scripts', 'Script minification settings', array( $this, 'field_scripts' ), 'script_minify_options', 'plugin_main' );

		add_settings_field( 'mph_minify_select_method', 'Style minification method', array( $this, 'field_method_styles' ), 'style_minify_options', 'plugin_main' );
		add_settings_field( 'mph_minify_manual_styles', 'Style minification settings', array( $this, 'field_styles' ), 'style_minify_options', 'plugin_main' );

	}

	/**
	 * Output the main options page content.
	 * @return null
	 */
	function options_page() { 

		if ( ! empty( $_GET['mph_minify_action'] ) && 'cache_cleared' == $_GET['mph_minify_action'] )
			echo '<div class="updated settings-error"><p>Cache Cleared</p></div>';

		?>

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

		<a href="<?php echo add_query_arg( 'mph_minify_action', 'clear_cache', remove_query_arg( 'mph_minify_action' ) ); ?>" class="button">Clear Cache</a>

	<?php }

	/**
	 * Output cache dir setting field
	 * 
	 * @return null
	 */
	function field_cache_dir() { ?>

		<input type="text" class="regular-text code" id="mph_minify_options_cache_dir" name="mph_minify_options[cache_dir]" value="<?php echo esc_attr( $this->options['cache_dir'] ); ?>"/>
		<input type="hidden" name="mph_minify_options[cache_dir_original]" value="<?php echo esc_attr( $this->options['cache_dir'] ); ?>"/>

	<?php }

	/**
	 * Output debugger setting field.
	 * 
	 * @return null
	 */
	function field_debugger() {	?>

		<input type="checkbox" id="mph_minify_options_debugger" name="mph_minify_options[debugger]" <?php checked( true, ( ! ( ! isset( $this->options['debugger'] ) || isset( $this->options['debugger'] ) && $this->options['debugger']  === false ) ) ); ?>/>
		<label for="mph_minify_options_debugger">Enable the debugger in the front end of the site. Helpful in working out which assets to minify and which to ignore.</label>

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
		<input type="radio" id="mph_minify_options_scripts_method_auto" name="mph_minify_options[scripts_method]" value="auto" <?php checked( 'auto', $this->options['scripts_method'] ); ?>/> <label for="mph_minify_options_scripts_method_auto">Semi-automatic minification</label><br/>
		<input type="radio" id="mph_minify_options_scripts_method_disabled" name="mph_minify_options[scripts_method]" value="disabled" <?php checked( 'disabled', $this->options['scripts_method'] ); ?>/> <label for="mph_minify_options_scripts_method_disabled">Disable minification</label>
	
	<?php }

	/**
	 * Output settings section for scripts.
	 * 
	 * @return null
	 */
	function field_scripts() {

		$value_manual = ( ! empty( $this->options['scripts_manual'] ) ) ? esc_attr( implode( ',', $this->options['scripts_manual'] ) ) : ''; 
		$value_ignore = ( ! empty( $this->options['scripts_ignore'] ) ) ? esc_attr( implode( ',', $this->options['scripts_ignore'] ) ) : '';
		$value_force  = ( ! empty( $this->options['scripts_force'] ) )  ? esc_attr( implode( ',', $this->options['scripts_force'] ) )  : '';

		?>

		<div id="field_manual_scripts">
			<label for="mph_minify_field_manual_scripts">
				<strong>Manual Scripts</strong> 
				<span class="description">Comma separated list of script handles to ignore.</span>
			<label>
			<textarea id="mph_minify_field_manual_scripts" name="mph_minify_options[scripts_manual]" class="large-text code"><?php echo $value_manual; ?></textarea>
		</div>

		<div id="field_auto_scripts">

			<label for="mph_minify_field_ignore_scripts">
				<strong>Ignore List</strong> 
				<span class="description">Comma separated list of script handles to ignore. These are never minified.</span>
			</label>
			<textarea id="mph_minify_field_ignore_scripts" name="mph_minify_options[scripts_ignore]" class="large-text code"><?php echo $value_ignore; ?></textarea>

			<label for="mph_minify_field_force_scripts">
				<strong>Force Scripts</strong> 
				<span class="description">Comma separated list of script handles that should always be minified even if not enqueued.</span>
			</label>
			<textarea id="mph_minify_field_force_scripts" name="mph_minify_options[scripts_force]" class="large-text code"><?php echo $value_force; ?></textarea>

		</div>

		<div id="field_disabled_scripts">
			<span class="description">Script minification is disabled</span>
		</div>

		<script>

			jQuery( document ).ready( function() {

				var scriptsManual = jQuery('#field_manual_scripts'),
					scriptsAuto = jQuery('#field_auto_scripts'),
					scriptsDisabled = jQuery('#field_disabled_scripts'),
					scriptsToggleManual = jQuery( '#mph_minify_options_scripts_method_manual' ),
					scriptsToggleAuto = jQuery( '#mph_minify_options_scripts_method_auto' );

				var myToggle = function () {

					if ( scriptsToggleManual.is( ':checked' ) ) {
						scriptsManual.slideDown( 100 );
						scriptsAuto.slideUp( 100 );
						scriptsDisabled.slideUp( 100 );
					} else if ( scriptsToggleAuto.is( ':checked' ) ) {
						scriptsManual.slideUp( 100 );
						scriptsAuto.slideDown( 100 );
						scriptsDisabled.slideUp( 100 );
					} else {
						scriptsAuto.slideUp( 100 );
						scriptsManual.slideUp( 100 );
						scriptsDisabled.slideDown( 100 );
					}

				}

				myToggle();
				scriptsToggleManual.siblings( 'input[type=radio]' ).andSelf().change( function() { myToggle(); } );

			} );

		</script>

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
		<input type="radio" id="mph_minify_options_styles_method_auto" name="mph_minify_options[styles_method]" value="auto" <?php checked( 'auto', $this->options['styles_method'] ); ?>/><label for="mph_minify_options_styles_method_auto"> Semi-automatic minification</label><br/>
		<input type="radio" id="mph_minify_options_styles_method_disabled" name="mph_minify_options[styles_method]" value="disabled" <?php checked( 'disabled', $this->options['styles_method'] ); ?>/> <label for="mph_minify_options_styles_method_disabled">Disable minification</label>
	
	<?php }

	function field_styles() {

		$value_manual = ( ! empty( $this->options['styles_manual'] ) ) ? esc_attr( implode( ',', $this->options['styles_manual'] ) ) : ''; 
		$value_ignore = ( ! empty( $this->options['styles_ignore'] ) ) ? esc_attr( implode( ',', $this->options['styles_ignore'] ) ) : '';
		$value_force  = ( ! empty( $this->options['styles_force'] ) )  ? esc_attr( implode( ',', $this->options['styles_force'] ) )  : '';

		?>

		<div id="field_manual_styles">
			<label for="mph_minify_field_manual_styles">
				<strong>Manual styles</strong> 
				<span class="description">Comma separated list of style handles to ignore.</span>
			<label>
			<textarea id="mph_minify_field_manual_styles" name="mph_minify_options[styles_manual]" class="large-text code"><?php echo $value_manual; ?></textarea>
		</div>

		<div id="field_auto_styles">

			<label for="mph_minify_field_ignore_styles">
				<strong>Ignore List</strong> 
				<span class="description">Comma separated list of style handles to ignore. These are never minified.</span>
			</label>
			<textarea id="mph_minify_field_ignore_styles" name="mph_minify_options[styles_ignore]" class="large-text code"><?php echo $value_ignore; ?></textarea>

			<label for="mph_minify_field_force_styles">
				<strong>Force styles</strong> 
				<span class="description">Comma separated list of style handles that should always be minified even if not enqueued.</span>
			</label>
			<textarea id="mph_minify_field_force_styles" name="mph_minify_options[styles_force]" class="large-text code"><?php echo $value_force; ?></textarea>

		</div>

		<div id="field_disabled_styles">
			<span class="description">Style minification is disabled</span>
		</div>

		<script>

			jQuery( document ).ready( function() {

				var stylesManual = jQuery('#field_manual_styles'),
					stylesAuto = jQuery('#field_auto_styles'),
					stylesDisabled = jQuery('#field_disabled_styles'),
					stylesToggleManual = jQuery( '#mph_minify_options_styles_method_manual' ),
					stylesToggleAuto = jQuery( '#mph_minify_options_styles_method_auto' );

				var myToggle = function () {

					if ( stylesToggleManual.is( ':checked' ) ) {
						stylesManual.slideDown( 100 );
						stylesAuto.slideUp( 100 );
						stylesDisabled.slideUp( 100 );
					} else if ( stylesToggleAuto.is( ':checked' ) ) {
						stylesManual.slideUp( 100 );
						stylesAuto.slideDown( 100 );
						stylesDisabled.slideUp( 100 );
					} else {
						stylesManual.slideUp( 100 );
						stylesAuto.slideUp( 100 );
						stylesDisabled.slideDown( 100 );
					}

				}

				myToggle();
				stylesToggleManual.siblings( 'input[type=radio]' ).andSelf().change( function() { myToggle(); } );

			} );

		</script>

		<?php 

	}

	/**
	 * Validation
	 */
	function options_validate( $input ) {

		// Create an array of handles & filter out empty ones
		$input['scripts_manual'] = $this->handle_list_filter( $input['scripts_manual'] );
		$input['scripts_ignore'] = $this->handle_list_filter( $input['scripts_ignore'] );
		$input['scripts_force']  = $this->handle_list_filter( $input['scripts_force'] );
		$input['styles_manual']  = $this->handle_list_filter( $input['styles_manual'] );
		$input['styles_ignore']  = $this->handle_list_filter( $input['styles_ignore'] );
		$input['styles_force']   = $this->handle_list_filter( $input['styles_force'] );

		$input['debugger'] = ( empty( $input['debugger'] ) ) ? false : true;

		// If the cache dir has changed delete the old one.
		if ( $input['cache_dir'] !== $input['cache_dir_original'] )
			$this->clear_cache( false );

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

		$list = explode(',', $list );		

		foreach( $list as &$item )
			$item = trim( $item );
		
		return array_filter( $list );

	}

}