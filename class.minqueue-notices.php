<?php

/**
 *	MinQueue Notices
 *
 * 	Abstraction for displaying admin notices across a site.
 * 	Errors on the front end can easily be stored and displayed in the admin.
 *
 *	@param  $ID Unique id. Multiple instances of the class wishing to access the same data should use the same ID.
 *
 */
class MinQueue_Admin_Notices {

	private $admin_notices = array();

	private $ID;

	function __construct( $ID = 'minqueue' ) {

		$this->ID = 'minqueue_an_' . $ID;

		$this->admin_notices = get_option( $this->ID, array() );

		if ( isset( $_GET[ $this->ID . '_notice_dismiss' ] ) || isset( $_GET['_wpnonce'] ) )
			add_action( 'admin_init', array( $this, 'delete_notice_action' ) );

		add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );

	}

	/**
	 * Creates an admin notice - saved in options to be shown in the admin, until dismissed.
	 *
	 * @param string $new_notice Message content
	 * @param string $type Message type - added as a class to the message when displayed. Reccommended to use: updated, error.
	 * @param bool $display_once Display message once, or require manual dismissal.
	 * @param int $notice_id Pass an ID. Useful if we need to access this notice later. Defaults to uniqid().
	 */
	public function add_notice( $message, $display_once = false, $type = 'updated', $notice_id = null ) {

		$notice = array(
			'message' => $message,
			'type' => $type,
			'display_once' => $display_once
		);

		$notice_id = ( $notice_id ) ? $notice_id : uniqid();

		if ( ! in_array( $notice , $this->admin_notices ) )
			$this->admin_notices[$notice_id] = $notice;

		$this->update_notices();

	}

	/**
	 * Delete Notice
	 *
	 * Also updates.
	 *
	 * @param  int $notice_id
	 * @return null
	 */
	public function delete_notice( $notice_id ) {

		$this->unset_admin_notice( $notice_id );

		$this->update_notices();

	}

	/**
	 * Output all notices in the admin.
	 *
	 * @return null
	 */
	public function display_admin_notices() {

		// Display admin notices
		foreach ( array_keys( $this->admin_notices ) as $notice_id )
			$this->display_admin_notice( $notice_id );

		$this->update_notices();

	}

	/**
	 * Output an individual notice.
	 *
	 * @param  string $notice_id The notice id (or key)
	 * @return null
	 */
	private function display_admin_notice ( $notice_id ) {

		if ( ! $notice = $this->admin_notices[$notice_id] )
			return;

		?>

		<div class="<?php echo esc_attr( $notice['type'] ); ?> ' fade">

			<p>

				<?php echo $notice['message']; ?>

				<?php if ( empty( $notice['display_once'] ) ) : ?>
					<a class="button" style="margin: -4px 10px -3px; color: inherit; text-decoration: none; " href="<?php echo wp_nonce_url( add_query_arg( $this->ID . '_notice_dismiss', $notice_id ), $this->ID . '_notice_dismiss' ); ?>">Dismiss</a>
				<?php endif; ?>

			</p>

		</div>

		<?php

		if ( $notice['display_once'] )
			$this->unset_admin_notice( $notice_id );

	}

	/**
	 * Save the current admin_notices.
	 *
	 * @return null
	 */
	private function update_notices() {

		$this->admin_notices = array_filter( $this->admin_notices );

		if ( empty( $this->admin_notices ) ) {
			delete_option( $this->ID );
		} else {
			update_option( $this->ID, $this->admin_notices );
		}

	}

	/**
	 * Remove an admin notice by key from $this->admin_notices
	 *
	 * @param  string $notice_id Notice ID (or key)
	 * @return null
	 */
	private function unset_admin_notice( $notice_id ) {

		if ( array_key_exists( $notice_id, $this->admin_notices ) )
			unset( $this->admin_notices[$notice_id] );

	}

	/**
	 * Deletes an admin notice.
	 *
	 * Requirements:
	 * $this->ID . '_notice_dismiss' nonce verification
	 * value of $_GET[$this->ID . '_notice_dismiss'] si the ID of the notice to be deleted.
	 *
	 * @param null
	 * @return null
	 */
	public function delete_notice_action() {

		if ( ! wp_verify_nonce( $_GET['_wpnonce'], $this->ID . '_notice_dismiss' ) )
			return;

		$this->delete_notice( $_GET[$this->ID . '_notice_dismiss'] );

	}

	/**
	 * Delete all admin notices.
	 *
	 * Reccommended to call this in deactivation hook.
	 *
	 * @return null
	 */
	function clean_up() {

		foreach ( array_keys( $this->admin_notices ) as $notice_id )
			$this->unset_admin_notice( $notice_id );

		$this->update_notices();

	}

}