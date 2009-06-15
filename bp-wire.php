<?php

/* Define the slug for the component */
if ( !defined( 'BP_WIRE_SLUG' ) )
	define ( 'BP_WIRE_SLUG', 'wire' );

require ( BP_PLUGIN_DIR . '/bp-wire/bp-wire-classes.php' );
require ( BP_PLUGIN_DIR . '/bp-wire/bp-wire-ajax.php' );
require ( BP_PLUGIN_DIR . '/bp-wire/bp-wire-templatetags.php' );
require ( BP_PLUGIN_DIR . '/bp-wire/bp-wire-cssjs.php' );
require ( BP_PLUGIN_DIR . '/bp-wire/bp-wire-filters.php' );

/**************************************************************************
 bp_wire_setup_globals()
 
 Set up and add all global variables for this component, and add them to 
 the $bp global variable array.
 **************************************************************************/

function bp_wire_install() {
	// Tables are installed on a per component basis, where needed.
}

function bp_wire_setup_globals() {
	global $bp, $wpdb;
	
	$bp->wire->image_base = BP_PLUGIN_URL . '/bp-wire/images';
	$bp->wire->slug = BP_WIRE_SLUG;

	$bp->version_numbers->wire = BP_WIRE_VERSION;
}
add_action( 'plugins_loaded', 'bp_wire_setup_globals', 5 );	
add_action( 'admin_menu', 'bp_wire_setup_globals', 1 );

/**************************************************************************
 bp_wire_setup_nav()
 
 Set up front end navigation.
 **************************************************************************/

function bp_wire_setup_nav() {
	global $bp;

	/* Add 'Wire' to the main navigation */
	bp_core_add_nav_item( __('Wire', 'buddypress'), $bp->wire->slug );
	bp_core_add_nav_default( $bp->wire->slug, 'bp_wire_screen_latest', 'all-posts' );

	/* Add the subnav items to the wire nav */
 	bp_core_add_subnav_item( $bp->wire->slug, 'all-posts', __('All Posts', 'buddypress'), $bp->loggedin_user->domain . $bp->wire->slug . '/', 'bp_wire_screen_latest' );
	
	if ( $bp->current_component == $bp->wire->slug ) {
		if ( bp_is_home() ) {
			$bp->bp_options_title = __('My Wire', 'buddypress');
		} else {
			$bp->bp_options_avatar = bp_core_get_avatar( $bp->displayed_user->id, 1 );
			$bp->bp_options_title = $bp->displayed_user->fullname; 
		}
	}
	
	do_action( 'bp_wire_setup_nav' );
}
add_action( 'wp', 'bp_wire_setup_nav', 2 );
add_action( 'admin_menu', 'bp_wire_setup_nav', 2 );

/***** Screens **********/

function bp_wire_screen_latest() {
	do_action( 'bp_wire_screen_latest' );
	bp_core_load_template( apply_filters( 'bp_wire_template_latest', 'wire/latest' ) );	
}

function bp_wire_record_activity( $args = true ) {
	if ( function_exists('bp_activity_record') ) {
		extract($args);

		bp_activity_record( $item_id, $component_name, $component_action, $is_private, $secondary_item_id, $user_id, $secondary_user_id );
	}
}

function bp_wire_delete_activity( $args = true ) {
	if ( function_exists('bp_activity_delete') ) {
		extract($args);
		bp_activity_delete( $item_id, $component_name, $component_action, $user_id, $secondary_item_id );
	}
}

function bp_wire_new_post( $item_id, $message, $component_name, $private_post = false, $table_name = null ) {
	global $bp;
	
	if ( empty($message) || !is_user_logged_in() )
		return false;
	
	if ( !$table_name )
		$table_name = $bp->{$component_name}->table_name_wire;

	$wire_post = new BP_Wire_Post( $table_name );
	$wire_post->item_id = $item_id;
	$wire_post->user_id = $bp->loggedin_user->id;
	$wire_post->date_posted = time();

	$allowed_tags = apply_filters( 'bp_wire_post_allowed_tags', '<a><b><strong><i><em><img>' );
		
	$message = strip_tags( $message, $allowed_tags );
	$wire_post->content = apply_filters( 'bp_wire_post_content', $message );
	
	if ( !$wire_post->save() )
		return false;
	
	if ( !$private_post ) {
		// Record in the activity streams
		bp_wire_record_activity( array( 'item_id' => $wire_post->id, 'component_name' => $component_name, 'component_action' => 'new_wire_post', 'is_private' => 0 ) );
	}
	
	do_action( 'bp_wire_post_posted', $wire_post->id, $wire_post->item_id, $wire_post->user_id );
	
	return $wire_post->id;
}

function bp_wire_delete_post( $wire_post_id, $component_name, $table_name = null ) {
	global $bp;

	if ( !is_user_logged_in() )
		return false;

	if ( !$table_name )
		$table_name = $bp->{$component_name}->table_name_wire;
	
	$wire_post = new BP_Wire_Post( $table_name, $wire_post_id );
	
	if ( !is_site_admin() ) {
		if ( !$bp->is_item_admin ) {
			if ( $wire_post->user_id != $bp->loggedin_user->id )
				return false;
		}
	}
	
	if ( !$wire_post->delete() )
		return false;

	// Delete activity stream items
	bp_wire_delete_activity( array( 'user_id' => $wire_post->user_id, 'item_id' => $wire_post->id, 'component_name' => $component_name, 'component_action' => 'new_wire_post' ) );	

	do_action( 'bp_wire_post_deleted', $wire_post->id, $wire_post->item_id, $wire_post->user_id );
	
	return true;
}

// List actions to clear super cached pages on, if super cache is installed
add_action( 'bp_wire_post_deleted', 'bp_core_clear_cache' );
add_action( 'bp_wire_post_posted', 'bp_core_clear_cache' );

?>