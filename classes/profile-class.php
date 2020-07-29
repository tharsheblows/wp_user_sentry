<?php
namespace wp_user_sentry;

/**
 * Sessions table class for user profile
 * wp-admin/profile.php
 *
 * @since 0.4.0
 */
class Profile {

	/**
	 * Adds a session table to profile.php for a user to show where their active sessions are.
	 *
	 * @access public static
	 * @since 0.4.0
	 * @param  object $user user object for the users whos profile it is.
	 * @return void
	 */
	static function userProfile( $user ) { // @phpcs:ignore
		$manager      = \WP_Session_Tokens::get_instance( $user->ID );
		$all_sessions = $manager->get_all();
		$user_info    = new \wp_user_sentry\User();
		?>
<div class="wp-user-sentry-session" id="wp-user-sentry-session">
  <h3><?php _e( 'Current Sessions', 'wp-user-sentry' ); ?></h3>
<table class="wp-list-table widefat fixed striped profile" id="wp-user-sentry-session-table">
	<thead>
		<tr>
			<th scope="col" class="manage-column column-primary" id="wp-user-sentry-session-login"><?php esc_html_e( 'Login', 'wp-user-sentry' ); ?></th>
			<th scope="col" class="manage-column" id="wp-user-sentry-session-ip"><?php esc_html_e( 'IP', 'wp-user-sentry' ); ?></th>
			<th scope="col" class="manage-column" id="wp-user-sentry-session-browser"><?php esc_html_e( 'Browser', 'wp-user-sentry' ); ?></th>
			<th scope="col" class="manage-column" id="wp-user-sentry-session-os"><?php esc_html_e( 'OS', 'wp-user-sentry' ); ?></th>
			<th scope="col" class="manage-column" id="wp-user-sentry-session-expiry"><?php esc_html_e( 'Expires', 'wp-user-sentry' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		if ( ! empty( $all_sessions ) && is_array( $all_sessions ) ) {
			foreach ( $all_sessions as $session ) {
				$device = $user_info->getDevice( $session['ua'] );
				$login  = self::tableDateFormat( $session['login'] );
				$expiry = self::tableDateFormat( $session['expiration'] );
				echo '<tr>';
				echo '<td>' . esc_html( $login ) . '</td>';
				echo '<td>' . esc_html( $session['ip'] ) . '</td>';
				echo '<td>' . esc_html( $device['browser'] ) . '</td>';
				echo '<td>' . esc_html( $device['os'] ) . '</td>';
				echo '<td>' . esc_html( $expiry ) . '</td>';
				echo '</tr>';
			}
		} else {
			echo '
      <tr>
      <td colspan="5" >' . __( 'No Current Sessions' ) . '</td>
      </tr>
      ';
		}
		?>
  </tbody>
</table>
</div>
		<?php
	}

	/**
	 * Provide Timestamp in usable format [date]@[time] .
	 *
	 * @access public static
	 * @since 0.4.0
	 * @param string $timestamp The timestamp of the login.
	 * @return string A formatted date and time.
	 */
	static function tableDateFormat( $timestamp ) { // @phpcs:ignore
		return date_i18n( get_option( 'date_format' ), $timestamp ) . ' @ ' . date_i18n( get_option( 'time_format' ), $timestamp );
	}

}
