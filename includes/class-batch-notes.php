<?php
/**
 * Batch notes: comments Users add to a Batch on the website.
 *
 * @package BrewLogForBrewfather
 */

namespace BrewLogForBrewfather;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Stores Batch notes as a hidden post type and shows them on Batch views.
 *
 * Users and above read and add notes. Authors edit and delete their own
 * notes; Brewers and Admins can delete any note. Notes appear immediately.
 * Notes belong to the website and are never sent to Brewfather.
 */
final class Batch_Notes {

	/**
	 * Post type that stores the notes.
	 */
	const POST_TYPE = 'blfb_batch_note';

	/**
	 * Post meta holding the Brewfather Batch ID.
	 */
	const BATCH_META = '_blfb_batch_id';

	/**
	 * Longest note accepted, in characters.
	 */
	const MAX_LENGTH = 5000;

	/**
	 * Query argument that opens a note for editing.
	 */
	const EDIT_ARG = 'edit_note';

	/**
	 * Query argument that carries an error code back to the Batch view.
	 */
	const ERROR_ARG = 'blfb_note_error';

	/**
	 * Registers the hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_filter( 'blfb_batch_view_after', array( __CLASS__, 'render' ), 10, 2 );
		add_action( 'admin_post_blfb_add_note', array( __CLASS__, 'handle_add' ) );
		add_action( 'admin_post_blfb_edit_note', array( __CLASS__, 'handle_edit' ) );
		add_action( 'admin_post_blfb_delete_note', array( __CLASS__, 'handle_delete' ) );
	}

	/**
	 * Registers the hidden post type.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'            => __( 'Batch notes', 'brew-log-for-brewfather' ),
				'public'           => false,
				'show_ui'          => false,
				'show_in_rest'     => false,
				'rewrite'          => false,
				'query_var'        => false,
				'supports'         => array( 'author', 'editor' ),
				'delete_with_user' => true,
			)
		);
	}

	/**
	 * The notes of a Batch, oldest first.
	 *
	 * @param string $batch_id Batch ID.
	 * @return \WP_Post[]
	 */
	public static function notes_for( $batch_id ) {
		return get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Few notes per site; indexed by meta_key.
				'meta_query'     => array(
					array(
						'key'   => self::BATCH_META,
						'value' => $batch_id,
					),
				),
			)
		);
	}

	/**
	 * Adds a note.
	 *
	 * @param string $batch_id Batch ID; must exist in Brewfather.
	 * @param int    $user_id  Author.
	 * @param string $content  Note text.
	 * @return int|WP_Error Note ID.
	 */
	public static function add( $batch_id, $user_id, $content ) {
		if ( Access::level_for_user( $user_id ) < Access::USER ) {
			return new WP_Error( 'forbidden' );
		}

		$content = self::clean( $content );
		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$batch_id = Frontend::sanitize_id( $batch_id );
		$batch    = '' === $batch_id ? null : Api_Client::from_settings()->get_batch( $batch_id );
		if ( ! is_array( $batch ) ) {
			return new WP_Error( 'unknown_batch' );
		}

		$id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'publish',
				'post_author'  => $user_id,
				'post_content' => $content,
				'post_title'   => $batch_id,
				'meta_input'   => array( self::BATCH_META => $batch_id ),
			),
			true
		);

		return is_wp_error( $id ) ? $id : (int) $id;
	}

	/**
	 * Changes the text of a note. Only its author may.
	 *
	 * @param int    $note_id Note ID.
	 * @param int    $user_id User making the change.
	 * @param string $content New text.
	 * @return true|WP_Error
	 */
	public static function update( $note_id, $user_id, $content ) {
		$note = self::get( $note_id );
		if ( ! $note || ! self::can_edit( $note, $user_id ) ) {
			return new WP_Error( 'forbidden' );
		}

		$content = self::clean( $content );
		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$result = wp_update_post(
			array(
				'ID'           => $note->ID,
				'post_content' => $content,
			),
			true
		);

		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Deletes a note. Its author, Brewers and Admins may.
	 *
	 * @param int $note_id Note ID.
	 * @param int $user_id User deleting it.
	 * @return true|WP_Error
	 */
	public static function delete( $note_id, $user_id ) {
		$note = self::get( $note_id );
		if ( ! $note || ! self::can_delete( $note, $user_id ) ) {
			return new WP_Error( 'forbidden' );
		}

		wp_delete_post( $note->ID, true );

		return true;
	}

	/**
	 * Whether a user may edit a note: only its author.
	 *
	 * @param \WP_Post $note    Note.
	 * @param int      $user_id User ID.
	 * @return bool
	 */
	public static function can_edit( $note, $user_id ) {
		return $user_id && (int) $note->post_author === (int) $user_id;
	}

	/**
	 * Whether a user may delete a note: its author, or any Brewer or Admin.
	 *
	 * @param \WP_Post $note    Note.
	 * @param int      $user_id User ID.
	 * @return bool
	 */
	public static function can_delete( $note, $user_id ) {
		return self::can_edit( $note, $user_id ) || Access::level_for_user( $user_id ) >= Access::BREWER;
	}

	/**
	 * The notes section of a Batch view. Visitors get a login link instead.
	 *
	 * @param string $html  HTML so far.
	 * @param array  $batch Batch.
	 * @return string
	 */
	public static function render( $html, $batch ) {
		$batch_id = Frontend::field( $batch, '_id' );
		$html    .= '<section class="blfb-notes" id="blfb-notes"><h3>' . esc_html__( 'Notes', 'brew-log-for-brewfather' ) . '</h3>';

		if ( ! Access::at_least( Access::USER ) ) {
			$login = wp_login_url( remove_query_arg( array( self::EDIT_ARG, self::ERROR_ARG ) ) );

			return $html . '<p><a href="' . esc_url( $login ) . '">' . esc_html__( 'Log in to read and add notes.', 'brew-log-for-brewfather' ) . '</a></p></section>';
		}

		$html   .= self::render_error();
		$user_id = get_current_user_id();
		$editing = self::id_from_request( self::EDIT_ARG );
		$notes   = self::notes_for( $batch_id );

		if ( ! $notes ) {
			$html .= Frontend::notice( __( 'No notes yet.', 'brew-log-for-brewfather' ) );
		}

		foreach ( $notes as $note ) {
			$author = get_userdata( (int) $note->post_author );
			$html  .= '<article class="blfb-note">'
				. '<p class="blfb-note-meta"><strong>' . esc_html( $author ? $author->display_name : __( 'Former user', 'brew-log-for-brewfather' ) ) . '</strong> · '
				. esc_html( (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $note->post_date_gmt . ' UTC' ) ) ) . '</p>';

			if ( $editing === (int) $note->ID && self::can_edit( $note, $user_id ) ) {
				$html .= self::form( 'blfb_edit_note', $batch_id, $note->post_content, __( 'Save note', 'brew-log-for-brewfather' ), (int) $note->ID );
			} else {
				$html .= '<div class="blfb-note-text">' . nl2br( esc_html( $note->post_content ) ) . '</div>';
				$html .= self::actions( $note, $user_id );
			}

			$html .= '</article>';
		}

		if ( ! $editing ) {
			$html .= self::form( 'blfb_add_note', $batch_id, '', __( 'Add note', 'brew-log-for-brewfather' ) );
		}

		return $html . '</section>';
	}

	/**
	 * Handles the add form.
	 */
	public static function handle_add() {
		self::verify( 'blfb_add_note' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in verify().
		$batch_id = isset( $_POST['batch'] ) ? sanitize_text_field( wp_unslash( $_POST['batch'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in verify().
		$content = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

		self::back( self::add( $batch_id, get_current_user_id(), $content ) );
	}

	/**
	 * Handles the edit form.
	 */
	public static function handle_edit() {
		self::verify( 'blfb_edit_note' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in verify().
		$note_id = isset( $_POST['note_id'] ) ? absint( $_POST['note_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in verify().
		$content = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

		self::back( self::update( $note_id, get_current_user_id(), $content ) );
	}

	/**
	 * Handles the delete button.
	 */
	public static function handle_delete() {
		self::verify( 'blfb_delete_note' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in verify().
		$note_id = isset( $_POST['note_id'] ) ? absint( $_POST['note_id'] ) : 0;

		self::back( self::delete( $note_id, get_current_user_id() ) );
	}

	/**
	 * Stops unless a logged-in user sent the form with a valid nonce.
	 *
	 * @param string $action Form action.
	 */
	private static function verify( $action ) {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'brew-log-for-brewfather' ), 403 );
		}
		check_admin_referer( $action );
	}

	/**
	 * Redirects back to the Batch view, with an error code if the action failed.
	 *
	 * @param mixed $result Result of add(), update() or delete().
	 */
	private static function back( $result ) {
		$url = remove_query_arg( array( self::EDIT_ARG, self::ERROR_ARG ), wp_get_referer() ? wp_get_referer() : home_url( '/' ) );
		if ( is_wp_error( $result ) ) {
			$url = add_query_arg( self::ERROR_ARG, $result->get_error_code(), $url );
		}

		wp_safe_redirect( $url . '#blfb-notes' );
		exit;
	}

	/**
	 * Trims a note and checks its length.
	 *
	 * @param string $content Note text, already sanitized.
	 * @return string|WP_Error
	 */
	private static function clean( $content ) {
		$content = trim( (string) $content );
		if ( '' === $content ) {
			return new WP_Error( 'empty' );
		}
		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $content ) > self::MAX_LENGTH : strlen( $content ) > self::MAX_LENGTH ) {
			return new WP_Error( 'too_long' );
		}

		return $content;
	}

	/**
	 * A note by ID, or null.
	 *
	 * @param int $note_id Note ID.
	 * @return \WP_Post|null
	 */
	private static function get( $note_id ) {
		$note = get_post( (int) $note_id );

		return $note && self::POST_TYPE === $note->post_type ? $note : null;
	}

	/**
	 * The Edit link and Delete button the user may use on a note.
	 *
	 * @param \WP_Post $note    Note.
	 * @param int      $user_id Current user.
	 * @return string
	 */
	private static function actions( $note, $user_id ) {
		$html = '';
		if ( self::can_edit( $note, $user_id ) ) {
			$url   = add_query_arg( self::EDIT_ARG, (int) $note->ID, remove_query_arg( self::ERROR_ARG ) ) . '#blfb-notes';
			$html .= '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Edit', 'brew-log-for-brewfather' ) . '</a> ';
		}
		if ( self::can_delete( $note, $user_id ) ) {
			$html .= '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post" class="blfb-inline">'
				. '<input type="hidden" name="action" value="blfb_delete_note">'
				. '<input type="hidden" name="note_id" value="' . esc_attr( (string) $note->ID ) . '">'
				. wp_nonce_field( 'blfb_delete_note', '_wpnonce', true, false )
				. '<button type="submit" class="blfb-link">' . esc_html__( 'Delete', 'brew-log-for-brewfather' ) . '</button>'
				. '</form>';
		}

		return '' === $html ? '' : '<p class="blfb-note-actions">' . $html . '</p>';
	}

	/**
	 * The add or edit form.
	 *
	 * @param string $action   Form action.
	 * @param string $batch_id Batch ID.
	 * @param string $content  Current text.
	 * @param string $label    Button label.
	 * @param int    $note_id  Note being edited, 0 when adding.
	 * @return string
	 */
	private static function form( $action, $batch_id, $content, $label, $note_id = 0 ) {
		$html = '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post" class="blfb-note-form">'
			. '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">'
			. '<input type="hidden" name="batch" value="' . esc_attr( $batch_id ) . '">'
			. ( $note_id ? '<input type="hidden" name="note_id" value="' . esc_attr( (string) $note_id ) . '">' : '' )
			. wp_nonce_field( $action, '_wpnonce', true, false )
			. '<label for="blfb-note-' . esc_attr( (string) $note_id ) . '" class="screen-reader-text">' . esc_html__( 'Note', 'brew-log-for-brewfather' ) . '</label>'
			. '<textarea id="blfb-note-' . esc_attr( (string) $note_id ) . '" name="note" rows="4" maxlength="' . esc_attr( (string) self::MAX_LENGTH ) . '" required>' . esc_textarea( $content ) . '</textarea>'
			. '<p><button type="submit">' . esc_html( $label ) . '</button>';

		if ( $note_id ) {
			$cancel = remove_query_arg( array( self::EDIT_ARG, self::ERROR_ARG ) ) . '#blfb-notes';
			$html  .= ' <a href="' . esc_url( $cancel ) . '">' . esc_html__( 'Cancel', 'brew-log-for-brewfather' ) . '</a>';
		}

		return $html . '</p></form>';
	}

	/**
	 * The message for an error code in the URL, if any.
	 *
	 * @return string
	 */
	private static function render_error() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only; checked against a fixed list.
		$code     = isset( $_GET[ self::ERROR_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::ERROR_ARG ] ) ) : '';
		$messages = array(
			'empty'         => __( 'The note is empty.', 'brew-log-for-brewfather' ),
			/* translators: %d: maximum number of characters. */
			'too_long'      => sprintf( __( 'The note is too long. Use at most %d characters.', 'brew-log-for-brewfather' ), self::MAX_LENGTH ),
			'forbidden'     => __( 'You are not allowed to do this.', 'brew-log-for-brewfather' ),
			'unknown_batch' => __( 'This batch could not be found.', 'brew-log-for-brewfather' ),
		);

		return isset( $messages[ $code ] ) ? '<p class="blfb-error">' . esc_html( $messages[ $code ] ) . '</p>' : '';
	}

	/**
	 * A positive integer from the query string, or 0.
	 *
	 * @param string $arg Query argument.
	 * @return int
	 */
	private static function id_from_request( $arg ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view selection.
		return isset( $_GET[ $arg ] ) ? absint( $_GET[ $arg ] ) : 0;
	}
}
