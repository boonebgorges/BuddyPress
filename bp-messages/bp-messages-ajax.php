<?php

function messages_ajax_send_reply() {
	global $bp;
	
	check_ajax_referer( 'messages_send_message' );
	
	$result = messages_send_message($_REQUEST['send_to'], $_REQUEST['subject'], $_REQUEST['content'], $_REQUEST['thread_id'], true, false, true); 

	if ( $result['status'] ) { ?>
			<div class="avatar-box">
				<?php if ( function_exists('bp_core_get_avatar') ) 
					echo bp_core_get_avatar($result['reply']->sender_id, 1);
				?>
	
				<h3><?php echo bp_core_get_userlink($result['reply']->sender_id) ?></h3>
				<small><?php echo bp_format_time($result['reply']->date_sent) ?></small>
			</div>
			<?php echo stripslashes( apply_filters( 'bp_get_message_content', $result['reply']->message ) ) ?>
			<div class="clear"></div>
		<?php
	} else {
		$result['message'] = '<img src="' . $bp->messages->image_base . '/warning.gif" alt="Warning" /> &nbsp;' . $result['message'];
		echo "-1[[split]]" . $result['message'];
	}
}
add_action( 'wp_ajax_messages_send_reply', 'messages_ajax_send_reply' );

function messages_ajax_autocomplete_results() {
	global $bp;

	// Get the friend ids based on the search terms
	$friends = apply_filters( 'bp_friends_autocomplete_list', friends_search_friends( $_GET['q'], $bp->loggedin_user->id, $_GET['limit'], 1 ), $_GET['q'], $_GET['limit'] );
	
	if ( $friends['friends'] ) {
		foreach ( $friends['friends'] as $user_id ) {
			$ud = get_userdata($user_id);
			$username = $ud->user_login;
			echo bp_core_get_avatar( $user_id, 1, 15, 15 ) . ' ' . bp_fetch_user_fullname( $user_id, false ) . ' (' . $username . ')
			';
		}		
	}
}
add_action( 'wp_ajax_messages_autocomplete_results', 'messages_ajax_autocomplete_results' );

function messages_ajax_markunread() {
	global $bp;

	if ( !isset($_POST['thread_ids']) ) {
		echo "-1[[split]]" . __('There was a problem marking messages as unread.', 'buddypress');
	} else {
		$thread_ids = explode( ',', $_POST['thread_ids'] );
		
		for ( $i = 0; $i < count($thread_ids); $i++ ) {
			BP_Messages_Thread::mark_as_unread($thread_ids[$i]);
		}
	}
}
add_action( 'wp_ajax_messages_markunread', 'messages_ajax_markunread' );

function messages_ajax_markread() {
	global $bp;
	
	if ( !isset($_POST['thread_ids']) ) {
		echo "-1[[split]]" . __('There was a problem marking messages as read.', 'buddypress');
	} else {
		$thread_ids = explode( ',', $_POST['thread_ids'] );

		for ( $i = 0; $i < count($thread_ids); $i++ ) {
			BP_Messages_Thread::mark_as_read($thread_ids[$i]);
		}
	}
}
add_action( 'wp_ajax_messages_markread', 'messages_ajax_markread' );

function messages_ajax_delete() {
	global $bp;

	if ( !isset($_POST['thread_ids']) ) {
		echo "-1[[split]]" . __( 'There was a problem deleting messages.', 'buddypress' );
	} else {
		$thread_ids = explode( ',', $_POST['thread_ids'] );

		for ( $i = 0; $i < count($thread_ids); $i++ ) {
			BP_Messages_Thread::delete($thread_ids[$i]);
		}
		
		_e('Messages deleted.', 'buddypress');
	}
}
add_action( 'wp_ajax_messages_delete', 'messages_ajax_delete' );

function messages_ajax_close_notice() {
	global $userdata;

	if ( !isset($_POST['notice_id']) ) {
		echo "-1[[split]]" . __('There was a problem closing the notice.', 'buddypress');
	} else {
		$notice_ids = get_usermeta( $userdata->ID, 'closed_notices' );
	
		$notice_ids[] = (int) $_POST['notice_id'];
		
		update_usermeta( $userdata->ID, 'closed_notices', $notice_ids );
	}
}
add_action( 'wp_ajax_messages_close_notice', 'messages_ajax_close_notice' );

?>