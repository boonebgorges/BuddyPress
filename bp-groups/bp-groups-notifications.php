<?php

function groups_notification_new_wire_post( $group_id, $wire_post_id ) {
	global $bp;
	
	if ( !isset( $_POST['wire-post-email-notify'] ) )
		return false;
	
	$wire_post = new BP_Wire_Post( $bp->groups->table_name_wire, $wire_post_id );
	$group = new BP_Groups_Group( $group_id, false, true );
	
	$poster_name = bp_core_get_user_displayname( $wire_post->user_id );
	$poster_ud = get_userdata( $wire_post->user_id );
	$poster_profile_link = site_url() . '/' . BP_MEMBERS_SLUG . '/' . $poster_ud->user_login;

	$subject = '[' . get_blog_option( 1, 'blogname' ) . '] ' . sprintf( __( 'New wire post on group: %s', 'buddypress' ), stripslashes($group->name) );

	foreach ( $group->user_dataset as $user ) {
		if ( 'no' == get_usermeta( $user->user_id, 'notification_groups_wire_post' ) ) continue;
		
		$ud = get_userdata( $user->user_id );
		
		// Set up and send the message
		$to = $ud->user_email;

		$wire_link = site_url() . '/' . $bp->groups->slug . '/' . $group->slug . '/wire';
		$group_link = site_url() . '/' . $bp->groups->slug . '/' . $group->slug;
		$settings_link = site_url() . '/' . BP_MEMBERS_SLUG . '/' . $ud->user_login . '/settings/notifications';

		$message = sprintf( __( 
'%s posted on the wire of the group "%s":

"%s"

To view the group wire: %s

To view the group home: %s

To view %s\'s profile page: %s

---------------------
', 'buddypress' ), $poster_name, stripslashes($group->name), stripslashes($wire_post->content), $wire_link, $group_link, $poster_name, $poster_profile_link );

		$message .= sprintf( __( 'To disable these notifications please log in and go to: %s', 'buddypress' ), $settings_link );

		// Send it
		wp_mail( $to, $subject, $message );
		
		unset( $message, $to );
	}
}
add_action( 'groups_new_wire_post', 'groups_notification_new_wire_post', 10, 2 );


function groups_notification_group_updated( $group_id ) {
	global $bp;
	
	$group = new BP_Groups_Group( $group_id, false, true );
	$subject = '[' . get_blog_option( 1, 'blogname' ) . '] ' . __( 'Group Details Updated', 'buddypress' );

	foreach ( $group->user_dataset as $user ) {
		if ( 'no' == get_usermeta( $user->user_id, 'notification_groups_group_updated' ) ) continue;
		
		$ud = get_userdata( $user->user_id );
		
		// Set up and send the message
		$to = $ud->user_email;

		$group_link = site_url() . '/' . $bp->groups->slug . '/' . $group->slug;
		$settings_link = site_url() . '/' . BP_MEMBERS_SLUG . '/' . $ud->user_login . '/settings/notifications';

		$message = sprintf( __( 
'Group details for the group "%s" were updated:

To view the group: %s

---------------------
', 'buddypress' ), stripslashes($group->name), $group_link );

		$message .= sprintf( __( 'To disable these notifications please log in and go to: %s', 'buddypress' ), $settings_link );

		// Send it
		wp_mail( $to, $subject, $message );

		unset( $message, $to );
	}
}

function groups_notification_new_membership_request( $requesting_user_id, $admin_id, $group_id, $membership_id ) {
	global $bp;

	bp_core_add_notification( $requesting_user_id, $admin_id, 'groups', 'new_membership_request', $group_id );

	if ( 'no' == get_usermeta( $admin_id, 'notification_groups_membership_request' ) )
		return false;
		
	$requesting_user_name = bp_core_get_user_displayname( $requesting_user_id );
	$group = new BP_Groups_Group( $group_id, false, false );
	
	$ud = get_userdata($admin_id);
	$requesting_ud = get_userdata($requesting_user_id);

	$group_request_accept = wp_nonce_url( bp_get_group_permalink( $group ) . '/admin/membership-requests/accept/' . $membership_id, 'groups_accept_membership_request' );
	$group_request_reject = wp_nonce_url( bp_get_group_permalink( $group ) . '/admin/membership-requests/reject/' . $membership_id, 'groups_reject_membership_request' );
	$profile_link = site_url() . '/' . BP_MEMBERS_SLUG . '/' . $requesting_ud->user_login . '/profile';
	$settings_link = site_url() . '/' . BP_MEMBERS_SLUG . '/' . $ud->user_login . '/settings/notifications';

	// Set up and send the message
	$to = $ud->user_email;
	$subject = '[' . get_blog_option( 1, 'blogname' ) . '] ' . sprintf( __( 'Membership request for group: %s', 'buddypress' ), stripslashes($group->name) );

$message = sprintf( __( 
'%s wants to join the group "%s".

Because you are the administrator of this group, you must either accept or reject the membership request.

To accept the membership request: %s

To reject the membership request: %s

To view %s\'s profile: %s

---------------------
', 'buddypress' ), $requesting_user_name, stripslashes($group->name), $group_request_accept, $group_request_reject, $requesting_user_name, $profile_link );

	$message .= sprintf( __( 'To disable these notifications please log in and go to: %s', 'buddypress' ), $settings_link );

	// Send it
	wp_mail( $to, $subject, $message );	
}

function groups_notification_membership_request_completed( $requesting_user_id, $group_id, $accepted = true ) {
	global $bp;
	
	// Post a screen notification first.
	if ( $accepted )
		bp_core_add_notification( $group_id, $requesting_user_id, 'groups', 'membership_request_accepted' );
	else
		bp_core_add_notification( $group_id, $requesting_user_id, 'groups', 'membership_request_rejected' );
	
	if ( 'no' == get_usermeta( $requesting_user_id, 'notification_membership_request_completed' ) )
		return false;
		
	$group = new BP_Groups_Group( $group_id, false, false );
	
	$ud = get_userdata($requesting_user_id);

	$group_link = bp_get_group_permalink( $group );
	$settings_link = site_url() . '/' . BP_MEMBERS_SLUG . '/' . $ud->user_login . '/settings/notifications';

	// Set up and send the message
	$to = $ud->user_email;
	
	if ( $accepted ) {
		$subject = '[' . get_blog_option( 1, 'blogname' ) . '] ' . sprintf( __( 'Membership request for group "%s" accepted', 'buddypress' ), stripslashes($group->name) );
		$message = sprintf( __( 
'Your membership request for the group "%s" has been accepted.

To view the group please login and visit: %s

---------------------
', 'buddypress' ), stripslashes($group->name), $group_link );
		
	} else {
		$subject = '[' . get_blog_option( 1, 'blogname' ) . '] ' . sprintf( __( 'Membership request for group "%s" rejected', 'buddypress' ), stripslashes($group->name) );
		$message = sprintf( __( 
'Your membership request for the group "%s" has been rejected.

To submit another request please log in and visit: %s

---------------------
', 'buddypress' ), stripslashes($group->name), $group_link );
	}
	
	$message .= sprintf( __( 'To disable these notifications please log in and go to: %s', 'buddypress' ), $settings_link );

	// Send it
	wp_mail( $to, $subject, $message );	
}

function groups_notification_promoted_member( $user_id, $group_id ) {
	global $bp;

	if ( groups_is_user_admin( $user_id, $group_id ) ) {
		$promoted_to = __( 'an administrator', 'buddypress' );
		$type = 'member_promoted_to_admin';
	} else {
		$promoted_to = __( 'a moderator', 'buddypress' );
		$type = 'member_promoted_to_mod';
	}
	
	// Post a screen notification first.
	bp_core_add_notification( $group_id, $user_id, 'groups', $type );

	if ( 'no' == get_usermeta( $user_id, 'notification_groups_admin_promotion' ) )
		return false;

	$group = new BP_Groups_Group( $group_id, false, false );
	$ud = get_userdata($user_id);

	$group_link = bp_get_group_permalink( $group );
	$settings_link = site_url() . '/' . BP_MEMBERS_SLUG . '/' . $ud->user_login . '/settings/notifications';

	// Set up and send the message
	$to = $ud->user_email;

	$subject = '[' . get_blog_option( 1, 'blogname' ) . '] ' . sprintf( __( 'You have been promoted in the group: "%s"', 'buddypress' ), stripslashes($group->name) );

	$message = sprintf( __( 
'You have been promoted to %s for the group: "%s".

To view the group please visit: %s

---------------------
', 'buddypress' ), $promoted_to, stripslashes($group->name), $group_link );

	$message .= sprintf( __( 'To disable these notifications please log in and go to: %s', 'buddypress' ), $settings_link );

	// Send it
	wp_mail( $to, $subject, $message );
}
add_action( 'groups_promoted_member', 'groups_notification_promoted_member', 10, 2 );

function groups_notification_group_invites( &$group, &$member, $inviter_user_id ) {
	global $bp;
	
	$inviter_ud = get_userdata($inviter_user_id);
	$inviter_name = bp_core_get_userlink( $inviter_user_id, true, false, true );
	$inviter_link = site_url() . '/' . BP_MEMBERS_SLUG . '/' . $inviter_ud->user_login;
	
	$group_link = bp_get_group_permalink( $group );
	
	if ( !$member->invite_sent ) {
		$invited_user_id = $member->user_id;

		// Post a screen notification first.
		bp_core_add_notification( $group->id, $invited_user_id, 'groups', 'group_invite' );

		if ( 'no' == get_usermeta( $invited_user_id, 'notification_groups_invite' ) )
			return false;

		$invited_ud = get_userdata($invited_user_id);
		$settings_link = site_url() . '/' . BP_MEMBERS_SLUG . '/' . $invited_ud->user_login . '/settings/notifications';
		$invited_link = site_url() . '/' . BP_MEMBERS_SLUG . '/' . $invited_ud->user_login;
		$invites_link = $invited_link . '/' . $bp->groups->slug . '/invites';

		// Set up and send the message
		$to = $invited_ud->user_email;

		$subject = '[' . get_blog_option( 1, 'blogname' ) . '] ' . sprintf( __( 'You have an invitation to the group: "%s"', 'buddypress' ), stripslashes($group->name) );

		$message = sprintf( __( 
'One of your friends %s has invited you to the group: "%s".

To view your group invites visit: %s

To view the group visit: %s

To view %s\'s profile visit: %s

---------------------
', 'buddypress' ), $inviter_name, stripslashes($group->name), $invites_link, $group_link, $inviter_name, $inviter_link );

		$message .= sprintf( __( 'To disable these notifications please log in and go to: %s', 'buddypress' ), $settings_link );

		// Send it
		wp_mail( $to, $subject, $message );
	}
}

?>