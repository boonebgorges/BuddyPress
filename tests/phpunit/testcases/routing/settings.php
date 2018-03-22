<?php
/**
 * @group settings
 * @group routing
 */
class BP_Tests_Routing_Settings extends BP_UnitTestCase {
	protected $old_current_user = 0;

	public function setUp() {
		parent::setUp();

		$this->old_current_user = get_current_user_id();
		$this->set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
	}

	public function tearDown() {
		parent::tearDown();
		$this->set_current_user( $this->old_current_user );
	}

	function test_member_settings() {
		$this->go_to( bp_core_get_user_domain( bp_loggedin_user_id() ) . bp_get_settings_slug() );
		$this->assertTrue( bp_is_user_settings_general() );
	}

	function test_member_settings_notifications() {
		$this->go_to( bp_core_get_user_domain( bp_loggedin_user_id() ) . bp_get_settings_slug() . '/notifications' );
		$this->assertTrue( bp_is_user_settings_notifications() );
	}

	// @todo How best to test this?
	/*function bp_is_user_settings_account_capbilities() {
		$this->go_to( bp_core_get_user_domain( bp_loggedin_user_id() ) . bp_get_settings_slug() . '/capabilities' );
	}*/

	function bp_is_user_settings_account_delete() {
		$this->go_to( bp_core_get_user_domain( bp_loggedin_user_id() ) . bp_get_settings_slug() . '/delete-account' );
		$this->assertTrue( bp_is_user_settings_account_delete() );
	}
}
