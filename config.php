<?php
namespace wpbf_bitcoin_faucet;
if ( ! defined( 'ABSPATH' ) ) die('nope!'); // Exit if accessed directly
$dbhost = "";
$dbuser = "";
$dbpass = "";
$dbname = "";

//WP
if(defined('ABSPATH') )
{
	$dbhost = DB_HOST;
	$dbuser = DB_USER;
	$dbpass = DB_PASSWORD;
	$dbname = DB_NAME;		 
}


$trof_table_prefix = 'wpf_Faucetinabox';

$trof_disable_admin_password = true;
$trof_disable_version_check = true;

$trof_exchange_list = false; //if true - list is shown

$display_errors = false; //TROF ATT!


$disable_admin_panel = false;

$connection_options = array(
    'disable_curl' => false,
    'local_cafile' => false,
    'force_ipv4' => false    // cURL only
);

// dsn - Data Source Name
// if you use MySQL, leave it as is
// more information:
// http://php.net/manual/en/pdo.construct.php

$dbdsn = "mysql:host=$dbhost;dbname=$dbname";
//echo($dbdsn);
global $session_prefix;
$session_prefix = crc32(__FILE__);

//params set by shortcodes
global $trof_wps_faucet_name;
$trof_wps_faucet_name = NULL;
global $trof_wps_faucet_description;
$trof_wps_faucet_description = NULL;
global $trof_wps_faucet_rewards;
$trof_wps_faucet_rewards = NULL;
global $trof_wps_faucet_timer;
$trof_wps_faucet_timer = NULL;
