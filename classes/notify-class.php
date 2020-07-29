<?php
namespace wp_user_sentry;

/**
 * Login Notification Class
 * Triggers on wp_login action
 *
 * @since 0.4.0
 */
class Notify {

	/**
	 * Confirms email should be sent. This is not run on failed logins.
	 *
	 * @access public static
	 * @since 0.4.0
	 * @param  string $user_login The username.
	 * @param  object $user The user object.
	 * @return bool   triggers sendEmail This always returns true I think.
	 */
	static function runNotify( $user_login, $user = false ) { // @phpcs:ignore

		error_log( 'in runNotify', 0 );
		if ( ! $user || empty( $user ) ) {
			$user = get_user_by( 'login', $user_login );
		}

		$settings = get_option( 'wp_user_sentry_settings' );
		$send     = true;

		// Check their roles and see if we should notify. If they are a super admin, they always get notified.
		if ( isset( $settings['notify_login_roles'] ) && is_array( $settings['notify_login_roles'] ) && ! is_super_admin( $user->ID ) ) {
			if ( ! array_intersect( $user->roles, $settings['notify_login_roles'] ) ) {
				$send = false;
			}
		}

		// Check if the email should be sent if an existing session with the same useragent and IP exist.
		if ( isset( $settings['notify_login_repeat'] ) && '2' === $settings['notify_login_repeat'] ) {
			if ( true === self::compareSessions( $user->ID ) ) {
				$send = false;
			}
		}

		// Filter whether or not we should send.
		$send = apply_filters( 'wp_user_sentry_notify', $send, $user->ID );
		if ( true !== $send ) {
			return true;
		}
		error_log( 'before sendEmail', 0 );
		return self::sendEmail( $user );
	}

	/**
	 * Send Email to user that is logging in.
	 *
	 * @access public static
	 * @since 0.4.0
	 * @param object $user The user object.
	 * @param string $email The email array with the information to send the email.
	 * @return bool
	 */
	static function sendEmail( $user, $email = false ) { // @phpcs:ignore

		$settings = get_option( 'wp_user_sentry_settings' );

		if ( ! empty( $email ) && isset( $email['message'] ) ) {
			// If we've passed it an email, use that.
			$message = $email['message'];
		} elseif ( isset( $settings['notify_login_email'] ) ) {
			// Otherwise, if there's a message in settings, use that.
			$message = $settings['notify_login_email'];
		} else {
			// The default if there's nothing in settings or no email passed.
			$message = __(
				'Hi, {display_name} [{user_login}],
Your account on {homeurl} was logged into at {time},
from a {os} machine running {browser}.
The IP address was {ip},{country}{flag}.
You are receiving this email to make sure it was you.
To review activity on your account visit {profile_url} or login to your admin on {homeurl} and navigate to your profile.
',
				'wp-user-sentry'
			);
		}
		$message = apply_filters( 'wp_user_sentry_email_message', $message );

		if ( ! empty( $email ) && isset( $email['subject'] ) ) {
			// If there's an email with a subject passed, use that.
			$subject = $email['subject'];
		} elseif ( isset( $settings['notify_login_email_subject'] ) ) {
			// Then try the one in settings.
			$subject = $settings['notify_login_email_subject'];
		} else {
			// The default subject.
			$subject = __( 'Successful login for {user_login}' );
		}

		$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

		// If there are cc addresses, add them in.
		$headers = [];
		if ( ! empty( $settings['notify_login_email_addresses'] ) ) {
			$cc_string = $settings['notify_login_email_addresses'];
			$cc_array  = explode( ',', $cc_string );

			foreach ( $cc_array as $cc ) {
				$headers[]    = "Cc: $cc";
			}
		}

		$subject = "[$blogname] $subject";
		$email   = array(
			'to'      => $user->user_email,
			'subject' => $subject,
			'message' => $message,
			'headers' => $headers,
		);

		error_log( print_r( $email, true ), 0 );

		/**
		 * This filter allows use of template tags in subject and message.
		 */
		$email = apply_filters( 'wp_user_sentry_login_email_prerender', $email, $user );

		$email['subject'] = self::render( $email['subject'], $user );
		$email['message'] = self::render( $email['message'], $user );
		$error_message    = self::render( 'WP User Sentry - Failed sending Email for {user_login}', $user );

		/**
		 * Filter email array after render.
		 */
		$email = apply_filters( 'wp_user_sentry_login_email', $email );

		$sent = wp_mail(
			$email['to'],
			$email['subject'],
			$email['message'],
			$email['headers']
		);

		if ( ! $sent ) {
			error_log( 'User Sentry Email was not sent', 0 );
		}
		error_log( 'mail sent', 0 );
		return true;
	}

	/**
	 * Replace the template tags with the appropriate values.
	 *
	 * @param string $text The text to be rendered.
	 * @param object $user The user object.
	 * @return string The rendered string or, if there is no user, the unrendered string.
	 */
	public static function render( $text, $user ) {

		// If there is no text to render or no user to render it again, bail.
		if ( empty( $text ) || empty( $user ) ) {
			return $text;
		}

		$user_info   = new \wp_user_sentry\User();
		$device      = $user_info->getDevice();
		$profile_url = admin_url( 'profile.php#wp-user-sentry-session' );

		$country = '';
		$flag    = '';
		if ( isset( $settings['geo_api_service'] ) ) {
			$geo = $user_info->getCountry( $device['ip'] );
			if ( ! empty( $geo ) ) {
				$country = $geo['country'];
				$flag    = $user_info->emojiFlag( $geo['code'] );
			}
		}

		$text = str_replace(
			array(
				'{user_login}',
				'{display_name}',
				'{homeurl}',
				'{time}',
				'{ip}',
				'{browser}',
				'{os}',
				'{profile_url}',
				'{country}',
				'{flag}',
			),
			array(
				$user->user_login,
				$user->display_name,
				get_home_url(),
				current_time( 'mysql' ),
				$device['ip'],
				$device['browser'],
				$device['os'],
				$profile_url,
				$country,
				$flag,
			),
			$text
		);

		return $text;
	}

	/**
	 * Compares the current login with previous logins.
	 *
	 * @access public static
	 * @since 0.4.0
	 * @param int|string $id The id of the user.
	 * @return bool True if the same useragent and IP exists. False otherwise.
	 */
	static function compareSessions( $id ) { // @phpcs:ignore
		$manager      = \WP_Session_Tokens::get_instance( $id );
		$all_sessions = $manager->get_all();
		$user_info    = new \wp_user_sentry\User();
		$ip           = $user_info->getIp();
		$user_agent   = $_SERVER['HTTP_USER_AGENT']; // @phpcs:ignore
		foreach ( $all_sessions as $session ) {
			if ( $ip === $session['ip'] && $user_agent === $session['ua'] ) {
				return true;
			}
		}
		return false;
	}
}
