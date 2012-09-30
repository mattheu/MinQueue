<?php

class MPH_Minify_Admin {

	var $options = '';
	var $notices = array();

	function __construct() {

		add_action( 'admin_menu', array( $this, 'admin_add_page' ) );

		$this->options = get_option( 'mph_minify_options', array( 'ignore-scripts' => 'admin-bar', 'ignore-styles' => 'admin-bar' ) );

		// Delete the cache if requested.
		// @todo nonce verification.
		if ( isset( $_GET['mph_minify_action'] ) && 'clear_cache' == $_GET['mph_minify_action'] ) {

			$minify = new MPH_Minify( 'WP_Scripts' );
			$minify->delete_cache();
		
			$this->notices[] = 'Cache deleted.';

		}

	}

	function admin_add_page() {

		// add the admin options page
		add_options_page( 'MPH Minify Plugin Page', 'MPH Minify', 'manage_options', 'mph_minify', array( $this, 'options_page' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );

	}

	function admin_init(){
	
		register_setting( 'mph_minify_options', 'mph_minify_options', array( $this, 'options_validate' ) );
		add_settings_section( 'plugin_main', 'Main Settings', array( $this, 'section_text' ), 'plugin' );
		
		add_settings_field( 'mph_minify_ignore_scripts', 'Never Minify Scripts', array( $this, 'field_ignore_scripts' ), 'plugin', 'plugin_main' );
		add_settings_field( 'mph_minify_force_scripts', 'Always Minify Scripts', array( $this, 'field_force_scripts' ), 'plugin', 'plugin_main' );

		add_settings_field( 'mph_minify_ignore_styles', 'Ingore Styles', array( $this, 'field_ignore_styles' ), 'plugin', 'plugin_main' );
		add_settings_field( 'mph_minify_force_styles', 'Always Minify Styles', array( $this, 'field_force_styles' ), 'plugin', 'plugin_main' );

		add_settings_field( 'mph_minify_clear_cache', 'Delete all cached files', array( $this, 'field_clear_cache' ), 'plugin', 'plugin_main' );

	}

	function options_page() { 

		?>
		
		<?php $this->display_notices(); ?>

		<div class="wrap">

			<h2>MPH Minify Plugin Settings</h2>
			
			<h3>Usage</h3>
			<p>Sometimes, different scripts and styles are enqueued on different pages. This can lead to multiple large minified files being created that are loaded on different pages, negating many of the benefits of minification and concatenation.</p>
			<p>To avoid this, you can ignore certain scripts, and force others to always be minified. Alternatively you can manually define a list of assets to be minified and concatenated.</p>
			
			
			<form action="options.php" method="post">

				<?php settings_fields('mph_minify_options'); ?>
				<?php do_settings_sections('plugin'); ?>
			
				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
				</p>

			</form></div>

		<?php
	
	}


	function section_text() {
	
		echo '<p>Main description of this section here.</p>';
	
	}


	function field_clear_cache() { 

		?>

		<a href="<?php echo add_query_arg( 'mph_minify_action', 'clear_cache' ); ?>" class="button">Clear Cache</a>

		<?php

	}

	function field_ignore_scripts() {

		$value = ( ! empty( $this->options['ignore_scripts'] ) ) ? implode( ',', $this->options['ignore_scripts'] ) : '';
		echo '<input id="mph_minify_field_ignore_scripts" name="mph_minify_options[ignore_scripts]" type="text" value="' . $value . '" />';
		echo '<p class="description">Comma separated list of script handles to ignore.</p>';
	
	}

	function field_force_scripts() {

		$value = ( ! empty( $this->options['force_scripts'] ) ) ? implode( ',', $this->options['force_scripts'] ) : '';
		echo '<input id="mph_minify_field_force_scripts" name="mph_minify_options[force_scripts]"  type="text" value="' . $value . '" />';
		echo '<p class="description">Comma separated list of script handles that should always be minified.</p>';
	
	}

	function field_ignore_styles() {

		$value = ( ! empty( $this->options['ignore_styles'] ) ) ? implode( ',', $this->options['ignore_styles'] ) : '';
			echo '<input id="mph_minify_field_force_styles" name="mph_minify_options[ignore_styles]" type="text" value="' . $value . '" />';
			echo '<p class="description">Comma separated list of style handles to ignore.</p>';
	
	}

	function field_force_styles() {

		$value = ( ! empty( $this->options['force_styles'] ) ) ? implode( ',', $this->options['force_styles'] ) : '';
			echo '<input id="mph_minify_field_force_styles" name="mph_minify_options[force_styles]" type="text" value="' . $value . '" />';
			echo '<p class="description">Comma separated list of style handles that should always be minified.</p>';
	
	}

	/**
	 * Validation
	 * 
	 * @todo  Do this better
	 */
	function options_validate($input) {

		// Create an array of handles & filter out empty ones
		$input['ignore_scripts'] = array_filter( explode(',', $input['ignore_scripts'] ) );
		$input['force_scripts'] = array_filter( explode(',', $input['force_scripts'] ) );
		$input['ignore_styles'] = array_filter( explode(',', $input['ignore_styles'] ) );
		$input['force_styles'] = array_filter( explode(',', $input['force_styles'] ) );

		return $input;

	}

	function display_notices() {

		if ( empty( $this->notices ) )
			return;

		foreach ( $this->notices as $notice ) {

			echo '<div class="updated settings-error"><p>' . $notice . '</p></div>';

		}

	}

}




