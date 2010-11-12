<?php
require ( BP_PLUGIN_DIR . '/bp-activity/bp-activity-classes.php' );
require ( BP_PLUGIN_DIR . '/bp-activity/bp-activity-templatetags.php' );
require ( BP_PLUGIN_DIR . '/bp-activity/bp-activity-filters.php' );

function bp_activity_setup_globals() {
	global $bp, $current_blog;

	if ( !defined( 'BP_ACTIVITY_SLUG' ) )
		define ( 'BP_ACTIVITY_SLUG', $bp->pages->activity->slug );

	// For internal identification
	$bp->activity->id = 'activity';
	$bp->activity->name = $bp->pages->activity->name;
	$bp->activity->slug = BP_ACTIVITY_SLUG;

	$bp->activity->table_name      = $bp->table_prefix . 'bp_activity';
	$bp->activity->table_name_meta = $bp->table_prefix . 'bp_activity_meta';

	$bp->activity->format_notification_function = 'bp_activity_format_notifications';

	// Register this in the active components array
	$bp->active_components[$bp->activity->slug] = $bp->activity->id;

	do_action( 'bp_activity_setup_globals' );
}
add_action( 'bp_setup_globals', 'bp_activity_setup_globals' );

function bp_activity_setup_nav() {
	global $bp;

	/* Add 'Activity' to the main navigation */
	bp_core_new_nav_item( array( 'name' => __( 'Activity', 'buddypress' ), 'slug' => $bp->activity->name, 'position' => 10, 'screen_function' => 'bp_activity_screen_my_activity', 'default_subnav_slug' => 'just-me', 'item_css_id' => $bp->activity->id ) );

	if ( !is_user_logged_in() && !isset( $bp->displayed_user->id ) )
		return;

	$user_domain = ( isset( $bp->displayed_user->domain ) ) ? $bp->displayed_user->domain : $bp->loggedin_user->domain;
	$user_login = ( isset( $bp->displayed_user->userdata->user_login ) ) ? $bp->displayed_user->userdata->user_login : $bp->loggedin_user->userdata->user_login;
	$activity_link = $user_domain . $bp->activity->name . '/';

	/* Add the subnav items to the activity nav item if we are using a theme that supports this */
	bp_core_new_subnav_item( array( 'name' => __( 'Personal', 'buddypress' ), 'slug' => 'just-me', 'parent_url' => $activity_link, 'parent_slug' => $bp->activity->name, 'screen_function' => 'bp_activity_screen_my_activity', 'position' => 10 ) );

	if ( bp_is_active( 'friends' ) )
		bp_core_new_subnav_item( array( 'name' => __( 'Friends', 'buddypress' ), 'slug' => BP_FRIENDS_SLUG, 'parent_url' => $activity_link, 'parent_slug' => $bp->activity->name, 'screen_function' => 'bp_activity_screen_friends', 'position' => 20, 'item_css_id' => 'activity-friends' ) );

	if ( bp_is_active( 'groups' ) )
		bp_core_new_subnav_item( array( 'name' => __( 'Groups', 'buddypress' ), 'slug' => BP_GROUPS_SLUG, 'parent_url' => $activity_link, 'parent_slug' => $bp->activity->name, 'screen_function' => 'bp_activity_screen_groups', 'position' => 30, 'item_css_id' => 'activity-groups' ) );

	bp_core_new_subnav_item( array( 'name' => __( 'Favorites', 'buddypress' ), 'slug' => 'favorites', 'parent_url' => $activity_link, 'parent_slug' => $bp->activity->name, 'screen_function' => 'bp_activity_screen_favorites', 'position' => 40, 'item_css_id' => 'activity-favs' ) );
	bp_core_new_subnav_item( array( 'name' => sprintf( __( '@%s Mentions', 'buddypress' ), $user_login ), 'slug' => 'mentions', 'parent_url' => $activity_link, 'parent_slug' => $bp->activity->name, 'screen_function' => 'bp_activity_screen_mentions', 'position' => 50, 'item_css_id' => 'activity-mentions' ) );

	if ( $bp->current_component == $bp->activity->slug ) {
		if ( bp_is_my_profile() ) {
			$bp->bp_options_title = __( 'My Activity', 'buddypress' );
		} else {
			$bp->bp_options_avatar = bp_core_fetch_avatar( array( 'item_id' => $bp->displayed_user->id, 'type' => 'thumb' ) );
			$bp->bp_options_title = $bp->displayed_user->fullname;
		}
	}

	do_action( 'bp_activity_setup_nav' );
}
add_action( 'bp_setup_nav', 'bp_activity_setup_nav' );

function bp_activity_directory_activity_setup() {
	global $bp;

	if ( $bp->current_component == $bp->activity->slug && empty( $bp->current_action ) ) {
		$bp->is_directory = true;

		do_action( 'bp_activity_directory_activity_setup' );
		bp_core_load_template( apply_filters( 'bp_activity_directory_activity_setup', 'activity/index' ) );
	}
}
add_action( 'wp', 'bp_activity_directory_activity_setup', 2 );


/********************************************************************************
 * Screen Functions
 *
 * Screen functions are the controllers of BuddyPress. They will execute when their
 * specific URL is caught. They will first save or manipulate data using business
 * functions, then pass on the user to a template file.
 */

function bp_activity_screen_my_activity() {
	do_action( 'bp_activity_screen_my_activity' );
	bp_core_load_template( apply_filters( 'bp_activity_template_my_activity', 'members/single/home' ) );
}

function bp_activity_screen_friends() {
	global $bp;

	if ( !bp_is_active( 'friends' ) )
		return false;

	if ( !is_super_admin() )
		$bp->is_item_admin = false;

	do_action( 'bp_activity_screen_friends' );
	bp_core_load_template( apply_filters( 'bp_activity_template_friends_activity', 'members/single/home' ) );
}

function bp_activity_screen_groups() {
	global $bp;

	if ( !bp_is_active( 'groups' ) )
		return false;

	if ( !is_super_admin() )
		$bp->is_item_admin = false;

	do_action( 'bp_activity_screen_groups' );
	bp_core_load_template( apply_filters( 'bp_activity_template_groups_activity', 'members/single/home' ) );
}

function bp_activity_screen_favorites() {
	global $bp;

	if ( !is_super_admin() )
		$bp->is_item_admin = false;

	do_action( 'bp_activity_screen_favorites' );
	bp_core_load_template( apply_filters( 'bp_activity_template_favorite_activity', 'members/single/home' ) );
}

function bp_activity_screen_mentions() {
	global $bp;

	if ( !is_super_admin() )
		$bp->is_item_admin = false;

	do_action( 'bp_activity_screen_mentions' );
	bp_core_load_template( apply_filters( 'bp_activity_template_mention_activity', 'members/single/home' ) );
}

/**
 * bp_activity_remove_screen_notifications()
 *
 * Removes activity notifications from the notification menu when a user clicks on them and
 * is taken to a specific screen.
 *
 * @package BuddyPress Activity
 */
function bp_activity_remove_screen_notifications() {
	global $bp;

	bp_core_delete_notifications_for_user_by_type( $bp->loggedin_user->id, $bp->activity->id, 'new_at_mention' );
}
add_action( 'bp_activity_screen_my_activity', 'bp_activity_remove_screen_notifications' );
add_action( 'bp_activity_screen_single_activity_permalink', 'bp_activity_remove_screen_notifications' );
add_action( 'bp_activity_screen_mentions', 'bp_activity_remove_screen_notifications' );

function bp_activity_screen_single_activity_permalink() {
	global $bp;

	if ( !$bp->displayed_user->id || $bp->current_component != $bp->activity->name )
		return false;

	if ( empty( $bp->current_action ) || !is_numeric( $bp->current_action ) )
		return false;

	/* Get the activity details */
	$activity = bp_activity_get_specific( array( 'activity_ids' => $bp->current_action ) );

	if ( !$activity = $activity['activities'][0] )
		bp_core_redirect( $bp->root_domain );

	$has_access = true;
	/* Redirect based on the type of activity */
	if ( $activity->component == $bp->groups->id ) {
		if ( !function_exists( 'groups_get_group' ) )
			bp_core_redirect( $bp->root_domain );

		if ( $group = groups_get_group( array( 'group_id' => $activity->item_id ) ) ) {
			/* Check to see if the group is not public, if so, check the user has access to see this activity */
			if ( 'public' != $group->status ) {
				if ( !groups_is_user_member( $bp->loggedin_user->id, $group->id ) )
					$has_access = false;
			}
		}
	}

	$has_access = apply_filters( 'bp_activity_permalink_access', $has_access, &$activity );

	do_action( 'bp_activity_screen_single_activity_permalink', $activity, $has_access );

	if ( !$has_access ) {
		bp_core_add_message( __( 'You do not have access to this activity.', 'buddypress' ), 'error' );

		if ( is_user_logged_in() )
			bp_core_redirect( $bp->loggedin_user->domain );
		else
			bp_core_redirect( site_url( 'wp-login.php?redirect_to=' . esc_url( $bp->root_domain . '/' . $bp->activity->slug . '/p/' . $bp->current_action ) ) );
	}

	bp_core_load_template( apply_filters( 'bp_activity_template_profile_activity_permalink', 'members/single/activity/permalink' ) );
}
/* This screen is not attached to a nav item, so we need to add an action for it. */
add_action( 'wp', 'bp_activity_screen_single_activity_permalink', 3 );

function bp_activity_screen_notification_settings() {
	global $bp; ?>
	<table class="notification-settings zebra" id="activity-notification-settings">
		<thead>
			<tr>
				<th class="icon"></th>
				<th class="title"><?php _e( 'Activity', 'buddypress' ) ?></th>
				<th class="yes"><?php _e( 'Yes', 'buddypress' ) ?></th>
				<th class="no"><?php _e( 'No', 'buddypress' )?></th>
			</tr>
		</thead>

		<tbody>
			<tr>
				<td></td>
				<td><?php printf( __( 'A member mentions you in an update using "@%s"', 'buddypress' ), bp_core_get_username( $bp->loggedin_user->id, $bp->loggedin_user->userdata->user_nicename, $bp->loggedin_user->userdata->user_login ) ) ?></td>
				<td class="yes"><input type="radio" name="notifications[notification_activity_new_mention]" value="yes" <?php if ( !get_user_meta( $bp->loggedin_user->id, 'notification_activity_new_mention', true ) || 'yes' == get_user_meta( $bp->loggedin_user->id, 'notification_activity_new_mention', true ) ) { ?>checked="checked" <?php } ?>/></td>
				<td class="no"><input type="radio" name="notifications[notification_activity_new_mention]" value="no" <?php if ( 'no' == get_user_meta( $bp->loggedin_user->id, 'notification_activity_new_mention', true ) ) { ?>checked="checked" <?php } ?>/></td>
			</tr>
			<tr>
				<td></td>
				<td><?php _e( "A member replies to an update or comment you've posted", 'buddypress' ) ?></td>
				<td class="yes"><input type="radio" name="notifications[notification_activity_new_reply]" value="yes" <?php if ( !get_user_meta( $bp->loggedin_user->id, 'notification_activity_new_reply', true ) || 'yes' == get_user_meta( $bp->loggedin_user->id, 'notification_activity_new_reply', true ) ) { ?>checked="checked" <?php } ?>/></td>
				<td class="no"><input type="radio" name="notifications[notification_activity_new_reply]" value="no" <?php if ( 'no' == get_user_meta( $bp->loggedin_user->id, 'notification_activity_new_reply', true ) ) { ?>checked="checked" <?php } ?>/></td>
			</tr>

			<?php do_action( 'bp_activity_screen_notification_settings' ) ?>
		</tbody>
	</table>
<?php
}
add_action( 'bp_notification_settings', 'bp_activity_screen_notification_settings', 1 );

/********************************************************************************
 * Action Functions
 *
 * Action functions are exactly the same as screen functions, however they do not
 * have a template screen associated with them. Usually they will send the user
 * back to the default screen after execution.
 */

function bp_activity_action_permalink_router() {
	global $bp;

	if ( $bp->current_component != $bp->activity->slug || $bp->current_action != 'p' )
		return false;

	if ( empty( $bp->action_variables[0] ) || !is_numeric( $bp->action_variables[0] ) )
		return false;

	/* Get the activity details */
	$activity = bp_activity_get_specific( array( 'activity_ids' => $bp->action_variables[0] ) );

	if ( !$activity = $activity['activities'][0] )
		bp_core_redirect( $bp->root_domain );

	$redirect = false;
	/* Redirect based on the type of activity */
	if ( $activity->component == $bp->groups->id ) {
		if ( $activity->user_id )
			$redirect = bp_core_get_user_domain( $activity->user_id, $activity->user_nicename, $activity->user_login ) . $bp->activity->slug . '/' . $activity->id . '/';
		else {
			if ( $group = groups_get_group( array( 'group_id' => $activity->item_id ) ) )
				$redirect = bp_get_group_permalink( $group ) . $bp->activity->slug . '/' . $activity->id . '/';
		}
	} else {
		$redirect = bp_core_get_user_domain( $activity->user_id, $activity->user_nicename, $activity->user_login ) . $bp->activity->slug . '/' . $activity->id;
	}

	$redirect = apply_filters( 'bp_activity_permalink_redirect_url', $redirect, &$activity );

	if ( !$redirect )
		bp_core_redirect( $bp->root_domain );

	/* Redirect to the actual activity permalink page */
	bp_core_redirect( $redirect );
}
add_action( 'wp', 'bp_activity_action_permalink_router', 3 );

function bp_activity_action_delete_activity() {
	global $bp;

	if ( $bp->current_component != $bp->activity->slug || $bp->current_action != 'delete' )
		return false;

	if ( empty( $bp->action_variables[0] ) || !is_numeric( $bp->action_variables[0] ) )
		return false;

	/* Check the nonce */
	check_admin_referer( 'bp_activity_delete_link' );

	$activity_id = $bp->action_variables[0];
	$activity = new BP_Activity_Activity( $activity_id );

	/* Check access */
	if ( !is_super_admin() && $activity->user_id != $bp->loggedin_user->id )
		return false;

	/* Call the action before the delete so plugins can still fetch information about it */
	do_action( 'bp_activity_action_delete_activity', $activity_id, $activity->user_id );

	/* Now delete the activity item */
	if ( bp_activity_delete( array( 'id' => $activity_id, 'user_id' => $activity->user_id ) ) )
		bp_core_add_message( __( 'Activity deleted', 'buddypress' ) );
	else
		bp_core_add_message( __( 'There was an error when deleting that activity', 'buddypress' ), 'error' );

	bp_core_redirect( wp_get_referer() );
}
add_action( 'wp', 'bp_activity_action_delete_activity', 3 );

function bp_activity_action_post_update() {
	global $bp;

	if ( !is_user_logged_in() || $bp->current_component != $bp->activity->slug || $bp->current_action != 'post' )
		return false;

	/* Check the nonce */
	check_admin_referer( 'post_update', '_wpnonce_post_update' );

	$content = apply_filters( 'bp_activity_post_update_content', $_POST['whats-new'] );
	$object = apply_filters( 'bp_activity_post_update_object', $_POST['whats-new-post-object'] );
	$item_id = apply_filters( 'bp_activity_post_update_item_id', $_POST['whats-new-post-in'] );

	if ( empty( $content ) ) {
		bp_core_add_message( __( 'Please enter some content to post.', 'buddypress' ), 'error' );
		bp_core_redirect( wp_get_referer() );
	}

	if ( !(int)$item_id ) {
		$activity_id = bp_activity_post_update( array( 'content' => $content ) );

	} else if ( 'groups' == $object && function_exists( 'groups_post_update' ) ) {
		if ( (int)$item_id ) {
			$activity_id = groups_post_update( array( 'content' => $content, 'group_id' => $item_id ) );
		}
	} else
		$activity_id = apply_filters( 'bp_activity_custom_update', $object, $item_id, $content );

	if ( !empty( $activity_id ) )
		bp_core_add_message( __( 'Update Posted!', 'buddypress' ) );
	else
		bp_core_add_message( __( 'There was an error when posting your update, please try again.', 'buddypress' ), 'error' );

	bp_core_redirect( wp_get_referer() );
}
add_action( 'wp', 'bp_activity_action_post_update', 3 );

function bp_activity_action_post_comment() {
	global $bp;

	if ( !is_user_logged_in() || $bp->current_component != $bp->activity->slug || $bp->current_action != 'reply' )
		return false;

	/* Check the nonce */
	check_admin_referer( 'new_activity_comment', '_wpnonce_new_activity_comment' );

	$activity_id = apply_filters( 'bp_activity_post_comment_activity_id', $_POST['comment_form_id'] );
	$content = apply_filters( 'bp_activity_post_comment_content', $_POST['ac_input_' . $activity_id] );

	if ( empty( $content ) ) {
		bp_core_add_message( __( 'Please do not leave the comment area blank.', 'buddypress' ), 'error' );
		bp_core_redirect( wp_get_referer() . '#ac-form-' . $activity_id );
	}

	$comment_id = bp_activity_new_comment( array(
		'content' => $content,
		'activity_id' => $activity_id,
		'parent_id' => $parent_id
	));

	if ( !empty( $comment_id ) )
		bp_core_add_message( __( 'Reply Posted!', 'buddypress' ) );
	else
		bp_core_add_message( __( 'There was an error posting that reply, please try again.', 'buddypress' ), 'error' );

	bp_core_redirect( wp_get_referer() . '#ac-form-' . $activity_id );
}
add_action( 'wp', 'bp_activity_action_post_comment', 3 );

function bp_activity_action_mark_favorite() {
	global $bp;

	if ( !is_user_logged_in() || $bp->current_component != $bp->activity->slug || $bp->current_action != 'favorite' )
		return false;

	/* Check the nonce */
	check_admin_referer( 'mark_favorite' );

	if ( bp_activity_add_user_favorite( $bp->action_variables[0] ) )
		bp_core_add_message( __( 'Activity marked as favorite.', 'buddypress' ) );
	else
		bp_core_add_message( __( 'There was an error marking that activity as a favorite, please try again.', 'buddypress' ), 'error' );

	bp_core_redirect( wp_get_referer() . '#activity-' . $bp->action_variables[0] );
}
add_action( 'wp', 'bp_activity_action_mark_favorite', 3 );

function bp_activity_action_remove_favorite() {
	global $bp;

	if ( !is_user_logged_in() || $bp->current_component != $bp->activity->slug || $bp->current_action != 'unfavorite' )
		return false;

	/* Check the nonce */
	check_admin_referer( 'unmark_favorite' );

	if ( bp_activity_remove_user_favorite( $bp->action_variables[0] ) )
		bp_core_add_message( __( 'Activity removed as favorite.', 'buddypress' ) );
	else
		bp_core_add_message( __( 'There was an error removing that activity as a favorite, please try again.', 'buddypress' ), 'error' );

	bp_core_redirect( wp_get_referer() . '#activity-' . $bp->action_variables[0] );
}
add_action( 'wp', 'bp_activity_action_remove_favorite', 3 );

function bp_activity_action_sitewide_feed() {
	global $bp, $wp_query;

	if ( $bp->current_component != $bp->activity->slug || $bp->current_action != 'feed' || ( isset( $bp->displayed_user->id ) && $bp->displayed_user->id ) || isset( $bp->groups->current_group ) )
		return false;

	$wp_query->is_404 = false;
	status_header( 200 );

	include_once( 'bp-activity/feeds/bp-activity-sitewide-feed.php' );
	die;
}
add_action( 'wp', 'bp_activity_action_sitewide_feed', 3 );

function bp_activity_action_personal_feed() {
	global $bp, $wp_query;

	if ( $bp->current_component != $bp->activity->slug || !$bp->displayed_user->id || $bp->current_action != 'feed' )
		return false;

	$wp_query->is_404 = false;
	status_header( 200 );

	include_once( 'bp-activity/feeds/bp-activity-personal-feed.php' );
	die;
}
add_action( 'wp', 'bp_activity_action_personal_feed', 3 );

function bp_activity_action_friends_feed() {
	global $bp, $wp_query;

	if ( $bp->current_component != $bp->activity->slug || !$bp->displayed_user->id || $bp->current_action != $bp->friends->slug || !isset( $bp->action_variables[0] ) || $bp->action_variables[0] != 'feed' )
		return false;

	$wp_query->is_404 = false;
	status_header( 200 );

	include_once( 'bp-activity/feeds/bp-activity-friends-feed.php' );
	die;
}
add_action( 'wp', 'bp_activity_action_friends_feed', 3 );

function bp_activity_action_my_groups_feed() {
	global $bp, $wp_query;

	if ( $bp->current_component != $bp->activity->slug || !$bp->displayed_user->id || $bp->current_action != $bp->groups->slug || !isset( $bp->action_variables[0] ) || $bp->action_variables[0] != 'feed' )
		return false;

	$wp_query->is_404 = false;
	status_header( 200 );

	include_once( 'bp-activity/feeds/bp-activity-mygroups-feed.php' );
	die;
}
add_action( 'wp', 'bp_activity_action_my_groups_feed', 3 );

function bp_activity_action_mentions_feed() {
	global $bp, $wp_query;

	if ( $bp->current_component != $bp->activity->slug || !$bp->displayed_user->id || $bp->current_action != 'mentions' || !isset( $bp->action_variables[0] ) || $bp->action_variables[0] != 'feed' )
		return false;

	$wp_query->is_404 = false;
	status_header( 200 );

	include_once( 'bp-activity/feeds/bp-activity-mentions-feed.php' );
	die;
}
add_action( 'wp', 'bp_activity_action_mentions_feed', 3 );

function bp_activity_action_favorites_feed() {
	global $bp, $wp_query;

	if ( $bp->current_component != $bp->activity->slug || !$bp->displayed_user->id || $bp->current_action != 'favorites' || !isset( $bp->action_variables[0] ) || $bp->action_variables[0] != 'feed' )
		return false;

	$wp_query->is_404 = false;
	status_header( 200 );

	include_once( 'bp-activity/feeds/bp-activity-favorites-feed.php' );
	die;
}
add_action( 'wp', 'bp_activity_action_favorites_feed', 3 );

/**
 * bp_activity_format_notifications()
 *
 * Formats notifications related to activity
 *
 * @package BuddyPress Activity
 * @param $action The type of activity item. Just 'new_at_mention' for now
 * @param $item_id The activity id
 * @param $secondary_item_id In the case of at-mentions, this is the mentioner's id
 * @param $total_items The total number of notifications to format
 */
function bp_activity_format_notifications( $action, $item_id, $secondary_item_id, $total_items ) {
	global $bp;

	switch ( $action ) {
		case 'new_at_mention':
			$activity_id = $item_id;
			$poster_user_id = $secondary_item_id;

			$at_mention_link = $bp->loggedin_user->domain . $bp->activity->slug . '/mentions/';
			$at_mention_title = sprintf( __( '@%s Mentions', 'buddypress' ), $bp->loggedin_user->userdata->user_nicename );

			if ( (int)$total_items > 1 ) {
				return apply_filters( 'bp_activity_multiple_at_mentions_notification', '<a href="' . $at_mention_link . '" title="' . $at_mention_title . '">' . sprintf( __( 'You have %1$d new activity mentions', 'buddypress' ), (int)$total_items ) . '</a>', $at_mention_link, $total_items, $activity_id, $poster_user_id );
			} else {
				$user_fullname = bp_core_get_user_displayname( $poster_user_id );
				
				return apply_filters( 'bp_activity_single_at_mentions_notification', '<a href="' . $at_mention_link . '" title="' . $at_mention_title . '">' . sprintf( __( '%1$s mentioned you in an activity update', 'buddypress' ), $user_fullname ) . '</a>', $at_mention_link, $total_items, $activity_id, $poster_user_id );
			}
		break;
	}

	do_action( 'activity_format_notifications', $action, $item_id, $secondary_item_id, $total_items );

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

function bp_activity_get( $args = '' ) {
	$defaults = array(
		'max' => false, // Maximum number of results to return
		'page' => 1, // page 1 without a per_page will result in no pagination.
		'per_page' => false, // results per page
		'sort' => 'DESC', // sort ASC or DESC
		'display_comments' => false, // false for no comments. 'stream' for within stream display, 'threaded' for below each activity item

		'search_terms' => false, // Pass search terms as a string
		'show_hidden' => false, // Show activity items that are hidden site-wide?
		'exclude' => false, // Comma-separated list of activity IDs to exclude

		/**
		 * Pass filters as an array -- all filter items can be multiple values comma separated:
		 * array(
		 * 	'user_id' => false, // user_id to filter on
		 *	'object' => false, // object to filter on e.g. groups, profile, status, friends
		 *	'action' => false, // action to filter on e.g. activity_update, profile_updated
		 *	'primary_id' => false, // object ID to filter on e.g. a group_id or forum_id or blog_id etc.
		 *	'secondary_id' => false, // secondary object ID to filter on e.g. a post_id
		 * );
		 */
		'filter' => array()
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	/* Attempt to return a cached copy of the first page of sitewide activity. */
	if ( 1 == (int)$page && empty( $max ) && empty( $search_terms ) && empty( $filter ) && 'DESC' == $sort && empty( $exclude ) ) {
		if ( !$activity = wp_cache_get( 'bp_activity_sitewide_front', 'bp' ) ) {
			$activity = BP_Activity_Activity::get( $max, $page, $per_page, $sort, $search_terms, $filter, $display_comments, $show_hidden );
			wp_cache_set( 'bp_activity_sitewide_front', $activity, 'bp' );
		}
	} else
		$activity = BP_Activity_Activity::get( $max, $page, $per_page, $sort, $search_terms, $filter, $display_comments, $show_hidden, $exclude );

	return apply_filters( 'bp_activity_get', $activity, &$r );
}

function bp_activity_get_specific( $args = '' ) {
	$defaults = array(
		'activity_ids' => false, // A single activity_id or array of IDs.
		'page' => 1, // page 1 without a per_page will result in no pagination.
		'per_page' => false, // results per page
		'max' => false, // Maximum number of results to return
		'sort' => 'DESC', // sort ASC or DESC
		'display_comments' => false // true or false to display threaded comments for these specific activity items
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	return apply_filters( 'bp_activity_get_specific', BP_Activity_Activity::get_specific( $activity_ids, $max, $page, $per_page, $sort, $display_comments ) );
}

function bp_activity_add( $args = '' ) {
	global $bp;

	$defaults = array(
		'id'                => false, // Pass an existing activity ID to update an existing entry.

		'action'            => '', // The activity action - e.g. "Jon Doe posted an update"
		'content'           => '', // Optional: The content of the activity item e.g. "BuddyPress is awesome guys!"

		'component'         => false, // The name/ID of the component e.g. groups, profile, mycomponent
		'type'              => false, // The activity type e.g. activity_update, profile_updated
		'primary_link'      => '', // Optional: The primary URL for this item in RSS feeds (defaults to activity permalink)

		'user_id'           => $bp->loggedin_user->id, // Optional: The user to record the activity for, can be false if this activity is not for a user.
		'item_id'           => false, // Optional: The ID of the specific item being recorded, e.g. a blog_id
		'secondary_item_id' => false, // Optional: A second ID used to further filter e.g. a comment_id
		'recorded_time'     => bp_core_current_time(), // The GMT time that this activity was recorded
		'hide_sitewide'     => false // Should this be hidden on the sitewide activity stream?
	);

	$params = wp_parse_args( $args, $defaults );
	extract( $params, EXTR_SKIP );

	/* Make sure we are backwards compatible */
	if ( empty( $component ) && !empty( $component_name ) )
		$component = $component_name;

	if ( empty( $type ) && !empty( $component_action ) )
		$type = $component_action;

	$activity = new BP_Activity_Activity( $id );

	$activity->user_id = $user_id;
	$activity->component = $component;
	$activity->type = $type;
	$activity->action = $action;
	$activity->content = $content;
	$activity->primary_link = $primary_link;
	$activity->item_id = $item_id;
	$activity->secondary_item_id = $secondary_item_id;
	$activity->date_recorded = $recorded_time;
	$activity->hide_sitewide = $hide_sitewide;

	if ( !$activity->save() )
		return false;

	/* If this is an activity comment, rebuild the tree */
	if ( 'activity_comment' == $activity->type )
		BP_Activity_Activity::rebuild_activity_comment_tree( $activity->item_id );

	wp_cache_delete( 'bp_activity_sitewide_front', 'bp' );
	do_action( 'bp_activity_add', $params );

	return $activity->id;
}

function bp_activity_post_update( $args = '' ) {
	global $bp;

	$defaults = array(
		'content' => false,
		'user_id' => $bp->loggedin_user->id
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	if ( empty( $content ) || !strlen( trim( $content ) ) )
		return false;

	/* Record this on the user's profile */
	$from_user_link = bp_core_get_userlink( $user_id );
	$activity_action = sprintf( __( '%s posted an update:', 'buddypress' ), $from_user_link );
	$activity_content = $content;

	$primary_link = bp_core_get_userlink( $user_id, false, true );

	/* Now write the values */
	$activity_id = bp_activity_add( array(
		'user_id' => $user_id,
		'action' => apply_filters( 'bp_activity_new_update_action', $activity_action ),
		'content' => apply_filters( 'bp_activity_new_update_content', $activity_content ),
		'primary_link' => apply_filters( 'bp_activity_new_update_primary_link', $primary_link ),
		'component' => $bp->activity->id,
		'type' => 'activity_update'
	) );

	/* Add this update to the "latest update" usermeta so it can be fetched anywhere. */
	update_user_meta( $bp->loggedin_user->id, 'bp_latest_update', array( 'id' => $activity_id, 'content' => wp_filter_kses( $content ) ) );

 	/* Require the notifications code so email notifications can be set on the 'bp_activity_posted_update' action. */
	require_once( BP_PLUGIN_DIR . '/bp-activity/bp-activity-notifications.php' );

	do_action( 'bp_activity_posted_update', $content, $user_id, $activity_id );

	return $activity_id;
}

function bp_activity_new_comment( $args = '' ) {
	global $bp;

	$defaults = array(
		'id' => false,
		'content' => false,
		'user_id' => $bp->loggedin_user->id,
		'activity_id' => false, // ID of the root activity item
		'parent_id' => false // ID of a parent comment (optional)
	);

	$params = wp_parse_args( $args, $defaults );
	extract( $params, EXTR_SKIP );

	if ( empty($content) || empty($user_id) || empty($activity_id) )
		return false;

	if ( empty($parent_id) )
		$parent_id = $activity_id;

	/* Check to see if the parent activity is hidden, and if so, hide this comment publically. */
	$activity = new BP_Activity_Activity( $activity_id );
	$is_hidden = ( (int)$activity->hide_sitewide ) ? 1 : 0;

	/* Insert the activity comment */
	$comment_id = bp_activity_add( array(
		'id' => $id,
		'action' => apply_filters( 'bp_activity_comment_action', sprintf( __( '%s posted a new activity comment:', 'buddypress' ), bp_core_get_userlink( $user_id ) ) ),
		'content' => apply_filters( 'bp_activity_comment_content', $content ),
		'component' => $bp->activity->id,
		'type' => 'activity_comment',
		'user_id' => $user_id,
		'item_id' => $activity_id,
		'secondary_item_id' => $parent_id,
		'hide_sitewide' => $is_hidden
	) );

	/* Send an email notification if settings allow */
	require_once( BP_PLUGIN_DIR . '/bp-activity/bp-activity-notifications.php' );
	bp_activity_new_comment_notification( $comment_id, $user_id, $params );

	/* Clear the comment cache for this activity */
	wp_cache_delete( 'bp_activity_comments_' . $parent_id );

	do_action( 'bp_activity_comment_posted', $comment_id, $params );

	return $comment_id;
}

/**
 * bp_activity_get_activity_id()
 *
 * Fetch the activity_id for an existing activity entry in the DB.
 *
 * @package BuddyPress Activity
 */
function bp_activity_get_activity_id( $args = '' ) {
	$defaults = array(
		'user_id' => false,
		'component' => false,
		'type' => false,
		'item_id' => false,
		'secondary_item_id' => false,
		'action' => false,
		'content' => false,
		'date_recorded' => false,
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

 	return apply_filters( 'bp_activity_get_activity_id', BP_Activity_Activity::get_id( $user_id, $component, $type, $item_id, $secondary_item_id, $action, $content, $date_recorded ) );
}

/***
 * Deleting Activity
 *
 * If you're looking to hook into one action that provides the ID(s) of
 * the activity/activities deleted, then use:
 *
 * add_action( 'bp_activity_deleted_activities', 'my_function' );
 *
 * The action passes one parameter that is a single activity ID or an
 * array of activity IDs depending on the number deleted.
 *
 * If you are deleting an activity comment please use bp_activity_delete_comment();
*/

function bp_activity_delete( $args = '' ) {
	global $bp;

	/* Pass one or more the of following variables to delete by those variables */
	$defaults = array(
		'id' => false,
		'action' => false,
		'content' => false,
		'component' => false,
		'type' => false,
		'primary_link' => false,
		'user_id' => false,
		'item_id' => false,
		'secondary_item_id' => false,
		'date_recorded' => false,
		'hide_sitewide' => false
	);

	$args = wp_parse_args( $args, $defaults );

	if ( !$activity_ids_deleted = BP_Activity_Activity::delete( $args ) )
		return false;

	/* Check if the user's latest update has been deleted */
	if ( empty( $args['user_id'] ) )
		$user_id = $bp->loggedin_user->id;
	else
		$user_id = $args['user_id'];

	$latest_update = get_user_meta( $user_id, 'bp_latest_update', true );
	if ( !empty( $latest_update ) ) {
		if ( in_array( (int)$latest_update['id'], (array)$activity_ids_deleted ) )
			delete_user_meta( $user_id, 'bp_latest_update' );
	}

	do_action( 'bp_activity_delete', $args );
	do_action( 'bp_activity_deleted_activities', $activity_ids_deleted );

	wp_cache_delete( 'bp_activity_sitewide_front', 'bp' );

	return true;
}
	/* The following functions have been deprecated in place of bp_activity_delete() */
	function bp_activity_delete_by_item_id( $args = '' ) {
		global $bp;

		$defaults = array( 'item_id' => false, 'component' => false, 'type' => false, 'user_id' => false, 'secondary_item_id' => false );
		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		return bp_activity_delete( array( 'item_id' => $item_id, 'component' => $component, 'type' => $type, 'user_id' => $user_id, 'secondary_item_id' => $secondary_item_id ) );
	}

	function bp_activity_delete_by_activity_id( $activity_id ) {
		return bp_activity_delete( array( 'id' => $activity_id ) );
	}

	function bp_activity_delete_by_content( $user_id, $content, $component, $type ) {
		return bp_activity_delete( array( 'user_id' => $user_id, 'content' => $content, 'component' => $component, 'type' => $type ) );
	}

	function bp_activity_delete_for_user_by_component( $user_id, $component ) {
		return bp_activity_delete( array( 'user_id' => $user_id, 'component' => $component ) );
	}
	/* End deprecation */

function bp_activity_delete_comment( $activity_id, $comment_id ) {
	/***
	 * You may want to hook into this filter if you want to override this function and
	 * handle the deletion of child comments differently. Make sure you return false.
	 */
	if ( !apply_filters( 'bp_activity_delete_comment_pre', true, $activity_id, $comment_id ) )
		return false;

	/* Delete any children of this comment. */
	bp_activity_delete_children( $activity_id, $comment_id );

	/* Delete the actual comment */
	if ( !bp_activity_delete( array( 'id' => $comment_id, 'type' => 'activity_comment' ) ) )
		return false;

	/* Recalculate the comment tree */
	BP_Activity_Activity::rebuild_activity_comment_tree( $activity_id );

	do_action( 'bp_activity_delete_comment', $activity_id, $comment_id );

	return true;
}
	function bp_activity_delete_children( $activity_id, $comment_id) {
		/* Recursively delete all children of this comment. */
		if ( $children = BP_Activity_Activity::get_child_comments( $comment_id ) ) {
			foreach( (array)$children as $child )
				bp_activity_delete_children( $activity_id, $child->id );
		}
		bp_activity_delete( array( 'secondary_item_id' => $comment_id, 'type' => 'activity_comment', 'item_id' => $activity_id ) );
	}

function bp_activity_get_permalink( $activity_id, $activity_obj = false ) {
	global $bp;

	if ( !$activity_obj )
		$activity_obj = new BP_Activity_Activity( $activity_id );

	if ( 'new_blog_post' == $activity_obj->type || 'new_blog_comment' == $activity_obj->type || 'new_forum_topic' == $activity_obj->type || 'new_forum_post' == $activity_obj->type )
		$link = $activity_obj->primary_link;
	else {
		if ( 'activity_comment' == $activity_obj->type )
			$link = $bp->root_domain . '/' . BP_ACTIVITY_SLUG . '/p/' . $activity_obj->item_id . '/';
		else
			$link = $bp->root_domain . '/' . BP_ACTIVITY_SLUG . '/p/' . $activity_obj->id . '/';
	}

	return apply_filters( 'bp_activity_get_permalink', $link );
}

function bp_activity_hide_user_activity( $user_id ) {
	return BP_Activity_Activity::hide_all_for_user( $user_id );
}

/**
 * bp_activity_thumbnail_content_images()
 *
 * Take content, remove all images and replace them with one thumbnail image.
 *
 * @package BuddyPress Activity
 * @param $content str - The content to work with
 * @return $content str - The content with images stripped and replaced with a single thumb.
 */
function bp_activity_thumbnail_content_images( $content ) {
	preg_match_all( '/<img[^>]*>/Ui', $content, $matches );
	$content = preg_replace('/<img[^>]*>/Ui', '', $content );

	if ( !empty( $matches ) ) {
		/* Get the SRC value */
		preg_match( '/<img.*?(src\=[\'|"]{0,1}.*?[\'|"]{0,1})[\s|>]{1}/i', $matches[0][0], $src );

		/* Get the width and height */
		preg_match( '/<img.*?(height\=[\'|"]{0,1}.*?[\'|"]{0,1})[\s|>]{1}/i', $matches[0][0], $height );
		preg_match( '/<img.*?(width\=[\'|"]{0,1}.*?[\'|"]{0,1})[\s|>]{1}/i', $matches[0][0], $width );

		if ( !empty( $src ) ) {
			$src = substr( substr( str_replace( 'src=', '', $src[1] ), 0, -1 ), 1 );
			$height = substr( substr( str_replace( 'height=', '', $height[1] ), 0, -1 ), 1 );
			$width = substr( substr( str_replace( 'width=', '', $width[1] ), 0, -1 ), 1 );

			if ( empty( $width ) || empty( $height ) ) {
				$width = 100;
				$height = 100;
			}

			$ratio = (int)$width / (int)$height;
			$new_height = 100;
			$new_width = $new_height * $ratio;

			$content = '<img src="' . esc_attr( $src) . '" width="' . $new_width . '" height="' . $new_height . '" alt="' . __( 'Thumbnail', 'buddypress' ) . '" class="align-left thumbnail" />' . $content;
		}
	}

	return apply_filters( 'bp_activity_thumbnail_content_images', $content, $matches );
}

function bp_activity_set_action( $component_id, $key, $value ) {
	global $bp;

	if ( empty( $component_id ) || empty( $key ) || empty( $value ) )
		return false;

	$bp->activity->actions->{$component_id}->{$key} = apply_filters( 'bp_activity_set_action', array(
		'key' => $key,
		'value' => $value
	), $component_id, $key, $value );
}

function bp_activity_get_action( $component_id, $key ) {
	global $bp;

	if ( empty( $component_id ) || empty( $key ) )
		return false;

	return apply_filters( 'bp_activity_get_action', $bp->activity->actions->{$component_id}->{$key}, $component_id, $key );
}

function bp_activity_get_user_favorites( $user_id ) {
	$my_favs = maybe_unserialize( get_user_meta( $user_id, 'bp_favorite_activities', true ) );
	$existing_favs = bp_activity_get_specific( array( 'activity_ids' => $my_favs ) );

	foreach( (array)$existing_favs['activities'] as $fav )
		$new_favs[] = $fav->id;

	$new_favs = array_unique( (array)$new_favs );
	update_user_meta( $user_id, 'bp_favorite_activities', $new_favs );

	return apply_filters( 'bp_activity_get_user_favorites', $new_favs );
}

function bp_activity_add_user_favorite( $activity_id, $user_id = false ) {
	global $bp;

	if ( !$user_id )
		$user_id = $bp->loggedin_user->id;

	/* Update the user's personal favorites */
	$my_favs = maybe_unserialize( get_user_meta( $bp->loggedin_user->id, 'bp_favorite_activities', true ) );
	$my_favs[] = $activity_id;

	/* Update the total number of users who have favorited this activity */
	$fav_count = bp_activity_get_meta( $activity_id, 'favorite_count' );

	if ( !empty( $fav_count ) )
		$fav_count = (int)$fav_count + 1;
	else
		$fav_count = 1;

	update_user_meta( $bp->loggedin_user->id, 'bp_favorite_activities', $my_favs );
	bp_activity_update_meta( $activity_id, 'favorite_count', $fav_count );

	do_action( 'bp_activity_add_user_favorite', $activity_id, $user_id );

	return true;
}

function bp_activity_remove_user_favorite( $activity_id, $user_id = false ) {
	global $bp;

	if ( !$user_id )
		$user_id = $bp->loggedin_user->id;

	/* Remove the fav from the user's favs */
	$my_favs = maybe_unserialize( get_user_meta( $user_id, 'bp_favorite_activities', true ) );
	$my_favs = array_flip( (array) $my_favs );
	unset( $my_favs[$activity_id] );
	$my_favs = array_unique( array_flip( $my_favs ) );

	/* Update the total number of users who have favorited this activity */
	$fav_count = bp_activity_get_meta( $activity_id, 'favorite_count' );

	if ( !empty( $fav_count ) ) {
		$fav_count = (int)$fav_count - 1;
		bp_activity_update_meta( $activity_id, 'favorite_count', $fav_count );
	}

	update_user_meta( $user_id, 'bp_favorite_activities', $my_favs );

	do_action( 'bp_activity_remove_user_favorite', $activity_id, $user_id );

	return true;
}

function bp_activity_check_exists_by_content( $content ) {
	return apply_filters( 'bp_activity_check_exists_by_content', BP_Activity_Activity::check_exists_by_content( $content ) );
}

function bp_activity_get_last_updated() {
	return apply_filters( 'bp_activity_get_last_updated', BP_Activity_Activity::get_last_updated() );
}

function bp_activity_total_favorites_for_user( $user_id = false ) {
	global $bp;

	if ( !$user_id )
		$user_id = ( $bp->displayed_user->id ) ? $bp->displayed_user->id : $bp->loggedin_user->id;

	return BP_Activity_Activity::total_favorite_count( $user_id );
}

/********************************************************************************
 * Activity Meta Functions
 *
 * Meta functions allow you to store extra data for a particular item.
 */

function bp_activity_delete_meta( $activity_id, $meta_key = false, $meta_value = false ) {
	global $wpdb, $bp;

	if ( !is_numeric( $activity_id ) )
		return false;

	$meta_key = preg_replace( '|[^a-z0-9_]|i', '', $meta_key );

	if ( is_array( $meta_value ) || is_object( $meta_value ) )
		$meta_value = serialize( $meta_value );

	$meta_value = trim( $meta_value );

	if ( !$meta_key ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$bp->activity->table_name_meta} WHERE activity_id = %d", $activity_id ) );
	} else if ( $meta_value ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$bp->activity->table_name_meta} WHERE activity_id = %d AND meta_key = %s AND meta_value = %s", $activity_id, $meta_key, $meta_value ) );
	} else {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$bp->activity->table_name_meta} WHERE activity_id = %d AND meta_key = %s", $activity_id, $meta_key ) );
	}

	wp_cache_delete( 'bp_activity_meta_' . $meta_key . '_' . $activity_id, 'bp' );

	return true;
}

function bp_activity_get_meta( $activity_id, $meta_key = '' ) {
	global $wpdb, $bp;

	$activity_id = (int)$activity_id;

	if ( !$activity_id )
		return false;

	if ( !empty($meta_key) ) {
		$meta_key = preg_replace( '|[^a-z0-9_]|i', '', $meta_key );

		if ( !$metas = wp_cache_get( 'bp_activity_meta_' . $meta_key . '_' . $activity_id, 'bp' ) ) {
			$metas = $wpdb->get_col( $wpdb->prepare("SELECT meta_value FROM {$bp->activity->table_name_meta} WHERE activity_id = %d AND meta_key = %s", $activity_id, $meta_key ) );
			wp_cache_set( 'bp_activity_meta_' . $meta_key . '_' . $activity_id, $metas, 'bp' );
		}
	} else
		$metas = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$bp->activity->table_name_meta} WHERE activity_id = %d", $activity_id ) );

	if ( empty($metas) )
		return false;

	$metas = array_map( 'maybe_unserialize', (array)$metas );

	if ( 1 == count($metas) )
		return $metas[0];
	else
		return $metas;
}

function bp_activity_update_meta( $activity_id, $meta_key, $meta_value ) {
	global $wpdb, $bp;

	if ( !is_numeric( $activity_id ) )
		return false;

	$meta_key = preg_replace( '|[^a-z0-9_]|i', '', $meta_key );

	if ( is_string( $meta_value ) )
		$meta_value = stripslashes( $wpdb->escape( $meta_value ) );

	$meta_value = maybe_serialize( $meta_value );

	if ( empty( $meta_value ) ) {
		return bp_activity_delete_meta( $activity_id, $meta_key );
	}

	$cur = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$bp->activity->table_name_meta} WHERE activity_id = %d AND meta_key = %s", $activity_id, $meta_key ) );

	if ( !$cur ) {
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$bp->activity->table_name_meta} ( activity_id, meta_key, meta_value ) VALUES ( %d, %s, %s )", $activity_id, $meta_key, $meta_value ) );
	} else if ( $cur->meta_value != $meta_value ) {
		$wpdb->query( $wpdb->prepare( "UPDATE {$bp->activity->table_name_meta} SET meta_value = %s WHERE activity_id = %d AND meta_key = %s", $meta_value, $activity_id, $meta_key ) );
	} else {
		return false;
	}

	wp_cache_set( 'bp_activity_meta_' . $meta_key . '_' . $activity_id, $meta_value, 'bp' );

	return true;
}

function bp_activity_remove_data( $user_id ) {
	// Clear the user's activity from the sitewide stream and clear their activity tables
	bp_activity_delete( array( 'user_id' => $user_id ) );

	// Remove any usermeta
	delete_user_meta( $user_id, 'bp_latest_update' );
	delete_user_meta( $user_id, 'bp_favorite_activities' );

	do_action( 'bp_activity_remove_data', $user_id );
}
add_action( 'wpmu_delete_user', 'bp_activity_remove_data' );
add_action( 'delete_user', 'bp_activity_remove_data' );
add_action( 'make_spam_user', 'bp_activity_remove_data' );

/**
 * updates_register_activity_actions()
 * 
 * Register the activity stream actions for updates
 * 
 * @global array $bp
 */
function updates_register_activity_actions() {
	global $bp;

	bp_activity_set_action( $bp->activity->id, 'activity_update', __( 'Posted an update', 'buddypress' ) );

	do_action( 'updates_register_activity_actions' );
}
add_action( 'bp_register_activity_actions', 'updates_register_activity_actions' );

/********************************************************************************
 * Custom Actions
 *
 * Functions to set up custom BuddyPress actions that all other components can
 * hook in to.
 */

/* Allow core components and dependent plugins to register activity actions */
function bp_register_activity_actions() {
	do_action( 'bp_register_activity_actions' );
}
add_action( 'bp_loaded', 'bp_register_activity_actions', 8 );


?>