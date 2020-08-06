<?php
/**
 * Plugin Name: EDD Blacklist
 * Description: Block purchases from known bad customers using Programmer Bear's EDD Blacklist
 * Version: 1.0.0
 * Author: Programmer Bear
 * Author URI: http://programmerbear.com/
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
 * The main filter.
 */
add_filter( 'edd_get_banned_emails', 'pb_edd_cbl_filter_banned_emails' );
function pb_edd_cbl_filter_banned_emails( $emails ) {
	global $pagenow;
	
	if ( $pagenow === 'edit.php' && isset( $_GET['page'] ) && $_GET['page'] === 'edd-tools' ) {
		// don't display our blacklist co-mingled with whatever other emails you may have saved already
		return $emails;
	}
	
	$blacklist = get_option( 'pb_edd_cbl' );
	
	if ( is_array( $blacklist ) && ! empty( $blacklist['emails'] ) ) {
		foreach ( $blacklist['emails'] as $email ) {
			$emails[] = $email;
		}
	} else {
		pb_edd_cbl_update_blacklist();
	}
	
	return $emails;
}


/*
 * Just a nice little notice to show our plugin is working.
 */
add_action( 'edd_tools_banned_emails_before', 'pb_edd_cbl_active_notice', PHP_INT_MAX );
function pb_edd_cbl_active_notice() {
?>
	<div class="postbox">
		<h3><span><?php _e( 'EDD Blacklist', 'edd-blacklist' ); ?></span></h3>
		<div class="inside">
			<p><?php _e( 'Programmer Bear\'s EDD Blacklist is <span style="color:#60ad5d;">enabled</span> and protecting your business!', 'edd-blacklist' ); ?></p>
			<p><?php _e( 'The data from our blacklist is automatically processed, in addition to any banned email addresses you may have entered below.', 'edd-blacklist' ); ?></p>
		</div><!-- .inside -->
	</div><!-- .postbox -->
<?php
}


/*
 * A WP-cron task to check for an updated blacklist, once per day
 */
add_action( 'pb_edd_cbl_cron', 'pb_edd_cbl_cron_task' );
function pb_edd_cbl_cron_task() {
	pb_edd_cbl_update_blacklist();
}
if ( ! wp_next_scheduled( 'pb_edd_cbl_cron' ) ) {
	wp_schedule_event( time() + 10, 'daily', 'pb_edd_cbl_cron' );
}


/*
 * The function that gets the latest blacklist data and updates our local copy if needed.
 */
function pb_edd_cbl_update_blacklist() {

	$old_blacklist = get_option( 'pb_edd_cbl' );

	$response = wp_remote_get( 'http://programmerbear.com/edd-blacklist/json/' );
	if ( is_array( $response ) && ! is_wp_error( $response ) ) {
		$blacklist = json_decode( $response['body'], true );
	} else {
		return;
	}
	
	$update_needed = false;
	if ( ! $old_blacklist ) {
		$update_needed = true;
	}
	if ( isset( $old_blacklist['blacklist_version'] ) && $old_blacklist['blacklist_version'] < $blacklist['blacklist_version'] ) {
		$update_needed = true;
	}
	
	if ( $update_needed ) {
		update_option( 'pb_edd_cbl', $blacklist );
	}
	
}
