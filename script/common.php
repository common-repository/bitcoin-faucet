<?php
namespace wpbf_bitcoin_faucet;
use \PDO;
use \PDOException;

if ( ! defined( 'ABSPATH' ) ) die('nope!'); // Exit if accessed directly, not from WP
global $sql;
global $session_prefix;
global $display_errors;
global $dbdsn;
global $dbuser;
global $dbpass;


global $trof_admin_url;
$trof_admin_url = admin_url() . "options-general.php?page=bitcoin_faucet"; //TROF
global $trof_m;
$trof_m = '';

$version = '68';
global $security_settings;//TROF


function trof_cryptoomize_currencies($service,$supported_services) //$service - current service
{
	if($service == 'cryptoo')
	{
		foreach($supported_services as $sk => $sv) 
		{
			if($sk != $service)
			{
				foreach($sv['currencies'] as $ck => $cv) 
				{
					if($cv == 'BTC') // add supported here
					{
						unset($supported_services[$sk]['currencies'][$ck]);
					}
				}
				$supported_services[$sk]['currencies'] = array_values($supported_services[$sk]['currencies']);
			}
		}
	}
	return $supported_services;
}

function trof_wp_file_get_contents($url)
{
		$args = array(
			'timeout'     => 9,
		);
		$response = wp_remote_get( $url, $args );
		$http_code = wp_remote_retrieve_response_code( $response );		
		if($http_code != 200)
		{
			return FALSE;
		}
		$body = wp_remote_retrieve_body( $response );	
		return $body;
}

function trof_get_rewards_range($rewards_str, &$min_reward, &$max_reward)
{
	$a_rewards = explode(', ', $rewards_str); //10*11-22, 20*66, 30*33-44
	$a_singles = array(); //declare
	foreach ($a_rewards as $r) 
	{
		$a_i = explode('*',$r); //$i[0] is percentage - discard	
		$a_j = explode('-',$a_i[1]); //if has range
		for($k = 0; $k < count($a_j); $k++)
		{
			array_push($a_singles,$a_j[$k]);
		}//for
	}//foreach	
	sort($a_singles);
	if(count($a_singles) == 1)
	{
		$min_reward = $a_singles[0];
		$max_reward = $a_singles[0];
	}
	else //have several
	{
		$min_reward = $a_singles[0];
		$max_reward = $a_singles[ count($a_singles) - 1];	
	}
}

function trof_get_exchange_list_code($is_admin_page = false)
{
	global $trof_admin_url; //just to check protocol. we do not support https yet - http iframe in https page will error out
	if(strpos($trof_admin_url,'https') === 0) //oops - we are on https . note '==='
	{
		if($is_admin_page)
		{
			return('<center>Not supported for HTTPS yet,<br>not going to be visible on the Faucet</center>'); //inform admin... hmm.. Ugly ! =(
		}
		else
		{
			return(''); //here goes nothing
		}
	}
//ok, if we are here, we are not on https	
	global $sql;
	global $data;
//print_r($data);
	$res = '';

    $q = $sql->prepare("SELECT value FROM wpf_Faucet_Settings WHERE name = ?");
	
    $q->execute(array('rewards'));
    $faucet_rewards = $q->fetch();
    $faucet_rewards = $faucet_rewards[0];//this one from the DB, may be overwritten by shortcode
	global $trof_wps_faucet_rewards;
	if($trof_wps_faucet_rewards !== NULL) {$faucet_rewards = $trof_wps_faucet_rewards; }	
	$min_reward = -1;
	$max_reward = -1;
	trof_get_rewards_range($faucet_rewards, $min_reward, $max_reward);
	
    $q->execute(array('timer'));
    $faucet_timer = $q->fetch();
    $faucet_timer = $faucet_timer[0]; //this one from the DB, may be overwritten by shortcode
	global $trof_wps_faucet_timer;
	if($trof_wps_faucet_timer !== NULL) {$faucet_timer = $trof_wps_faucet_timer; }	

	$faucet_name = $data['name']; //this one from the DB, may be overwritten by shortcode trof_wps_faucet_name

	global $trof_wps_faucet_name;//overwritten my shortcode
	if($trof_wps_faucet_name !== NULL) {$faucet_name = $trof_wps_faucet_name; }

	if(($is_admin_page == false) && ($faucet_name == '') )
	{
		global $pagename;
		$faucet_name = $pagename;
	}
	$faucet_name = str_replace('/','',$faucet_name);	// '/' screws up parameters
	$faucet_name = str_replace('"','`',$faucet_name);	// " will break the tag
	$faucet_name = urlencode($faucet_name);
	
    $q->execute(array('currency'));
    $faucet_currency = $q->fetch();
    $faucet_currency = $faucet_currency[0]; //this one from the DB, may be overwritten by shortcode	
	
	$faucet_params = "/$faucet_timer/$min_reward/$max_reward/$faucet_currency/"; //just readability
	
	
	$cur_time = time();

	$res .= '<iframe  id="trof_exchage_list_frame" style="width:100%;border:0;" src="http://gra4.com/"></iframe>';
	$res .= "<script>
		document.getElementById('trof_exchage_list_frame').src = 'http://gra4.com/bitcoin/faucets/1/F/'+'".$cur_time."'+'/'+'".$faucet_name."'+'/'+encodeURIComponent(encodeURIComponent(document.location.href))+'".$faucet_params."';
        var attachFuncEvent = 'message';
        var attachFunc = window.addEventListener ;
        if (! window.addEventListener) {
            attachFunc = window.attachEvent;
            attachFuncEvent = 'onmessage';
        }

        attachFunc(attachFuncEvent, function(event) {
            if (event.data == 'FaucetListIframeIsDone') { // iframe is done callback here
				jQuery('#trof_exchange_list_wrap').slideDown();			
            }
        });
	</script>";
	
	return($res);
}

global $trof_exchange_list_code;
//$trof_exchange_list_code = trof_get_exchange_list_code();


function trof_check_update_balance() //one function so we cha show balance in admin too
{
	global $data,$connection_options,$sql;
    $fb = new Service($data['service'], $data['apikey'], $data['currency'], $connection_options);
    $ret = $fb->getBalance();

    if (array_key_exists('balance', $ret)) {
        if ($data['currency'] != 'DOGE')
            $balance = $ret['balance'];
        else
            $balance = $ret['balance_bitcoin'];
        $q = $sql->prepare("UPDATE wpf_Faucet_Settings SET value = ? WHERE name = ?");
        $q->execute(array(time(), 'last_balance_check'));
        $q->execute(array($balance, 'balance'));
        $data['balance'] = $balance;
        $data['last_balance_check'] = time();
    }
//echo(" trof_check_update_balance() " . $data['balance'] ." ".$data["unit"]);
}// trof_check_update_balance()


if (get_magic_quotes_gpc()) {
    $process = array(&$_GET, &$_POST, &$_COOKIE, &$_REQUEST);
    while (list($key, $val) = each($process)) {
        foreach ($val as $k => $v) {
            unset($process[$key][$k]);
            if (is_array($v)) {
                $process[$key][stripslashes($k)] = $v;
                $process[] = &$process[$key][stripslashes($k)];
            } else {
                $process[$key][stripslashes($k)] = stripslashes($v);
            }
        }
    }
    unset($process);
}

if(stripos($_SERVER['REQUEST_URI'], '@') !== FALSE ||
   stripos(urldecode($_SERVER['REQUEST_URI']), '@') !== FALSE) {
    header("Location: ."); die('Please wait...');
}

if (!session_id())
{
	session_start();
}
//header('Content-Type: text/html; charset=utf-8'); //sent by WP already
ini_set('display_errors', false);

$missing_configs = array();

//$session_prefix = crc32(__FILE__); //in config now

$disable_curl = false;
$verify_peer = true;
$local_cafile = false;
require_once( dirname( __FILE__ ) . "/../config.php");
global $disable_admin_panel; //TROF
if(!isset($disable_admin_panel)) {
    $disable_admin_panel = false;
    $missing_configs[] = array(
        "name" => "disable_admin_panel",
        "default" => "false",
        "desc" => "Allows to disable Admin Panel for increased security"
    );
}

global $connection_options; //TROF
if(!isset($connection_options)) {
    $connection_options = array(
        'disable_curl' => $disable_curl,
        'local_cafile' => $local_cafile,
        'verify_peer' => $verify_peer,
        'force_ipv4' => false
    );
}
if(!isset($connection_options['verify_peer'])) {
    $connection_options['verify_peer'] = $verify_peer;
}

if (!isset($display_errors)) $display_errors = false;
ini_set('display_errors', $display_errors);
if($display_errors)
    error_reporting(-1);


if(array_key_exists('HTTP_REFERER', $_SERVER)) {
    $referer = $_SERVER['HTTP_REFERER'];
} else {
    $referer = "";
}

$host = parse_url($referer, PHP_URL_HOST);
if($_SERVER['HTTP_HOST'] != $host) {
    if (
        array_key_exists("$session_prefix-address_input_name", $_SESSION) &&
        array_key_exists($_SESSION["$session_prefix-address_input_name"], $_POST)
    ) {
        $_POST[$_SESSION["$session_prefix-address_input_name"]] = "";
        trigger_error("REFERER CHECK FAILED, ASSUMING CSRF!");
    }
}

 
require_once(dirname( __FILE__ ) .'/../libs/services.php');
try {
	$sql = new PDO($dbdsn, $dbuser, $dbpass, array(PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));	
} catch(PDOException $e) {
    if ($display_errors) 
		die("Can't connect to database. Details: ".$e->getMessage());
    else 
		 die( __( 'Not configured!', 'wpbftd' ) . "<a href='$trof_admin_url'>" . __( 'Please visit Admin section first', 'wpbftd' ) . "</a>");
}



$db_updates = array(

    15 => array("INSERT IGNORE INTO `wpf_Faucet_Settings` (`name`, `value`) VALUES ('version', '15');"),
    17 => array("ALTER TABLE `wpf_Faucet_Settings` CHANGE `value` `value` TEXT NOT NULL;", "INSERT IGNORE INTO `wpf_Faucet_Settings` (`name`, `value`) VALUES ('balance', 'N/A');"),
    33 => array("INSERT IGNORE INTO `wpf_Faucet_Settings` (`name`, `value`) VALUES ('ayah_publisher_key', ''), ('ayah_scoring_key', '');"),
    34 => array("INSERT IGNORE INTO `wpf_Faucet_Settings` (`name`, `value`) VALUES ('custom_admin_link_default', 'true')"),
    38 => array("INSERT IGNORE INTO `wpf_Faucet_Settings` (`name`, `value`) VALUES ('reverse_proxy', 'none')", "INSERT IGNORE INTO `wpf_Faucet_Settings` (`name`, `value`) VALUES ('default_captcha', 'recaptcha')"),
    41 => array("INSERT IGNORE INTO `wpf_Faucet_Settings` (`name`, `value`) VALUES ('captchme_public_key', ''), ('captchme_private_key', ''), ('captchme_authentication_key', ''), ('reklamper_enabled', '')"),
    46 => array("INSERT IGNORE INTO `wpf_Faucet_Settings` (`name`, `value`) VALUES ('last_balance_check', '0')"),
    54 => array("INSERT IGNORE INTO `wpf_Faucet_Settings` (`name`, `value`) VALUES ('funcaptcha_public_key', ''), ('funcaptcha_private_key', '')"),
    55 => array("INSERT IGNORE INTO `wpf_Faucet_Settings` (`name`, `value`) VALUES ('block_adblock', ''), ('button_timer', '0')"),
    56 => array("INSERT IGNORE INTO `wpf_Faucet_Settings` (`name`, `value`) VALUES ('ip_check_server', ''),('ip_ban_list', ''),('hostname_ban_list', ''),('address_ban_list', '')"),
    58 => ["DELETE FROM `wpf_Faucet_Settings` WHERE `name` IN ('captchme_public_key', 'captchme_private_key', 'captchme_authentication_key', 'reklamper_enabled')"],
    63 => ["INSERT IGNORE INTO `wpf_Faucet_Settings` (`name`, `value`) VALUES ('safety_limits_end_time', '')"],
    64 => [
        "INSERT IGNORE INTO `wpf_Faucet_Settings` (`name`, `value`) VALUES ('iframe_sameorigin_only', ''), ('asn_ban_list', ''), ('country_ban_list', ''), ('nastyhosts_enabled', '')",
        "UPDATE `wpf_Faucet_Settings` new LEFT JOIN `wpf_Faucet_Settings` old ON old.name = 'ip_check_server' SET new.value = IF(old.value = 'http://v1.nastyhosts.com/', 'on', '') WHERE new.name = 'nastyhosts_enabled'",
        "DELETE FROM `wpf_Faucet_Settings` WHERE `name` = 'ip_check_server'",
    ],
    65 => [
        "DELETE FROM `wpf_Faucet_Settings` WHERE `name` IN ('ayah_publisher_key', 'ayah_scoring_key') ",
        "UPDATE `wpf_Faucet_Settings` SET `value` = IF(`value` != 'none' OR `value` != 'none-auto', 'on', '') WHERE `name` = 'reverse_proxy' "
    ],
    66 => [
        "ALTER TABLE `wpf_Faucet_Settings` CHANGE `value` `value` LONGTEXT NOT NULL;",
        "INSERT IGNORE INTO `wpf_Faucet_Settings` (`name`, `value`) VALUES ('service', 'cryptoo');",
        "CREATE TABLE IF NOT EXISTS `wpf_Faucet_IP_Locks` ( `ip` VARCHAR(20) NOT NULL PRIMARY KEY, `locked_since` TIMESTAMP NOT NULL );",
        "CREATE TABLE IF NOT EXISTS `wpf_Faucet_Address_Locks` ( `address` VARCHAR(60) NOT NULL PRIMARY KEY, `locked_since` TIMESTAMP NOT NULL );",
    ],
    67 => [
        "ALTER TABLE `wpf_Faucet_Refs` DROP COLUMN `balance`;",
        "INSERT IGNORE INTO `wpf_Faucet_Settings` (`name`, `value`) VALUES ('ip_white_list', ''), ('update_last_check', '');",
    ]

);



$default_data_query = <<<QUERY
create table if not exists wpf_Faucet_Settings (
    `name` varchar(64) not null,
    `value` longtext not null,
    primary key(`name`)
);
create table if not exists wpf_Faucet_IPs (
    `ip` varchar(20) not null,
    `last_used` timestamp not null,
    primary key(`ip`)
);
create table if not exists wpf_Faucet_Addresses (
    `address` varchar(60) not null,
    `ref_id` int null,
    `last_used` timestamp not null,
    primary key(`address`)
);
create table if not exists wpf_Faucet_Refs (
    `id` int auto_increment not null,
    `address` varchar(60) not null unique,
    primary key(`id`)
);
create table if not exists wpf_Faucet_Pages (
    `id` int auto_increment not null,
    `url_name` varchar(50) not null unique,
    `name` varchar(255) not null,
    `html` text not null,
    primary key(`id`)
);
create table if not exists `wpf_Faucet_IP_Locks` (
    `ip` varchar(20) not null primary key,
    `locked_since` timestamp not null
);
create table if not exists `wpf_Faucet_Address_Locks` (
    `address` varchar(60) not null primary key,
    `locked_since` timestamp not null
);

INSERT IGNORE INTO wpf_Faucet_Settings (name, value) VALUES
('apikey', ''),
('trof_apisecret', ''),
('timer', '60'),
('rewards', '90*10-20, 10*10-50'),
('referral', '20'),
('solvemedia_challenge_key', ''),
('solvemedia_verification_key', ''),
('solvemedia_auth_key', ''),
('recaptcha_private_key', ''),
('recaptcha_public_key', ''),
('funcaptcha_private_key', ''),
('funcaptcha_public_key', ''),
('coinhive_site_key', ''), 
('coinhive_secret_key', ''), 
('raincaptcha_public_key', ''),
('raincaptcha_secret_key', ''),
('raincaptcha_secret_key', ''),
('cryptoloot_site_key', ''), 
('cryptoloot_secret_key', ''), 
('cryptoloot_hashes', '512'),
('name', 'Bitcoin Faucet'),
('short', ''),
('template', 'default'),
('custom_body_cl_default', ''),
('custom_box_bottom_cl_default', ''),
('custom_box_bottom_default', ''),
('custom_box_top_cl_default', ''),
('custom_box_top_default', ''),
('custom_box_left_cl_default', ''),
('custom_box_left_default', ''),
('custom_box_right_cl_default', ''),
('custom_box_right_default', ''),
('custom_extra_code_NOBOX_default', '<style>\\n/* custom_css */\\n/* center everything! */\\n.row {\\n    text-align: center;\\n}\\n#recaptcha_widget_div, #recaptcha_area {\\n    margin: 0 auto;\\n}\\n/* do not center lists */\\nul, ol {\\n    text-align: left;\\n} \\n</style>'),
('custom_visitor_hint_NOBOX_default', 'Yep, we give you crypto-currency for free. <br>\\nRead more at <a target=_new href="https://bitcoin.org">bitcoin.org</a>'),
('custom_footer_cl_default', ''),
('custom_footer_default', ''),
('custom_main_box_cl_default', ''),
('custom_palette_default', ''),
('custom_admin_link_default', 'true'),
('version', '$version'),
('currency', 'BTC'),
('balance', 'N/A'),
('reverse_proxy', 'on'),
('last_balance_check', '0'),
('default_captcha', 'RainCaptcha'),
('ip_ban_list', ''),
('hostname_ban_list', ''),
('address_ban_list', ''),
('block_adblock', 'on'),
('button_timer', '5'),
('safety_limits_end_time', ''),
('iframe_sameorigin_only', ''),
('asn_ban_list', ''),
('country_ban_list', ''),
('nastyhosts_enabled', ''),
('service', 'cryptoo'),
('ip_white_list', ''),
('update_last_check', ''),
('trof_hide_faucet_balance', 'no'),
('trof_rewards_view_mode', 'range'),
('trof_donate_name_0', ''),
('trof_donate_url_0', ''),
('trof_donate_address_0', ''),
('trof_donate_name_1', ''),
('trof_donate_url_1', ''),
('trof_donate_address_1', ''),
('trof_donate_name_2', ''),
('trof_donate_url_2', ''),
('trof_donate_address_3', ''),
('trof_donate_name_4', ''),
('trof_donate_url_4', ''),
('trof_donate_address_4', ''),
('trof_donate_name_5', ''),
('trof_donate_url_5', ''),
('trof_donate_address_5', ''),
('trof_allow_donations', '1'),
('trof_exchange_list_mode', 'always')
;
QUERY;

/**/
global $new_settings_query;
$new_settings_query = <<<NEW_SETTINGS_QUERY
INSERT IGNORE INTO wpf_Faucet_Settings (name, value) VALUES
('coinhive_site_key', ''), 
('coinhive_secret_key', ''), 
('raincaptcha_public_key', ''),
('raincaptcha_secret_key', ''),
('cryptoloot_site_key', ''), 
('cryptoloot_secret_key', ''), 
('cryptoloot_hashes', '512') 
;
NEW_SETTINGS_QUERY;


/**/
function addNewSettings(){
	$mustHaveField = 'cryptoloot_hashes'; //must be last one of the new
	global $sql;
	$t = "SELECT name, value FROM wpf_Faucet_Settings WHERE name = '$mustHaveField'";
	$q = $sql->query($t);
	$row = $q->fetch();
	if($row['name'] != $mustHaveField)
	{
		global $new_settings_query;
		$sql->query($new_settings_query);
	}
}//addNewSettings()

/*
TROF TODO: 
captcha valid time
donations
trof_apisecret XAPO
long lines to separate file, locales
*/

function randHash($length) {
    $alphabet = str_split('qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM1234567890');
    $hash = '';
    for($i = 0; $i < $length; $i++) {
        $hash .= $alphabet[array_rand($alphabet)];
    }
    return $hash;
}

function getNastyHostsServer() {
    return "http://v1.nastyhosts.com/";
}

function checkRevProxyIp($file) {
    require_once(dirname( __FILE__ ) ."/../libs/http-foundation/IpUtils.php");
    return IpUtils::checkIp($_SERVER['REMOTE_ADDR'], array_map(function($v) { return trim($v); }, file($file)));
}

function detectRevProxyProvider() {
    if(checkRevProxyIp(dirname( __FILE__ ) ."/../libs/ips/cloudflare.txt")) {
        return "CloudFlare";
    } elseif(checkRevProxyIp(dirname( __FILE__ ) ."/../libs/ips/incapsula.txt")) {
        return "Incapsula";
    }
    return "none";
}

function getIP($extra_sql = null) { //TROF extra_sql
    global $sql;
    static $cache_ip;
    if ($cache_ip) return $cache_ip;
    $ip = null;
	if($extra_sql != null) //TROF
	{
		$sql = $extra_sql;
	}

    $type = $sql->query("SELECT `value` FROM `wpf_Faucet_Settings` WHERE `name` = 'reverse_proxy'")->fetch();
    if ($type && $type[0] == "on") {
        if (checkRevProxyIp(dirname( __FILE__ ) ."/../libs/ips/cloudflare.txt")) {
            $ip = array_key_exists('HTTP_CF_CONNECTING_IP', $_SERVER) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : null;
        } elseif (checkRevProxyIp(dirname( __FILE__ ) ."/../libs/ips/incapsula.txt")) {
            $ip = array_key_exists('HTTP_INCAP_CLIENT_IP', $_SERVER) ? $_SERVER['HTTP_INCAP_CLIENT_IP'] : null;
        }
    }
    if (empty($ip)) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    $cache_ip = $ip;
    return $ip;
}
 
function trof_is_ssl(){ //is_ssl() is WP func
    if(isset($_SERVER['HTTPS'])){
        if('on' == strtolower($_SERVER['HTTPS']))
            return true;
        if('1' == $_SERVER['HTTPS'])
            return true;
        if(true == $_SERVER['HTTPS'])
            return true;
    }elseif(isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])){
        return true;
    }
    if(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https') {
        return true;
    }
    return false;
}

function ipSubnetCheck ($ip, $network) {
    $network = explode("/", $network);
    $net = $network[0];

    if(count($network) > 1) {
        $mask = $network[1];
    } else {
        $mask = 32;
    }

    $net = ip2long ($net);
    $mask = ~((1 << (32 - $mask)) - 1);

    $ip_net = $ip & $mask;

    return ($ip_net == $net);
}

function suspicious($server, $comment) {
    if($server) {
        trof_wp_file_get_contents($server."report/1/".urlencode(getIP())."/".urlencode($comment));
    }
}

// check if configured
try {
    $pass = $sql->query("SELECT `value` FROM `wpf_Faucet_Settings` WHERE `name` = 'password'")->fetch();
} catch(PDOException $e) {
	if ($display_errors) echo("Not configured yet? Can't connect to database. Details: ".$e->getMessage()); //echo, not die
    $pass = null;
}

if ($pass) {
    // check db updates
    $dbversion = $sql->query("SELECT `value` FROM `wpf_Faucet_Settings` WHERE `name` = 'version'")->fetch();
    if ($dbversion) {
        $dbversion = intval($dbversion[0]);
    } else {
        $dbversion = -1;
    }
    foreach ($db_updates as $v => $update) {
        if($v > $dbversion) {
            foreach($update as $query) {
                $sql->exec($query);
            }
        }
    }
    if ($dbversion < 17) {
        // dogecoin changed from satoshi to doge
        // better clear rewards...
        $c = $sql->query("SELECT `value` FROM `wpf_Faucet_Settings` WHERE `name` = 'currency'")->fetch();
        if($c[0] == 'DOGE')
            $sql->exec("UPDATE `wpf_Faucet_Settings` SET `value` = '' WHERE name = 'rewards'");
    }
    if (intval($version) > intval($dbversion)) {
        $q = $sql->prepare("UPDATE `wpf_Faucet_Settings` SET `value` = ? WHERE `name` = 'version'");
        $q->execute(array($version));
    }

//TROF - called in the fetcher added headers_sent()
	if(!headers_sent())
	{
		$iframe_sameorigin_only = $sql->query("SELECT `value` FROM  `wpf_Faucet_Settings` WHERE `name` = 'iframe_sameorigin_only'")->fetch();
		if ($iframe_sameorigin_only && $iframe_sameorigin_only[0] == "on") { 
			header("X-Frame-Options: SAMEORIGIN");
		}
	}

    $security_settings = [];
    $nastyhosts_enabled = $sql->query("SELECT `value` FROM `wpf_Faucet_Settings` WHERE `name` = 'nastyhosts_enabled' ")->fetch();
    if ($nastyhosts_enabled && $nastyhosts_enabled[0]) {
        $security_settings["nastyhosts_enabled"] = true;
    } else {
        $security_settings["nastyhosts_enabled"] = false;
    }

    $q = $sql->query("SELECT `name`, `value` FROM `wpf_Faucet_Settings` WHERE `name` in ('ip_ban_list', 'ip_white_list', 'hostname_ban_list', 'address_ban_list', 'asn_ban_list', 'country_ban_list')");
    while ($row = $q->fetch()) {
        if (stripos($row["name"], "_list") !== false) {
            $security_settings[$row["name"]] = array();
            if (preg_match_all("/[^,;\s]+/", $row["value"], $matches)) {
                foreach($matches[0] as $m) {
                    $security_settings[$row["name"]][] = $m;
                }
            }
        } else {
            $security_settings[$row["name"]] = $row["value"];
        }
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $fake_address_input_used = false;
        if (!empty($_POST["address"])) {
            $fake_address_input_used = true;
        }
    }
}


function get_f2_ref_html($data)
{
//	global $data;
	$pluso_code = <<<PLUSO
<script type="text/javascript">(function() {
  if (window.pluso)if (typeof window.pluso.start == "function") return;
  if (window.ifpluso==undefined) { window.ifpluso = 1;
    var d = document, s = d.createElement('script'), g = 'getElementsByTagName';
    s.type = 'text/javascript'; s.charset='UTF-8'; s.async = true;
    s.src = ('https:' == window.location.protocol ? 'https' : 'http')  + '://share.pluso.ru/pluso-like.js';
    var h=d[g]('body')[0];
    h.appendChild(s);
  }})();</script>
<div class="pluso" data-background="transparent" data-options="small,square,line,horizontal,counter,theme=03" data-services="facebook,twitter,vkontakte,google,delicious,tumblr,digg,blogger,linkedin,odnoklassniki,stumbleupon" data-url="{URL}" data-title="{TITLE}"  data-lang="en"></div>
PLUSO;
	$pluso_code = str_replace('{URL}',$data["reflink"],$pluso_code);
	$pluso_code = str_replace('{TITLE}',$data["name"],$pluso_code);
	$pluso_code = str_replace('{DESC}','de',$pluso_code);
	$url_line = str_replace(array('&','?'),array('&#8203;&','&#8203;?'),$data["reflink"]); //&#8203; = zero char to break line
	$url_line = $data["reflink"]; //ATT - char break copies into lipboard and screws real URL!!!


//global $f2_ref_html;
	$f2_ref_html_switch_js = "jQuery('div#f2_ref_stub').slideUp('slow',function(){jQuery('div#f2_ref_full').slideDown('slow');})";
	$f2_ref_html = '';
	$f2_ref_html .= '<div id="f2_ref_wrap" class="text-left alert alert-success">';
	$f2_ref_html .= '<div id="f2_ref_stub"><a href="#" onclick="'.$f2_ref_html_switch_js.'">'.  __( 'Share and earn ', 'wpbftd' ) . ' ' . $data["referral"] . '%'. '</a></div>'; 
	$f2_ref_html .= '<div id="f2_ref_full" style="display:none;">'; 
	$f2_ref_html .= '<textarea style="width:100%;" onclick="this.select()">'.$url_line.'</textarea><br>'; 
	$f2_ref_html .= __( 'Share this link with your friends and earn', 'wpbftd' ) . '  ' . $data["referral"] . '%'. ' ' . __( 'referral commission', 'wpbftd' );
	$f2_ref_html .= '</div>'; //hidden block "f2_ref_full"
	$f2_ref_html .= $pluso_code;
	$f2_ref_html .= '</div>';//big one "f2_ref_wrap"
	return($f2_ref_html);
}

function trof_notify_low_balance($faucet_name,$balance,$max_reward){

	$user_info = get_userdata(1);
	$current_locale = get_locale(); //gotta switch back  after email is sent
	$owner_locale = get_user_locale($wp_user_id); //gotta send email in language of bond owner
	switch_to_locale($owner_locale);	
	$user_name = $user_info->display_name;
	$user_email = $user_info->user_email;
	$site_title = get_bloginfo('name');

	$site_description = get_bloginfo('description');
	$site_url = get_bloginfo('url');
	$faucet_admin_url = get_admin_url(null,'/options-general.php?page=bitcoin_faucet');
	
	$email_text = __(" 	Hello %%USERNAME%%<br/>
		Faucet <a href='%%FAUCETADMINURL%%'>%%FAUCETNAME%%</a> has only %%COINCOUNT%% satoshi left.<br/>
		Please <a href='https://cryptoo.me/deposit'>deposit some funds</a> to keep it running.<br/>
		You may also like to <a href='%%FAUCETADMINURL%%'>adjust the configuration</a> of this faucet.<br/>
		<br>
		Kind regards,<br/>
		<a href='%%SITEURL%%'>%%SITENAME%%</a> 
		", 'wpbftd' );
		
	$email_text = str_replace('%%USERNAME%%',$user_name,$email_text);
	$email_text = str_replace('%%FAUCETNAME%%',$faucet_name,$email_text);
	$email_text = str_replace('%%COINCOUNT%%',$balance,$email_text);
	$email_text = str_replace('%%SITENAME%%',$site_description,$email_text);
	$email_text = str_replace('%%SITEURL%%',$site_url,$email_text);
	$email_text = str_replace('%%FAUCETADMINURL%%',$faucet_admin_url,$email_text);
	$email_subject = __("Faucet '%%FAUCETNAME%%' has low balance !", 'wpbftd' );
	$email_subject = str_replace('%%FAUCETNAME%%',$faucet_name,$email_subject);

	$headers = array(
		'content-type: text/html', //must have
	);
	wp_mail( $user_email, $email_subject, $email_text , $headers );
}//trof_notify_low_balance()


