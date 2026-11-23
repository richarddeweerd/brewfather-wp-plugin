<?php

use BrewLogForBrewfather\Access;
use PHPUnit\Framework\TestCase;

class AccessTest extends TestCase {

	protected function setUp(): void {
		reset_wp();
		Access::install();
	}

	private function log_in( $role ) {
		$GLOBALS['blfb_test']['current'] = make_user( $role );
	}

	public function test_visitor() {
		$this->assertSame( Access::VISITOR, Access::current_level() );
		$this->assertTrue( Access::at_least( Access::VISITOR ) );
		$this->assertFalse( Access::at_least( Access::USER ) );
	}

	public function test_logged_in_user() {
		$this->log_in( 'subscriber' );

		$this->assertSame( Access::USER, Access::current_level() );
		$this->assertTrue( Access::at_least( Access::VISITOR ) );
		$this->assertFalse( Access::at_least( Access::BREWER ) );
	}

	public function test_brewer() {
		$this->log_in( Access::ROLE );

		$this->assertSame( Access::BREWER, Access::current_level() );
		$this->assertTrue( Access::at_least( Access::USER ) );
		$this->assertFalse( Access::at_least( Access::ADMIN ) );
	}

	public function test_admin_can_do_everything() {
		$this->log_in( 'administrator' );

		$this->assertSame( Access::ADMIN, Access::current_level() );
		$this->assertTrue( Access::at_least( Access::BREWER ) );
		$this->assertTrue( user_can( get_current_user_id(), Access::BREWER_CAP ) );
	}

	public function test_another_role_granted_the_capability_counts_as_brewer() {
		get_role( 'subscriber' )->add_cap( Access::BREWER_CAP );
		$this->log_in( 'subscriber' );

		$this->assertSame( Access::BREWER, Access::current_level() );
	}

	public function test_level_for_user() {
		$this->assertSame( Access::VISITOR, Access::level_for_user( 0 ) );
		$this->assertSame( Access::BREWER, Access::level_for_user( make_user( Access::ROLE ) ) );
	}

	public function test_install_creates_role_that_can_read() {
		$role = get_role( Access::ROLE );

		$this->assertNotNull( $role );
		$this->assertTrue( $role->capabilities['read'] );
		$this->assertTrue( $role->capabilities[ Access::BREWER_CAP ] );
	}

	public function test_install_is_safe_to_repeat() {
		get_role( Access::ROLE )->add_cap( 'custom_cap' );

		Access::install();

		$this->assertTrue( get_role( Access::ROLE )->capabilities['custom_cap'] );
	}

	public function test_uninstall_removes_role_and_capability() {
		Access::uninstall();

		$this->assertNull( get_role( Access::ROLE ) );
		$this->assertArrayNotHasKey( Access::BREWER_CAP, get_role( 'administrator' )->capabilities );
	}

	public function test_everyone_sees_ingredients_by_default() {
		foreach ( array( Access::VISITOR, Access::USER, Access::BREWER, Access::ADMIN ) as $level ) {
			$this->assertTrue( Access::can_see_ingredients( $level ) );
		}
	}

	public function test_hiding_from_visitors() {
		update_option( \BrewLogForBrewfather\Options::HIDE_INGREDIENTS_VISITORS, true );

		$this->assertFalse( Access::can_see_ingredients( Access::VISITOR ) );
		$this->assertTrue( Access::can_see_ingredients( Access::USER ) );
	}

	public function test_hiding_from_users_also_hides_from_visitors() {
		update_option( \BrewLogForBrewfather\Options::HIDE_INGREDIENTS_USERS, true );

		$this->assertFalse( Access::can_see_ingredients( Access::USER ) );
		$this->assertFalse( Access::can_see_ingredients( Access::VISITOR ) );
		$this->assertTrue( Access::can_see_ingredients( Access::BREWER ) );
		$this->assertTrue( Access::can_see_ingredients( Access::ADMIN ) );
	}

	public function test_role_name_is_translated_for_display() {
		Access::translate_role_name();

		$this->assertSame( 'Brewer', wp_roles()->role_names[ Access::ROLE ] );
	}
}
