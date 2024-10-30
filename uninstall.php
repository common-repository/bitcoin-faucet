<?php 
if( ! defined('WP_UNINSTALL_PLUGIN') )
{
	die('NOT THERE!');
}
else //called corectly
{
	wpbf_bitcoin_faucet_uninstall();
}

function wpbf_bitcoin_faucet_uninstall() //Scarry stuff!!
{
	global $wpdb;
	$result = $wpdb->query("DROP TABLE `wpf_Faucet_Addresses`, `wpf_Faucet_Address_Locks`, `wpf_Faucet_IPs`, `wpf_Faucet_IP_Locks`, `wpf_Faucet_Pages`, `wpf_Faucet_Refs`, `wpf_Faucet_Settings`");
}
//we done