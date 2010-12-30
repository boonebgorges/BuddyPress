<?php
/***
 * Members template loop that will allow you to loop all members or friends of a member
 * if you pass a user_id.
 */

class BP_Core_Members_Template {
	var $current_member = -1;
	var $member_count;
	var $members;
	var $member;

	var $in_the_loop;

	var $pag_page;
	var $pag_num;
	var $pag_links;
	var $total_member_count;

	function bp_core_members_template( $type, $page_number, $per_page, $max, $user_id, $search_terms, $include, $populate_extras, $exclude ) {
		global $bp;

		$this->pag_page = isset( $_REQUEST['upage'] ) ? intval( $_REQUEST['upage'] ) : $page_number;
		$this->pag_num  = isset( $_REQUEST['num'] ) ? intval( $_REQUEST['num'] ) : $per_page;
		$this->type     = $type;

		if ( isset( $_REQUEST['letter'] ) && '' != $_REQUEST['letter'] )
			$this->members = BP_Core_User::get_users_by_letter( $_REQUEST['letter'], $this->pag_num, $this->pag_page, $populate_extras, $exclude );
		else if ( false !== $include )
			$this->members = BP_Core_User::get_specific_users( $include, $this->pag_num, $this->pag_page, $populate_extras );
		else
			$this->members = bp_core_get_users( array( 'type' => $this->type, 'per_page' => $this->pag_num, 'page' => $this->pag_page, 'user_id' => $user_id, 'include' => $include, 'search_terms' => $search_terms, 'populate_extras' => $populate_extras, 'exclude' => $exclude ) );

		if ( !$max || $max >= (int)$this->members['total'] )
			$this->total_member_count = (int)$this->members['total'];
		else
			$this->total_member_count = (int)$max;

		$this->members = $this->members['users'];

		if ( $max ) {
			if ( $max >= count( $this->members ) ) {
				$this->member_count = count( $this->members );
			} else {
				$this->member_count = (int)$max;
			}
		} else {
			$this->member_count = count( $this->members );
		}

		if ( (int)$this->total_member_count && (int)$this->pag_num ) {
			$this->pag_links = paginate_links( array(
				'base'      => add_query_arg( 'upage', '%#%' ),
				'format'    => '',
				'total'     => ceil( (int)$this->total_member_count / (int)$this->pag_num ),
				'current'   => (int) $this->pag_page,
				'prev_text' => '&larr;',
				'next_text' => '&rarr;',
				'mid_size'   => 1
			) );
		}
	}

	function has_members() {
		if ( $this->member_count )
			return true;

		return false;
	}

	function next_member() {
		$this->current_member++;
		$this->member = $this->members[$this->current_member];

		return $this->member;
	}

	function rewind_members() {
		$this->current_member = -1;
		if ( $this->member_count > 0 ) {
			$this->member = $this->members[0];
		}
	}

	function members() {
		if ( $this->current_member + 1 < $this->member_count ) {
			return true;
		} elseif ( $this->current_member + 1 == $this->member_count ) {
			do_action('member_loop_end');
			// Do some cleaning up after the loop
			$this->rewind_members();
		}

		$this->in_the_loop = false;
		return false;
	}

	function the_member() {
		global $member, $bp;

		$this->in_the_loop = true;
		$this->member = $this->next_member();

		if ( 0 == $this->current_member ) // loop has just started
			do_action('member_loop_start');
	}
}

function bp_rewind_members() {
	global $members_template;

	return $members_template->rewind_members();
}

function bp_has_members( $args = '' ) {
	global $bp, $members_template;

	/***
	 * Set the defaults based on the current page. Any of these will be overridden
	 * if arguments are directly passed into the loop. Custom plugins should always
	 * pass their parameters directly to the loop.
	 */
	$type = 'active';
	$user_id = false;
	$page = 1;
	$search_terms = null;

	// User filtering
	if ( !empty( $bp->displayed_user->id ) )
		$user_id = $bp->displayed_user->id;

	// type: active ( default ) | random | newest | popular | online | alphabetical
	$defaults = array(
		'type' => $type,
		'page' => $page,
		'per_page' => 20,
		'max' => false,

		'include' => false, // Pass a user_id or a list (comma-separated or array) of user_ids to only show these users
		'exclude' => false, // Pass a user_id or a list (comma-separated or array) of user_ids to exclude these users

		'user_id' => $user_id, // Pass a user_id to only show friends of this user
		'search_terms' => $search_terms, // Pass search_terms to filter users by their profile data

		'populate_extras' => true // Fetch usermeta? Friend count, last active etc.
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r );

	// Pass a filter if ?s= is set.
	if ( is_null( $search_terms ) ) {
		if ( isset( $_REQUEST['s'] ) && !empty( $_REQUEST['s'] ) )
			$search_terms = $_REQUEST['s'];
		else
			$search_terms = false;
	}

	if ( $max ) {
		if ( $per_page > $max )
			$per_page = $max;
	}

	// Make sure we return no members if we looking at friendship requests and there are none.
	if ( empty( $include ) && $bp->friends->slug == $bp->current_component && 'requests' == $bp->current_action )
		return false;

	$members_template = new BP_Core_Members_Template( $type, $page, $per_page, $max, $user_id, $search_terms, $include, (bool)$populate_extras, $exclude );
	return apply_filters( 'bp_has_members', $members_template->has_members(), $members_template );
}

function bp_the_member() {
	global $members_template;
	return $members_template->the_member();
}

function bp_members() {
	global $members_template;
	return $members_template->members();
}

function bp_members_pagination_count() {
	echo bp_get_members_pagination_count();
}
	function bp_get_members_pagination_count() {
		global $bp, $members_template;

		$start_num = intval( ( $members_template->pag_page - 1 ) * $members_template->pag_num ) + 1;
		$from_num = bp_core_number_format( $start_num );
		$to_num = bp_core_number_format( ( $start_num + ( $members_template->pag_num - 1 ) > $members_template->total_member_count ) ? $members_template->total_member_count : $start_num + ( $members_template->pag_num - 1 ) );
		$total = bp_core_number_format( $members_template->total_member_count );

		if ( 'active' == $members_template->type )
			$pag = sprintf( __( 'Viewing member %1$s to %2$s (of %3$s active members)', 'buddypress' ), $from_num, $to_num, $total );
		else if ( 'popular' == $members_template->type )
			$pag = sprintf( __( 'Viewing member %1$s to %2$s (of %3$s members with friends)', 'buddypress' ), $from_num, $to_num, $total );
		else if ( 'online' == $members_template->type )
			$pag = sprintf( __( 'Viewing member %1$s to %2$s (of %3$s members online)', 'buddypress' ), $from_num, $to_num, $total );
		else
			$pag = sprintf( __( 'Viewing member %1$s to %2$s (of %3$s members)', 'buddypress' ), $from_num, $to_num, $total );

		return apply_filters( 'bp_members_pagination_count', $pag . '<span class="ajax-loader"></span>' );
	}

function bp_members_pagination_links() {
	echo bp_get_members_pagination_links();
}
	function bp_get_members_pagination_links() {
		global $members_template;

		return apply_filters( 'bp_get_members_pagination_links', $members_template->pag_links );
	}

/**
 * bp_member_user_id()
 *
 * Echo id from bp_get_member_user_id()
 *
 * @uses bp_get_member_user_id()
 */
function bp_member_user_id() {
	echo bp_get_member_user_id();
}
	/**
	 * bp_get_member_user_id()
	 *
	 * Get the id of the user in a members loop
	 *
	 * @global object $members_template
	 * @return string Members id
	 */
	function bp_get_member_user_id() {
		global $members_template;

		return apply_filters( 'bp_get_member_user_id', $members_template->member->id );
	}

/**
 * bp_member_user_nicename()
 *
 * Echo nicename from bp_get_member_user_nicename()
 *
 * @uses bp_get_member_user_nicename()
 */
function bp_member_user_nicename() {
	echo bp_get_member_user_nicename();
}
	/**
	 * bp_get_member_user_nicename()
	 *
	 * Get the nicename of the user in a members loop
	 *
	 * @global object $members_template
	 * @return string Members nicename
	 */
	function bp_get_member_user_nicename() {
		global $members_template;
		return apply_filters( 'bp_get_member_user_nicename', $members_template->member->user_nicename );
	}

/**
 * bp_member_user_login()
 *
 * Echo login from bp_get_member_user_login()
 *
 * @uses bp_get_member_user_login()
 */
function bp_member_user_login() {
	echo bp_get_member_user_login();
}
	/**
	 * bp_get_member_user_login()
	 *
	 * Get the login of the user in a members loop
	 *
	 * @global object $members_template
	 * @return string Members login
	 */
	function bp_get_member_user_login() {
		global $members_template;
		return apply_filters( 'bp_get_member_user_login', $members_template->member->user_login );
	}

/**
 * bp_member_user_email()
 *
 * Echo email address from bp_get_member_user_email()
 *
 * @uses bp_get_member_user_email()
 */
function bp_member_user_email() {
	echo bp_get_member_user_email();
}
	/**
	 * bp_get_member_user_email()
	 *
	 * Get the email address of the user in a members loop
	 *
	 * @global object $members_template
	 * @return string Members email address
	 */
	function bp_get_member_user_email() {
		global $members_template;
		return apply_filters( 'bp_get_member_user_email', $members_template->member->user_email );
	}

function bp_member_is_loggedin_user() {
	global $bp, $members_template;
	return apply_filters( 'bp_member_is_loggedin_user', $bp->loggedin_user->id == $members_template->member->id ? true : false );
}

function bp_member_avatar( $args = '' ) {
	echo apply_filters( 'bp_member_avatar', bp_get_member_avatar( $args ) );
}
	function bp_get_member_avatar( $args = '' ) {
		global $bp, $members_template;

		$defaults = array(
			'type' => 'thumb',
			'width' => false,
			'height' => false,
			'class' => 'avatar',
			'id' => false,
			'alt' => __( 'Member avatar', 'buddypress' )
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		return apply_filters( 'bp_get_member_avatar', bp_core_fetch_avatar( array( 'item_id' => $members_template->member->id, 'type' => $type, 'alt' => $alt, 'css_id' => $id, 'class' => $class, 'width' => $width, 'height' => $height, 'email' => $members_template->member->user_email ) ) );
	}

function bp_member_permalink() {
	echo bp_get_member_permalink();
}
	function bp_get_member_permalink() {
		global $members_template;

		return apply_filters( 'bp_get_member_permalink', bp_core_get_user_domain( $members_template->member->id, $members_template->member->user_nicename, $members_template->member->user_login ) );
	}
	function bp_member_link() { echo bp_get_member_permalink(); }
	function bp_get_member_link() { return bp_get_member_permalink(); }

function bp_member_name() {
	echo apply_filters( 'bp_member_name', bp_get_member_name() );
}
	function bp_get_member_name() {
		global $members_template;

		if ( empty($members_template->member->fullname) )
			$members_template->member->fullname = $members_template->member->display_name;

		return apply_filters( 'bp_get_member_name', $members_template->member->fullname );
	}
	add_filter( 'bp_get_member_name', 'wp_filter_kses' );
	add_filter( 'bp_get_member_name', 'stripslashes' );
	add_filter( 'bp_get_member_name', 'strip_tags' );

function bp_member_last_active() {
	echo bp_get_member_last_active();
}
	function bp_get_member_last_active() {
		global $members_template;

		$last_activity = bp_core_get_last_activity( $members_template->member->last_activity, __( 'active %s ago', 'buddypress' ) );

		return apply_filters( 'bp_member_last_active', $last_activity );
	}

function bp_member_latest_update( $args = '' ) {
	echo bp_get_member_latest_update( $args );
}
	function bp_get_member_latest_update( $args = '' ) {
		global $members_template, $bp;

		$defaults = array(
			'length' => 70
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		if ( !isset( $members_template->member->latest_update ) || !$update = maybe_unserialize( $members_template->member->latest_update ) )
			return false;

		$update_content = apply_filters( 'bp_get_activity_latest_update', strip_tags( bp_create_excerpt( $update['content'], $length ) ) );

		if ( !empty( $update['id'] ) )
			$update_content .= ' &middot; <a href="' . $bp->root_domain . '/' . BP_ACTIVITY_SLUG . '/p/' . $update['id'] . '">' . __( 'View', 'buddypress' ) . '</a>';

		return apply_filters( 'bp_get_member_latest_update', $update_content );
	}

function bp_member_profile_data( $args = '' ) {
	echo bp_get_member_profile_data( $args );
}
	function bp_get_member_profile_data( $args = '' ) {
		global $members_template;

		if ( !bp_is_active( 'xprofile' ) )
			return false;

		$defaults = array(
			'field' => false, // Field name
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		// Populate the user if it hasn't been already.
		if ( empty( $members_template->member->profile_data ) && method_exists( 'BP_XProfile_ProfileData', 'get_all_for_user' ) )
			$members_template->member->profile_data = BP_XProfile_ProfileData::get_all_for_user( $members_template->member->id );

		$data = xprofile_format_profile_field( $members_template->member->profile_data[$field]['field_type'], $members_template->member->profile_data[$field]['field_data'] );

		return apply_filters( 'bp_get_member_profile_data', $data );
	}

function bp_member_registered() {
	echo bp_get_member_registered();
}
	function bp_get_member_registered() {
		global $members_template;

		$registered = esc_attr( bp_core_get_last_activity( $members_template->member->user_registered, __( 'registered %s ago', 'buddypress' ) ) );

		return apply_filters( 'bp_member_last_active', $registered );
	}

function bp_member_random_profile_data() {
	global $members_template;

	if ( function_exists( 'xprofile_get_random_profile_data' ) ) { ?>
		<?php $random_data = xprofile_get_random_profile_data( $members_template->member->id, true ); ?>
			<strong><?php echo wp_filter_kses( $random_data[0]->name ) ?></strong>
			<?php echo wp_filter_kses( $random_data[0]->value ) ?>
	<?php }
}

function bp_member_hidden_fields() {
	if ( isset( $_REQUEST['s'] ) ) {
		echo '<input type="hidden" id="search_terms" value="' . esc_attr( $_REQUEST['s'] ) . '" name="search_terms" />';
	}

	if ( isset( $_REQUEST['letter'] ) ) {
		echo '<input type="hidden" id="selected_letter" value="' . esc_attr( $_REQUEST['letter'] ) . '" name="selected_letter" />';
	}

	if ( isset( $_REQUEST['members_search'] ) ) {
		echo '<input type="hidden" id="search_terms" value="' . esc_attr( $_REQUEST['members_search'] ) . '" name="search_terms" />';
	}
}

function bp_directory_members_search_form() {
	global $bp;

	$search_value = __( 'Search anything...', 'buddypress' );
	if ( !empty( $_GET['s'] ) )
	 	$search_value = stripslashes( $_GET['s'] );

	?>
	<form action="" method="get" id="search-members-form">
		<label><input type="text" name="s" id="members_search" value="<?php echo esc_attr( $search_value ) ?>"  onfocus="if (this.value == '<?php _e( 'Search anything...', 'buddypress' ) ?>') {this.value = '';}" onblur="if (this.value == '') {this.value = '<?php _e( 'Search anything...', 'buddypress' ) ?>';}" /></label>
		<input type="submit" id="members_search_submit" name="members_search_submit" value="<?php _e( 'Search', 'buddypress' ) ?>" />
	</form>
<?php
}

function bp_total_site_member_count() {
	echo bp_get_total_site_member_count();
}
	function bp_get_total_site_member_count() {
		return apply_filters( 'bp_get_total_site_member_count', bp_core_number_format( bp_core_get_total_member_count() ) );
	}


/** Navigation and other misc template tags **/

/**
 * bp_get_nav()
 * TEMPLATE TAG
 *
 * Uses the $bp->bp_nav global to render out the navigation within a BuddyPress install.
 * Each component adds to this navigation array within its own [component_name]_setup_nav() function.
 *
 * This navigation array is the top level navigation, so it contains items such as:
 *      [Blog, Profile, Messages, Groups, Friends] ...
 *
 * The function will also analyze the current component the user is in, to determine whether
 * or not to highlight a particular nav item.
 *
 * @package BuddyPress Core
 * @todo Move to a back-compat file?
 * @deprecated Does not seem to be called anywhere in the core
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 */
function bp_get_loggedin_user_nav() {
	global $bp, $current_blog;

	/* Loop through each navigation item */
	foreach( (array) $bp->bp_nav as $nav_item ) {
		/* If the current component matches the nav item id, then add a highlight CSS class. */
		if ( !bp_is_directory() && $bp->active_components[$bp->current_component] == $nav_item['css_id'] )
			$selected = ' class="current selected"';
		else
			$selected = '';

		/* If we are viewing another person (current_userid does not equal loggedin_user->id)
		   then check to see if the two users are friends. if they are, add a highlight CSS class
		   to the friends nav item if it exists. */
		if ( !bp_is_my_profile() && $bp->displayed_user->id ) {
			$selected = '';

			if ( bp_is_active( 'friends' ) ) {
				if ( $nav_item['css_id'] == $bp->friends->id ) {
					if ( friends_check_friendship( $bp->loggedin_user->id, $bp->displayed_user->id ) )
						$selected = ' class="current selected"';
				}
			}
		}

		/* echo out the final list item */
		echo apply_filters( 'bp_get_loggedin_user_nav_' . $nav_item['css_id'], '<li id="li-nav-' . $nav_item['css_id'] . '" ' . $selected . '><a id="my-' . $nav_item['css_id'] . '" href="' . $nav_item['link'] . '">' . $nav_item['name'] . '</a></li>', &$nav_item );
	}

	/* Always add a log out list item to the end of the navigation */
	if ( function_exists( 'wp_logout_url' ) ) {
		$logout_link = '<li><a id="wp-logout" href="' .  wp_logout_url( $bp->root_domain ) . '">' . __( 'Log Out', 'buddypress' ) . '</a></li>';
	} else {
		$logout_link = '<li><a id="wp-logout" href="' . site_url() . '/wp-login.php?action=logout&amp;redirect_to=' . $bp->root_domain . '">' . __( 'Log Out', 'buddypress' ) . '</a></li>';
	}

	echo apply_filters( 'bp_logout_nav_link', $logout_link );
}

/**
 * bp_get_displayed_user_nav()
 * TEMPLATE TAG
 *
 * Uses the $bp->bp_users_nav global to render out the user navigation when viewing another user other than
 * yourself.
 *
 * @package BuddyPress Core
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 */
function bp_get_displayed_user_nav() {
	global $bp;

	foreach ( (array)$bp->bp_nav as $user_nav_item ) {
		if ( !$user_nav_item['show_for_displayed_user'] && !bp_is_my_profile() )
			continue;

		if ( $bp->current_component == $user_nav_item['slug'] )
			$selected = ' class="current selected"';
		else
			$selected = '';

		if ( $bp->loggedin_user->domain )
			$link = str_replace( $bp->loggedin_user->domain, $bp->displayed_user->domain, $user_nav_item['link'] );
		else
			$link = $bp->displayed_user->domain . $user_nav_item['link'];

		echo apply_filters( 'bp_get_displayed_user_nav_' . $user_nav_item['css_id'], '<li id="' . $user_nav_item['css_id'] . '-personal-li" ' . $selected . '><a id="user-' . $user_nav_item['css_id'] . '" href="' . $link . '">' . $user_nav_item['name'] . '</a></li>', &$user_nav_item );
	}
}

/**
 * bp_get_options_nav()
 * TEMPLATE TAG
 *
 * Uses the $bp->bp_options_nav global to render out the sub navigation for the current component.
 * Each component adds to its sub navigation array within its own [component_name]_setup_nav() function.
 *
 * This sub navigation array is the secondary level navigation, so for profile it contains:
 *      [Public, Edit Profile, Change Avatar]
 *
 * The function will also analyze the current action for the current component to determine whether
 * or not to highlight a particular sub nav item.
 *
 * @package BuddyPress Core
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @uses bp_get_user_nav() Renders the navigation for a profile of a currently viewed user.
 */
function bp_get_options_nav() {
	global $bp;

	if ( count( $bp->bp_options_nav[$bp->current_component] ) < 1 )
		return false;

	/* Loop through each navigation item */
	foreach ( (array)$bp->bp_options_nav[$bp->current_component] as $subnav_item ) {
		if ( !$subnav_item['user_has_access'] )
			continue;

		/* If the current action or an action variable matches the nav item id, then add a highlight CSS class. */
		if ( $subnav_item['slug'] == $bp->current_action ) {
			$selected = ' class="current selected"';
		} else {
			$selected = '';
		}

		/* echo out the final list item */
		echo apply_filters( 'bp_get_options_nav_' . $subnav_item['css_id'], '<li id="' . $subnav_item['css_id'] . '-personal-li" ' . $selected . '><a id="' . $subnav_item['css_id'] . '" href="' . $subnav_item['link'] . '">' . $subnav_item['name'] . '</a></li>', $subnav_item );
	}
}

function bp_get_options_title() {
	global $bp;

	if ( empty( $bp->bp_options_title ) )
		$bp->bp_options_title = __( 'Options', 'buddypress' );

	echo apply_filters( 'bp_get_options_title', esc_attr( $bp->bp_options_title ) );
}


/** AVATAR TEMPLATE TAGS *******************************************************/

/**
 * bp_has_options_avatar()
 * TEMPLATE TAG
 *
 * Check to see if there is an options avatar. An options avatar is an avatar for something
 * like a group, or a friend. Basically an avatar that appears in the sub nav options bar.
 *
 * @package BuddyPress Core
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 */
function bp_has_options_avatar() {
	global $bp;

	if ( empty( $bp->bp_options_avatar ) )
		return false;

	return true;
}

function bp_get_options_avatar() {
	global $bp;

	echo apply_filters( 'bp_get_options_avatar', $bp->bp_options_avatar );
}

function bp_comment_author_avatar() {
	global $comment;

	if ( function_exists('bp_core_fetch_avatar') ) {
		echo apply_filters( 'bp_comment_author_avatar', bp_core_fetch_avatar( array( 'item_id' => $comment->user_id, 'type' => 'thumb' ) ) );
	} else if ( function_exists('get_avatar') ) {
		get_avatar();
	}
}

function bp_post_author_avatar() {
	global $post;

	if ( function_exists('bp_core_fetch_avatar') ) {
		echo apply_filters( 'bp_post_author_avatar', bp_core_fetch_avatar( array( 'item_id' => $post->post_author, 'type' => 'thumb' ) ) );
	} else if ( function_exists('get_avatar') ) {
		get_avatar();
	}
}

function bp_loggedin_user_avatar( $args = '' ) {
	echo bp_get_loggedin_user_avatar( $args );
}
	function bp_get_loggedin_user_avatar( $args = '' ) {
		global $bp;

		$defaults = array(
			'type'		=> 'thumb',
			'width'		=> false,
			'height'	=> false,
			'html'		=> true
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		return apply_filters( 'bp_get_loggedin_user_avatar', bp_core_fetch_avatar( array( 'item_id' => $bp->loggedin_user->id, 'type' => $type, 'width' => $width, 'height' => $height, 'html' => $html ) ) );
	}

function bp_displayed_user_avatar( $args = '' ) {
	echo bp_get_displayed_user_avatar( $args );
}
	function bp_get_displayed_user_avatar( $args = '' ) {
		global $bp;

		$defaults = array(
			'type'		=> 'thumb',
			'width'		=> false,
			'height'	=> false,
			'html'		=> true
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		return apply_filters( 'bp_get_displayed_user_avatar', bp_core_fetch_avatar( array( 'item_id' => $bp->displayed_user->id, 'type' => $type, 'width' => $width, 'height' => $height, 'html' => $html ) ) );
	}

function bp_avatar_admin_step() {
	echo bp_get_avatar_admin_step();
}
	function bp_get_avatar_admin_step() {
		global $bp;

		if ( isset( $bp->avatar_admin->step ) )
			$step = $bp->avatar_admin->step;
		else
			$step = '';

		return apply_filters( 'bp_get_avatar_admin_step', $step );
	}

function bp_avatar_to_crop() {
	echo bp_get_avatar_to_crop();
}
	function bp_get_avatar_to_crop() {
		global $bp;

		if ( isset( $bp->avatar_admin->image->url ) )
			$url = $bp->avatar_admin->image->url;
		else
			$url = '';

		return apply_filters( 'bp_get_avatar_to_crop', $url );
	}

function bp_avatar_to_crop_src() {
	echo bp_get_avatar_to_crop_src();
}
	function bp_get_avatar_to_crop_src() {
		global $bp;

		return apply_filters( 'bp_get_avatar_to_crop_src', str_replace( WP_CONTENT_DIR, '', $bp->avatar_admin->image->dir ) );
	}

function bp_avatar_cropper() {
	global $bp;

	echo '<img id="avatar-to-crop" class="avatar" src="' . $bp->avatar_admin->image . '" />';
}

function bp_site_name() {
	echo apply_filters( 'bp_site_name', get_bloginfo( 'name', 'display' ) );
}

function bp_core_get_wp_profile() {
	global $bp;

	$ud = get_userdata( $bp->displayed_user->id );
?>

<div class="bp-widget wp-profile">
	<h4><?php _e( 'My Profile' ) ?></h4>

	<table class="wp-profile-fields zebra">
		<?php if ( $ud->display_name ) { ?>
		<tr id="wp_displayname">
			<td class="label">
				<?php _e( 'Name', 'buddypress' ) ?>
			</td>
			<td class="data">
				<?php echo $ud->display_name ?>
			</td>
		</tr>
		<?php } ?>
		<?php if ( $ud->user_description ) { ?>
		<tr id="wp_desc">
			<td class="label">
				<?php _e( 'About Me', 'buddypress' ) ?>
			</td>
			<td class="data">
				<?php echo $ud->user_description ?>
			</td>
		</tr>
		<?php } ?>
		<?php if ( $ud->user_url ) { ?>
		<tr id="wp_website">
			<td class="label">
				<?php _e( 'Website', 'buddypress' ) ?>
			</td>
			<td class="data">
				<?php echo make_clickable( $ud->user_url ) ?>
			</td>
		</tr>
		<?php } ?>
		<?php if ( $ud->jabber ) { ?>
		<tr id="wp_jabber">
			<td class="label">
				<?php _e( 'Jabber', 'buddypress' ) ?>
			</td>
			<td class="data">
				<?php echo $ud->jabber ?>
			</td>
		</tr>
		<?php } ?>
		<?php if ( $ud->aim ) { ?>
		<tr id="wp_aim">
			<td class="label">
				<?php _e( 'AOL Messenger', 'buddypress' ) ?>
			</td>
			<td class="data">
				<?php echo $ud->aim ?>
			</td>
		</tr>
		<?php } ?>
		<?php if ( $ud->yim ) { ?>
		<tr id="wp_yim">
			<td class="label">
				<?php _e( 'Yahoo Messenger', 'buddypress' ) ?>
			</td>
			<td class="data">
				<?php echo $ud->yim ?>
			</td>
		</tr>
		<?php } ?>
	</table>
</div>
<?php
}

function bp_get_profile_header() {
	locate_template( array( '/profile/profile-header.php' ), true );
}

function bp_exists( $component_name ) {
	if ( function_exists($component_name . '_install') )
		return true;

	return false;
}

function bp_format_time( $time, $just_date = false ) {
	if ( !$time )
		return false;

	// Get GMT offset from root blog
	$root_blog_offset = get_blog_option( BP_ROOT_BLOG, 'gmt_offset' );

	// Calculate offset time
	$time_offset = $time + ( $root_blog_offset * 3600 );

	// Current date (January 1, 2010)
	$date = date_i18n( get_option( 'date_format' ), $time_offset );

	// Should we show the time also?
	if ( !$just_date ) {
		// Current time (9:50pm)
		$time = date_i18n( ' g:ia', $time_offset );

		// Return string formatted with date and time
		$date = sprintf( __( '%1$s at %2$s', 'buddypress' ), $date, $time );
	}

	return apply_filters( 'bp_format_time', $date );
}

function bp_word_or_name( $youtext, $nametext, $capitalize = true, $echo = true ) {
	global $bp;

	if ( $capitalize )
		$youtext = bp_core_ucfirst($youtext);

	if ( $bp->displayed_user->id == $bp->loggedin_user->id ) {
		if ( $echo )
			echo apply_filters( 'bp_word_or_name', $youtext );
		else
			return apply_filters( 'bp_word_or_name', $youtext );
	} else {
		$fullname = (array)explode( ' ', $bp->displayed_user->fullname );
		$nametext = sprintf( $nametext, $fullname[0] );
		if ( $echo )
			echo apply_filters( 'bp_word_or_name', $nametext );
		else
			return apply_filters( 'bp_word_or_name', $nametext );
	}
}


function bp_get_plugin_sidebar() {
	locate_template( array( 'plugin-sidebar.php' ), true );
}

function bp_page_title() {
	echo bp_get_page_title();
}

function bp_get_page_title() {
	global $bp, $post, $wp_query, $current_blog;

	if ( is_front_page() || ( is_home() && bp_is_page( 'home' ) ) ) {
		$title = __( 'Home', 'buddypress' );

	} else if ( bp_is_blog_page() ) {
		if ( is_single() ) {
			$title = __( 'Blog &#124; ' . $post->post_title, 'buddypress' );
		} else if ( is_category() ) {
			$title = __( 'Blog &#124; Categories &#124; ' . ucwords( $wp_query->query_vars['category_name'] ), 'buddypress' );
		} else if ( is_tag() ) {
			$title = __( 'Blog &#124; Tags &#124; ' . ucwords( $wp_query->query_vars['tag'] ), 'buddypress' );
		} else if ( is_page() ){
			$title = $post->post_title;
		} else
			$title = __( 'Blog', 'buddypress' );

	} else if ( !empty( $bp->displayed_user->fullname ) ) {
 		$title = strip_tags( $bp->displayed_user->fullname . ' &#124; ' . ucwords( $bp->current_component ) );

	} else if ( !empty( $bp->is_single_item ) ) {
		$title = ucwords( $bp->current_component ) . ' &#124; ' . $bp->bp_options_title . ' &#124; ' . $bp->bp_options_nav[$bp->current_component][$bp->current_action]['name'];

	} else if ( !empty( $bp->is_directory ) ) {
		if ( !$bp->current_component )
			$title = sprintf( __( '%s Directory', 'buddypress' ), ucwords( BP_MEMBERS_SLUG ) );
		else
			$title = sprintf( __( '%s Directory', 'buddypress' ), ucwords( $bp->current_component ) );

	} else if ( bp_is_register_page() ) {
		$title = __( 'Create an Account', 'buddypress' );

	} else if ( bp_is_activation_page() ) {
		$title = __( 'Activate your Account', 'buddypress' );

	} else if ( bp_is_group_create() ) {
		$title = __( 'Create a Group', 'buddypress' );

	} else if ( bp_is_create_blog() ) {
		$title = __( 'Create a Blog', 'buddypress' );
	}

	return apply_filters( 'bp_page_title', esc_attr( get_bloginfo( 'name', 'display' ) . ' &#124; ' . $title ), esc_attr( $title ) );
}

function bp_styles() {
	do_action( 'bp_styles' );
	wp_print_styles();
}

function bp_has_custom_signup_page() {
	if ( locate_template( array( 'register.php' ), false ) || locate_template( array( '/registration/register.php' ), false ) )
		return true;

	return false;
}

function bp_signup_page() {
	echo bp_get_signup_page();
}
	function bp_get_signup_page() {
		global $bp;

		if ( bp_has_custom_signup_page() )
			$page = $bp->root_domain . '/' . BP_REGISTER_SLUG;
		else
			$page = $bp->root_domain . '/wp-signup.php';

		return apply_filters( 'bp_get_signup_page', $page );
	}

function bp_has_custom_activation_page() {
	if ( locate_template( array( 'activate.php' ), false ) || locate_template( array( '/registration/activate.php' ), false ) )
		return true;

	return false;
}

function bp_activation_page() {
	echo bp_get_activation_page();
}
	function bp_get_activation_page() {
		global $bp;

		if ( bp_has_custom_activation_page() )
			$page = trailingslashit( $bp->root_domain ) . BP_ACTIVATION_SLUG;
		else
			$page = trailingslashit( $bp->root_domain ) . 'wp-activate.php';

		return apply_filters( 'bp_get_activation_page', $page );
	}

/**
 * bp_search_form_available()
 *
 * Only show the search form if there are available objects to search for.
 *
 * @global array $bp
 * @uses function_exists
 * @uses is_multisite()
 * @return bool Filterable result
 */
function bp_search_form_enabled() {
	global $bp;

	if ( bp_is_active( 'xprofile' )
		 || bp_is_active( 'groups' )
		 || ( bp_is_active( 'blogs' ) && is_multisite() )
		 || ( function_exists( 'bp_forums_setup' ) && !(int)$bp->site_options['bp-disable-forum-directory'] )
		) {
		$search_enabled = true;
	} else {
		$search_enabled = false;
	}

	return apply_filters( 'bp_search_form_enabled', $search_enabled );
}

function bp_search_form_action() {
	global $bp;

	return apply_filters( 'bp_search_form_action', $bp->root_domain . '/' . BP_SEARCH_SLUG );
}

/**
 * Generates the basic search form as used in BP-Default's header.
 *
 * @global object $bp BuddyPress global settings
 * @return string HTML <select> element
 * @since 1.0
 */
function bp_search_form_type_select() {
	global $bp;

	$options = array();

	if ( bp_is_active( 'xprofile' ) )
		$options['members'] = __( 'Members', 'buddypress' );

	if ( bp_is_active( 'groups' ) )
		$options['groups'] = __( 'Groups', 'buddypress' );

	if ( function_exists( 'bp_forums_is_installed_correctly' ) && bp_forums_is_installed_correctly() && !isset( $bp->site_options['bp-disable-forum-directory'] ) )
		$options['forums'] = __( 'Forums', 'buddypress' );

	if ( bp_is_active( 'blogs' ) && is_multisite() )
		$options['blogs'] = __( 'Blogs', 'buddypress' );

	// Eventually this won't be needed and a page will be built to integrate all search results.
	$selection_box = '<select name="search-which" id="search-which" style="width: auto">';

	$options = apply_filters( 'bp_search_form_type_select_options', $options );
	foreach( (array)$options as $option_value => $option_title )
		$selection_box .= sprintf( '<option value="%s">%s</option>', $option_value, $option_title );

	$selection_box .= '</select>';

	return apply_filters( 'bp_search_form_type_select', $selection_box );
}

function bp_search_form() {
	$form = '
		<form action="' . bp_search_form_action() . '" method="post" id="search-form">
			<input type="text" id="search-terms" name="search-terms" value="" />
			' . bp_search_form_type_select() . '

			<input type="submit" name="search-submit" id="search-submit" value="' . __( 'Search', 'buddypress' ) . '" />
			' . wp_nonce_field( 'bp_search_form' ) . '
		</form>
	';

	echo apply_filters( 'bp_search_form', $form );
}

function bp_log_out_link() {
	global $bp;
	if ( function_exists('wp_logout_url') )
		$logout_link = '<a href="' . wp_logout_url( $bp->root_domain ) . '">' . __( 'Log Out', 'buddypress' ) . '</a>';
	else
		$logout_link = '<a href="' . $bp->root_domain . '/wp-login.php?action=logout&amp;redirect_to=' . $bp->root_domain . '">' . __( 'Log Out', 'buddypress' ) . '</a>';

	echo apply_filters( 'bp_logout_link', $logout_link );
}

function bp_custom_profile_boxes() {
	do_action( 'bp_custom_profile_boxes' );
}

function bp_custom_profile_sidebar_boxes() {
	do_action( 'bp_custom_profile_sidebar_boxes' );
}

/**
 * bp_button( $button )
 *
 * Creates and outputs a button.
 * Args: div_id | div_class | a_href | a_title | a_id | a_class | a_rel | a_text
 *
 * @param array $button
 */
function bp_button( $button = '' ) {
	echo bp_get_button( $button );
}
	/**
	 * bp_get_button( $button )
	 *
	 * Creates and returns a button.
	 * Args: div_id | div_class | a_href | a_title | a_id | a_class | a_rel | a_text
	 *
	 * @param array $button
	 * @return string
	 */
	function bp_get_button( $button = '' ) {
		$btn = new BP_Button( $button );
		return apply_filters( 'bp_get_button', $btn->contents, $button );
	}

/**
 * bp_create_excerpt()
 *
 * Fakes an excerpt on any content. Will not truncate words.
 *
 * @package BuddyPress Core
 * @param $text str The text to create the excerpt from
 * @uses $excerpt_length The maximum length in characters of the excerpt.
 * @return str The excerpt text
 */
function bp_create_excerpt( $text, $excerpt_length = 225, $filter_shortcodes = true ) { // Fakes an excerpt if needed
	$original_text = $text;
	$text = str_replace(']]>', ']]&gt;', $text);

	if ( $filter_shortcodes )
		$text = preg_replace( '|\[(.+?)\](.+?\[/\\1\])?|s', '', $text );

	preg_match( "%\s*((?:<[^>]+>)+\S*)\s*|\s+%s", $text, $matches, PREG_OFFSET_CAPTURE, $excerpt_length );
	
	if ( !empty( $matches ) ) {
		$pos = array_pop( array_pop( $matches ) );	
		$text = substr( $text, 0, $pos ) . ' [...]';
	}
	
	return apply_filters( 'bp_create_excerpt', $text, $original_text );
}
add_filter( 'bp_create_excerpt', 'wp_trim_excerpt' );
add_filter( 'bp_create_excerpt', 'stripslashes_deep' );
add_filter( 'bp_create_excerpt', 'force_balance_tags' );

function bp_total_member_count() {
	echo bp_get_total_member_count();
}
	function bp_get_total_member_count() {
		return apply_filters( 'bp_get_total_member_count', bp_core_get_total_member_count() );
	}
	add_filter( 'bp_get_total_member_count', 'bp_core_number_format' );

/*** Signup form template tags **********************/

function bp_signup_username_value() {
	echo bp_get_signup_username_value();
}
	function bp_get_signup_username_value() {
		$value = '';
		if ( isset( $_POST['signup_username'] ) )
			$value = $_POST['signup_username'];

		return apply_filters( 'bp_get_signup_username_value', $value );
	}

function bp_signup_email_value() {
	echo bp_get_signup_email_value();
}
	function bp_get_signup_email_value() {
		$value = '';
		if ( isset( $_POST['signup_email'] ) )
			$value = $_POST['signup_email'];

		return apply_filters( 'bp_get_signup_email_value', $value );
	}

function bp_signup_with_blog_value() {
	echo bp_get_signup_with_blog_value();
}
	function bp_get_signup_with_blog_value() {
		$value = '';
		if ( isset( $_POST['signup_with_blog'] ) )
			$value = $_POST['signup_with_blog'];

		return apply_filters( 'bp_get_signup_with_blog_value', $value );
	}

function bp_signup_blog_url_value() {
	echo bp_get_signup_blog_url_value();
}
	function bp_get_signup_blog_url_value() {
		$value = '';
		if ( isset( $_POST['signup_blog_url'] ) )
			$value = $_POST['signup_blog_url'];

		return apply_filters( 'bp_get_signup_blog_url_value', $value );
	}

function bp_signup_blog_title_value() {
	echo bp_get_signup_blog_title_value();
}
	function bp_get_signup_blog_title_value() {
		$value = '';
		if ( isset( $_POST['signup_blog_title'] ) )
			$value = $_POST['signup_blog_title'];

		return apply_filters( 'bp_get_signup_blog_title_value', $value );
	}

function bp_signup_blog_privacy_value() {
	echo bp_get_signup_blog_privacy_value();
}
	function bp_get_signup_blog_privacy_value() {
		$value = '';
		if ( isset( $_POST['signup_blog_privacy'] ) )
			$value = $_POST['signup_blog_privacy'];

		return apply_filters( 'bp_get_signup_blog_privacy_value', $value );
	}

function bp_signup_avatar_dir_value() {
	echo bp_get_signup_avatar_dir_value();
}
	function bp_get_signup_avatar_dir_value() {
		global $bp;

		return apply_filters( 'bp_get_signup_avatar_dir_value', $bp->signup->avatar_dir );
	}

function bp_current_signup_step() {
	echo bp_get_current_signup_step();
}
	function bp_get_current_signup_step() {
		global $bp;

		return $bp->signup->step;
	}

function bp_signup_avatar( $args = '' ) {
	echo bp_get_signup_avatar( $args );
}
	function bp_get_signup_avatar( $args = '' ) {
		global $bp;

		$defaults = array(
			'size' => BP_AVATAR_FULL_WIDTH,
			'class' => 'avatar',
			'alt' => __( 'Your Avatar', 'buddypress' )
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		$signup_avatar_dir = ( !empty( $_POST['signup_avatar_dir'] ) ) ? $_POST['signup_avatar_dir'] : $bp->signup->avatar_dir;

		if ( empty( $signup_avatar_dir ) ) {
			if ( empty( $bp->grav_default->user ) ) {
				$default_grav = 'wavatar';
			} else if ( 'mystery' == $bp->grav_default->user ) {
				$default_grav = apply_filters( 'bp_core_mysteryman_src', BP_PLUGIN_URL . '/bp-core/images/mystery-man.jpg' );
			} else {
				$default_grav = $bp->grav_default->user;
			}

			$gravatar_url = apply_filters( 'bp_gravatar_url', 'http://www.gravatar.com/avatar/' );
			$gravatar_img = '<img src="' . $gravatar_url . md5( strtolower( $_POST['signup_email'] ) ) . '?d=' . $default_grav . '&amp;s=' . $size . '" width="' . $size . '" height="' . $size . '" alt="' . $alt . '" class="' . $class . '" />';
		} else {
			$gravatar_img = bp_core_fetch_avatar( array( 'item_id' => $signup_avatar_dir, 'object' => 'signup', 'avatar_dir' => 'avatars/signups', 'type' => 'full', 'width' => $size, 'height' => $size, 'alt' => $alt, 'class' => $class ) );
		}

		return apply_filters( 'bp_get_signup_avatar', $gravatar_img );
	}

function bp_signup_allowed() {
	echo bp_get_signup_allowed();
}
	function bp_get_signup_allowed() {
		global $bp;

		if ( is_multisite() ) {
			if ( in_array( $bp->site_options['registration'], array( 'all', 'user' ) ) )
				return true;
		} else {
			if ( (int)get_option( 'users_can_register') )
				return true;
		}
		return false;
	}

function bp_blog_signup_allowed() {
	echo bp_get_blog_signup_allowed();
}
	function bp_get_blog_signup_allowed() {
		global $bp;

		if ( !is_multisite() )
			return false;

		$status = $bp->site_options['registration'];
		if ( 'none' != $status && 'user' != $status )
			return true;

		return false;
	}

function bp_account_was_activated() {
	global $bp;

	$activation_complete = !empty( $bp->activation_complete ) ? $bp->activation_complete : false;

	return $activation_complete;
}

function bp_registration_needs_activation() {
	return apply_filters( 'bp_registration_needs_activation', true );
}

function bp_mentioned_user_display_name( $user_id_or_username ) {
	echo bp_get_mentioned_user_display_name( $user_id_or_username );
}
	function bp_get_mentioned_user_display_name( $user_id_or_username ) {
		if ( !$name = bp_core_get_user_displayname( $user_id_or_username ) )
			$name = __( 'a user' );

		return apply_filters( 'bp_get_mentioned_user_display_name', $name, $user_id_or_username );
	}

function bp_get_option( $option_name ) {
	global $bp;

	return apply_filters( 'bp_get_option', $bp->site_options[$option_name] );
}

/**
 * Allow templates to pass parameters directly into the template loops via AJAX
 *
 * For the most part this will be filtered in a theme's functions.php for example
 * in the default theme it is filtered via bp_dtheme_ajax_querystring()
 *
 * By using this template tag in the templates it will stop them from showing errors
 * if someone copies the templates from the default theme into another WordPress theme
 * without coping the functions from functions.php.
 */
function bp_ajax_querystring( $object = false ) {
	global $bp;

	if ( !isset( $bp->ajax_querystring ) )
		$bp->ajax_querystring = '';

	return apply_filters( 'bp_ajax_querystring', $bp->ajax_querystring, $object );
}


/*** CUSTOM LOOP TEMPLATE CLASSES *******************/


/* Template functions for fetching globals, without querying the DB again
   also means we dont have to use the $bp variable in the template (looks messy) */

function bp_last_activity( $user_id = false, $echo = true ) {
	global $bp;

	if ( !$user_id )
		$user_id = $bp->displayed_user->id;

	$last_activity = bp_core_get_last_activity( get_user_meta( $user_id, 'last_activity', true ), __('active %s ago', 'buddypress') );

	if ( $echo )
		echo apply_filters( 'bp_last_activity', $last_activity );
	else
		return apply_filters( 'bp_last_activity', $last_activity );
}

function bp_user_has_access() {
	global $bp;

	if ( is_super_admin() || is_user_logged_in() && $bp->loggedin_user->id == $bp->displayed_user->id )
		$has_access = true;
	else
		$has_access = false;

	return apply_filters( 'bp_user_has_access', $has_access );
}

function bp_user_firstname() {
	echo bp_get_user_firstname();
}
	function bp_get_user_firstname( $name = false ) {
		global $bp;

		// Try to get displayed user
		if ( empty( $name ) )
			$name = $bp->displayed_user->fullname;

		// Fall back on logged in user
		if ( empty( $name ) )
			$name = $bp->loggedin_user->fullname;

		$fullname = (array)explode( ' ', $name );

		return apply_filters( 'bp_get_user_firstname', $fullname[0], $fullname );
	}

function bp_loggedin_user_link() {
	echo bp_get_loggedin_user_link();
}
	function bp_get_loggedin_user_link() {
		global $bp;

		return apply_filters( 'bp_get_loggedin_user_link', $bp->loggedin_user->domain );
	}

/* @todo Deprecate incorrectly named function? */
function bp_loggedinuser_link() {
	global $bp;

	if ( $link = bp_core_get_userlink( $bp->loggedin_user->id ) )
		echo apply_filters( 'bp_loggedin_user_link', $link );
}

function bp_displayed_user_link() {
	echo bp_get_displayed_user_link();
}
	function bp_get_displayed_user_link() {
		global $bp;

		return apply_filters( 'bp_get_displayed_user_link', $bp->displayed_user->domain );
	}
	function bp_user_link() { bp_displayed_user_link(); } // Deprecated.

function bp_displayed_user_id() {
	global $bp;
	return apply_filters( 'bp_displayed_user_id', $bp->displayed_user->id );
}
	function bp_current_user_id() { return bp_displayed_user_id(); }

function bp_loggedin_user_id() {
	global $bp;
	return apply_filters( 'bp_loggedin_user_id', $bp->loggedin_user->id );
}

function bp_displayed_user_domain() {
	global $bp;
	return apply_filters( 'bp_displayed_user_domain', $bp->displayed_user->domain );
}

function bp_loggedin_user_domain() {
	global $bp;
	return apply_filters( 'bp_loggedin_user_domain', $bp->loggedin_user->domain );
}

function bp_displayed_user_fullname() {
	echo bp_get_displayed_user_fullname();
}
	function bp_get_displayed_user_fullname() {
		global $bp;

		return apply_filters( 'bp_displayed_user_fullname', $bp->displayed_user->fullname );
	}
	function bp_user_fullname() { echo bp_get_displayed_user_fullname(); }


function bp_loggedin_user_fullname() {
	echo bp_get_loggedin_user_fullname();
}
	function bp_get_loggedin_user_fullname() {
		global $bp;
		return apply_filters( 'bp_get_loggedin_user_fullname', $bp->loggedin_user->fullname );
	}

function bp_displayed_user_username() {
	echo bp_get_displayed_user_username();
}
	function bp_get_displayed_user_username() {
		global $bp;
		return apply_filters( 'bp_get_displayed_user_username', bp_core_get_username( $bp->displayed_user->id, $bp->displayed_user->userdata->user_nicename, $bp->displayed_user->userdata->user_login ) );
	}

function bp_loggedin_user_username() {
	echo bp_get_loggedin_user_username();
}
	function bp_get_loggedin_user_username() {
		global $bp;
		return apply_filters( 'bp_get_loggedin_user_username', bp_core_get_username( $bp->loggedin_user->id, $bp->loggedin_user->userdata->user_nicename, $bp->loggedin_user->userdata->user_login ) );
	}

function bp_current_component() {
	global $bp;
	return apply_filters( 'bp_current_component', $bp->current_component );
}

function bp_current_action() {
	global $bp;
	return apply_filters( 'bp_current_action', $bp->current_action );
}

function bp_current_item() {
	global $bp;
	return apply_filters( 'bp_current_item', $bp->current_item );
}

function bp_action_variables() {
	global $bp;
	return apply_filters( 'bp_action_variables', $bp->action_variables );
}

function bp_root_domain() {
	echo bp_get_root_domain();
}
	function bp_get_root_domain() {
		global $bp;

		return apply_filters( 'bp_get_root_domain', $bp->root_domain );
	}

/* Template is_() functions to determine the current page */

/**
 * Checks if the site's front page is set to the specified BuddyPress component page in wp-admin's Settings > Reading screen.
 *
 * @global $bp The global BuddyPress settings variable created in bp_core_setup_globals()
 * @global $current_blog WordPress global containing information and settings for the current blog being viewed.
 * @param string $component Optional; name of the component to check for. If not specified, uses $bp->current_component.
 * @return bool True if the specified component is set to be the site's front page.
 * @since 1.3
 */
function bp_is_component_front_page( $component='' ) {
	global $bp, $current_blog;

	if ( !$component && !empty( $bp->current_component ) )
		$component = $bp->current_component;

	if ( is_main_site() )
		$path = bp_core_get_site_path();
	else
		$path = $current_blog->path;

	if ( 'page' != get_option( 'show_on_front' ) || !$component || empty( $bp->pages->{$component} ) || $_SERVER['REQUEST_URI'] != $path )
		return false;

	return apply_filters( 'bp_is_component_front_page', ( $bp->pages->{$component}->id == get_option( 'page_on_front' ) ), $component );
}

function bp_is_blog_page() {
	global $bp, $is_member_page, $wp_query;

	if ( $wp_query->is_home && ( !isset( $bp->is_directory ) || !$bp->is_directory ) )
		return true;

	if ( !$bp->displayed_user->id && !$bp->is_single_item && ( !isset( $bp->is_directory ) || !$bp->is_directory ) && !bp_core_is_root_component( $bp->current_component ) )
		return true;

	return false;
}

function bp_is_my_profile() {
	global $bp;

	if ( is_user_logged_in() && $bp->loggedin_user->id == $bp->displayed_user->id )
		$my_profile = true;
	else
		$my_profile = false;

	return apply_filters( 'bp_is_my_profile', $my_profile );
}
function bp_is_home() { return bp_is_my_profile(); }

/**
 * Is the user on the front page of the site?
 *
 * @deprecated 1.3
 * @deprecated Use is_front_page()
 * @return bool
 */
function bp_is_front_page() {
	_deprecated_function( __FUNCTION__, '1.3', "is_front_page()" );
	return is_front_page();
}

/**
 * Is the front page of the site set to the Activity component?
 *
 * @deprecated 1.3
 * @deprecated Use bp_is_component_front_page( 'activity' )
 * @return bool
 */
function bp_is_activity_front_page() {
	_deprecated_function( __FUNCTION__, '1.3', "bp_is_component_front_page( 'activity' )" );
	return bp_is_component_front_page( 'activity' );
}

function bp_is_directory() {
	global $bp;

	return ( isset( $bp->is_directory ) && $bp->is_directory );
}

function bp_is_page($page) {
	global $bp;

	if ( !$bp->displayed_user->id && $bp->current_component == $page )
		return true;

	if ( 'home' == $page )
		return bp_is_front_page();

	return false;
}

function bp_is_active( $component ) {
	global $bp_deactivated;

	if ( !isset( $bp_deactivated[ 'bp-' . $component . '.php' ] ) )
		return true;

	return false;
}

function bp_is_profile_component() {
	global $bp;

	if ( BP_XPROFILE_SLUG == $bp->current_component )
		return true;

	return false;
}

function bp_is_activity_component() {
	global $bp;

	if ( BP_ACTIVITY_SLUG == $bp->current_component )
		return true;

	return false;
}

function bp_is_blogs_component() {
	global $bp;

	if ( is_multisite() && BP_BLOGS_SLUG == $bp->current_component )
		return true;

	return false;
}

function bp_is_messages_component() {
	global $bp;

	if ( BP_MESSAGES_SLUG == $bp->current_component )
		return true;

	return false;
}

function bp_is_friends_component() {
	global $bp;

	if ( BP_FRIENDS_SLUG == $bp->current_component )
		return true;

	return false;
}

function bp_is_groups_component() {
	global $bp;

	if ( BP_GROUPS_SLUG == $bp->current_component )
		return true;

	return false;
}

function bp_is_settings_component() {
	global $bp;

	if ( BP_SETTINGS_SLUG == $bp->current_component )
		return true;

	return false;
}

function bp_is_member() {
	global $bp;

	if ( $bp->displayed_user->id )
		return true;

	return false;
}

function bp_is_user_activity() {
	global $bp;

	if ( $bp->activity->slug == $bp->current_component )
		return true;

	return false;
}

function bp_is_user_friends_activity() {
	global $bp;

	if ( $bp->activity->slug == $bp->current_component && 'my-friends' == $bp->current_action )
		return true;

	return false;
}

function bp_is_activity_permalink() {
	global $bp;

	if ( BP_ACTIVITY_SLUG == $bp->current_component && is_numeric( $bp->current_action ) )
		return true;

	return false;
}

function bp_is_user_profile() {
	global $bp;

	if ( defined( 'BP_XPROFILE_SLUG' ) && BP_XPROFILE_SLUG == $bp->current_component || isset( $bp->core->profile->slug ) && $bp->core->profile->slug == $bp->current_component )
		return true;

	return false;
}

function bp_is_profile_edit() {
	global $bp;

	if ( BP_XPROFILE_SLUG == $bp->current_component && 'edit' == $bp->current_action )
		return true;

	return false;
}

function bp_is_change_avatar() {
	global $bp;

	if ( BP_XPROFILE_SLUG == $bp->current_component && 'change-avatar' == $bp->current_action )
		return true;

	return false;
}

function bp_is_user_groups() {
	global $bp;

	if ( $bp->groups->slug == $bp->current_component )
		return true;

	return false;
}

function bp_is_group() {
	global $bp;

	if ( BP_GROUPS_SLUG == $bp->current_component && isset( $bp->groups->current_group ) && $bp->groups->current_group )
		return true;

	return false;
}

function bp_is_group_home() {
	global $bp;

	if ( BP_GROUPS_SLUG == $bp->current_component && $bp->is_single_item && ( !$bp->current_action || 'home' == $bp->current_action ) )
		return true;

	return false;
}

function bp_is_group_create() {
	global $bp;

	if ( BP_GROUPS_SLUG == $bp->current_component && 'create' == $bp->current_action )
		return true;

	return false;
}


function bp_is_group_admin_page() {
	global $bp;

	if ( BP_GROUPS_SLUG == $bp->current_component && $bp->is_single_item && 'admin' == $bp->current_action )
		return true;

	return false;
}

function bp_is_group_forum() {
	global $bp;

	if ( BP_GROUPS_SLUG == $bp->current_component && $bp->is_single_item && 'forum' == $bp->current_action )
		return true;

	return false;
}

function bp_is_group_activity() {
	global $bp;

	if ( BP_GROUPS_SLUG == $bp->current_component && $bp->is_single_item && 'activity' == $bp->current_action )
		return true;

	return false;
}

function bp_is_group_forum_topic() {
	global $bp;

	if ( BP_GROUPS_SLUG == $bp->current_component && $bp->is_single_item && 'forum' == $bp->current_action && isset( $bp->action_variables[0] ) && 'topic' == $bp->action_variables[0] )
		return true;

	return false;
}

function bp_is_group_forum_topic_edit() {
	global $bp;

	if ( BP_GROUPS_SLUG == $bp->current_component && $bp->is_single_item && 'forum' == $bp->current_action && isset( $bp->action_variables[0] ) && 'topic' == $bp->action_variables[0] && isset( $bp->action_variables[2] ) && 'edit' == $bp->action_variables[2] )
		return true;

	return false;
}

function bp_is_group_members() {
	global $bp;

	if ( BP_GROUPS_SLUG == $bp->current_component && $bp->is_single_item && 'members' == $bp->current_action )
		return true;

	return false;
}

function bp_is_group_invites() {
	global $bp;

	if ( BP_GROUPS_SLUG == $bp->current_component && 'send-invites' == $bp->current_action )
		return true;

	return false;
}

function bp_is_group_membership_request() {
	global $bp;

	if ( BP_GROUPS_SLUG == $bp->current_component && 'request-membership' == $bp->current_action )
		return true;

	return false;
}

function bp_is_group_leave() {
	global $bp;

	if ( BP_GROUPS_SLUG == $bp->current_component && $bp->is_single_item && 'leave-group' == $bp->current_action )
		return true;

	return false;
}

function bp_is_group_single() {
	global $bp;

	if ( BP_GROUPS_SLUG == $bp->current_component && $bp->is_single_item )
		return true;

	return false;
}

function bp_is_user_blogs() {
	global $bp;

	if ( is_multisite() && BP_BLOGS_SLUG == $bp->current_component )
		return true;

	return false;
}

function bp_is_user_recent_posts() {
	global $bp;

	if ( is_multisite() && BP_BLOGS_SLUG == $bp->current_component && 'recent-posts' == $bp->current_action )
		return true;

	return false;
}

function bp_is_user_recent_commments() {
	global $bp;

	if ( is_multisite() && BP_BLOGS_SLUG == $bp->current_component && 'recent-comments' == $bp->current_action )
		return true;

	return false;
}

function bp_is_create_blog() {
	global $bp;

	if ( is_multisite() && BP_BLOGS_SLUG == $bp->current_component && 'create' == $bp->current_action )
		return true;

	return false;
}

function bp_is_user_friends() {
	global $bp;

	if ( BP_FRIENDS_SLUG == $bp->current_component )
		return true;

	return false;
}

function bp_is_friend_requests() {
	global $bp;

	if ( BP_FRIENDS_SLUG == $bp->current_component && 'requests' == $bp->current_action )
		return true;

	return false;
}

function bp_is_user_messages() {
	global $bp;

	if ( BP_MESSAGES_SLUG == $bp->current_component )
		return true;

	return false;
}

function bp_is_messages_inbox() {
	global $bp;

	if ( BP_MESSAGES_SLUG == $bp->current_component && ( !$bp->current_action || 'inbox' == $bp->current_action ) )
		return true;

	return false;
}

function bp_is_messages_sentbox() {
	global $bp;

	if ( BP_MESSAGES_SLUG == $bp->current_component && 'sentbox' == $bp->current_action )
		return true;

	return false;
}


function bp_is_notices() {
	global $bp;

	if ( BP_MESSAGES_SLUG == $bp->current_component && 'notices' == $bp->current_action )
		return true;

	return false;
}


function bp_is_messages_compose_screen() {
	global $bp;

	if ( BP_MESSAGES_SLUG == $bp->current_component && 'compose' == $bp->current_action )
		return true;

	return false;
}

function bp_is_single_item() {
	global $bp;

	if ( $bp->is_single_item )
		return true;

	return false;
}

function bp_is_activation_page() {
	global $bp;

	if ( BP_ACTIVATION_SLUG == $bp->current_component )
		return true;

	return false;
}

function bp_is_register_page() {
	global $bp;

	if ( BP_REGISTER_SLUG == $bp->current_component )
		return true;

	return false;
}

/* Use the above is_() functions to output a body class for each page */

function bp_the_body_class() {
	echo bp_get_the_body_class();
}
	function bp_get_the_body_class( $wp_classes, $custom_classes = false ) {
		global $bp;

		$bp_classes = array();

		if ( is_front_page() )
			$bp_classes[] = 'home-page';

		if ( bp_is_directory() )
			$bp_classes[] = 'directory';

		if ( bp_is_user_profile() && !bp_is_blog_page() )
			$bp_classes[] = 'profile';

		if ( bp_is_activity_component() && !bp_is_blog_page() )
			$bp_classes[] = 'activity';

		if ( bp_is_blogs_component() && !bp_is_blog_page() )
			$bp_classes[] = 'blogs';

		if ( bp_is_messages_component() && !bp_is_blog_page()  )
			$bp_classes[] = 'messages';

		if ( bp_is_friends_component() && !bp_is_blog_page() )
			$bp_classes[] = 'friends';

		if ( bp_is_groups_component() && !bp_is_blog_page() )
			$bp_classes[] = 'groups';

		if ( bp_is_settings_component() && !bp_is_blog_page()  )
			$bp_classes[] = 'settings';

		if ( bp_is_single_item() )
			$bp_classes[] = 'single-item';

		if ( bp_is_messages_inbox() )
			$bp_classes[] = 'inbox';

		if ( bp_is_messages_sentbox() )
			$bp_classes[] = 'sentbox';

		if ( bp_is_messages_compose_screen() )
			$bp_classes[] = 'compose';

		if ( bp_is_notices() )
			$bp_classes[] = 'notices';

		if ( bp_is_friend_requests() )
			$bp_classes[] = 'friend-requests';

		if ( bp_is_user_friends() )
			$bp_classes[] = 'my-friends';

		if ( bp_is_create_blog() )
			$bp_classes[] = 'create-blog';

		if ( bp_is_user_recent_commments() )
			$bp_classes[] = 'recent-comments';

		if ( bp_is_user_recent_posts() )
			$bp_classes[] = 'recent-posts';

		if ( bp_is_user_blogs() && !bp_is_directory() )
			$bp_classes[] = 'my-blogs';

		if ( bp_is_user_groups() && !bp_is_directory() )
			$bp_classes[] = 'my-groups';

		if ( bp_is_group_leave() )
			$bp_classes[] = 'leave-group';

		if ( bp_is_group_invites() )
			$bp_classes[] = 'group-invites';

		if ( bp_is_group_members() )
			$bp_classes[] = 'group-members';

		if ( bp_is_group_forum_topic() )
			$bp_classes[] = 'group-forum-topic';

		if ( bp_is_group_forum_topic_edit() )
			$bp_classes[] = 'group-forum-topic-edit';

		if ( bp_is_group_forum() )
			$bp_classes[] = 'group-forum';

		if ( bp_is_group_admin_page() )
			$bp_classes[] = 'group-admin';

		if ( bp_is_group_create() )
			$bp_classes[] = 'group-create';

		if ( bp_is_group_home() )
			$bp_classes[] = 'group-home';

		if ( bp_is_change_avatar() )
			$bp_classes[] = 'change-avatar';

		if ( bp_is_profile_edit() )
			$bp_classes[] = 'profile-edit';

		if ( bp_is_user_friends_activity() )
			$bp_classes[] = 'friends-activity';

		if ( bp_is_user_activity() && !bp_is_directory() )
			$bp_classes[] = 'my-activity';

		if ( bp_is_activity_permalink() )
			$bp_classes[] = 'activity-permalink';

		if ( bp_is_register_page() )
			$bp_classes[] = 'registration';

		if ( bp_is_activation_page() )
			$bp_classes[] = 'activation';

		if ( is_user_logged_in() )
			$bp_classes[] = 'logged-in';

		/* Add the current_component, current_action into the bp classes */
		if ( !bp_is_blog_page() ) {
			if ( !empty( $bp->current_component ) )
				$bp_classes[] = $bp->current_component;

			if ( !empty( $bp->current_action ) )
				$bp_classes[] = $bp->current_action;
		}

		/* We don't want WordPress blog classes to appear on non-blog pages. */
		if ( !bp_is_blog_page() || is_home() ) {
			/* Preserve any custom classes already set */
			if ( !empty( $custom_classes ) )
				$wp_classes = (array) $custom_classes;
			else
				$wp_classes = array();
		}

		/* Merge WP classes with BP classes */
		$classes = array_merge( (array) $bp_classes, (array) $wp_classes );

		/* Remove any duplicates */
		$classes = array_unique( $classes );

		return apply_filters( 'bp_get_the_body_class', $classes, $bp_classes, $wp_classes, $custom_classes );
	}
	add_filter( 'body_class', 'bp_get_the_body_class', 10, 2 )


?>