<?php 
/*
 * Plugin Name: Bitcoin / Altcoin Faucet
 * Plugin URI: http://gra4.com
 * Description: Bitcoin / Altcoin ('BTC', 'BCH', 'DASH', 'DGB', 'DOGE', 'ETH', 'LTC') Faucet for WordPress
 * Author: Alexey Trofimov
 * Version: 1.6.0
 * Author URI: http://gra4.com
 * License: GPLv2
 * Text Domain: wpbftd
 * Domain Path: /languages
*/

/*
This plugin is based on (and uses code samples of) :
	Faucet in a BOX 
	Copyright (c) 2014-2016 LiveHome Sp. z o. o.
	https://faucetinabox.com/
	Distributed under the terms of the GNU General Public License
*/
if ( ! defined( 'ABSPATH' ) ) die('nope!'); // Exit if accessed directly
//language and session stuff

global $self_version;
$self_version = "1.5.0";

function wpbf_bitcoin_faucet_plugin_init() {
	global $self_version;
	load_plugin_textdomain( 'wpbftd', false,basename( dirname( __FILE__ ) ) . '/languages'  );
	if(!session_id()) {
        session_start();
    }  
	header('Content-Type: text/html; charset=utf-8');
}
add_action('init', 'wpbf_bitcoin_faucet_plugin_init');


function wpbf_bitcoin_faucet_admin_notice(){
	if ( is_admin() ) {
		global $wpdb;
//FaucetHub is out START		
		$db_ret = $wpdb->get_results("SELECT value FROM wpf_Faucet_Settings WHERE name = 'service' ");
		$ret = $db_ret[0]->value; 
		if( $ret == 'faucethub' ){
			$text = __("FaucetHub is selected");
			$link = "<a href='" . admin_url( "options-general.php?page=bitcoin_faucet") ."'>".__('Please change the Service', 'wpbftd')."</a>";
			echo('<div class="error error-info is-dismissible">' .$text. ". " .$link. '</div>');
		}
//FaucetHub is out END
//Balance START		
		$db_ret = $wpdb->get_results("SELECT value FROM wpf_Faucet_Settings WHERE name = 'balance' ");
		$ret = $db_ret[0]->value; 	
		if( $ret < 100 ){
			$link = "<a href='" . admin_url( "options-general.php?page=bitcoin_faucet") ."'>".__('Low balance on the Faucet', 'wpbftd').  ": <b>" . $ret . "</b></a>";
			echo('<div class="notice notice-info is-dismissible">'  .$link. '</div>');
		}
		
//Balance END			
	}
}
add_action('admin_notices', 'wpbf_bitcoin_faucet_admin_notice');



function wpbf_bitcoin_faucet_bad_version_shortcode(){
	$plugin_data = get_plugin_data( __FILE__ );
	$plugin_name = $plugin_data['Name'];	
	deactivate_plugins( plugin_basename( __FILE__ ) , false);	
	echo("<div class=\"error\"><p>"
		. '<b>' . __('Please note', 'wpbftd') . ":</b> "
		.  __('Plugin') 
		. ' <b>' . $plugin_name . "</b> "
		. __('requires at least PHP version 5.4 to function properly. ', 'wpbftd') 
		. '<br>' . __('Your PHP version is', 'wpbftd') . ' ' . phpversion() . ' .'
		. '<br>' . __('Please upgrade the PHP, usually it is easy to do via the hosting control panel.', 'wpbftd') 		
		. '<br>' . __('Plugin') . ' ' . __('deactivated', 'wpbftd') . '.'		
		. "</p></div>");
}



function wpbf_bitcoin_faucet_plugin_add_link( $links ) {
//    $settings_link = '<a href="' . admin_url('/options-general.php?page=bitcoin_faucet') . '">' . __( 'Settings' ) . '</a>';
    $settings_link = '<a href="' . admin_url('/options-general.php?page=bitcoin_faucet') . '"><img style="vertical-align: middle;width:24px;height:24px;border:0;" src="'. plugin_dir_url( __FILE__ ) . 'bin/bitcoin_64.png'.'"></img>' . __( 'Settings' ) . '</a>';
//    array_push( $links, $settings_link );
	array_unshift($links , $settings_link);	
  	return $links;
}

if ( version_compare( PHP_VERSION, '5.4', '<' ) ) 
{
    add_action( 'admin_init', 'wpbf_bitcoin_faucet_bad_version_shortcode' );
    return;
} 
else 
{
	add_filter( "plugin_action_links_" . plugin_basename(  __FILE__ ), 'wpbf_bitcoin_faucet_plugin_add_link' );
	include_once( plugin_basename('wpbf_fetcher.php') );
}
//Unistall stuff done in uninstall.php
//register_uninstall_hook( __FILE__, 'wpbf_bitcoin_faucet_uninstall' );
