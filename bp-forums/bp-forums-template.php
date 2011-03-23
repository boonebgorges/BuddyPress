<?php

/**
 * Output the forums component slug
 *
 * @package BuddyPress
 * @subpackage Forums Template
 * @since BuddyPress {unknown}
 *
 * @uses bp_get_forums_slug()
 */
function bp_forums_slug() {
	echo bp_get_forums_slug();
}
	/**
	 * Return the forums component slug
	 *
	 * @package BuddyPress
	 * @subpackage Forums Template
	 * @since BuddyPress {unknown}
	 */
	function bp_get_forums_slug() {
		global $bp;
		return apply_filters( 'bp_get_forums_slug', $bp->forums->slug );
	}

/**
 * Output the forums component root slug
 *
 * @package BuddyPress
 * @subpackage Forums Template
 * @since BuddyPress {unknown}
 *
 * @uses bp_get_forums_root_slug()
 */
function bp_forums_root_slug() {
	echo bp_get_forums_root_slug();
}
	/**
	 * Return the forums component root slug
	 *
	 * @package BuddyPress
	 * @subpackage Forums Template
	 * @since BuddyPress {unknown}
	 */
	function bp_get_forums_root_slug() {
		global $bp;
		return apply_filters( 'bp_get_forums_root_slug', $bp->forums->root_slug );
	}

class BP_Forums_Template_Forum {
	var $current_topic = -1;
	var $topic_count;
	var $topics;
	var $topic;

	var $forum_id;

	var $in_the_loop;

	var $pag_page;
	var $pag_num;
	var $pag_links;
	var $total_topic_count;

	var $single_topic = false;

	var $sort_by;
	var $order;

	function BP_Forums_Template_Forum( $type, $forum_id, $user_id, $page, $per_page, $max, $no_stickies, $search_terms ) {
		global $bp;

		$this->pag_page     = isset( $_REQUEST['p'] ) ? intval( $_REQUEST['p'] ) : $page;
		$this->pag_num      = isset( $_REQUEST['n'] ) ? intval( $_REQUEST['n'] ) : $per_page;
		$this->type         = $type;
		$this->search_terms = $search_terms;
		$this->forum_id     = $forum_id;

		switch ( $type ) {
			case 'newest': default:
				$this->topics = bp_forums_get_forum_topics( array( 'user_id' => $user_id, 'forum_id' => $forum_id, 'filter' => $search_terms, 'page' => $this->pag_page, 'per_page' => $this->pag_num, 'show_stickies' => $no_stickies ) );
				break;

			case 'popular':
				$this->topics = bp_forums_get_forum_topics( array( 'user_id' => $user_id, 'type' => 'popular', 'filter' => $search_terms, 'forum_id' => $forum_id, 'page' => $this->pag_page, 'per_page' => $this->pag_num, 'show_stickies' => $no_stickies ) );
				break;

			case 'unreplied':
				$this->topics = bp_forums_get_forum_topics( array( 'user_id' => $user_id, 'type' => 'unreplied', 'filter' => $search_terms, 'forum_id' => $forum_id, 'page' => $this->pag_page, 'per_page' => $this->pag_num, 'show_stickies' => $no_stickies ) );
				break;

			case 'tags':
				$this->topics = bp_forums_get_forum_topics( array( 'user_id' => $user_id, 'type' => 'tags', 'filter' => $search_terms, 'forum_id' => $forum_id, 'page' => $this->pag_page, 'per_page' => $this->pag_num, 'show_stickies' => $no_stickies ) );
				break;
		}

		$this->topics = apply_filters( 'bp_forums_template_topics', $this->topics, $type, $forum_id, $per_page, $max, $no_stickies );

		if ( !(int)$this->topics ) {
			$this->topic_count       = 0;
			$this->total_topic_count = 0;
		} else {
			if ( !empty( $forum_id ) ) {
				$topic_count = bp_forums_get_forum( $forum_id );
				$topic_count = (int)$topic_count->topics;
			} else if ( !empty( $bp->groups->current_group ) ) {
				$topic_count = (int)groups_total_public_forum_topic_count( $type );
			} else {
				$topic_count = count( $this->topics );
			}

			if ( !$max || $max >= $topic_count ) {
				$this->total_topic_count = $topic_count;
			} else {
				$this->total_topic_count = (int)$max;
			}

			if ( $max ) {
				if ( $max >= count($this->topics) ) {
					$this->topic_count = count( $this->topics );
				} else {
					$this->topic_count = (int)$max;
				}
			} else {
				$this->topic_count = count( $this->topics );
			}
		}

		$this->topic_count       = apply_filters_ref_array( 'bp_forums_template_topic_count',                                 array( $this->topic_count, &$this->topics, $type, $forum_id, $per_page, $max, $no_stickies ) );
		$this->total_topic_count = apply_filters_ref_array( 'bp_forums_template_total_topic_count', array( $this->total_topic_count, $this->topic_count, &$this->topics, $type, $forum_id, $per_page, $max, $no_stickies ) );

		if ( !$no_stickies ) {
			$stickies = array();
			$standard = array();

			// Place stickies at the top - not sure why bbPress doesn't do this?
			foreach( (array)$this->topics as $topic ) {
				if ( isset( $topic->topic_sticky ) && 1 == (int)$topic->topic_sticky ) {
					$stickies[] = $topic;
				} else {
					$standard[] = $topic;
				}
			}

			$this->topics = array_merge( (array)$stickies, (array)$standard );
		}

		// Fetch extra information for topics, so we don't have to query inside the loop
		$this->topics = bp_forums_get_topic_extras( $this->topics );

		if ( (int)$this->total_topic_count && (int)$this->pag_num ) {
			$this->pag_links = paginate_links( array(
				'base'      => add_query_arg( array( 'p' => '%#%', 'n' => $this->pag_num ) ),
				'format'    => '',
				'total'     => ceil( (int)$this->total_topic_count / (int)$this->pag_num),
				'current'   => $this->pag_page,
				'prev_text' => '&larr;',
				'next_text' => '&rarr;',
				'mid_size'  => 1
			) );
		}
	}

	function has_topics() {
		if ( $this->topic_count )
			return true;

		return false;
	}

	function next_topic() {
		$this->current_topic++;
		$this->topic = $this->topics[$this->current_topic];

		return $this->topic;
	}

	function rewind_topics() {
		$this->current_topic = -1;
		if ( $this->topic_count > 0 ) {
			$this->topic = $this->topics[0];
		}
	}

	function user_topics() {
		if ( $this->current_topic + 1 < $this->topic_count ) {
			return true;
		} elseif ( $this->current_topic + 1 == $this->topic_count ) {
			do_action('forum_loop_end');
			// Do some cleaning up after the loop
			$this->rewind_topics();
		}

		$this->in_the_loop = false;
		return false;
	}

	function the_topic() {
		global $topic;

		$this->in_the_loop = true;
		$this->topic = $this->next_topic();
		$this->topic = (object)$this->topic;

		if ( $this->current_topic == 0 ) // loop has just started
			do_action('forum_loop_start');
	}
}

function bp_has_forum_topics( $args = '' ) {
	global $forum_template, $bp;

	/***
	 * Set the defaults based on the current page. Any of these will be overridden
	 * if arguments are directly passed into the loop. Custom plugins should always
	 * pass their parameters directly to the loop.
	 */
	$type         = 'newest';
	$user_id      = 0;
	$forum_id     = false;
	$search_terms = false;
	$no_stickies  = 'all';

	// User filtering
	if ( !empty( $bp->displayed_user->id ) )
		$user_id = $bp->displayed_user->id;

	// If we're in a single group, set this group's forum_id
	if ( !$forum_id && !empty( $bp->groups->current_group ) ) {
		$bp->groups->current_group->forum_id = groups_get_groupmeta( $bp->groups->current_group->id, 'forum_id' );

		// If it turns out there is no forum for this group, return false so
		// we don't fetch all global topics
		if ( empty( $bp->groups->current_group->forum_id ) )
			return false;

		$forum_id = $bp->groups->current_group->forum_id;
	}

	// If $_GET['fs'] is set, let's auto populate the search_terms var
	if ( bp_is_directory() && !empty( $_GET['fs'] ) )
		$search_terms = $_GET['fs'];

	// Show stickies on a group forum
	if ( isset( $bp->groups->current_group ) )
		$no_stickies = null;

	$defaults = array(
		'type'         => $type,
		'forum_id'     => $forum_id,
		'user_id'      => $user_id,
		'page'         => 1,
		'per_page'     => 20,
		'max'          => false,
		'no_stickies'  => $no_stickies,
		'search_terms' => $search_terms
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r );

	// If we're viewing a tag URL in the directory, let's override the type and
	// set it to tags and the filter to the tag name
	if ( 'tag' == $bp->current_action && !empty( $bp->action_variables[0] ) ) {
		$search_terms = $bp->action_variables[0];
		$type = 'tags';
	}

	$forum_template = new BP_Forums_Template_Forum( $type, $forum_id, $user_id, $page, $per_page, $max, $no_stickies, $search_terms );
	return apply_filters( 'bp_has_topics', $forum_template->has_topics(), $forum_template );
}

function bp_forum_topics() {
	global $forum_template;
	return $forum_template->user_topics();
}

function bp_the_forum_topic() {
	global $forum_template;
	return $forum_template->the_topic();
}

function bp_the_topic_id() {
	echo bp_get_the_topic_id();
}
	function bp_get_the_topic_id() {
		global $forum_template;

		return apply_filters( 'bp_get_the_topic_id', $forum_template->topic->topic_id );
	}

function bp_the_topic_title() {
	echo bp_get_the_topic_title();
}
	function bp_get_the_topic_title() {
		global $forum_template;

		return apply_filters( 'bp_get_the_topic_title', stripslashes( $forum_template->topic->topic_title ) );
	}

function bp_the_topic_slug() {
	echo bp_get_the_topic_slug();
}
	function bp_get_the_topic_slug() {
		global $forum_template;

		return apply_filters( 'bp_get_the_topic_slug', $forum_template->topic->topic_slug );
	}

function bp_the_topic_text() {
	echo bp_get_the_topic_text();
}
	function bp_get_the_topic_text() {
		global $forum_template;

		$post = bb_get_first_post( (int)$forum_template->topic->topic_id, false );
		return apply_filters( 'bp_get_the_topic_text', esc_attr( $post->post_text ) );
	}

function bp_the_topic_poster_id() {
	echo bp_get_the_topic_poster_id();
}
	function bp_get_the_topic_poster_id() {
		global $forum_template;

		return apply_filters( 'bp_get_the_topic_poster_id', $forum_template->topic->topic_poster );
	}

function bp_the_topic_poster_avatar( $args = '' ) {
	echo bp_get_the_topic_poster_avatar( $args );
}
	function bp_get_the_topic_poster_avatar( $args = '' ) {
		global $forum_template;

		$defaults = array(
			'type'   => 'thumb',
			'width'  => false,
			'height' => false,
			'alt'    => __( 'Profile picture of %s', 'buddypress' )
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		return apply_filters( 'bp_get_the_topic_poster_avatar', bp_core_fetch_avatar( array( 'item_id' => $forum_template->topic->topic_poster, 'type' => $type, 'width' => $width, 'height' => $height, 'alt' => $alt ) ) );
	}

function bp_the_topic_poster_name() {
	echo bp_get_the_topic_poster_name();
}
	function bp_get_the_topic_poster_name() {
		global $forum_template;

		$poster_id = ( empty( $forum_template->topic->poster_id ) ) ? $forum_template->topic->topic_poster : $forum_template->topic->poster_id;

		if ( !$name = bp_core_get_userlink( $poster_id ) )
			return __( 'Deleted User', 'buddypress' );

		return apply_filters( 'bp_get_the_topic_poster_name', $name );
	}

function bp_the_topic_object_id() {
	echo bp_get_the_topic_object_id();
}
	function bp_get_the_topic_object_id() {
		global $forum_template;

		return apply_filters( 'bp_get_the_topic_object_id', $forum_template->topic->object_id );
	}

function bp_the_topic_object_name() {
	echo bp_get_the_topic_object_name();
}
	function bp_get_the_topic_object_name() {
		global $forum_template;

		if ( isset( $forum_template->topic->object_name ) )
			$retval = $forum_template->topic->object_name;
		else
			$retval = '';

		return apply_filters( 'bp_get_the_topic_object_name', $retval );
	}

function bp_the_topic_object_slug() {
	echo bp_get_the_topic_object_slug();
}
	function bp_get_the_topic_object_slug() {
		global $forum_template;

		return apply_filters( 'bp_get_the_topic_object_slug', $forum_template->topic->object_slug );
	}

function bp_the_topic_object_permalink() {
	echo bp_get_the_topic_object_permalink();
}
	function bp_get_the_topic_object_permalink() {
		global $bp, $forum_template;

		// Currently this will only work with group forums, extended support in the future
		if ( bp_is_active( 'groups' ) )
			$permalink = trailingslashit( bp_get_root_domain() . '/' . bp_get_groups_root_slug() . '/' . $forum_template->topic->object_slug . '/forum/' );
		else
			$permalink = '';

		return apply_filters( 'bp_get_the_topic_object_permalink', $permalink );
	}

function bp_the_topic_last_poster_name() {
	echo bp_get_the_topic_last_poster_name();
}
	function bp_get_the_topic_last_poster_name() {
		global $forum_template;

		if ( !$domain = bp_core_get_user_domain( $forum_template->topic->topic_last_poster, $forum_template->topic->topic_last_poster_nicename, $forum_template->topic->topic_last_poster_login ) )
			return __( 'Deleted User', 'buddypress' );

		return apply_filters( 'bp_get_the_topic_last_poster_name', '<a href="' . $domain . '">' . $forum_template->topic->topic_last_poster_displayname . '</a>' );
	}

function bp_the_topic_object_avatar( $args = '' ) {
	echo bp_get_the_topic_object_avatar( $args );
}
	function bp_get_the_topic_object_avatar( $args = '' ) {
		global $forum_template;

		if ( !isset( $forum_template->topic->object_id ) )
			return false;

		$defaults = array(
			'type'   => 'thumb',
			'width'  => false,
			'height' => false,
			'alt'    => __( 'Group logo for %s', 'buddypress' )
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		return apply_filters( 'bp_get_the_topic_object_avatar', bp_core_fetch_avatar( array( 'item_id' => $forum_template->topic->object_id, 'type' => $type, 'object' => 'group', 'width' => $width, 'height' => $height, 'alt' => $alt ) ) );
	}

function bp_the_topic_last_poster_avatar( $args = '' ) {
	echo bp_get_the_topic_last_poster_avatar( $args );
}
	function bp_get_the_topic_last_poster_avatar( $args = '' ) {
		global $forum_template;

		$defaults = array(
			'type'   => 'thumb',
			'width'  => false,
			'height' => false,
			'alt'    => __( 'Profile picture of %s', 'buddypress' )
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		return apply_filters( 'bp_get_the_topic_last_poster_avatar', bp_core_fetch_avatar( array( 'email' => $forum_template->topic->topic_last_poster_email, 'item_id' => $forum_template->topic->topic_last_poster, 'type' => $type, 'width' => $width, 'height' => $height, 'alt' => $alt ) ) );
	}

function bp_the_topic_start_time() {
	echo bp_get_the_topic_start_time();
}
	function bp_get_the_topic_start_time() {
		global $forum_template;

		return apply_filters( 'bp_get_the_topic_start_time', $forum_template->topic->topic_start_time );
	}

function bp_the_topic_time() {
	echo bp_get_the_topic_time();
}
	function bp_get_the_topic_time() {
		global $forum_template;

		return apply_filters( 'bp_get_the_topic_time', $forum_template->topic->topic_time );
	}

function bp_the_topic_forum_id() {
	echo bp_get_the_topic_forum_id();
}
	function bp_get_the_topic_forum_id() {
		global $forum_template;

		return apply_filters( 'bp_get_the_topic_forum_id', $forum_template->topic->topic_forum_id );
	}

function bp_the_topic_status() {
	echo bp_get_the_topic_status();
}
	function bp_get_the_topic_status() {
		global $forum_template;

		return apply_filters( 'bp_get_the_topic_status', $forum_template->topic->topic_status );
	}

function bp_the_topic_is_topic_open() {
	echo bp_get_the_topic_is_topic_open();
}
	function bp_get_the_topic_is_topic_open() {
		global $forum_template;

		return apply_filters( 'bp_get_the_topic_is_topic_open', $forum_template->topic->topic_open );
	}

function bp_the_topic_last_post_id() {
	echo bp_get_the_topic_last_post_id();
}
	function bp_get_the_topic_last_post_id() {
		global $forum_template;

		return apply_filters( 'bp_get_the_topic_last_post_id', $forum_template->topic->topic_last_post_id );
	}

function bp_the_topic_is_sticky() {
	echo bp_get_the_topic_is_sticky();
}
	function bp_get_the_topic_is_sticky() {
		global $forum_template;

		return apply_filters( 'bp_get_the_topic_is_sticky', $forum_template->topic->topic_sticky );
	}

function bp_the_topic_total_post_count() {
	echo bp_get_the_topic_total_post_count();
}
	function bp_get_the_topic_total_post_count() {
		global $forum_template;

		if ( $forum_template->topic->topic_posts == 1 )
			return apply_filters( 'bp_get_the_topic_total_post_count', sprintf( __( '%d post', 'buddypress' ), $forum_template->topic->topic_posts ) );
		else
			return apply_filters( 'bp_get_the_topic_total_post_count', sprintf( __( '%d posts', 'buddypress' ), $forum_template->topic->topic_posts ) );
	}

function bp_the_topic_total_posts() {
	echo bp_get_the_topic_total_posts();
}
	function bp_get_the_topic_total_posts() {
		global $forum_template;

		return apply_filters( 'bp_get_the_topic_total_posts', $forum_template->topic->topic_posts );
	}

function bp_the_topic_tag_count() {
	echo bp_get_the_topic_tag_count();
}
	function bp_get_the_topic_tag_count() {
		global $forum_template;

		return apply_filters( 'bp_get_the_topic_tag_count', $forum_template->topic->tag_count );
	}

function bp_the_topic_permalink() {
	echo bp_get_the_topic_permalink();
}
	function bp_get_the_topic_permalink() {
		global $forum_template, $bp;

		if ( bp_is_active( 'groups' ) && !empty( $forum_template->topic->object_slug ) )
			$permalink = trailingslashit( bp_get_root_domain() . '/' . bp_get_groups_root_slug() . '/' . $forum_template->topic->object_slug . '/forum' );
		else if ( bp_is_single_item() )
			$permalink = trailingslashit( bp_get_root_domain() . '/' . bp_get_forums_root_slug() . '/' . bp_current_item() );
		else
			$permalink = trailingslashit( bp_get_root_domain() . '/' . bp_get_forums_root_slug() );

		return apply_filters( 'bp_get_the_topic_permalink', trailingslashit( $permalink . 'topic/' . $forum_template->topic->topic_slug ) );
	}

function bp_the_topic_time_since_created() {
	echo bp_get_the_topic_time_since_created();
}
	function bp_get_the_topic_time_since_created() {
		global $forum_template;

		return apply_filters( 'bp_get_the_topic_time_since_created', bp_core_time_since( strtotime( $forum_template->topic->topic_start_time ) ) );
	}

function bp_the_topic_latest_post_excerpt( $args = '' ) {
	echo bp_get_the_topic_latest_post_excerpt( $args );
}
	function bp_get_the_topic_latest_post_excerpt( $args = '' ) {
		global $forum_template;

		$defaults = array(
			'length' => 45
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		$post = bp_forums_get_post( $forum_template->topic->topic_last_post_id );
		$post = bp_create_excerpt( $post->post_text, $length );
		return apply_filters( 'bp_get_the_topic_latest_post_excerpt', $post );
	}

function bp_the_topic_time_since_last_post() {
	global $forum_template;

	echo bp_get_the_topic_time_since_last_post();
}
	function bp_get_the_topic_time_since_last_post() {
		global $forum_template;

		return apply_filters( 'bp_get_the_topic_time_since_last_post', bp_core_time_since( strtotime( $forum_template->topic->topic_time ) ) );
	}

function bp_the_topic_is_mine() {
	echo bp_get_the_topic_is_mine();
}
	function bp_get_the_topic_is_mine() {
		global $bp, $forum_template;

		return $bp->loggedin_user->id == $forum_template->topic->topic_poster;
	}

function bp_the_topic_admin_links( $args = '' ) {
	echo bp_get_the_topic_admin_links( $args );
}
	function bp_get_the_topic_admin_links( $args = '' ) {
		global $bp, $forum_template;

		$defaults = array(
			'seperator' => '|'
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		$links[] = '<a href="' . wp_nonce_url( bp_get_the_topic_permalink() . 'edit', 'bp_forums_edit_topic' ) . '">' . __( 'Edit Topic', 'buddypress' ) . '</a>';

		if ( $bp->is_item_admin || $bp->is_item_mod || is_super_admin() ) {
			if ( 0 == (int)$forum_template->topic->topic_sticky )
				$links[] = '<a href="' . wp_nonce_url( bp_get_the_topic_permalink() . 'stick', 'bp_forums_stick_topic' ) . '">' . __( 'Sticky Topic', 'buddypress' ) . '</a>';
			else
				$links[] = '<a href="' . wp_nonce_url( bp_get_the_topic_permalink() . 'unstick', 'bp_forums_unstick_topic' ) . '">' . __( 'Un-stick Topic', 'buddypress' ) . '</a>';

			if ( 0 == (int)$forum_template->topic->topic_open )
				$links[] = '<a href="' . wp_nonce_url( bp_get_the_topic_permalink() . 'open', 'bp_forums_open_topic' ) . '">' . __( 'Open Topic', 'buddypress' ) . '</a>';
			else
				$links[] = '<a href="' . wp_nonce_url( bp_get_the_topic_permalink() . 'close', 'bp_forums_close_topic' ) . '">' . __( 'Close Topic', 'buddypress' ) . '</a>';

			$links[] = '<a class="confirm" id="topic-delete-link" href="' . wp_nonce_url( bp_get_the_topic_permalink() . 'delete', 'bp_forums_delete_topic' ) . '">' . __( 'Delete Topic', 'buddypress' ) . '</a>';
		}

		return implode( ' ' . $seperator . ' ', (array) $links );
	}

function bp_the_topic_css_class() {
	echo bp_get_the_topic_css_class();
}

	function bp_get_the_topic_css_class() {
		global $forum_template;

		$class = false;

		if ( $forum_template->current_topic % 2 == 1 )
			$class .= 'alt';

		if ( isset( $forum_template->topic->topic_sticky ) && 1 == (int)$forum_template->topic->topic_sticky )
			$class .= ' sticky';

		if ( !isset( $forum_template->topic->topic_open ) || 0 == (int)$forum_template->topic->topic_open )
			$class .= ' closed';

		return apply_filters( 'bp_get_the_topic_css_class', trim( $class ) );
	}

function bp_my_forum_topics_link() {
	echo bp_get_my_forum_topics_link();
}
	function bp_get_my_forum_topics_link() {
		global $bp;

		return apply_filters( 'bp_get_my_forum_topics_link', bp_get_root_domain() . '/' . $bp->forums->slug . '/personal/' );
	}

function bp_unreplied_forum_topics_link() {
	echo bp_get_unreplied_forum_topics_link();
}
	function bp_get_unreplied_forum_topics_link() {
		global $bp;

		return apply_filters( 'bp_get_unreplied_forum_topics_link', bp_get_root_domain() . '/' . $bp->forums->slug . '/unreplied/' );
	}


function bp_popular_forum_topics_link() {
	echo bp_get_popular_forum_topics_link();
}
	function bp_get_popular_forum_topics_link() {
		global $bp;

		return apply_filters( 'bp_get_popular_forum_topics_link', bp_get_root_domain() . '/' . $bp->forums->slug . '/popular/' );
	}

function bp_newest_forum_topics_link() {
	echo bp_get_newest_forum_topics_link();
}
	function bp_get_newest_forum_topics_link() {
		global $bp;

		return apply_filters( 'bp_get_newest_forum_topics_link', bp_get_root_domain() . '/' . $bp->forums->slug . '/' );
	}

function bp_forum_topic_type() {
	echo bp_get_forum_topic_type();
}
	function bp_get_forum_topic_type() {
		global $bp;

		if ( !bp_is_directory() || !$bp->current_action )
			return 'newest';


		return apply_filters( 'bp_get_forum_topic_type', $bp->current_action );
	}

function bp_forums_tag_name() {
	echo bp_get_forums_tag_name();
}
	function bp_get_forums_tag_name() {
		global $bp;

		if ( bp_is_directory() && $bp->forums->slug == $bp->current_component )
			return apply_filters( 'bp_get_forums_tag_name', $bp->action_variables[0] );
	}

function bp_forum_pagination() {
	echo bp_get_forum_pagination();
}
	function bp_get_forum_pagination() {
		global $forum_template;

		return apply_filters( 'bp_get_forum_pagination', $forum_template->pag_links );
	}

function bp_forum_pagination_count() {
	echo bp_get_forum_pagination_count();
}
	function bp_get_forum_pagination_count() {
		global $bp, $forum_template;

		$start_num  = intval( ( $forum_template->pag_page - 1 ) * $forum_template->pag_num ) + 1;
		$from_num   = bp_core_number_format( $start_num );
		$to_num     = bp_core_number_format( ( $start_num + ( $forum_template->pag_num - 1  ) > $forum_template->total_topic_count ) ? $forum_template->total_topic_count : $start_num + ( $forum_template->pag_num - 1 ) );
		$total      = bp_core_number_format( $forum_template->total_topic_count );
		$pag_filter = false;

		if ( 'tags' == $forum_template->type && !empty( $forum_template->search_terms ) )
			$pag_filter = sprintf( __( ' matching tag "%s"', 'buddypress' ), $forum_template->search_terms );

		return apply_filters( 'bp_get_forum_pagination_count', sprintf( __( 'Viewing topic %s to %s (of %s total topics%s)', 'buddypress' ), $from_num, $to_num, $total, $pag_filter ) . '<span class="ajax-loader"></span>' );
	}

function bp_is_edit_topic() {
	global $bp;

	if ( in_array( 'post', (array)$bp->action_variables ) && in_array( 'edit', (array)$bp->action_variables ) )
		return false;

	return true;
}


class BP_Forums_Template_Topic {
	var $current_post = -1;
	var $post_count;
	var $posts;
	var $post;

	var $forum_id;
	var $topic_id;
	var $topic;

	var $in_the_loop;

	var $pag_page;
	var $pag_num;
	var $pag_links;
	var $total_post_count;

	var $single_post = false;

	var $sort_by;
	var $order;

	function BP_Forums_Template_Topic( $topic_id, $per_page, $max, $order ) {
		global $bp, $current_user, $forum_template;

		$this->pag_page        = isset( $_REQUEST['topic_page'] ) ? intval( $_REQUEST['topic_page'] ) : 1;
		$this->pag_num         = isset( $_REQUEST['num'] ) ? intval( $_REQUEST['num'] ) : $per_page;

		$this->order           = $order;
		$this->topic_id        = $topic_id;
		$forum_template->topic = (object) bp_forums_get_topic_details( $this->topic_id );
		$this->forum_id        = $forum_template->topic->forum_id;

		$this->posts           = bp_forums_get_topic_posts( array( 'topic_id' => $this->topic_id, 'page' => $this->pag_page, 'per_page' => $this->pag_num, 'order' => $this->order ) );

		if ( !$this->posts ) {
			$this->post_count       = 0;
			$this->total_post_count = 0;
		} else {
			if ( !$max || $max >= (int)$forum_template->topic->topic_posts ) {
				$this->total_post_count = (int)$forum_template->topic->topic_posts;
			} else {
				$this->total_post_count = (int)$max;
			}

			if ( $max ) {
				if ( $max >= count( $this->posts ) ) {
					$this->post_count = count( $this->posts );
				} else {
					$this->post_count = (int)$max;
				}
			} else {
				$this->post_count = count( $this->posts );
			}
		}

		if ( (int)$this->total_post_count && (int)$this->pag_num ) {
			$this->pag_links = paginate_links( array(
				'base'      => add_query_arg( array( 'topic_page' => '%#%', 'num' => (int)$this->pag_num ) ),
				'format'    => '',
				'total'     => ceil( (int)$this->total_post_count / (int)$this->pag_num ),
				'current'   => $this->pag_page,
				'prev_text' => '&larr;',
				'next_text' => '&rarr;',
				'mid_size'  => 1
			) );

			$this->pag->total_pages = ceil( (int)$this->total_post_count / (int)$this->pag_num );
		} else {
			$this->pag->total_pages = 1;
		}
	}

	function has_posts() {
		if ( $this->post_count )
			return true;

		return false;
	}

	function next_post() {
		$this->current_post++;
		$this->post = $this->posts[$this->current_post];

		return $this->post;
	}

	function rewind_posts() {
		$this->current_post = -1;
		if ( $this->post_count > 0 ) {
			$this->post = $this->posts[0];
		}
	}

	function user_posts() {
		if ( $this->current_post + 1 < $this->post_count ) {
			return true;
		} elseif ( $this->current_post + 1 == $this->post_count ) {
			do_action('topic_loop_end');
			// Do some cleaning up after the loop
			$this->rewind_posts();
		}

		$this->in_the_loop = false;
		return false;
	}

	function the_post() {
		global $post;

		$this->in_the_loop = true;
		$this->post = $this->next_post();
		$this->post = (object)$this->post;

		if ( $this->current_post == 0 ) // loop has just started
			do_action('topic_loop_start');
	}
}

function bp_has_forum_topic_posts( $args = '' ) {
	global $topic_template, $bp;

	$defaults = array(
		'topic_id' => false,
		'per_page' => 15,
		'max'      => false,
		'order'    => 'ASC'
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	if ( empty( $topic_id ) && bp_is_current_component( 'groups') && bp_is_current_action( 'forum' ) && !empty( $bp->action_variables[0] ) && 'topic' == $bp->action_variables[0] && !empty( $bp->action_variables[1] ) )
		$topic_id = bp_forums_get_topic_id_from_slug( $bp->action_variables[1] );
	elseif ( empty( $topic_id ) && bp_is_current_component( 'forums') && bp_is_current_action( 'topic' ) && !empty( $bp->action_variables[0] ) )
		$topic_id = bp_forums_get_topic_id_from_slug( $bp->action_variables[0] );

	if ( empty( $topic_id ) ) {
		return false;

	} else {
		$topic_template = new BP_Forums_Template_Topic( (int) $topic_id, $per_page, $max, $order );

		// Current topic forum_id needs to match current_group forum_id
		if ( bp_is_current_component( 'groups' ) && $topic_template->forum_id != groups_get_groupmeta( $bp->groups->current_group->id, 'forum_id' ) )
			return false;
	}

	return apply_filters( 'bp_has_topic_posts', $topic_template->has_posts(), $topic_template );
}

function bp_forum_topic_posts() {
	global $topic_template;
	return $topic_template->user_posts();
}

function bp_the_forum_topic_post() {
	global $topic_template;
	return $topic_template->the_post();
}

function bp_the_topic_post_id() {
	echo bp_get_the_topic_post_id();
}
	function bp_get_the_topic_post_id() {
		global $topic_template;

		return apply_filters( 'bp_get_the_topic_post_id', $topic_template->post->post_id );
	}

function bp_the_topic_post_content() {
	echo bp_get_the_topic_post_content();
}
	function bp_get_the_topic_post_content() {
		global $topic_template;

		return apply_filters( 'bp_get_the_topic_post_content', stripslashes( $topic_template->post->post_text ) );
	}

function bp_the_topic_post_css_class() {
	echo bp_get_the_topic_post_css_class();
}

	function bp_get_the_topic_post_css_class() {
		global $topic_template;

		$class = false;

		if ( $topic_template->current_post % 2 == 1 )
			$class .= 'alt';

		if ( 1 == (int)$topic_template->post->post_status )
			$class .= ' deleted';

		if ( 0 == (int)$topic_template->post->post_status )
			$class .= ' open';

		return apply_filters( 'bp_get_the_topic_post_css_class', trim( $class ) );
	}

function bp_the_topic_post_poster_avatar( $args = '' ) {
	echo bp_get_the_topic_post_poster_avatar( $args );
}
	function bp_get_the_topic_post_poster_avatar( $args = '' ) {
		global $topic_template;

		$defaults = array(
			'type' => 'thumb',
			'width' => 20,
			'height' => 20,
			'alt' => __( 'Profile picture of %s', 'buddypress' )
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		return apply_filters( 'bp_get_the_topic_post_poster_avatar', bp_core_fetch_avatar( array( 'item_id' => $topic_template->post->poster_id, 'type' => $type, 'width' => $width, 'height' => $height, 'alt' => $alt ) ) );
	}

function bp_the_topic_post_poster_name() {
	echo bp_get_the_topic_post_poster_name();
}
	function bp_get_the_topic_post_poster_name() {
		global $topic_template;

		if ( !$link = bp_core_get_user_domain( $topic_template->post->poster_id, $topic_template->post->poster_nicename, $topic_template->post->poster_login ) )
			return __( 'Deleted User', 'buddypress' );

		return apply_filters( 'bp_get_the_topic_post_poster_name', '<a href="' . $link . '" title="' . $topic_template->post->poster_name . '">' . $topic_template->post->poster_name . '</a>' );
	}

function bp_the_topic_post_poster_link() {
	echo bp_get_the_topic_post_poster_link();
}
	function bp_get_the_topic_post_poster_link() {
		global $topic_template;

		return apply_filters( 'bp_the_topic_post_poster_link', bp_core_get_user_domain( $topic_template->post->poster_id, $topic_template->post->poster_nicename, $topic_template->post->poster_login ) );
	}

function bp_the_topic_post_time_since() {
	echo bp_get_the_topic_post_time_since();
}
	function bp_get_the_topic_post_time_since() {
		global $topic_template;

		return apply_filters( 'bp_get_the_topic_post_time_since', bp_core_time_since( strtotime( $topic_template->post->post_time ) ) );
	}

function bp_the_topic_post_is_mine() {
	echo bp_the_topic_post_is_mine();
}
	function bp_get_the_topic_post_is_mine() {
		global $bp, $topic_template;

		return $bp->loggedin_user->id == $topic_template->post->poster_id;
	}

function bp_the_topic_post_admin_links( $args = '' ) {
	echo bp_get_the_topic_post_admin_links( $args );
}
	function bp_get_the_topic_post_admin_links( $args = '' ) {
		global $topic_template;

		// Never show for the first post in a topic.
		if ( 0 == $topic_template->current_post && 1 == $topic_template->pag_page )
			return;

		$defaults = array(
			'separator' => ' | '
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		$query_vars = '';
		if ( $_SERVER['QUERY_STRING'] )
			$query_vars = '?' . $_SERVER['QUERY_STRING'];

		$links    = array();
		$links[]  = '<a href="' . wp_nonce_url( bp_get_the_topic_permalink() . 'edit/post/' . $topic_template->post->post_id . '/' . $query_vars, 'bp_forums_edit_post' ) . '">' . __( 'Edit', 'buddypress' ) . '</a>';
		$links[] .= '<a class="confirm" id="post-delete-link" href="' . wp_nonce_url( bp_get_the_topic_permalink() . 'delete/post/' . $topic_template->post->post_id, 'bp_forums_delete_post' ) . '">' . __( 'Delete', 'buddypress' ) . '</a>';

		return apply_filters( 'bp_get_the_topic_post_admin_links', implode( $separator, $links ), $links, $r );
	}

function bp_the_topic_post_edit_text() {
	echo bp_get_the_topic_post_edit_text();
}
	function bp_get_the_topic_post_edit_text() {
		global $bp;

		$post = bp_forums_get_post( $bp->action_variables[4] );
		return apply_filters( 'bp_get_the_topic_post_edit_text', esc_attr( $post->post_text ) );
	}

function bp_the_topic_pagination() {
	echo bp_get_the_topic_pagination();
}
	function bp_get_the_topic_pagination() {
		global $topic_template;

		return apply_filters( 'bp_get_the_topic_pagination', $topic_template->pag_links );
	}

function bp_the_topic_pagination_count() {
	global $bp, $topic_template;

	$start_num = intval( ( $topic_template->pag_page - 1 ) * $topic_template->pag_num ) + 1;
	$from_num = bp_core_number_format( $start_num );
	$to_num = bp_core_number_format( ( $start_num + ( $topic_template->pag_num - 1  ) > $topic_template->total_post_count ) ? $topic_template->total_post_count : $start_num + ( $topic_template->pag_num - 1 ) );
	$total = bp_core_number_format( $topic_template->total_post_count );

	echo apply_filters( 'bp_the_topic_pagination_count', sprintf( __( 'Viewing post %1$s to %2$s (%3$s total posts)', 'buddypress' ), $from_num, $to_num, $total ) );
?>
	<span class="ajax-loader"></span>
<?php
}

function bp_the_topic_is_last_page() {
	echo bp_get_the_topic_is_last_page();
}
	function bp_get_the_topic_is_last_page() {
		global $topic_template;

		return apply_filters( 'bp_get_the_topic_is_last_page', $topic_template->pag_page == $topic_template->pag->total_pages );
	}

function bp_directory_forums_search_form() {
	global $bp;

	$default_search_value = bp_get_search_default_text( 'forums' );
	$search_value = !empty( $_REQUEST['fs'] ) ? stripslashes( $_REQUEST['fs'] ) : $default_search_value;  ?>

	<form action="" method="get" id="search-forums-form">
		<label><input type="text" name="s" id="forums_search" value="<?php echo esc_attr( $search_value ); ?>"  onfocus="if (this.value == '<?php echo $default_search_value ?>') {this.value = '';}" onblur="if (this.value == '') {this.value = '<?php echo $default_search_value ?>';}" /></label>
		<input type="submit" id="forums_search_submit" name="forums_search_submit" value="<?php _e( 'Search', 'buddypress' ); ?>" />
	</form>

<?php
}

function bp_forum_permalink( $forum_id = 0 ) {
	echo bp_get_forum_permalink( $forum_id );
}
	function bp_get_forum_permalink( $forum_id = 0 ) {
		global $bp;

		if ( bp_is_groups_component() ) {
			$permalink = trailingslashit( bp_get_root_domain() . '/' . bp_get_groups_root_slug() . '/' . bp_current_item() . 'forum' );
		} else {
			if ( empty( $forum_id ) ) {
				global $topic_template;
				if ( isset( $topic_template->forum_id ) )
					$forum_id = $topic_template->forum_id;
			}

			if ( $forum = bp_forums_get_forum( $forum_id ) )
				$permalink = trailingslashit( bp_get_root_domain() . '/' . bp_get_forums_root_slug() . '/forum/' . $forum->forum_slug );
			else
				return false;
		}

		return apply_filters( 'bp_get_forum_permalink', trailingslashit( $permalink ) );
	}

function bp_forum_name( $forum_id = 0 ) {
	echo bp_get_forum_name( $forum_id );
}
	function bp_get_forum_name( $forum_id = 0 ) {
		global $bp;

		if ( empty( $forum_id ) ) {
			global $topic_template;
			if ( isset( $topic_template->forum_id ) )
				$forum_id = $topic_template->forum_id;
		}

		if ( $forum = bp_forums_get_forum( $forum_id ) )
			return apply_filters( 'bp_get_forum_name', $forum->forum_name, $forum->forum_id );
		else
			return false;
	}

function bp_forum_directory_permalink() {
	echo bp_get_forum_directory_permalink();
}
	function bp_get_forum_directory_permalink() {
		global $bp;

		return apply_filters( 'bp_get_forum_directory_permalink', trailingslashit( bp_get_root_domain() . '/' . $bp->forums->root_slug ) );
	}

function bp_forums_tag_heat_map( $args = '' ) {
	$defaults = array(
		'smallest' => '10',
		'largest'  => '42',
		'sizing'   => 'px',
		'limit'    => '50'
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	bb_tag_heat_map( $smallest, $largest, $sizing, $limit );
}

/**
 * Echo the current topic's tag list, comma-separated
 *
 * @package BuddyPress
 * @since 1.3
 */
function bp_forum_topic_tag_list() {
	echo bp_get_forum_topic_tag_list();
}
	/**
	 * Get the current topic's tag list
	 *
	 * @package BuddyPress
	 * @since 1.3
	 *
	 * @param str $format 'string' returns comma-separated string; otherwise returns array
	 * @return mixed $tags
	 */
	function bp_get_forum_topic_tag_list( $format = 'string' ) {
		do_action( 'bbpress_init' );
		
		// Pull up the tag objects
		$tags_data = bb_get_topic_tags( bp_get_the_topic_id() );
		
		// Convert them to a comma-separated string
		$tags = array();
		foreach( $tags_data as $tag_data ) {
			$tags[] = $tag_data->name;
		}
		
		if ( 'string' == $format )
			$tags = implode( ', ', $tags );
			
		return apply_filters( 'bp_forum_topic_tag_list', $tags, $format );
	}

function bp_forum_action() {
	echo bp_get_forum_action();
}
	function bp_get_forum_action() {
		global $topic_template;

		return apply_filters( 'bp_get_forum_action', bp_get_root_domain() . esc_attr( $_SERVER['REQUEST_URI'] ) );
	}

function bp_forum_topic_action() {
	echo bp_get_forum_topic_action();
}
	function bp_get_forum_topic_action() {
		global $bp;

		return apply_filters( 'bp_get_forum_topic_action', bp_get_root_domain() . esc_attr( $_SERVER['REQUEST_URI'] ) );
	}

function bp_forum_topic_count_for_user( $user_id = 0 ) {
	echo bp_get_forum_topic_count_for_user( $user_id );
}
	function bp_get_forum_topic_count_for_user( $user_id = 0 ) {
		return apply_filters( 'bp_get_forum_topic_count_for_user', bp_forums_total_topic_count_for_user( $user_id ) );
	}

function bp_forum_topic_count( $user_id = 0 ) {
	echo bp_get_forum_topic_count( $user_id );
}
	function bp_get_forum_topic_count( $user_id = 0 ) {
		return apply_filters( 'bp_get_forum_topic_count', bp_forums_total_topic_count( $user_id ) );
	}
?>