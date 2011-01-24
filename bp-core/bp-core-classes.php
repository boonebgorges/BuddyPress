<?php
/**
 * BP_Core_User class can be used by any component. It will fetch useful
 * details for any user when provided with a user_id.
 *
 * Example:
 *    $user = new BP_Core_User( $user_id );
 *    $user_avatar = $user->avatar;
 *	  $user_email = $user->email;
 *    $user_status = $user->status;
 *    etc.
 *
 * @package BuddyPress Core
 */
class BP_Core_User {
	var $id;
	var $avatar;
	var $avatar_thumb;
	var $avatar_mini;
	var $fullname;
	var $email;

	var $user_url;
	var $user_link;

	var $last_active;

	/* Extras */
	var $total_friends;
	var $total_blogs;
	var $total_groups;

	function bp_core_user( $user_id, $populate_extras = false ) {
		if ( $user_id ) {
			$this->id = $user_id;
			$this->populate();

			if ( $populate_extras )
				$this->populate_extras();
		}
	}

	/**
	 * populate()
	 *
	 * Populate the instantiated class with data based on the User ID provided.
	 *
	 * @package BuddyPress Core
 	 * @global $userdata WordPress user data for the current logged in user.
	 * @uses bp_core_get_userurl() Returns the URL with no HTML markup for a user based on their user id
	 * @uses bp_core_get_userlink() Returns a HTML formatted link for a user with the user's full name as the link text
	 * @uses bp_core_get_user_email() Returns the email address for the user based on user ID
	 * @uses get_user_meta() WordPress function returns the value of passed usermeta name from usermeta table
	 * @uses bp_core_fetch_avatar() Returns HTML formatted avatar for a user
	 * @uses bp_profile_last_updated_date() Returns the last updated date for a user.
	 */
	function populate() {
		if ( bp_is_active( 'profile' ) )
			$this->profile_data = $this->get_profile_data();

		if ( $this->profile_data ) {
			$this->user_url  = bp_core_get_user_domain( $this->id, $this->profile_data['user_nicename'], $this->profile_data['user_login'] );
			$this->fullname  = esc_attr( $this->profile_data[BP_XPROFILE_FULLNAME_FIELD_NAME]['field_data'] );
			$this->user_link = "<a href='{$this->user_url}' title='{$this->fullname}'>{$this->fullname}</a>";
			$this->email     = esc_attr( $this->profile_data['user_email'] );
		} else {
			$this->user_url  = bp_core_get_user_domain( $this->id );
			$this->user_link = bp_core_get_userlink( $this->id );
			$this->fullname  = esc_attr( bp_core_get_user_displayname( $this->id ) );
			$this->email     = esc_attr( bp_core_get_user_email( $this->id ) );
		}

		// Cache a few things that are fetched often
		wp_cache_set( 'bp_user_fullname_' . $this->id, $this->fullname, 'bp' );
		wp_cache_set( 'bp_user_email_' . $this->id, $this->email, 'bp' );
		wp_cache_set( 'bp_user_url_' . $this->id, $this->user_url, 'bp' );

		$this->avatar       = bp_core_fetch_avatar( array( 'item_id' => $this->id, 'type' => 'full'  ) );
		$this->avatar_thumb = bp_core_fetch_avatar( array( 'item_id' => $this->id, 'type' => 'thumb' ) );
		$this->avatar_mini  = bp_core_fetch_avatar( array( 'item_id' => $this->id, 'type' => 'thumb', 'width' => 30, 'height' => 30 ) );
		$this->last_active  = bp_core_get_last_activity( get_user_meta( $this->id, 'last_activity', true ), __( 'active %s ago', 'buddypress' ) );
	}

	function populate_extras() {
		global $bp;

		if ( bp_is_active( 'friends' ) )
			$this->total_friends = BP_Friends_Friendship::total_friend_count( $this->id );

		if ( bp_is_active( 'groups' ) ) {
			$this->total_groups = BP_Groups_Member::total_group_count( $this->id );

			if ( $this->total_groups ) {
				if ( 1 == $this->total_groups )
					$this->total_groups .= ' ' . __( 'group', 'buddypress' );
				else
					$this->total_groups .= ' ' . __( 'groups', 'buddypress' );
			}
		}
	}

	function get_profile_data() {
		return BP_XProfile_ProfileData::get_all_for_user( $this->id );
	}

	/** Static Functions ******************************************************/

	function get_users( $type, $limit = null, $page = 1, $user_id = 0, $include = false, $search_terms = false, $populate_extras = true, $exclude = false ) {
		global $wpdb, $bp;

		$sql = array();

		$sql['select_main'] = "SELECT DISTINCT u.ID as id, u.user_registered, u.user_nicename, u.user_login, u.display_name, u.user_email";

		if ( 'active' == $type || 'online' == $type )
			$sql['select_active'] = ", um.meta_value as last_activity";

		if ( 'newest' == $type )
			$sql['select_newest'] = ", um.meta_value as date_activated";

		if ( 'popular' == $type )
			$sql['select_popular'] = ", um.meta_value as total_friend_count";

		if ( 'alphabetical' == $type )
			$sql['select_alpha'] = ", pd.value as fullname";

		$sql['from'] = "FROM " . CUSTOM_USER_TABLE . " u LEFT JOIN " . CUSTOM_USER_META_TABLE . " um ON um.user_id = u.ID";

		if ( $search_terms && bp_is_active( 'profile' ) || 'alphabetical' == $type )
			$sql['join_profiledata'] = "LEFT JOIN {$bp->profile->table_name_data} pd ON u.ID = pd.user_id";

		$sql['where'] = 'WHERE ' . bp_core_get_status_sql( 'u.' );

		if ( 'active' == $type || 'online' == $type )
			$sql['where_active'] = "AND um.meta_key = 'last_activity'";

		if ( 'newest' == $type )
			$sql['where_newest'] = "AND um.meta_key = 'date_activated'";

		if ( 'popular' == $type )
			$sql['where_popular'] = "AND um.meta_key = 'total_friend_count'";

		if ( 'online' == $type )
			$sql['where_online'] = "AND DATE_ADD( um.meta_value, INTERVAL 5 MINUTE ) >= UTC_TIMESTAMP()";

		if ( 'alphabetical' == $type )
			$sql['where_alpha'] = "AND pd.field_id = 1";

		if ( !empty( $exclude ) )
			$sql['where_exclude'] = "AND u.ID NOT IN ({$exclude})";

		if ( $include ) {
			if ( is_array( $include ) )
				$uids = $wpdb->escape( implode( ',', (array)$include ) );
			else
				$uids = $wpdb->escape( $include );

			if ( !empty( $uids ) )
				$sql['where_users'] = "AND u.ID IN ({$uids})";
		}

		else if ( $user_id && bp_is_active( 'friends' ) ) {
			$friend_ids = friends_get_friend_user_ids( $user_id );
			$friend_ids = $wpdb->escape( implode( ',', (array)$friend_ids ) );

			if ( !empty( $friend_ids ) )
				$sql['where_friends'] = "AND u.ID IN ({$friend_ids})";

			// User has no friends, return false since there will be no users to fetch.
			else
				return false;

		}

		if ( $search_terms && bp_is_active( 'profile' ) ) {
			$search_terms             = like_escape( $wpdb->escape( $search_terms ) );
			$sql['where_searchterms'] = "AND pd.value LIKE '%%$search_terms%%'";
		}

		switch ( $type ) {
			case 'active': case 'online': default:
				$sql[] = "ORDER BY um.meta_value DESC";
				break;
			case 'newest':
				$sql[] = "ORDER BY um.meta_value DESC";
				break;
			case 'alphabetical':
				$sql[] = "ORDER BY pd.value ASC";
				break;
			case 'random':
				$sql[] = "ORDER BY rand()";
				break;
			case 'popular':
				$sql[] = "ORDER BY CONVERT(um.meta_value, SIGNED) DESC";
				break;
		}

		if ( $limit && $page )
			$sql['pagination'] = $wpdb->prepare( "LIMIT %d, %d", intval( ( $page - 1 ) * $limit), intval( $limit ) );

		// Get paginated results
		$paged_users_sql = apply_filters( 'bp_core_get_paged_users_sql', join( ' ', (array)$sql ), $sql );
		$paged_users     = $wpdb->get_results( $paged_users_sql );

		// Re-jig the SQL so we can get the total user count
		unset( $sql['select_main'] );

		if ( !empty( $sql['select_active'] ) )
			unset( $sql['select_active'] );

		if ( !empty( $sql['select_newest'] ) )
			unset( $sql['select_newest'] );

		if ( !empty( $sql['select_popular'] ) )
			unset( $sql['select_popular'] );

		if ( !empty( $sql['select_alpha'] ) )
			unset( $sql['select_alpha'] );

		if ( !empty( $sql['pagination'] ) )
			unset( $sql['pagination'] );

		array_unshift( $sql, "SELECT COUNT(DISTINCT u.ID)" );

		// Get total user results
		$total_users_sql = apply_filters( 'bp_core_get_total_users_sql', join( ' ', (array)$sql ), $sql );
		$total_users     = $wpdb->get_var( $total_users_sql );

		/***
		 * Lets fetch some other useful data in a separate queries, this will be faster than querying the data for every user in a list.
		 * We can't add these to the main query above since only users who have this information will be returned (since the much of the data is in usermeta and won't support any type of directional join)
		 */
		if ( $populate_extras ) {
			$user_ids = array();

			foreach ( (array)$paged_users as $user )
				$user_ids[] = $user->id;

			$user_ids = $wpdb->escape( join( ',', (array)$user_ids ) );

			// Add additional data to the returned results
			$paged_users = BP_Core_User::get_user_extras( $paged_users, $user_ids, $type );
		}

		return array( 'users' => $paged_users, 'total' => $total_users );
	}

	function get_users_by_letter( $letter, $limit = null, $page = 1, $populate_extras = true, $exclude = false ) {
		global $wpdb, $bp;

		$pag_sql = '';
		if ( $limit && $page )
			$pag_sql = $wpdb->prepare( " LIMIT %d, %d", intval( ( $page - 1 ) * $limit), intval( $limit ) );

		// Multibyte compliance
		if ( function_exists( 'mb_strlen' ) ) {
			if ( mb_strlen( $letter, 'UTF-8' ) > 1 || is_numeric( $letter ) || !$letter ) {
				return false;
			}
		} else {
			if ( strlen( $letter ) > 1 || is_numeric( $letter ) || !$letter ) {
				return false;
			}
		}

		$letter     = like_escape( $wpdb->escape( $letter ) );
		$status_sql = bp_core_get_status_sql( 'u.' );

		$exclude_sql = ( !empty( $exclude ) ) ? " AND u.ID NOT IN ({$exclude})" : "";

		$total_users_sql = apply_filters( 'bp_core_users_by_letter_count_sql', $wpdb->prepare( "SELECT COUNT(DISTINCT u.ID) FROM " . CUSTOM_USER_TABLE . " u LEFT JOIN {$bp->profile->table_name_data} pd ON u.ID = pd.user_id LEFT JOIN {$bp->profile->table_name_fields} pf ON pd.field_id = pf.id WHERE {$status_sql} AND pf.name = %s {$exclude_sql} AND pd.value LIKE '$letter%%'  ORDER BY pd.value ASC", BP_XPROFILE_FULLNAME_FIELD_NAME ), $letter );
		$paged_users_sql = apply_filters( 'bp_core_users_by_letter_sql', $wpdb->prepare( "SELECT DISTINCT u.ID as id, u.user_registered, u.user_nicename, u.user_login, u.user_email FROM " . CUSTOM_USER_TABLE . " u LEFT JOIN {$bp->profile->table_name_data} pd ON u.ID = pd.user_id LEFT JOIN {$bp->profile->table_name_fields} pf ON pd.field_id = pf.id WHERE {$status_sql} AND pf.name = %s {$exclude_sql} AND pd.value LIKE '$letter%%' ORDER BY pd.value ASC{$pag_sql}", BP_XPROFILE_FULLNAME_FIELD_NAME ), $letter, $pag_sql );

		$total_users = $wpdb->get_var( $total_users_sql );
		$paged_users = $wpdb->get_results( $paged_users_sql );

		/***
		 * Lets fetch some other useful data in a separate queries, this will be faster than querying the data for every user in a list.
		 * We can't add these to the main query above since only users who have this information will be returned (since the much of the data is in usermeta and won't support any type of directional join)
		 */
		$user_ids = array();
		foreach ( (array)$paged_users as $user )
			$user_ids[] = $user->id;

		$user_ids = $wpdb->escape( join( ',', (array)$user_ids ) );

		/* Add additional data to the returned results */
		if ( $populate_extras )
			$paged_users = BP_Core_User::get_user_extras( $paged_users, $user_ids );

		return array( 'users' => $paged_users, 'total' => $total_users );
	}

	function get_specific_users( $user_ids, $limit = null, $page = 1, $populate_extras = true ) {
		global $wpdb, $bp;

		$pag_sql = '';
		if ( $limit && $page )
			$pag_sql = $wpdb->prepare( " LIMIT %d, %d", intval( ( $page - 1 ) * $limit), intval( $limit ) );

		$user_sql   = " AND user_id IN ( " . $wpdb->escape( $user_ids ) . " ) ";
		$status_sql = bp_core_get_status_sql();

		$total_users_sql = apply_filters( 'bp_core_get_specific_users_count_sql', $wpdb->prepare( "SELECT COUNT(DISTINCT ID) FROM " . CUSTOM_USER_TABLE . " WHERE {$status_sql} AND ID IN ( " . $wpdb->escape( $user_ids ) . " ) " ), $wpdb->escape( $user_ids ) );
		$paged_users_sql = apply_filters( 'bp_core_get_specific_users_count_sql', $wpdb->prepare( "SELECT DISTINCT ID as id, user_registered, user_nicename, user_login, user_email FROM " . CUSTOM_USER_TABLE . " WHERE {$status_sql} AND ID IN ( " . $wpdb->escape( $user_ids ) . " ) {$pag_sql}" ), $wpdb->escape( $user_ids ) );

		$total_users = $wpdb->get_var( $total_users_sql );
		$paged_users = $wpdb->get_results( $paged_users_sql );

		/***
		 * Lets fetch some other useful data in a separate queries, this will be faster than querying the data for every user in a list.
		 * We can't add these to the main query above since only users who have this information will be returned (since the much of the data is in usermeta and won't support any type of directional join)
		 */

		/* Add additional data to the returned results */
		if ( $populate_extras )
			$paged_users = BP_Core_User::get_user_extras( $paged_users, $user_ids );

		return array( 'users' => $paged_users, 'total' => $total_users );
	}

	function search_users( $search_terms, $limit = null, $page = 1, $populate_extras = true ) {
		global $wpdb, $bp;

		if ( $limit && $page )
			$pag_sql = $wpdb->prepare( " LIMIT %d, %d", intval( ( $page - 1 ) * $limit), intval( $limit ) );

		$search_terms = like_escape( $wpdb->escape( $search_terms ) );
		$status_sql   = bp_core_get_status_sql( 'u.' );

		$total_users_sql = apply_filters( 'bp_core_search_users_count_sql', "SELECT COUNT(DISTINCT u.ID) as id FROM " . CUSTOM_USER_TABLE . " u LEFT JOIN {$bp->profile->table_name_data} pd ON u.ID = pd.user_id WHERE {$status_sql} AND pd.value LIKE '%%$search_terms%%' ORDER BY pd.value ASC", $search_terms );
		$paged_users_sql = apply_filters( 'bp_core_search_users_sql', "SELECT DISTINCT u.ID as id, u.user_registered, u.user_nicename, u.user_login, u.user_email FROM " . CUSTOM_USER_TABLE . " u LEFT JOIN {$bp->profile->table_name_data} pd ON u.ID = pd.user_id WHERE {$status_sql} AND pd.value LIKE '%%$search_terms%%' ORDER BY pd.value ASC{$pag_sql}", $search_terms, $pag_sql );

		$total_users = $wpdb->get_var( $total_users_sql );
		$paged_users = $wpdb->get_results( $paged_users_sql );

		/***
		 * Lets fetch some other useful data in a separate queries, this will be faster than querying the data for every user in a list.
		 * We can't add these to the main query above since only users who have this information will be returned (since the much of the data is in usermeta and won't support any type of directional join)
		 */
		foreach ( (array)$paged_users as $user )
			$user_ids[] = $user->id;

		$user_ids = $wpdb->escape( join( ',', (array)$user_ids ) );

		// Add additional data to the returned results
		if ( $populate_extras )
			$paged_users = BP_Core_User::get_user_extras( $paged_users, $user_ids );

		return array( 'users' => $paged_users, 'total' => $total_users );
	}

	function get_user_extras( &$paged_users, &$user_ids, $type = false ) {
		global $bp, $wpdb;

		if ( empty( $user_ids ) )
			return $paged_users;

		// Fetch the user's full name
		if ( bp_is_active( 'profile' ) && 'alphabetical' != $type ) {
			// Ensure xprofile globals are set
			if ( !defined( 'BP_XPROFILE_FULLNAME_FIELD_NAME' ) )
				xprofile_setup_globals();

			$names = $wpdb->get_results( $wpdb->prepare( "SELECT pd.user_id as id, pd.value as fullname FROM {$bp->profile->table_name_fields} pf, {$bp->profile->table_name_data} pd WHERE pf.id = pd.field_id AND pf.name = %s AND pd.user_id IN ( {$user_ids} )", BP_XPROFILE_FULLNAME_FIELD_NAME ) );
			for ( $i = 0; $i < count( $paged_users ); $i++ ) {
				foreach ( (array)$names as $name ) {
					if ( $name->id == $paged_users[$i]->id )
						$paged_users[$i]->fullname = $name->fullname;
				}
			}
		}

		// Fetch the user's total friend count
		if ( 'popular' != $type ) {
			$friend_count = $wpdb->get_results( "SELECT user_id as id, meta_value as total_friend_count FROM " . CUSTOM_USER_META_TABLE . " WHERE meta_key = 'total_friend_count' AND user_id IN ( {$user_ids} )" );
			for ( $i = 0; $i < count( $paged_users ); $i++ ) {
				foreach ( (array)$friend_count as $count ) {
					if ( $count->id == $paged_users[$i]->id )
						$paged_users[$i]->total_friend_count = (int)$count->total_friend_count;
				}
			}
		}

		// Fetch whether or not the user is a friend
		if ( bp_is_active( 'friends' ) ) {
			$friend_status = $wpdb->get_results( $wpdb->prepare( "SELECT initiator_user_id, friend_user_id, is_confirmed FROM {$bp->friends->table_name} WHERE (initiator_user_id = %d AND friend_user_id IN ( {$user_ids} ) ) OR (initiator_user_id IN ( {$user_ids} ) AND friend_user_id = %d )", $bp->loggedin_user->id, $bp->loggedin_user->id ) );
			for ( $i = 0; $i < count( $paged_users ); $i++ ) {
				foreach ( (array)$friend_status as $status ) {
					if ( $status->initiator_user_id == $paged_users[$i]->id || $status->friend_user_id == $paged_users[$i]->id )
						$paged_users[$i]->is_friend = $status->is_confirmed;
				}
			}
		}

		if ( 'active' != $type ) {
			$user_activity = $wpdb->get_results( "SELECT user_id as id, meta_value as last_activity FROM " . CUSTOM_USER_META_TABLE . " WHERE meta_key = 'last_activity' AND user_id IN ( {$user_ids} )" );
			for ( $i = 0; $i < count( $paged_users ); $i++ ) {
				foreach ( (array)$user_activity as $activity ) {
					if ( $activity->id == $paged_users[$i]->id )
						$paged_users[$i]->last_activity = $activity->last_activity;
				}
			}
		}

		// Fetch the user's last_activity
		if ( 'active' != $type ) {
			$user_activity = $wpdb->get_results( "SELECT user_id as id, meta_value as last_activity FROM " . CUSTOM_USER_META_TABLE . " WHERE meta_key = 'last_activity' AND user_id IN ( {$user_ids} )" );
			for ( $i = 0; $i < count( $paged_users ); $i++ ) {
				foreach ( (array)$user_activity as $activity ) {
					if ( $activity->id == $paged_users[$i]->id )
						$paged_users[$i]->last_activity = $activity->last_activity;
				}
			}
		}

		// Fetch the user's latest update
		$user_update = $wpdb->get_results( "SELECT user_id as id, meta_value as latest_update FROM " . CUSTOM_USER_META_TABLE . " WHERE meta_key = 'bp_latest_update' AND user_id IN ( {$user_ids} )" );
		for ( $i = 0; $i < count( $paged_users ); $i++ ) {
			foreach ( (array)$user_update as $update ) {
				if ( $update->id == $paged_users[$i]->id )
					$paged_users[$i]->latest_update = $update->latest_update;
			}
		}

		return $paged_users;
	}

	function get_core_userdata( $user_id ) {
		global $wpdb;

		if ( !$user = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->users WHERE ID = %d LIMIT 1", $user_id ) ) )
			return false;

		return $user;
	}
}


/**
 * BP_Core_Notification class can be used by any component.
 * It will handle the fetching, saving and deleting of a user notification.
 *
 * @package BuddyPress Core
 */

class BP_Core_Notification {
	var $id;
	var $item_id;
	var $secondary_item_id = null;
	var $user_id;
	var $component_name;
	var $component_action;
	var $date_notified;
	var $is_new;

	function bp_core_notification( $id = false ) {
		if ( $id ) {
			$this->id = $id;
			$this->populate();
		}
	}

	function populate() {
		global $wpdb, $bp;

		if ( $notification = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$bp->core->table_name_notifications} WHERE id = %d", $this->id ) ) ) {
			$this->item_id = $notification->item_id;
			$this->secondary_item_id = $notification->secondary_item_id;
			$this->user_id           = $notification->user_id;
			$this->component_name    = $notification->component_name;
			$this->component_action  = $notification->component_action;
			$this->date_notified     = $notification->date_notified;
			$this->is_new            = $notification->is_new;
		}
	}

	function save() {
		global $wpdb, $bp;

		// Update
		if ( $this->id )
			$sql = $wpdb->prepare( "UPDATE {$bp->core->table_name_notifications} SET item_id = %d, secondary_item_id = %d, user_id = %d, component_name = %s, component_action = %d, date_notified = %s, is_new = %d ) WHERE id = %d", $this->item_id, $this->secondary_item_id, $this->user_id, $this->component_name, $this->component_action, $this->date_notified, $this->is_new, $this->id );

		// Save
		else
			$sql = $wpdb->prepare( "INSERT INTO {$bp->core->table_name_notifications} ( item_id, secondary_item_id, user_id, component_name, component_action, date_notified, is_new ) VALUES ( %d, %d, %d, %s, %s, %s, %d )", $this->item_id, $this->secondary_item_id, $this->user_id, $this->component_name, $this->component_action, $this->date_notified, $this->is_new );

		if ( !$result = $wpdb->query( $sql ) )
			return false;

		$this->id = $wpdb->insert_id;
		return true;
	}

	/** Static functions ******************************************************/

	function check_access( $user_id, $notification_id ) {
		global $wpdb, $bp;

		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$bp->core->table_name_notifications} WHERE id = %d AND user_id = %d", $notification_id, $user_id ) );
	}

	function get_all_for_user( $user_id ) {
		global $wpdb, $bp;

 		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$bp->core->table_name_notifications} WHERE user_id = %d AND is_new = 1", $user_id ) );
	}

	function delete_for_user_by_type( $user_id, $component_name, $component_action ) {
		global $wpdb, $bp;

		return $wpdb->query( $wpdb->prepare( "DELETE FROM {$bp->core->table_name_notifications} WHERE user_id = %d AND component_name = %s AND component_action = %s", $user_id, $component_name, $component_action ) );
	}

	function delete_for_user_by_item_id( $user_id, $item_id, $component_name, $component_action, $secondary_item_id ) {
		global $wpdb, $bp;

		$secondary_item_sql = !empty( $secondary_item_id ) ? $wpdb->prepare( " AND secondary_item_id = %d", $secondary_item_id ) : '';

		return $wpdb->query( $wpdb->prepare( "DELETE FROM {$bp->core->table_name_notifications} WHERE user_id = %d AND item_id = %d AND component_name = %s AND component_action = %s{$secondary_item_sql}", $user_id, $item_id, $component_name, $component_action ) );
	}

	function delete_from_user_by_type( $user_id, $component_name, $component_action ) {
		global $wpdb, $bp;

		return $wpdb->query( $wpdb->prepare( "DELETE FROM {$bp->core->table_name_notifications} WHERE item_id = %d AND component_name = %s AND component_action = %s", $user_id, $component_name, $component_action ) );
	}

	function delete_all_by_type( $item_id, $component_name, $component_action, $secondary_item_id ) {
		global $wpdb, $bp;

		if ( $component_action )
			$component_action_sql = $wpdb->prepare( "AND component_action = %s", $component_action );
		else
			$component_action_sql = '';

		if ( $secondary_item_id )
			$secondary_item_sql = $wpdb->prepare( "AND secondary_item_id = %d", $secondary_item_id );
		else
			$secondary_item_sql = '';

		return $wpdb->query( $wpdb->prepare( "DELETE FROM {$bp->core->table_name_notifications} WHERE item_id = %d AND component_name = %s {$component_action_sql} {$secondary_item_sql}", $item_id, $component_name ) );
	}
}

/**
 * BP_Button
 *
 * API to create BuddyPress buttons
 *
 * @package BuddyPress Core
 * @since 1.2.6
 */
class BP_Button {

	// Button properties
	var $id;
	var $component;
	var $must_be_logged_in;
	var $block_self;

	// Wrapper
	var $wrapper;
	var $wrapper_class;
	var $wrapper_id;

	// Button
	var $link_href;
	var $link_class;
	var $link_id;
	var $link_rel;
	var $link_title;
	var $link_text;

	// HTML result
	var $contents;

	/**
	 * bp_button()
	 *
	 * Builds the button based on passed parameters:
	 *
	 * component: Which component this button is for
	 * must_be_logged_in: Button only appears for logged in users
	 * block_self: Button will not appear when viewing your own profile.
	 * wrapper: div|span|p|li|false for no wrapper
	 * wrapper_id: The DOM ID of the button wrapper
	 * wrapper_class: The DOM class of the button wrapper
	 * link_href: The destination link of the button
	 * link_title: Title of the button
	 * link_id: The DOM ID of the button
	 * link_class: The DOM class of the button
	 * link_rel: The DOM rel of the button
	 * link_text: The contents of the button
	 *
	 * @param array $args
	 * @return bool False if not allowed
	 */
	function bp_button( $args = '' ) {

		// Default arguments
		$defaults = array(
			'id'                => '',
			'component'         => 'core',
			'must_be_logged_in' => true,
			'block_self'        => true,

			'wrapper'           => 'div',
			'wrapper_id'        => '',
			'wrapper_class'     => '',

			'link_href'         => '',
			'link_title'        => '',
			'link_id'           => '',
			'link_class'        => '',
			'link_rel'          => '',
			'link_text'         => '',
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		// Required button properties
		$this->id                = $id;
		$this->component         = $component;
		$this->must_be_logged_in = (bool)$must_be_logged_in;
		$this->block_self        = (bool)$block_self;
		$this->wrapper           = $wrapper;

		// $id and $component are required
		if ( empty( $id ) || empty( $component ) )
			return false;

		// No button if component is not active
		if ( !bp_is_active( $this->component ) )
			return false;

		// No button for guests if must be logged in
		if ( true == $this->must_be_logged_in && !is_user_logged_in() )
			return false;

		// No button if viewing your own profile
		if ( true == $this->block_self && bp_is_my_profile() )
			return false;

		// Wrapper properties
		if ( false !== $this->wrapper ) {

			// Wrapper ID
			if ( !empty( $wrapper_id ) )
				$this->wrapper_id    = ' id="' . $wrapper_id . '"';

			// Wrapper class
			if ( !empty( $wrapper_class ) )
				$this->wrapper_class = ' class="generic-button ' . $wrapper_class . '"';
			else
				$this->wrapper_class = ' class="generic-button"';

			// Set before and after
			$before = '<' . $wrapper . $this->wrapper_class . $this->wrapper_id . '>';
			$after  = '</' . $wrapper . '>';

		// No wrapper
		} else {
			$before = $after = '';
		}

		// Link properties
		if ( !empty( $link_id ) )
			$this->link_id    = ' id="' . $link_id . '"';

		if ( !empty( $link_href ) )
			$this->link_href  = ' href="' . $link_href . '"';

		if ( !empty( $link_title ) )
			$this->link_title = ' title="' . $link_title . '"';

		if ( !empty( $link_rel ) )
			$this->link_rel   = ' rel="' . $link_rel . '"';

		if ( !empty( $link_class ) )
			$this->link_class = ' class="' . $link_class . '"';

		if ( !empty( $link_text ) )
			$this->link_text  = $link_text;

		// Build the button
		$this->contents = $before . '<a'. $this->link_href . $this->link_title . $this->link_id . $this->link_rel . $this->link_class . '>' . $this->link_text . '</a>' . $after;

		// Allow button to be manipulated externally
		$this->contents = apply_filters( 'bp_button_' . $component . '_' . $id, $this->contents, $this, $before, $after );
	}

	/**
	 * contents()
	 *
	 * Return contents of button
	 *
	 * @return string
	 */
	function contents() {
		return $this->contents;
	}

	/**
	 * display()
	 *
	 * Output contents of button
	 */
	function display() {
		if ( !empty( $this->contents ) )
			echo $this->contents;
	}
}

?>
