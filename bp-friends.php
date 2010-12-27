<?php

define ( 'BP_FRIENDS_DB_VERSION', '1800' );

/* Define the slug for the component */
if ( !defined( 'BP_FRIENDS_SLUG' ) )
	define ( 'BP_FRIENDS_SLUG', 'friends' );

require ( BP_PLUGIN_DIR . '/bp-friends/bp-friends-classes.php' );
require ( BP_PLUGIN_DIR . '/bp-friends/bp-friends-templatetags.php' );

function friends_setup_globals() {
	global $bp;

	/* For internal identification */
	$bp->friends->id = 'friends';

	$bp->friends->slug = BP_FRIENDS_SLUG;

	$bp->friends->table_name = $bp->table_prefix . 'bp_friends';

	$bp->friends->format_notification_function = 'friends_format_notifications';

	/* Register this in the active components array */
	$bp->active_components[$bp->friends->slug] = $bp->friends->id;

	do_action( 'friends_setup_globals' );
}
add_action( 'bp_setup_globals', 'friends_setup_globals' );

function friends_setup_nav() {
	global $bp;

	/* Add 'Friends' to the main navigation */
	bp_core_new_nav_item( array( 'name' => sprintf( __( 'Friends <span>(%d)</span>', 'buddypress' ), friends_get_total_friend_count() ), 'slug' => $bp->friends->slug, 'position' => 60, 'screen_function' => 'friends_screen_my_friends', 'default_subnav_slug' => 'my-friends', 'item_css_id' => $bp->friends->id ) );

	$friends_link = $bp->loggedin_user->domain . $bp->friends->slug . '/';

	/* Add the subnav items to the friends nav item */
	bp_core_new_subnav_item( array( 'name' => __( 'My Friends', 'buddypress' ), 'slug' => 'my-friends', 'parent_url' => $friends_link, 'parent_slug' => $bp->friends->slug, 'screen_function' => 'friends_screen_my_friends', 'position' => 10, 'item_css_id' => 'friends-my-friends' ) );
	bp_core_new_subnav_item( array( 'name' => __( 'Requests', 'buddypress' ), 'slug' => 'requests', 'parent_url' => $friends_link, 'parent_slug' => $bp->friends->slug, 'screen_function' => 'friends_screen_requests', 'position' => 20, 'user_has_access' => bp_is_my_profile() ) );

	if ( $bp->current_component == $bp->friends->slug ) {
		if ( bp_is_my_profile() ) {
			$bp->bp_options_title = __( 'My Friends', 'buddypress' );
		} else {
			$bp->bp_options_avatar = bp_core_fetch_avatar( array( 'item_id' => $bp->displayed_user->id, 'type' => 'thumb' ) );
			$bp->bp_options_title = $bp->displayed_user->fullname;
		}
	}

	do_action( 'friends_setup_nav' );
}
add_action( 'bp_setup_nav', 'friends_setup_nav' );

/********************************************************************************
 * Screen Functions
 *
 * Screen functions are the controllers of BuddyPress. They will execute when their
 * specific URL is caught. They will first save or manipulate data using business
 * functions, then pass on the user to a template file.
 */

function friends_screen_my_friends() {
	global $bp;

	// Delete any friendship acceptance notifications for the user when viewing a profile
	bp_core_delete_notifications_for_user_by_type( $bp->loggedin_user->id, $bp->friends->id, 'friendship_accepted' );

	do_action( 'friends_screen_my_friends' );

	bp_core_load_template( apply_filters( 'friends_template_my_friends', 'members/single/home' ) );
}

function friends_screen_requests() {
	global $bp;

	if ( isset( $bp->action_variables[0] ) && isset( $bp->action_variables[1] ) && 'accept' == $bp->action_variables[0] && is_numeric( $bp->action_variables[1] ) ) {
		/* Check the nonce */
		check_admin_referer( 'friends_accept_friendship' );

		if ( friends_accept_friendship( $bp->action_variables[1] ) )
			bp_core_add_message( __( 'Friendship accepted', 'buddypress' ) );
		else
			bp_core_add_message( __( 'Friendship could not be accepted', 'buddypress' ), 'error' );

		bp_core_redirect( $bp->loggedin_user->domain . $bp->current_component . '/' . $bp->current_action );

	} elseif ( isset( $bp->action_variables[0] ) && isset( $bp->action_variables[1] ) && 'reject' == $bp->action_variables[0] && is_numeric( $bp->action_variables[1] ) ) {
		/* Check the nonce */
		check_admin_referer( 'friends_reject_friendship' );

		if ( friends_reject_friendship( $bp->action_variables[1] ) )
			bp_core_add_message( __( 'Friendship rejected', 'buddypress' ) );
		else
			bp_core_add_message( __( 'Friendship could not be rejected', 'buddypress' ), 'error' );

		bp_core_redirect( $bp->loggedin_user->domain . $bp->current_component . '/' . $bp->current_action );
	}

	do_action( 'friends_screen_requests' );

	if ( isset( $_GET['new'] ) )
		bp_core_delete_notifications_for_user_by_type( $bp->loggedin_user->id, $bp->friends->id, 'friendship_request' );

	bp_core_load_template( apply_filters( 'friends_template_requests', 'members/single/home' ) );
}

function friends_screen_notification_settings() {
	global $current_user; ?>
	<table class="notification-settings zebra" id="friends-notification-settings">
		<thead>
			<tr>
				<th class="icon"></th>
				<th class="title"><?php _e( 'Friends', 'buddypress' ) ?></th>
				<th class="yes"><?php _e( 'Yes', 'buddypress' ) ?></th>
				<th class="no"><?php _e( 'No', 'buddypress' )?></th>
			</tr>
		</thead>

		<tbody>
			<tr id="friends-notification-settings-request">
				<td></td>
				<td><?php _e( 'A member sends you a friendship request', 'buddypress' ) ?></td>
				<td class="yes"><input type="radio" name="notifications[notification_friends_friendship_request]" value="yes" <?php if ( !get_user_meta( $current_user->id, 'notification_friends_friendship_request', true ) || 'yes' == get_user_meta( $current_user->id, 'notification_friends_friendship_request', true ) ) { ?>checked="checked" <?php } ?>/></td>
				<td class="no"><input type="radio" name="notifications[notification_friends_friendship_request]" value="no" <?php if ( get_user_meta( $current_user->id, 'notification_friends_friendship_request', true ) == 'no' ) { ?>checked="checked" <?php } ?>/></td>
			</tr>
			<tr id="friends-notification-settings-accepted">
				<td></td>
				<td><?php _e( 'A member accepts your friendship request', 'buddypress' ) ?></td>
				<td class="yes"><input type="radio" name="notifications[notification_friends_friendship_accepted]" value="yes" <?php if ( !get_user_meta( $current_user->id, 'notification_friends_friendship_accepted', true ) || 'yes' == get_user_meta( $current_user->id, 'notification_friends_friendship_accepted', true ) ) { ?>checked="checked" <?php } ?>/></td>
				<td class="no"><input type="radio" name="notifications[notification_friends_friendship_accepted]" value="no" <?php if ( 'no' == get_user_meta( $current_user->id, 'notification_friends_friendship_accepted', true ) ) { ?>checked="checked" <?php } ?>/></td>
			</tr>

			<?php do_action( 'friends_screen_notification_settings' ); ?>
		</tbody>
	</table>
<?php
}
add_action( 'bp_notification_settings', 'friends_screen_notification_settings' );


/********************************************************************************
 * Action Functions
 *
 * Action functions are exactly the same as screen functions, however they do not
 * have a template screen associated with them. Usually they will send the user
 * back to the default screen after execution.
 */

function friends_action_add_friend() {
	global $bp;

	if ( $bp->current_component != $bp->friends->slug || $bp->current_action != 'add-friend' )
		return false;

	$potential_friend_id = $bp->action_variables[0];

	if ( !is_numeric( $potential_friend_id ) || !isset( $potential_friend_id ) )
		return false;

	if ( $potential_friend_id == $bp->loggedin_user->id )
		return false;

	$friendship_status = BP_Friends_Friendship::check_is_friend( $bp->loggedin_user->id, $potential_friend_id );

	if ( 'not_friends' == $friendship_status ) {

		if ( !check_admin_referer( 'friends_add_friend' ) )
			return false;

		if ( !friends_add_friend( $bp->loggedin_user->id, $potential_friend_id ) ) {
			bp_core_add_message( __( 'Friendship could not be requested.', 'buddypress' ), 'error' );
		} else {
			bp_core_add_message( __( 'Friendship requested', 'buddypress' ) );
		}
	} else if ( 'is_friend' == $friendship_status ) {
		bp_core_add_message( __( 'You are already friends with this user', 'buddypress' ), 'error' );
	} else {
		bp_core_add_message( __( 'You already have a pending friendship request with this user', 'buddypress' ), 'error' );
	}

	bp_core_redirect( wp_get_referer() );

	return false;
}
add_action( 'init', 'friends_action_add_friend' );

function friends_action_remove_friend() {
	global $bp;

	if ( $bp->current_component != $bp->friends->slug || $bp->current_action != 'remove-friend' )
		return false;

	$potential_friend_id = $bp->action_variables[0];

	if ( !is_numeric( $potential_friend_id ) || !isset( $potential_friend_id ) )
		return false;

	if ( $potential_friend_id == $bp->loggedin_user->id )
		return false;

	$friendship_status = BP_Friends_Friendship::check_is_friend( $bp->loggedin_user->id, $potential_friend_id );

	if ( 'is_friend' == $friendship_status ) {

		if ( !check_admin_referer( 'friends_remove_friend' ) )
			return false;

		if ( !friends_remove_friend( $bp->loggedin_user->id, $potential_friend_id ) ) {
			bp_core_add_message( __( 'Friendship could not be canceled.', 'buddypress' ), 'error' );
		} else {
			bp_core_add_message( __( 'Friendship canceled', 'buddypress' ) );
		}
	} else if ( 'is_friends' == $friendship_status ) {
		bp_core_add_message( __( 'You are not yet friends with this user', 'buddypress' ), 'error' );
	} else {
		bp_core_add_message( __( 'You have a pending friendship request with this user', 'buddypress' ), 'error' );
	}

	bp_core_redirect( wp_get_referer() );

	return false;
}
add_action( 'init', 'friends_action_remove_friend' );


/********************************************************************************
 * Activity & Notification Functions
 *
 * These functions handle the recording, deleting and formatting of activity and
 * notifications for the user and for this specific component.
 */

function friends_record_activity( $args = '' ) {
	global $bp;

	if ( !function_exists( 'bp_activity_add' ) )
		return false;

	$defaults = array(
		'user_id' => $bp->loggedin_user->id,
		'action' => '',
		'content' => '',
		'primary_link' => '',
		'component' => $bp->friends->id,
		'type' => false,
		'item_id' => false,
		'secondary_item_id' => false,
		'recorded_time' => bp_core_current_time(),
		'hide_sitewide' => false
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	return bp_activity_add( array( 'user_id' => $user_id, 'action' => $action, 'content' => $content, 'primary_link' => $primary_link, 'component' => $component, 'type' => $type, 'item_id' => $item_id, 'secondary_item_id' => $secondary_item_id, 'recorded_time' => $recorded_time, 'hide_sitewide' => $hide_sitewide ) );
}

function friends_delete_activity( $args ) {
	global $bp;

	if ( function_exists('bp_activity_delete_by_item_id') ) {
		extract( (array)$args );
		bp_activity_delete_by_item_id( array( 'item_id' => $item_id, 'component' => $bp->friends->id, 'type' => $type, 'user_id' => $user_id, 'secondary_item_id' => $secondary_item_id ) );
	}
}

function friends_register_activity_actions() {
	global $bp;

	if ( !function_exists( 'bp_activity_set_action' ) )
		return false;

	bp_activity_set_action( $bp->friends->id, 'friends_register_activity_action', __( 'New friendship created', 'buddypress' ) );

	do_action( 'friends_register_activity_actions' );
}
add_action( 'bp_register_activity_actions', 'friends_register_activity_actions' );

function friends_format_notifications( $action, $item_id, $secondary_item_id, $total_items ) {
	global $bp;

	switch ( $action ) {
		case 'friendship_accepted':
			if ( (int)$total_items > 1 ) {
				return apply_filters( 'bp_friends_multiple_friendship_accepted_notification', '<a href="' . $bp->loggedin_user->domain . $bp->friends->slug . '/my-friends/newest" title="' . __( 'My Friends', 'buddypress' ) . '">' . sprintf( __('%d friends accepted your friendship requests', 'buddypress' ), (int)$total_items ) . '</a>', (int)$total_items );
			} else {
				$user_fullname = bp_core_get_user_displayname( $item_id );
				$user_url = bp_core_get_user_domain( $item_id );
				return apply_filters( 'bp_friends_single_friendship_accepted_notification', '<a href="' . $user_url . '?new" title="' . $user_fullname .'\'s profile">' . sprintf( __( '%s accepted your friendship request', 'buddypress' ), $user_fullname ) . '</a>', $user_fullname );
			}
		break;

		case 'friendship_request':
			if ( (int)$total_items > 1 ) {
				return apply_filters( 'bp_friends_multiple_friendship_request_notification', '<a href="' . $bp->loggedin_user->domain . $bp->friends->slug . '/requests/?new" title="' . __( 'Friendship requests', 'buddypress' ) . '">' . sprintf( __('You have %d pending friendship requests', 'buddypress' ), (int)$total_items ) . '</a>', $total_items );
			} else {
				$user_fullname = bp_core_get_user_displayname( $item_id );
				$user_url = bp_core_get_user_domain( $item_id );
				return apply_filters( 'bp_friends_single_friendship_request_notification', '<a href="' . $bp->loggedin_user->domain . $bp->friends->slug . '/requests/?new" title="' . __( 'Friendship requests', 'buddypress' ) . '">' . sprintf( __('You have a friendship request from %s', 'buddypress' ), $user_fullname ) . '</a>', $user_fullname );
			}
		break;
	}

	do_action( 'friends_format_notifications', $action, $item_id, $secondary_item_id, $total_items );

	return false;
}


/********************************************************************************
 * Business Functions
 *
 * Business functions are where all the magic happens in BuddyPress. They will
 * handle the actual saving or manipulation of information. Usually they will
 * hand off to a database class for data access, then return
 * true or false on success or failure.
 */

function friends_add_friend( $initiator_userid, $friend_userid, $force_accept = false ) {
	global $bp;

	$friendship = new BP_Friends_Friendship;

	if ( (int)$friendship->is_confirmed )
		return true;

	$friendship->initiator_user_id = $initiator_userid;
	$friendship->friend_user_id = $friend_userid;
	$friendship->is_confirmed = 0;
	$friendship->is_limited = 0;
	$friendship->date_created = bp_core_current_time();

	if ( $force_accept )
		$friendship->is_confirmed = 1;

	if ( $friendship->save() ) {

		if ( !$force_accept ) {
			// Add the on screen notification
			bp_core_add_notification( $friendship->initiator_user_id, $friendship->friend_user_id, $bp->friends->id, 'friendship_request' );

			// Send the email notification
			require_once( BP_PLUGIN_DIR . '/bp-friends/bp-friends-notifications.php' );
			friends_notification_new_request( $friendship->id, $friendship->initiator_user_id, $friendship->friend_user_id );

			do_action( 'friends_friendship_requested', $friendship->id, $friendship->initiator_user_id, $friendship->friend_user_id );
		} else {
			do_action( 'friends_friendship_accepted', $friendship->id, $friendship->initiator_user_id, $friendship->friend_user_id );
		}

		return true;
	}

	return false;
}

function friends_remove_friend( $initiator_userid, $friend_userid ) {
	global $bp;

	$friendship_id = BP_Friends_Friendship::get_friendship_id( $initiator_userid, $friend_userid );
	$friendship = new BP_Friends_Friendship( $friendship_id );

	// Remove the activity stream item for the user who canceled the friendship
	friends_delete_activity( array( 'item_id' => $friendship_id, 'type' => 'friendship_accepted', 'user_id' => $bp->displayed_user->id ) );

	do_action( 'friends_friendship_deleted', $friendship_id, $initiator_userid, $friend_userid );

	if ( $friendship->delete() ) {
		friends_update_friend_totals( $initiator_userid, $friend_userid, 'remove' );

		return true;
	}

	return false;
}

function friends_accept_friendship( $friendship_id ) {
	global $bp;

	$friendship = new BP_Friends_Friendship( $friendship_id, true, false );

	if ( !$friendship->is_confirmed && BP_Friends_Friendship::accept( $friendship_id ) ) {
		friends_update_friend_totals( $friendship->initiator_user_id, $friendship->friend_user_id );

		// Remove the friend request notice
		bp_core_delete_notifications_for_user_by_item_id( $friendship->friend_user_id, $friendship->initiator_user_id, $bp->friends->id, 'friendship_request' );

		// Add a friend accepted notice for the initiating user
		bp_core_add_notification( $friendship->friend_user_id, $friendship->initiator_user_id, $bp->friends->id, 'friendship_accepted' );

		$initiator_link = bp_core_get_userlink( $friendship->initiator_user_id );
		$friend_link = bp_core_get_userlink( $friendship->friend_user_id );

		// Record in activity streams for the initiator
		friends_record_activity( array(
			'user_id'           => $friendship->initiator_user_id,
			'type'              => 'friendship_created',
			'action'            => apply_filters( 'friends_activity_friendship_accepted_action', sprintf( __( '%1$s and %2$s are now friends', 'buddypress' ), $initiator_link, $friend_link ), $friendship ),
			'item_id'           => $friendship_id,
			'secondary_item_id' => $friendship->friend_user_id
		) );

		// Record in activity streams for the friend
		friends_record_activity( array(
			'user_id'           => $friendship->friend_user_id,
			'type'              => 'friendship_created',
			'action'            => apply_filters( 'friends_activity_friendship_accepted_action', sprintf( __( '%1$s and %2$s are now friends', 'buddypress' ), $friend_link, $initiator_link ), $friendship ),
			'item_id'           => $friendship_id,
			'secondary_item_id' => $friendship->initiator_user_id,
			'hide_sitewide'     => true // We've already got the first entry site wide
		) );

		// Send the email notification
		require_once( BP_PLUGIN_DIR . '/bp-friends/bp-friends-notifications.php' );
		friends_notification_accepted_request( $friendship->id, $friendship->initiator_user_id, $friendship->friend_user_id );

		do_action( 'friends_friendship_accepted', $friendship->id, $friendship->initiator_user_id, $friendship->friend_user_id );
		return true;
	}

	return false;
}

function friends_reject_friendship( $friendship_id ) {
	$friendship = new BP_Friends_Friendship( $friendship_id, true, false );

	if ( !$friendship->is_confirmed && BP_Friends_Friendship::reject( $friendship_id ) ) {
		// Remove the friend request notice
		bp_core_delete_notifications_for_user_by_item_id( $friendship->friend_user_id, $friendship->initiator_user_id, $bp->friends->id, 'friendship_request' );

		do_action( 'friends_friendship_rejected', $friendship_id, &$friendship );
		return true;
	}

	return false;
}

function friends_check_friendship( $user_id, $possible_friend_id ) {
	global $bp;

	if ( 'is_friend' == BP_Friends_Friendship::check_is_friend( $user_id, $possible_friend_id ) )
		return true;

	return false;
}

function friends_check_friendship_status( $user_id, $possible_friend_id ) {
	/* Returns - 'is_friend', 'not_friends', 'pending' */
	return BP_Friends_Friendship::check_is_friend( $user_id, $possible_friend_id );
}

function friends_get_total_friend_count( $user_id = false ) {
	global $bp;

	if ( !$user_id )
		$user_id = ( $bp->displayed_user->id ) ? $bp->displayed_user->id : $bp->loggedin_user->id;

	if ( !$count = wp_cache_get( 'bp_total_friend_count_' . $user_id, 'bp' ) ) {
		$count = get_user_meta( $user_id, 'total_friend_count', true );
		if ( empty( $count ) ) $count = 0;
		wp_cache_set( 'bp_total_friend_count_' . $user_id, $count, 'bp' );
	}

	return apply_filters( 'friends_get_total_friend_count', $count );
}

function friends_check_user_has_friends( $user_id ) {
	$friend_count = friends_get_total_friend_count( $user_id );

	if ( empty( $friend_count ) )
		return false;

	if ( !(int)$friend_count )
		return false;

	return true;
}

function friends_get_friendship_id( $initiator_user_id, $friend_user_id ) {
	return BP_Friends_Friendship::get_friendship_id( $initiator_user_id, $friend_user_id );
}

function friends_get_friend_user_ids( $user_id, $friend_requests_only = false, $assoc_arr = false, $filter = false ) {
	return BP_Friends_Friendship::get_friend_user_ids( $user_id, $friend_requests_only, $assoc_arr, $filter );
}

function friends_search_friends( $search_terms, $user_id, $pag_num = 10, $pag_page = 1 ) {
	return BP_Friends_Friendship::search_friends( $search_terms, $user_id, $pag_num, $pag_page );
}

function friends_get_friendship_request_user_ids( $user_id ) {
	return BP_Friends_Friendship::get_friendship_request_user_ids( $user_id );
}

function friends_get_recently_active( $user_id, $per_page = false, $page = false, $filter = false ) {
	return apply_filters( 'friends_get_recently_active', BP_Core_User::get_users( 'active', $per_page, $page, $user_id, $filter ) );
}

function friends_get_alphabetically( $user_id, $per_page = false, $page = false, $filter = false ) {
	return apply_filters( 'friends_get_alphabetically', BP_Core_User::get_users( 'alphabetical', $per_page, $page, $user_id, $filter ) );
}

function friends_get_newest( $user_id, $per_page = false, $page = false, $filter = false ) {
	return apply_filters( 'friends_get_newest', BP_Core_User::get_users( 'newest', $per_page, $page, $user_id, $filter ) );
}

function friends_get_bulk_last_active( $friend_ids ) {
	return BP_Friends_Friendship::get_bulk_last_active( $friend_ids );
}

function friends_get_friends_invite_list( $user_id = false ) {
	global $bp;

	if ( !$user_id )
		$user_id = $bp->loggedin_user->id;

	if ( bp_has_members( 'user_id=' . $user_id . '&type=alphabetical&per_page=0' ) ) {
		while ( bp_members() ) : bp_the_member();
			$friends[] = array(
				'id' => bp_get_member_user_id(),
				'full_name' => bp_get_member_name()
			);
		endwhile;
	}

	if ( empty($friends) )
		return false;

	return $friends;
}

function friends_count_invitable_friends( $user_id, $group_id ) {
	return BP_Friends_Friendship::get_invitable_friend_count( $user_id, $group_id );
}

function friends_get_friend_count_for_user( $user_id ) {
	return BP_Friends_Friendship::total_friend_count( $user_id );
}

function friends_search_users( $search_terms, $user_id, $pag_num = false, $pag_page = false ) {
	global $bp;

	$user_ids = BP_Friends_Friendship::search_users( $search_terms, $user_id, $pag_num, $pag_page );

	if ( !$user_ids )
		return false;

	for ( $i = 0; $i < count($user_ids); $i++ ) {
		$users[] = new BP_Core_User($user_ids[$i]);
	}

	return array( 'users' => $users, 'count' => BP_Friends_Friendship::search_users_count($search_terms) );
}

function friends_is_friendship_confirmed( $friendship_id ) {
	$friendship = new BP_Friends_Friendship( $friendship_id );
	return $friendship->is_confirmed;
}

function friends_update_friend_totals( $initiator_user_id, $friend_user_id, $status = 'add' ) {
	if ( 'add' == $status ) {
		update_user_meta( $initiator_user_id, 'total_friend_count', (int)get_user_meta( $initiator_user_id, 'total_friend_count', true ) + 1 );
		update_user_meta( $friend_user_id, 'total_friend_count', (int)get_user_meta( $friend_user_id, 'total_friend_count', true ) + 1 );
	} else {
		update_user_meta( $initiator_user_id, 'total_friend_count', (int)get_user_meta( $initiator_user_id, 'total_friend_count', true ) - 1 );
		update_user_meta( $friend_user_id, 'total_friend_count', (int)get_user_meta( $friend_user_id, 'total_friend_count', true ) - 1 );
	}
}

function friends_remove_data( $user_id ) {
	global $bp;

	BP_Friends_Friendship::delete_all_for_user($user_id);

	/* Remove usermeta */
	delete_user_meta( $user_id, 'total_friend_count' );

	/* Remove friendship requests FROM user */
	bp_core_delete_notifications_from_user( $user_id, $bp->friends->id, 'friendship_request' );

	do_action( 'friends_remove_data', $user_id );
}
add_action( 'wpmu_delete_user', 'friends_remove_data' );
add_action( 'delete_user', 'friends_remove_data' );
add_action( 'bp_make_spam_user', 'friends_remove_data' );


/********************************************************************************
 * Caching
 *
 * Caching functions handle the clearing of cached objects and pages on specific
 * actions throughout BuddyPress.
 */

function friends_clear_friend_object_cache( $friendship_id ) {
	if ( !$friendship = new BP_Friends_Friendship( $friendship_id ) )
		return false;

	wp_cache_delete( 'friends_friend_ids_' . $friendship->initiator_user_id, 'bp' );
	wp_cache_delete( 'friends_friend_ids_' . $friendship->friend_user_id, 'bp' );
	wp_cache_delete( 'bp_total_friend_count_' . $friendship->initiator_user_id, 'bp' );
	wp_cache_delete( 'bp_total_friend_count_' . $friendship->friend_user_id, 'bp' );
}

function friends_clear_friend_notifications() {
	global $bp;

	if ( isset( $_GET['new'] ) )
		bp_core_delete_notifications_for_user_by_type( $bp->loggedin_user->id, $bp->friends->id, 'friendship_accepted' );
}
add_action( 'bp_activity_screen_my_activity', 'friends_clear_friend_notifications' );

// List actions to clear object caches on
add_action( 'friends_friendship_accepted', 'friends_clear_friend_object_cache' );
add_action( 'friends_friendship_deleted', 'friends_clear_friend_object_cache' );

// List actions to clear super cached pages on, if super cache is installed
add_action( 'friends_friendship_rejected', 'bp_core_clear_cache' );
add_action( 'friends_friendship_accepted', 'bp_core_clear_cache' );
add_action( 'friends_friendship_deleted', 'bp_core_clear_cache' );
add_action( 'friends_friendship_requested', 'bp_core_clear_cache' );

?>