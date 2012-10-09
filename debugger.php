<?php

/**
 *	Debugger/Helper Tool
 *
 *	When enabled, shown on the front end of the site.
 * 	Debugger window shows all enqueued scripts, and highlights those that are minified. 
 */

/**
 * Debugger Style. Inserted into head.
 * @return null
 */
function mph_minify_debugger_style() {

	?>

	<style>
		#mph-minify-debugger { position: fixed; top: 50px; right: 30px; width: 160px; border-radius: 10px; background: rgba(0,0,0,0.8); border: 1px solid rgba(0,0,0,0.5); color: #FFF; padding: 15px 30px; margin-bottom: 30px; z-index: 9999; }
		#mph-minify-debugger h2 { font-family: sans-serif; font-size: 18px; line-height: 1.5; margin-bottom: 5px; letter-spacing: normal; color: #FFF; font-size: 12px; font-family: verdana, sans-serif; }
		#mph-minify-debugger ul { margin-bottom: 15px; }
		#mph-minify-debugger ul,
		#mph-minify-debugger li { list-style: disc outside; padding: 0; margin-left: 0; margin-right: 0; font-size: 10px; font-family: verdana, sans-serif; line-height: 1.5; }
		#mph-minify-debugger li.mph-min-header { color: orange;}
		#mph-minify-debugger li.mph-min-footer { color: yellow;}
	</style>

	<?php

}

/**
 * Helper tool for the minifyier
 *
 * All a bit hacked together - but its useful!
 *
 * @param array $instances array of instances of MPH_Minify.
 */
function mph_minify_debugger( $instances ) {
	
	global $wp_scripts, $wp_styles;

	// The basic queue - scripts & styles enqueued on this page
	$scripts_enqueued = $wp_scripts->queue;
	$styles_enqueued = $wp_styles->queue;
	
	$header_queue = array();
	$footer_queue = array();

	echo '<div id="mph-minify-debugger">';

	if ( isset( $instances['scripts'] ) ) {		
		foreach ( $instances['scripts'] as $instance ) {

			$min = $instance->get_asset_queue();
			$header_queue = array_merge( $header_queue, array_keys( ( ! empty( $min[0] ) ) ? $min[0] : array() ) );
			$footer_queue = array_merge( $footer_queue, array_keys( ( ! empty( $min[1] ) ) ? $min[1] : array() ) );

			// Add anything that we are minifying but are not enqueued to the queue
			foreach ( $instance->get_asset_queue() as $queue )
				$scripts_enqueued = array_merge( $scripts_enqueued, array_keys( $queue ) );

		}
	}

	echo '<h2>Enqueued Scripts</h2>';
	echo '<ul>';

	foreach( array_unique( $scripts_enqueued ) as $handle )
		if ( 0 === strpos( $handle, 'mph-min' ) )
			continue;			
		elseif ( in_array( $handle, $header_queue ) )
			echo '<li class="mph-min-header">' . $handle . '</li>';
		elseif ( in_array( $handle, $footer_queue ) )
			echo '<li class="mph-min-footer">' . $handle . '</li>';
		else
			echo '<li>' . $handle . '</li>';
	
	echo '</ul>';

	$header_queue = array();
	$footer_queue = array();

	if ( isset( $instances['styles'] ) ) {		
		foreach( $instances['styles'] as $instance ) {

			$min = $instance->get_asset_queue();
			$header_queue = array_merge( $header_queue, array_keys( ( ! empty( $min[0] ) ) ? $min[0] : array() ) );

			// Add anything that we are minifying but are not enqueued to the queue
			foreach ( $instance->get_asset_queue() as $queue )
				$styles_enqueued = array_merge( $styles_enqueued, array_keys( $queue ) );

		}
	}

	echo '<h2>Enqueued Styles</h2>';
	echo '<ul>';
	
	foreach( $styles_enqueued = array_unique( $styles_enqueued ) as $handle )
		if ( 0 === strpos( $handle, 'mph-min' ) )
			continue;
		elseif ( in_array( $handle, $header_queue ) )
			echo '<li class="mph-min-header">' . $handle . '</li>';
		elseif ( in_array( $handle, $footer_queue ) )
			echo '<li class="mph-min-footer">' . $handle . '</li>';
		else
			echo '<li>' . $handle . '</li>';

	echo '</ul>';
	
	echo '<h2>Key</h2><ul><li class="mph-min-header">Orange: Minified in header</li><li class="mph-min-footer">Yellow: Minified in footer</li><li>White: not minified.</li></ul>';

	echo '</div>';

}
