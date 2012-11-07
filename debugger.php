<?php

/**
 *	Debugger/Helper Tool
 *
 *	When enabled, shown on the front end of the site.
 * 	Debugger window shows all enqueued scripts, and highlights those that are minified.
 */

$options = mph_minify_get_plugin_options();
if ( isset( $options['debugger'] ) && true === $options['debugger'] ) {

	add_action( 'init', 'mph_minify_tool_process' );

	add_action( 'wp_head', 'mph_minify_debugger_style' );
	add_action( 'wp_footer', 'mph_minify_debugger', 9999 );

}

/**
 * Debugger Style. Inserted into head.
 *
 * @todo Seriously i'm not even correclty enqueuing my own styles!
 * @return null
 */
function mph_minify_debugger_style() {

	?>

	<style>
		#mph-minify-debugger { position: fixed; top: 50px; right: 30px; width: 180px; border-radius: 10px; background: rgba(0,0,0,0.8); border: 1px solid rgba(0,0,0,0.5); color: #FFF; padding: 10px; margin-bottom: 30px; z-index: 9999; }
		#mph-minify-debugger h2 { font-family: sans-serif; font-size: 18px; line-height: 1.5; margin-bottom: 5px; letter-spacing: normal; color: #FFF; font-size: 12px; font-family: verdana, sans-serif; }
		#mph-minify-debugger ul { margin-bottom: 15px; }
		#mph-minify-debugger ul,
		#mph-minify-debugger p,
		#mph-minify-debugger li { list-style: none; padding: 0; margin-left: 0; margin-right: 0; font-size: 10px; font-family: verdana, sans-serif; line-height: 1.5; }
		#mph-minify-debugger li.mph-min-group-0 { color: orange;}
		#mph-minify-debugger li.mph-min-group-1 { color: yellow;}
		#mph-minify-debugger li input { margin-right: 7px; }
	</style>

	<?php

}

/**
 * Helper tool for the minifyier
 *
 * Uses global var $minified_deps (as well as $wp_scritps & $wp_styles)
 */
function mph_minify_debugger() {

	global $wp_scripts, $wp_styles, $minified_deps;

	$styles_enqueued = array();
	$scripts_enqueued = array();

	// Get the queue of all scripts & styles that should be loaded.
	// A bit of a round about way as we need to know those loaded because they are a dependency.

	if ( ! empty( $wp_scripts ) ) {
		$wp_scripts->done = array();
		$wp_scripts->to_do = array();
		$wp_scripts->all_deps( $wp_scripts->queue );
		$scripts_enqueued = $wp_scripts->to_do;
	}

	if ( ! empty( $wp_styles ) ) {
		$wp_styles->done = array();
		$wp_scripts->to_do = array();
		$wp_styles->all_deps( $wp_styles->queue );
		$styles_enqueued = $wp_styles->to_do;
	}

	?>

	<div id="mph-minify-debugger">

		<h2>Enqueued Scripts</h2>

		<form method="post">

		<ul>
			<?php mph_minify_debugger_list( array_diff( $scripts_enqueued, array_keys( $minified_deps['WP_Scripts'] ) ) ); ?>
		</ul>

		<h2>Minified Scripts</h2>
		<ul>
			<?php mph_minify_debugger_list( array_keys( $minified_deps['WP_Scripts'] ) ); ?>
		</ul>

		<h2>Enqueued Styles</h2>
		<ul>
			<?php mph_minify_debugger_list( array_diff( $styles_enqueued, array_keys( $minified_deps['WP_Styles'] ) ), false ); ?>
		</ul>

		<h2>Minified Styles</h2>
		<ul>
			<?php mph_minify_debugger_list( array_keys( $minified_deps['WP_Styles'] ), false ); ?>
		</ul>

		<?php wp_nonce_field( 'mph_minify_tool', 'mph_minify_tool_nonce', false ); ?>

		<button type="submit">Update</button>

		</form>

		<h2>Key</h2>
		<ul>
			<li class="mph-min-group-0">Orange: in header</li>
			<li class="mph-min-group-1">Yellow: in footer</li>
		</ul>
		<p>Note: minified files displayed in order of processing, not order in which they are loaded.</p>

	</div>

	<?php

}


/**
 * Output a list of assets for use in the debugger
 *
 * @param  array  $asset_list list of handles to display
 * @param  boolean $scripts   whether minifying scripts. If false, minifyling styles.
 * @return null outputs <li> for each handle.
 */
function mph_minify_debugger_list( $asset_list, $scripts = true ) {

	global $minified_deps, $wp_scripts, $wp_styles;

	if ( $scripts )
		$class = &$wp_scripts;
	else
		$class = &$wp_styles;

	foreach ( $asset_list as $handle ) {

		// Don't show minified scripts.
		if ( 0 === strpos( $handle, 'mph-min' ) )
			continue;

		$classes = array();
		$classes['group'] = 'mph-min-group-' . ( isset( $class->registered[$handle]->extra['group'] ) ? $class->registered[$handle]->extra['group'] : 0 );

		if ( array_key_exists( $handle, $minified_deps[get_class($class)] ) )
			$classes['minified'] = 'mph-min-minified';

		?>
		<li class="<?php echo implode( ' ', $classes ); ?>" title="<?php echo implode( ', ', $class->registered[$handle]->deps ); ?>">
			<label for="mph_minify_<?php echo ( $scripts ) ? 'scripts' : 'styles'; ?>_<?php echo $handle; ?>">
				<input type="checkbox" name="mph_minify_<?php echo ( $scripts ) ? 'scripts' : 'styles'; ?>[]" id="mph_minify_<?php echo ( $scripts ) ? 'scripts' : 'styles'; ?>_<?php echo $handle; ?>" value="<?php echo $handle; ?>" <?php checked( array_key_exists( $handle, $minified_deps[get_class($class)] ) ); ?> />
				<?php echo $handle; ?>
			</label>
		</li>
		<?php

	}

}


function mph_minify_tool_process() {

	if ( ! isset( $_POST['mph_minify_tool_nonce'] ) || ! wp_verify_nonce( $_POST['mph_minify_tool_nonce'], 'mph_minify_tool' ) )
		return;

	$options = mph_minify_get_plugin_options();

	$submitted = ( isset( $_POST['mph_minify_scripts'] ) ) ? $_POST['mph_minify_scripts'] : array();
	$minified_scripts = array();
	foreach ( $options['scripts_manual'] as $queue_key => $queue ) {
		if ( is_array( $queue ) ) {
			foreach ( $queue as $handle_key => $handle  ) {

				array_push( $minified_scripts, $handle );

				if ( ! in_array( $handle, $submitted ) )
					unset( $options['scripts_manual'][$queue_key][$handle_key] );

			}
		}
	}

	foreach ( $submitted as $handle ) {
		if ( ! in_array( $handle, $minified_scripts ) ) {

			if ( empty( $options['scripts_manual'][0] ) )
				$options['scripts_manual'][0] = array();

			array_push( $options['scripts_manual'][0], $handle );

		}
	}

	$submitted = ( isset( $_POST['mph_minify_styles'] ) ) ? $_POST['mph_minify_styles'] : array();
	$minified_styles = array();
	foreach ( $options['styles_manual'] as $queue_key => $queue ) {
		if ( is_array( $queue ) ) {
			foreach ( $queue as $handle_key => $handle  ) {

				array_push( $minified_styles, $handle );

				// Unset saved options not in post data.
				if ( ! in_array( $handle, $submitted ) )
					unset( $options['styles_manual'][$queue_key][$handle_key] );

			}
		}
	}

	foreach( $submitted as $handle ) {
		if ( ! in_array( $handle, $minified_styles ) ) {

			if ( empty( $options['styles_manual'][0] ) )
				$options['styles_manual'][0] = array();

			array_push( $options['styles_manual'][0], $handle );

		}
	}

	update_option( 'mph_minify_options', $options );

}