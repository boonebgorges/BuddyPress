<?php


function bp_forums_is_installed_correctly() {
	global $bp;

	if ( isset( $bp->forums->bbconfig ) && file_exists( $bp->forums->bbconfig ) )
		return true;

	return false;
}

/**
 * Convenience function to determine if the forum directory has been disabled
 * by the site admin.
 *
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @return bool True if forum is disabled
 * @since 1.3
 */
function bp_forum_directory_is_disabled() {
	global $bp;

	return !empty( $bp->site_options['bp-disable-forum-directory'] );
}

function bp_forums_directory_forums_setup() {
	global $bp;

	if ( bp_is_current_component( 'forums' ) ) {
		if ( bp_forum_directory_is_disabled() || !bp_is_active( 'groups' ) )
			return false;

		if ( !bp_forums_is_installed_correctly() ) {
			bp_core_add_message( __( 'The forums component has not been set up yet.', 'buddypress' ), 'error' );
			bp_core_redirect( $bp->root_domain );
		}

		$bp->is_directory = true;

		do_action( 'bbpress_init' );

		// Check to see if the user has posted a new topic from the forums page.
		if ( isset( $_POST['submit_topic'] ) && function_exists( 'bp_forums_new_topic' ) ) {
			check_admin_referer( 'bp_forums_new_topic' );

			if ( $bp->groups->current_group = groups_get_group( array( 'group_id' => $_POST['topic_group_id'] ) ) ) {
				// Auto join this user if they are not yet a member of this group
				if ( !is_super_admin() && 'public' == $bp->groups->current_group->status && !groups_is_user_member( $bp->loggedin_user->id, $bp->groups->current_group->id ) )
					groups_join_group( $bp->groups->current_group->id, $bp->groups->current_group->id );

				$error_message = '';
				if ( $forum_id = groups_get_groupmeta( $bp->groups->current_group->id, 'forum_id' ) ) {
					if ( empty( $_POST['topic_title'] ) )
						$error_message = __( 'Please provide a title for your forum topic.', 'buddypress' );
					else if ( empty( $_POST['topic_text'] ) )
						$error_message = __( 'Forum posts cannot be empty. Please enter some text.', 'buddypress' );

					if ( $error_message ) {
						bp_core_add_message( $error_message, 'error' );
						$redirect = bp_get_group_permalink( $bp->groups->current_group ) . 'forum';
					} else {
						if ( !$topic = groups_new_group_forum_topic( $_POST['topic_title'], $_POST['topic_text'], $_POST['topic_tags'], $forum_id ) ) {
							bp_core_add_message( __( 'There was an error when creating the topic', 'buddypress'), 'error' );
							$redirect = bp_get_group_permalink( $bp->groups->current_group ) . 'forum';
						} else {
							bp_core_add_message( __( 'The topic was created successfully', 'buddypress') );
							$redirect = bp_get_group_permalink( $bp->groups->current_group ) . 'forum/topic/' . $topic->topic_slug . '/';
						}
					}

					bp_core_redirect( $redirect );

				} else {
					bp_core_add_message( __( 'Please pick the group forum where you would like to post this topic.', 'buddypress' ), 'error' );
				}
			}
		}

		do_action( 'bp_forums_directory_forums_setup' );

		bp_core_load_template( apply_filters( 'bp_forums_template_directory_forums_setup', 'forums/index' ) );
	}
}
add_action( 'wp', 'bp_forums_directory_forums_setup', 2 );

/** Forum Functions ***********************************************************/

function bp_forums_get_forum( $forum_id ) {
	do_action( 'bbpress_init' );
	return bb_get_forum( $forum_id );
}

function bp_forums_new_forum( $args = '' ) {
	do_action( 'bbpress_init' );

	$defaults = array(
		'forum_name'        => '',
		'forum_desc'        => '',
		'forum_parent_id'   => BP_FORUMS_PARENT_FORUM_ID,
		'forum_order'       => false,
		'forum_is_category' => 0
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	return bb_new_forum( array( 'forum_name' => stripslashes( $forum_name ), 'forum_desc' => stripslashes( $forum_desc ), 'forum_parent' => $forum_parent_id, 'forum_order' => $forum_order, 'forum_is_category' => $forum_is_category ) );
}

function bp_forums_update_forum( $args = '' ) {
	do_action( 'bbpress_init' );

	$defaults = array(
		'forum_id'			=> '',
		'forum_name'		=> '',
		'forum_desc'		=> '',
		'forum_slug'		=> '',
		'forum_parent_id'	=> BP_FORUMS_PARENT_FORUM_ID,
		'forum_order'		=> false,
		'forum_is_category'	=> 0
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	return bb_update_forum( array( 'forum_id' => (int)$forum_id, 'forum_name' => stripslashes( $forum_name ), 'forum_desc' => stripslashes( $forum_desc ), 'forum_slug' => stripslashes( $forum_slug ), 'forum_parent' => $forum_parent_id, 'forum_order' => $forum_order, 'forum_is_category' => $forum_is_category ) );
}

/** Topic Functions ***********************************************************/

function bp_forums_get_forum_topics( $args = '' ) {
	global $bp;

	do_action( 'bbpress_init' );

	$defaults = array(
		'type'          => 'newest',
		'forum_id'      => false,
		'user_id'       => false,
		'page'          => 1,
		'per_page'      => 15,
		'exclude'       => false,
		'show_stickies' => 'all',
		'filter'        => false // if $type = tag then filter is the tag name, otherwise it's terms to search on.
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	if ( class_exists( 'BB_Query' ) ) {
		switch ( $type ) {
			case 'newest':
				$query = new BB_Query( 'topic', array( 'forum_id' => $forum_id, 'topic_author_id' => $user_id, 'per_page' => $per_page, 'page' => $page, 'number' => $per_page, 'exclude' => $exclude, 'topic_title' => $filter, 'sticky' => $show_stickies ), 'get_latest_topics' );
				$topics =& $query->results;
				break;

			case 'popular':
				$query = new BB_Query( 'topic', array( 'forum_id' => $forum_id, 'topic_author_id' => $user_id, 'per_page' => $per_page, 'page' => $page, 'order_by' => 't.topic_posts', 'topic_title' => $filter, 'sticky' => $show_stickies ) );
				$topics =& $query->results;
				break;

			case 'unreplied':
				$query = new BB_Query( 'topic', array( 'forum_id' => $forum_id, 'topic_author_id' => $user_id, 'post_count' => 1, 'per_page' => $per_page, 'page' => $page, 'order_by' => 't.topic_time', 'topic_title' => $filter, 'sticky' => $show_stickies ) );
				$topics =& $query->results;
				break;

			case 'tags':
				$query = new BB_Query( 'topic', array( 'forum_id' => $forum_id, 'topic_author_id' => $user_id, 'tag' => $filter, 'per_page' => $per_page, 'page' => $page, 'order_by' => 't.topic_time', 'sticky' => $show_stickies ) );
				$topics =& $query->results;
				break;
		}
	} else {
		$topics = array();
	}

	return apply_filters_ref_array( 'bp_forums_get_forum_topics', array( &$topics, &$r ) );
}

function bp_forums_get_topic_details( $topic_id ) {
	do_action( 'bbpress_init' );

	$query = new BB_Query( 'topic', 'topic_id=' . $topic_id . '&page=1' /* Page override so bbPress doesn't use the URI */ );

	return $query->results[0];
}

function bp_forums_get_topic_id_from_slug( $topic_slug ) {
	do_action( 'bbpress_init' );

	if ( empty( $topic_slug ) )
		return false;

	return bb_get_id_from_slug( 'topic', $topic_slug );
}

function bp_forums_new_topic( $args = '' ) {
	global $bp;

	do_action( 'bbpress_init' );

	$defaults = array(
		'topic_title'            => '',
		'topic_slug'             => '',
		'topic_text'             => '',
		'topic_poster'           => $bp->loggedin_user->id,       // accepts ids
		'topic_poster_name'      => $bp->loggedin_user->fullname, // accept names
		'topic_last_poster'      => $bp->loggedin_user->id,       // accepts ids
		'topic_last_poster_name' => $bp->loggedin_user->fullname, // accept names
		'topic_start_time'       => bp_core_current_time(),
		'topic_time'             => bp_core_current_time(),
		'topic_open'             => 1,
		'topic_tags'             => false, // accepts array or comma delim
		'forum_id'               => 0      // accepts ids or slugs
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	$topic_title = strip_tags( $topic_title );

	if ( empty( $topic_title ) || !strlen( trim( $topic_title ) ) )
		return false;

	if ( empty( $topic_slug ) )
		$topic_slug = sanitize_title( $topic_title );

	if ( !$topic_id = bb_insert_topic( array( 'topic_title' => stripslashes( $topic_title ), 'topic_slug' => $topic_slug, 'topic_poster' => $topic_poster, 'topic_poster_name' => $topic_poster_name, 'topic_last_poster' => $topic_last_poster, 'topic_last_poster_name' => $topic_last_poster_name, 'topic_start_time' => $topic_start_time, 'topic_time' => $topic_time, 'topic_open' => $topic_open, 'forum_id' => (int)$forum_id, 'tags' => $topic_tags ) ) )
		return false;

	// Now insert the first post.
	if ( !bp_forums_insert_post( array( 'topic_id' => $topic_id, 'post_text' => $topic_text, 'post_time' => $topic_time, 'poster_id' => $topic_poster ) ) )
		return false;

	do_action( 'bp_forums_new_topic', $topic_id );

	return $topic_id;
}

function bp_forums_update_topic( $args = '' ) {
	global $bp;

	do_action( 'bbpress_init' );

	$defaults = array(
		'topic_id'    => false,
		'topic_title' => '',
		'topic_text'  => ''
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	if ( !$topic_id = bb_insert_topic( array( 'topic_id' => $topic_id, 'topic_title' => stripslashes( $topic_title ) ) ) )
		return false;

	if ( !$post = bb_get_first_post( $topic_id ) )
		return false;

	// Update the first post
	if ( !$post = bp_forums_insert_post( array( 'post_id' => $post->post_id, 'topic_id' => $topic_id, 'post_text' => $topic_text, 'post_time' => $post->post_time, 'poster_id' => $post->poster_id, 'poster_ip' => $post->poster_ip, 'post_status' => $post->post_status, 'post_position' => $post->post_position ) ) )
		return false;

	return bp_forums_get_topic_details( $topic_id );
}

function bp_forums_sticky_topic( $args = '' ) {
	global $bp;

	do_action( 'bbpress_init' );

	$defaults = array(
		'topic_id' => false,
		'mode'     => 'stick' // stick/unstick
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	if ( 'stick' == $mode )
		return bb_stick_topic( $topic_id );
	else if ( 'unstick' == $mode )
		return bb_unstick_topic( $topic_id );

	return false;
}

function bp_forums_openclose_topic( $args = '' ) {
	global $bp;

	do_action( 'bbpress_init' );

	$defaults = array(
		'topic_id' => false,
		'mode'     => 'close' // stick/unstick
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	if ( 'close' == $mode )
		return bb_close_topic( $topic_id );
	else if ( 'open' == $mode )
		return bb_open_topic( $topic_id );

	return false;
}

function bp_forums_delete_topic( $args = '' ) {
	global $bp;

	do_action( 'bbpress_init' );

	$defaults = array(
		'topic_id' => false
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	return bb_delete_topic( $topic_id, 1 );
}

function bp_forums_total_topic_count() {
	global $bbdb;

	do_action( 'bbpress_init' );

	if ( isset( $bbdb ) ) {
		$count = $bbdb->get_results( $bbdb->prepare( "SELECT t.topic_id FROM {$bbdb->topics} AS t". groups_add_forum_tables_sql( '' ) . " WHERE " . groups_add_forum_where_sql( "t.topic_status = 0" ) ) );
		$count = count( (array)$count );
	} else {
		$count = 0;
	}

	return apply_filters( 'bp_forums_total_topic_count', $count );
}

function bp_forums_total_topic_count_for_user( $user_id = 0 ) {
	global $bp;

	do_action( 'bbpress_init' );

	if ( !$user_id )
		$user_id = ( $bp->displayed_user->id ) ? $bp->displayed_user->id : $bp->loggedin_user->id;

	if ( class_exists( 'BB_Query' ) ) {
		$query = new BB_Query( 'topic', array( 'topic_author_id' => $user_id, 'page' => 1, 'per_page' => -1, 'count' => true ) );
		$count = $query->count;
		$query = null;
	} else {
		$count = 0;
	}

	return $count;
}

function bp_forums_get_topic_extras( $topics ) {
	global $bp, $wpdb, $bbdb;

	if ( empty( $topics ) )
		return $topics;

	// Get the topic ids
	foreach ( (array)$topics as $topic ) $topic_ids[] = $topic->topic_id;
	$topic_ids = $wpdb->escape( join( ',', (array)$topic_ids ) );

	// Fetch the topic's last poster details
	$poster_details = $wpdb->get_results( $wpdb->prepare( "SELECT t.topic_id, t.topic_last_poster, u.user_login, u.user_nicename, u.user_email, u.display_name FROM {$wpdb->users} u, {$bbdb->topics} t WHERE u.ID = t.topic_last_poster AND t.topic_id IN ( {$topic_ids} )" ) );
	for ( $i = 0; $i < count( $topics ); $i++ ) {
		foreach ( (array)$poster_details as $poster ) {
			if ( $poster->topic_id == $topics[$i]->topic_id ) {
				$topics[$i]->topic_last_poster_email       = $poster->user_email;
				$topics[$i]->topic_last_poster_nicename    = $poster->user_nicename;
				$topics[$i]->topic_last_poster_login       = $poster->user_login;
				$topics[$i]->topic_last_poster_displayname = $poster->display_name;
			}
		}
	}

	// Fetch fullname for the topic's last poster
	if ( bp_is_active( 'profile' ) ) {
		$poster_names = $wpdb->get_results( $wpdb->prepare( "SELECT t.topic_id, pd.value FROM {$bp->profile->table_name_data} pd, {$bbdb->topics} t WHERE pd.user_id = t.topic_last_poster AND pd.field_id = 1 AND t.topic_id IN ( {$topic_ids} )" ) );
		for ( $i = 0; $i < count( $topics ); $i++ ) {
			foreach ( (array)$poster_names as $name ) {
				if ( $name->topic_id == $topics[$i]->topic_id )
					$topics[$i]->topic_last_poster_displayname = $name->value;
			}
		}
	}

	return $topics;
}

/** Post Functions ************************************************************/

function bp_forums_get_topic_posts( $args = '' ) {
	do_action( 'bbpress_init' );

	$defaults = array(
		'topic_id' => false,
		'page'     => 1,
		'per_page' => 15,
		'order'    => 'ASC'
	);

	$args  = wp_parse_args( $args, $defaults );
	$query = new BB_Query( 'post', $args, 'get_thread' );

	return bp_forums_get_post_extras( $query->results );
}

function bp_forums_get_post( $post_id ) {
	do_action( 'bbpress_init' );
	return bb_get_post( $post_id );
}

function bp_forums_delete_post( $args = '' ) {
	global $bp;

	do_action( 'bbpress_init' );

	$defaults = array(
		'post_id' => false
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	return bb_delete_post( $post_id, 1 );
}

function bp_forums_insert_post( $args = '' ) {
	global $bp;

	do_action( 'bbpress_init' );

	$defaults = array(
		'post_id'       => false,
		'topic_id'      => false,
		'post_text'     => '',
		'post_time'     => bp_core_current_time(),
		'poster_id'     => $bp->loggedin_user->id, // accepts ids or names
		'poster_ip'     => $_SERVER['REMOTE_ADDR'],
		'post_status'   => 0, // use bb_delete_post() instead
		'post_position' => false
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	if ( !$post = bp_forums_get_post( $post_id ) )
		$post_id = false;

	if ( !isset( $topic_id ) )
		$topic_id = $post->topic_id;

	if ( empty( $post_text ) )
		$post_text = $post->post_text;

	if ( !isset( $post_time ) )
		$post_time = $post->post_time;

	if ( !isset( $post_position ) )
		$post_position = $post->post_position;

	$post_id = bb_insert_post( array( 'post_id' => $post_id, 'topic_id' => $topic_id, 'post_text' => stripslashes( trim( $post_text ) ), 'post_time' => $post_time, 'poster_id' => $poster_id, 'poster_ip' => $poster_ip, 'post_status' => $post_status, 'post_position' => $post_position ) );

	if ( !empty( $post_id ) )
		do_action( 'bp_forums_new_post', $post_id );

	return $post_id;
}

function bp_forums_get_post_extras( $posts ) {
	global $bp, $wpdb;

	if ( empty( $posts ) )
		return $posts;

	// Get the user ids
	foreach ( (array)$posts as $post ) $user_ids[] = $post->poster_id;
	$user_ids = $wpdb->escape( join( ',', (array)$user_ids ) );

	// Fetch the poster's user_email, user_nicename and user_login
	$poster_details = $wpdb->get_results( $wpdb->prepare( "SELECT u.ID as user_id, u.user_login, u.user_nicename, u.user_email, u.display_name FROM {$wpdb->users} u WHERE u.ID IN ( {$user_ids} )" ) );

	for ( $i = 0; $i < count( $posts ); $i++ ) {
		foreach ( (array)$poster_details as $poster ) {
			if ( $poster->user_id == $posts[$i]->poster_id ) {
				$posts[$i]->poster_email    = $poster->user_email;
				$posts[$i]->poster_login    = $poster->user_nicename;
				$posts[$i]->poster_nicename = $poster->user_login;
				$posts[$i]->poster_name     = $poster->display_name;
			}
		}
	}

	// Fetch fullname for each poster.
	if ( bp_is_active( 'profile' ) ) {
		$poster_names = $wpdb->get_results( $wpdb->prepare( "SELECT pd.user_id, pd.value FROM {$bp->profile->table_name_data} pd WHERE pd.user_id IN ( {$user_ids} )" ) );
		for ( $i = 0; $i < count( $posts ); $i++ ) {
			foreach ( (array)$poster_names as $name ) {
				if ( isset( $topics[$i] ) && $name->user_id == $topics[$i]->user_id )
				$posts[$i]->poster_name = $poster->value;
			}
		}
	}

	return apply_filters( 'bp_forums_get_post_extras', $posts );
}

function bp_forums_get_forum_topicpost_count( $forum_id ) {
	global $wpdb, $bbdb;

	do_action( 'bbpress_init' );

	// Need to find a bbPress function that does this
	return $wpdb->get_results( $wpdb->prepare( "SELECT topics, posts from {$bbdb->forums} WHERE forum_id = %d", $forum_id ) );
}

function bp_forums_filter_caps( $allcaps ) {
	global $bp, $wp_roles, $bb_table_prefix;

	if ( !isset( $bp->loggedin_user->id ) )
		return $allcaps;

	$bb_cap = get_user_meta( $bp->loggedin_user->id, $bb_table_prefix . 'capabilities', true );

	if ( empty( $bb_cap ) )
		return $allcaps;

	$bb_cap = array_keys($bb_cap);
	$bb_cap = $wp_roles->get_role( $bb_cap[0] );
	$bb_cap = $bb_cap->capabilities;

	return array_merge( (array) $allcaps, (array) $bb_cap );
}
add_filter( 'user_has_cap', 'bp_forums_filter_caps' );


/********************************************************************************
 * Caching
 *
 * Caching functions handle the clearing of cached objects and pages on specific
 * actions throughout BuddyPress.
 */

// List actions to clear super cached pages on, if super cache is installed
add_action( 'bp_forums_new_forum', 'bp_core_clear_cache' );
add_action( 'bp_forums_new_topic', 'bp_core_clear_cache' );
add_action( 'bp_forums_new_post',  'bp_core_clear_cache' );

?>
