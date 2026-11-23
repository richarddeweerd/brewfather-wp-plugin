<?php
/**
 * User levels and the brewer role.
 *
 * @package BrewLogForBrewfather
 */

namespace BrewLogForBrewfather;

defined( 'ABSPATH' ) || exit;

/**
 * Decides which user level a viewer has.
 *
 * Levels, low to high: Visitor < User < Brewer < Admin. A higher level can do
 * everything a lower one can, so features check with at_least() rather than
 * comparing levels for equality.
 *
 * Brewer is a capability (BREWER_CAP), not a role check. The `brewer` role
 * has it, administrators get it too, and site owners can grant it to other
 * roles with any role-editor plugin.
 */
final class Access {

	const VISITOR = 0;
	const USER    = 1;
	const BREWER  = 2;
	const ADMIN   = 3;

	/**
	 * Role slug the plugin registers.
	 */
	const ROLE = 'brewer';

	/**
	 * Capability that makes a user a Brewer.
	 */
	const BREWER_CAP = 'blfb_brewer';

	/**
	 * Capability that makes a user an Admin.
	 */
	const ADMIN_CAP = 'manage_options';

	/**
	 * The current viewer's level.
	 *
	 * @return int One of the level constants.
	 */
	public static function current_level() {
		if ( ! is_user_logged_in() ) {
			return self::VISITOR;
		}

		return self::level_for_user( get_current_user_id() );
	}

	/**
	 * The level of a specific user.
	 *
	 * @param int|\WP_User $user User ID or object. 0 means a Visitor.
	 * @return int One of the level constants.
	 */
	public static function level_for_user( $user ) {
		if ( ! $user ) {
			return self::VISITOR;
		}
		if ( user_can( $user, self::ADMIN_CAP ) ) {
			return self::ADMIN;
		}
		if ( user_can( $user, self::BREWER_CAP ) ) {
			return self::BREWER;
		}

		return self::USER;
	}

	/**
	 * Whether the current viewer has at least the given level.
	 *
	 * @param int $level One of the level constants.
	 * @return bool
	 */
	public static function at_least( $level ) {
		return self::current_level() >= $level;
	}

	/**
	 * Whether a level may see Recipe Ingredients, given the hiding settings.
	 *
	 * Brewers and Admins always may. Hiding from Users also hides from
	 * Visitors, because a lower level can never see more than a higher one.
	 *
	 * @param int $level One of the level constants.
	 * @return bool
	 */
	public static function can_see_ingredients( $level ) {
		if ( $level >= self::BREWER ) {
			return true;
		}
		if ( get_option( Options::HIDE_INGREDIENTS_USERS, false ) ) {
			return false;
		}
		if ( self::VISITOR === $level && get_option( Options::HIDE_INGREDIENTS_VISITORS, false ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Creates the brewer role and gives administrators the brewer capability.
	 * Safe to run more than once.
	 */
	public static function install() {
		if ( ! get_role( self::ROLE ) ) {
			// Stored untranslated; translated for display in translate_role_name().
			add_role( self::ROLE, 'Brewer', array( 'read' => true ) );
		}

		foreach ( array( self::ROLE, 'administrator' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				$role->add_cap( self::BREWER_CAP );
			}
		}
	}

	/**
	 * Removes the brewer role and capability. Users who had only the brewer
	 * role are left without a role, as WordPress does for any deleted role.
	 */
	public static function uninstall() {
		remove_role( self::ROLE );

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->remove_cap( self::BREWER_CAP );
		}
	}

	/**
	 * Shows the role name in the site's language on the user screens.
	 * Role names are stored in English in the database.
	 */
	public static function translate_role_name() {
		$roles = wp_roles();
		if ( isset( $roles->role_names[ self::ROLE ] ) ) {
			$roles->role_names[ self::ROLE ] = _x( 'Brewer', 'user role', 'brew-log-for-brewfather' );
		}
	}
}
