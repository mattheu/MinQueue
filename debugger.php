<?php

/**
 *	Debugger/Helper Tool
 *
 *	When enabled, shown on the front end of the site.
 * 	Debugger window shows all enqueued scripts, and highlights those that are minified.
 */

add_action( 'init', 'mph_minify_tool' );
function mph_minify_tool () {

	if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) )
		return;

	$options = mph_minify_get_options();

	if ( isset( $options['debugger'] ) && true === $options['debugger'] ) {

		add_action( 'wp_head', 'mph_minify_debugger_style' );
		add_action( 'wp_footer', 'mph_minify_debugger', 9999 );

		mph_minify_tool_process();

	}

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
		#mph-minify-debugger { position: fixed; top: 10px; right: 10px; overflow: hidden; width: 220px; height: 60%; border-radius: 10px; background: rgba(0,0,0,0.8); border: none; color: #FFF; padding: 10px;  margin-bottom: 30px; z-index: 9999; }
		.admin-bar #mph-minify-debugger { top: 38px; }
		#mph-minify-debugger form { height: 100%; }
		#mph-minify-debugger-inner { height: 100%; overflow: auto; }


		#mph-minify-debugger * { background: none !important; text-shadow: none !important; padding: 0 !important; }
		#mph-minify-debugger h2 { font-family: sans-serif; font-size: 18px; line-height: 1.5; margin-bottom: 5px; letter-spacing: normal; color: #FFF; font-size: 12px; font-family: verdana, sans-serif; background: none; text-shadow: none; padding: 0;  }
		#mph-minify-debugger ul { margin-bottom: 15px; }
		#mph-minify-debugger ul,
		#mph-minify-debugger p,
		#mph-minify-debugger li { list-style: none; padding: 0; margin-left: 0; margin-right: 0; font-size: 10px; font-family: verdana, sans-serif; line-height: 1.5; }
		#mph-minify-debugger li.mph-min-group-0 { color: orange;}
		#mph-minify-debugger li.mph-min-group-1 { color: yellow;}
		#mph-minify-debugger li input { margin-right: 7px; }
		#mph-minify-debugger-submit,
		#mph-minify-debugger-submit:hover
		#mph-minify-debugger-submit:active { border: 1px solid black !important; border-radius: 5px; box-shadow: inset 0 1px 0 rgba(255,255,255,0.6), inset 0 -1px 3px rgba(0,0,0,0.2); padding-bottom: 3px !important; padding-left: 6px !important; padding-right: 6px !important; padding-top: 2px !important; vertical-align: middle; }
		#mph-minify-debugger-submit { background-image: -moz-linear-gradient(top,#CCC,#999) !important; background-image: -ms-linear-gradient(top,#CCC,#999) !important; background-image: -webkit-gradient(linear,0 0,0 100%,from(#CCC),to(#999)) !important; background-image: -webkit-linear-gradient(top,#CCC,#999) !important; background-image: -o-linear-gradient(top,#CCC,#999) !important; background-image: -webkit-linear-gradient(top,#CCC,#999) !important; background-image: linear-gradient(top,#CCC,#999) !important;  }
		#mph-minify-debugger-submit:hover { background-image: -moz-linear-gradient(top,#FFF,#AAA) !important; background-image: -ms-linear-gradient(top,#FFF,#AAA) !important; background-image: -webkit-gradient(linear,0 0,0 100%,from(#FFF),to(#AAA)) !important; background-image: -webkit-linear-gradient(top,#FFF,#AAA) !important; background-image: -o-linear-gradient(top,#FFF,#AAA) !important; background-image: -webkit-linear-gradient(top,#FFF,#AAA) !important; background-image: linear-gradient(top,#FFF,#AAA) !important;  }
		#mph-minify-debugger-submit:active {  background-image: -moz-linear-gradient(top,#999,#AAA) !important; background-image: -ms-linear-gradient(top,#999,#AAA) !important; background-image: -webkit-gradient(linear,0 0,0 100%,from(#999),to(#AAA)) !important; background-image: -webkit-linear-gradient(top,#999,#AAA) !important; background-image: -o-linear-gradient(top,#999,#AAA) !important; background-image: -webkit-linear-gradient(top,#999,#AAA) !important; background-image: linear-gradient(top,#999,#AAA) !important; box-shadow: inset 0 -1px 1px rgba(255,255,255,0.3), inset 0 1px 3px rgba(0,0,0,0.2); }
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
		$scripts = wp_clone( $wp_scripts );
		$scripts->done = array();
		$scripts->to_do = array();
		$queue = array_unique( array_merge( array_keys( $minified_deps['WP_Scripts'] ), $scripts->queue ) );
		$scripts->all_deps( $queue );
		$scripts_enqueued = $scripts->to_do;
	}

	if ( ! empty( $wp_styles ) ) {
		$styles = wp_clone( $wp_styles );
		$styles->done = array();
		$styles->to_do = array();
		$queue = array_unique( array_merge( array_keys( $minified_deps['WP_Styles'] ), $styles->queue ) );
		$styles->all_deps( $queue );
		$styles_enqueued = $styles->to_do;
	}


	?>

	<div id="mph-minify-debugger">

		<form method="post">

			<div id="mph-minify-debugger-inner">

				<h2>Enqueued Scripts</h2>

				<ul>
					<?php mph_minify_debugger_list( $scripts_enqueued ); ?>
				</ul>

				<h2>Enqueued Styles</h2>

				<ul>
					<?php mph_minify_debugger_list( $styles_enqueued, false ); ?>
				</ul>

				<?php wp_nonce_field( 'mph_minify_tool', 'mph_minify_tool_nonce', false ); ?>

				<?php if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) : ?>
					<button type="submit"  id="mph-minify-debugger-submit" >
						Update
					</button>
				<?php endif; ?>

				<h2>Key</h2>
				<ul>
					<li class="mph-min-group-0">Orange: in header</li>
					<li class="mph-min-group-1">Yellow: in footer</li>
				</ul>
				<p>Files displayed in the order in which they are loaded.</p>
				<p>Only visible to admin users.<p>
				<p>Remember some scripts are loaded conditionally (on certain pages, or for certain visitors).</p>

			</div>


		</form>

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

	$options = mph_minify_get_options();

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

		$checked = false;
		foreach( $options[( 'WP_Scripts' == get_class($class) ) ? 'scripts_manual' : 'styles_manual'] as $queue )
			if( ! $checked )
				$checked = in_array( $handle, $queue );

		$disabled = ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) ? true : false;

		?>
		<li class="<?php echo implode( ' ', $classes ); ?>" title="<?php echo implode( ', ', $class->registered[$handle]->deps ); ?>">
			<label for="mph_minify_<?php echo ( $scripts ) ? 'scripts' : 'styles'; ?>_<?php echo $handle; ?>">
				<input
					type="checkbox"
					name="mph_minify_<?php echo ( $scripts ) ? 'scripts' : 'styles'; ?>[]"
					id="mph_minify_<?php echo ( $scripts ) ? 'scripts' : 'styles'; ?>_<?php echo $handle; ?>"
					value="<?php echo $handle; ?>"
					<?php checked( $checked ); ?>
					<?php disabled( $disabled ); ?>
				/>
				<?php echo $handle; ?>
			</label>
		</li>
		<?php

	}

}


function mph_minify_tool_process() {

	if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) )
		return;

	if ( ! isset( $_POST['mph_minify_tool_nonce'] ) || ! wp_verify_nonce( $_POST['mph_minify_tool_nonce'], 'mph_minify_tool' ) )
		return;

	$options = mph_minify_get_options();

	$submitted = ( isset( $_POST['mph_minify_scripts'] ) ) ? $_POST['mph_minify_scripts'] : array();
	$minified_scripts = array();
	foreach ( (array) $options['scripts_manual'] as $queue_key => $queue ) {
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
	foreach ( (array) $options['styles_manual'] as $queue_key => $queue ) {
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

	if ( ! empty( $options['scripts_manual'] ) )
		$options['scripts_method'] = 'manual';
	else
		$options['scripts_manual'] = array();

	if ( ! empty( $options['styles_manual'] ) )
		$options['styles_method'] = 'manual';
	else
		$options['styles_manual'] = array();

	update_option( 'mph_minify_options', $options );

}

