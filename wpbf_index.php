<?php
namespace wpbf_bitcoin_faucet;
use \PDO;
use \PDOException;


if ( ! defined( 'ABSPATH' ) ) die('nope!'); // Exit if accessed directly
require_once(dirname( __FILE__ ) . "/script/common.php");
global $data; //TROF
global $captcha;//TROF
global $trof_wpbf_ajax_mode;
//global $sql;//TROF

//params set by shortcodes in config.php
global $trof_wps_faucet_name;
global $trof_wps_faucet_description;
global $trof_wps_faucet_rewards;
global $trof_wps_faucet_timer;



$index_name = __( 'Index', 'wpbftd' );
//echo($index_name);


if (!$pass) {
    // first run
 //   header("Location: admin.php");
    die("Not configured! <a href='$trof_admin_url'>"."Please visit Admin section first"."</a>");
}

if (array_key_exists("p", $_GET) && in_array($_GET["p"], ["admin", "password-reset"])) {
 //   header("Location: admin.php");
    die("<a href='$trof_admin_url'>"."Not configured!"."</a>");
}

#reCaptcha template
$recaptcha_template = <<<TEMPLATE
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<div class="g-recaptcha" data-sitekey="<:: your_site_key ::>"></div>
<noscript>
  <div style="width: 302px; height: 352px;">
    <div style="width: 302px; height: 352px; position: relative;">
      <div style="width: 302px; height: 352px; position: absolute;">
        <iframe src="https://www.google.com/recaptcha/api/fallback?k=<:: your_site_key ::>"
                frameborder="0" scrolling="no"
                style="width: 302px; height:352px; border-style: none;">
        </iframe>
      </div>
      <div style="width: 250px; height: 80px; position: absolute; border-style: none;
                  bottom: 21px; left: 25px; margin: 0px; padding: 0px; right: 25px;">
        <textarea id="g-recaptcha-response" name="g-recaptcha-response"
                  class="g-recaptcha-response"
                  style="width: 250px; height: 80px; border: 1px solid #c1c1c1;
                         margin: 0px; padding: 0px; resize: none;" value="">
        </textarea>
      </div>
    </div>
  </div>
</noscript>
TEMPLATE;

//TROF  - separate ajax ih fetcher now
/*
if (!empty($_POST["mmc"])) {
    $_SESSION["$session_prefix-mouse_movement_detected"] = true;
    die();
}
*/

// Check functions

function checkTimeForIP($ip, &$time_left = NULL) {
    global $sql, $data;
    $q = $sql->prepare("SELECT TIMESTAMPDIFF(MINUTE, last_used, CURRENT_TIMESTAMP()) FROM wpf_Faucet_IPs WHERE ip = ?");
    $q->execute([$ip]);
    if ($time = $q->fetch()) {
        $time = intval($time[0]);
        $required = intval($data["timer"]);
        
        $time_left = $required-$time;
        return $time >= intval($data["timer"]);
    } else {
        $time_left = 0;
        return true;
    }
}

function checkTimeForAddress($address, &$time_left) {
    global $sql, $data;
    $q = $sql->prepare("SELECT TIMESTAMPDIFF(MINUTE, last_used, CURRENT_TIMESTAMP()) FROM wpf_Faucet_Addresses WHERE `address` = ?");
    $q->execute([$address]);
    if ($time = $q->fetch()) {
        $time = intval($time[0]);
        $required = intval($data["timer"]);

        $time_left = $required-$time;
        return $time >= intval($data["timer"]);
    } else {
        $time_left = 0;
        return true;
    }
}

function checkAddressValidity($address) {
    return (preg_match("/^[0-9A-Za-z]{26,110}$/", $address) === 1);
}

function checkAddressBlacklist($address) {
    global $security_settings;
    return !in_array($address, $security_settings["address_ban_list"]);
}

function checkIPIsWhitelisted() {
    global $security_settings;
    $ip = ip2long(getIP());
    if ($ip) { // only ipv4 supported here
        foreach ($security_settings["ip_white_list"] as $whitelisted) {
            if (ipSubnetCheck($ip, $whitelisted)) {
                return true;
            }
        }
    }
    return false;
}

function checkIPBlacklist() {
    global $security_settings;
    $ip = ip2long(getIP());
    if ($ip) { // only ipv4 supported here
        foreach ($security_settings["ip_ban_list"] as $ban) {
            if (ipSubnetCheck($ip, $ban)) {
                trigger_error("Banned: ".getIP()." (blacklist: {$ban})");
                return false;
            }
        }
    }
    return true;
}


function cacheNastyIP($ip)
{
	$cacheNastyIPs = array();
	$cacheNastyIPs = get_transient("BF2_NastyIPs");	
	array_push($cacheNastyIPs, $ip . ',' .time() );
	set_transient("BF2_NastyIPs",$cacheNastyIPs,60*60*24);
}

function isCachedNastyIP($ip)
{
	$isNasty = FALSE;
	$oldCacheNastyIPs = array();
	$oldCacheNastyIPs = get_transient("BF2_NastyIPs");
	$newCacheNastyIPs = array();
	$expiredTime = time() - (60*60*24);
	for($i = 0; $i < count($oldCacheNastyIPs); $i++)
	{
		$ipRecord = explode(",",$oldCacheNastyIPs[$i]);
		$ip_addr = $ipRecord[0];
		$ip_time = $ipRecord[1];
		if($ip_time > $expiredTime)
		{
			array_push($newCacheNastyIPs,$oldCacheNastyIPs[$i]);
		}
		if($ip_addr == $ip)
		{
			$isNasty = TRUE;
		}
	}
	$newCacheNastyIPs = array_slice($newCacheNastyIPs, 0, 1000,TRUE);//just in case
	set_transient("BF2_NastyIPs",$newCacheNastyIPs,60*60*24); 
	return $isNasty;
}


function checkNastyHosts() {
    global $security_settings;
    if ($security_settings["nastyhosts_enabled"]) {
		$ip = getIP();

		if(isCachedNastyIP($ip)){
            trigger_error("Banned: ".$ip." (cached NastyHosts)");
            return false;		
		}
 	
		$hostnames = trof_wp_file_get_contents(getNastyHostsServer().$ip.'?source=fiab');
        $hostnames = json_decode($hostnames);

        if ($hostnames && property_exists($hostnames, "status") && $hostnames->status == 200) {
            if (property_exists($hostnames, "suggestion") && $hostnames->suggestion == "deny") {
				cacheNastyIP($ip);
                trigger_error("Banned: ".$ip." (NastyHosts)");
                return false;
            }
            if (property_exists($hostnames, "asn") && property_exists($hostnames->asn, "asn")) {
                foreach ($security_settings["asn_ban_list"] as $ban) {
                    if ($ban == $hostnames->asn->asn) {
                        trigger_error("Banned: ".$ip." (ASN: {$ban})");
                        return false;
                    }
                }
            }
            if (property_exists($hostnames, "country") && property_exists($hostnames->country, "code")) {
                foreach ($security_settings["country_ban_list"] as $ban) {
                    if ($ban == $hostnames->country->code) {
                        trigger_error("Banned: ".$ip." (country: {$ban})");
                        return false;
                    }
                }
            }
            if (property_exists($hostnames, "hostnames")) {
                foreach ($security_settings["hostname_ban_list"] as $ban) {
                    foreach ($hostnames->hostnames as $hostname) {
                        if (stripos($hostname, $ban) !== false) {
                            trigger_error("Banned: ".$ip." (hostname: {$ban})");
                            return false;
                        }
                    }
                }
            }
        } else {
			return true; //if NastyHostsServer is down - presume it's ok IP
            // nastyhosts down or status != 200
            trigger_error("Couldn't connect to NastyHost, refusing to payout!");
            return false;
        }
    }
    return true;
}

function checkCaptcha() {
    global $data, $captcha;
    
    switch ($captcha["selected"]) {
        case "SolveMedia":
            require_once(dirname( __FILE__ ) . "/libs/solvemedialib.php");
            $resp = solvemedia_check_answer(
                $data["solvemedia_verification_key"],
                getIP(),
                (array_key_exists("adcopy_challenge", $_POST) ? $_POST["adcopy_challenge"] : ""),
                (array_key_exists("adcopy_response", $_POST) ? $_POST["adcopy_response"] : ""),
                $data["solvemedia_auth_key"]
            );
            return $resp->is_valid;
        break;
        case "reCaptcha":
            $url = "https://www.google.com/recaptcha/api/siteverify?secret=".$data["recaptcha_private_key"]."&response=".(array_key_exists("g-recaptcha-response", $_POST) ? $_POST["g-recaptcha-response"] : "")."&remoteip=".getIP();
			
            $resp = json_decode(trof_wp_file_get_contents($url), true);
			
            return $resp["success"];
        break;
        case "FunCaptcha":
            require_once(dirname( __FILE__ ) . "/libs/funcaptcha.php");
            $funcaptcha = new FUNCAPTCHA();
            return $funcaptcha->checkResult($data["funcaptcha_private_key"]);
        break;
        case 'CoinHive':
            require_once(dirname( __FILE__ ) . '/libs/coinhive.php');
            $coinhiveobj = new coinhive();
            return $coinhiveobj->checkResult($_POST['coinhive-captcha-token']);
        break;
        case 'CryptoLoot':
            require_once(dirname( __FILE__ ) . '/libs/cryptoloot.php');
            $cryptolootobj = new cryptoloot();
            return $cryptolootobj->checkResult($_POST['CRLT-captcha-token']);
        break;		
        case 'RainCaptcha':
			$client = new \SoapClient('https://raincaptcha.com/captcha.wsdl');
			$response = $client->send($data['raincaptcha_secret_key'], $_POST['rain-captcha-response'], getIP());
			if ($response->status === 1) {
				return true;
			} else {
				return false;
			}
        break;
		
		
    }
    
    return false;
}

function releaseAddressLock($address) {
    global $sql;
    $q = $sql->prepare("DELETE FROM wpf_Faucet_Address_Locks WHERE address = ?");
    $q->execute([$address]);
}

function claimAddressLock($address) {
    global $sql;
    $q = $sql->prepare("DELETE FROM wpf_Faucet_Address_Locks WHERE address = ? AND TIMESTAMPDIFF(MINUTE, locked_since, CURRENT_TIMESTAMP()) > 5");
    $q->execute([$address]);
    $q = $sql->prepare("INSERT INTO wpf_Faucet_Address_Locks (address, locked_since) VALUES (?, CURRENT_TIMESTAMP())");
    try {
        $q->execute([$address]);
    } catch (PDOException $e) {
        if($e->getCode() == 23000) {
            return false;
        } else {
            throw $e;
        }
    }
    register_shutdown_function('wpbf_bitcoin_faucet\releaseAddressLock', $address); //ATT! - namespace
    return true;
}

function releaseIPLock($ip) {
    global $sql;
    $q = $sql->prepare("DELETE FROM wpf_Faucet_IP_Locks WHERE ip = ?");
    $q->execute([$ip]);
}

function claimIPLock($ip) {
    global $sql;
    $q = $sql->prepare("DELETE FROM wpf_Faucet_IP_Locks WHERE ip = ? AND TIMESTAMPDIFF(MINUTE, locked_since, CURRENT_TIMESTAMP()) > 5");
    $q->execute([$ip]);
    $q = $sql->prepare("INSERT INTO wpf_Faucet_IP_Locks (ip, locked_since) VALUES (?, CURRENT_TIMESTAMP())");
    try {
        $q->execute([$ip]);
    } catch (PDOException $e) {
        if($e->getCode() == 23000) {
            return false;
        } else {
            throw $e;
        }
    }
    register_shutdown_function('wpbf_bitcoin_faucet\releaseIPLock', $ip); //ATT- namespace
    return true;
}

function getClaimError($address) {
    if (!claimAddressLock($address)) {
        return "You were locked for multiple claims, try again in 5 " . __( 'minutes', 'wpbftd' );
    }
    if (!claimIPLock(getIP())) {
        return "You were locked for multiple claims, try again in 5 " . __( 'minutes', 'wpbftd' );
    }
    if ( (!checkAddressValidity($address)) && (!filter_var($address, FILTER_VALIDATE_EMAIL)) ){
        return __( 'Invalid address', 'wpbftd' );
    }
    if (!checkCaptcha()) {
        return __( 'Invalid captcha code', 'wpbftd' );;
    }
    if (!checkTimeForAddress($address, $time_left)) {
        return __( 'You have to wait', 'wpbftd' ) . " {$time_left} " . __( 'minutes', 'wpbftd' );
    }
    if (!checkTimeForIP(getIP(), $time_left)) {
        return __( 'You have to wait', 'wpbftd' ) . " {$time_left} " . __( 'minutes', 'wpbftd' );
    }
    if (!checkAddressBlacklist($address)) {
        return __( 'Unknown error', 'wpbftd' );
    }
    if(!checkIPIsWhitelisted()) {
        if (!checkIPBlacklist()) {
            return __( 'Unknown error', 'wpbftd' );
        }
        if (!checkNastyHosts()) {
            return __( 'Unknown error', 'wpbftd' );
        }
    }
    return null;
}



// Get template
$q = $sql->query("SELECT value FROM wpf_Faucet_Settings WHERE name = 'template'");
$template = $q->fetch();
$template = $template[0];
if (!file_exists(dirname( __FILE__ ) . "/templates/{$template}/index.php")) {
    $templates = glob("templates/*");
    if ($templates)
        $template = substr($templates[0], strlen("templates/"));
    else
        die(str_replace('<:: content ::>', "<div class='alert alert-danger' role='alert'>No templates found!</div>", $master_template));
}

// Check protocol
if (array_key_exists("HTTPS", $_SERVER) && $_SERVER["HTTPS"])
    $protocol = "https://";
else
    $protocol = "http://";


// Get address
if (array_key_exists("$session_prefix-address_input_name", $_SESSION) && array_key_exists($_SESSION["$session_prefix-address_input_name"], $_POST)) {
    $_POST["address"] = $_POST[$_SESSION["$session_prefix-address_input_name"]];
} else {
    if ($_SERVER['REQUEST_METHOD'] == "POST") {
//print_r($_SESSION);		
        if (array_key_exists("$session_prefix-address_input_name", $_SESSION)) {
            trigger_error("Post request, but invalid address input name.");
        } else {
            trigger_error("Post request, but session is invalid.");
        }
    }
    unset($_POST["address"]);
}

$ref_url = $protocol.$_SERVER['HTTP_HOST'].strtok($_SERVER['REQUEST_URI'], '?');
if($trof_wpbf_ajax_mode)
{
	$ref_url =  $_SERVER['HTTP_REFERER']; //TROF
	$param_start_pos = strpos($ref_url,'?');
	if($param_start_pos)
	{
		$ref_url = substr($ref_url,0,$param_start_pos);
	}
}

$data = array(
    "paid" => false,
    "disable_admin_panel" => $disable_admin_panel,
    "address" => "",
    "captcha_valid" => true, //for people who won't update templates
    "captcha" => false,
    "enabled" => false,
    "error" => false,
    "address_eligible" => true,
	"reflink" => $ref_url .'?r=',	//we are in ajax here!
//    "reflink" => $protocol.$_SERVER['HTTP_HOST'].strtok($_SERVER['REQUEST_URI'], '?').'?r='
);


// Show ref link
if (array_key_exists('address', $_POST)) {
    $data["reflink"] .= sanitize_text_field($_POST['address']);
} else if (array_key_exists('address', $_COOKIE)) {
    $data["reflink"] .= $_COOKIE['address'];
    $data["address"] = $_COOKIE['address'];
} else {
    $data["reflink"] .= 'Your_Address';
}

addNewSettings();
// Get settings from DB
$q = $sql->query("SELECT name, value FROM wpf_Faucet_Settings WHERE name <> 'password'");
while ($row = $q->fetch()) {
    if ($row[0] == "safety_limits_end_time") {
        $time = strtotime($row[1]);
        if ($time !== false && $time < time()) {
            $row[1] = "";
        }
    }
    $data[$row[0]] = $row[1];
}

//echo('HERE WE HAVE ALL THE DATA: ');print_r($data);
if($trof_wps_faucet_name !== NULL) {$data['name'] = $trof_wps_faucet_name; }
if($trof_wps_faucet_description !== NULL) {$data['short'] = $trof_wps_faucet_description; }
if($trof_wps_faucet_rewards !== NULL) {$data['rewards'] = $trof_wps_faucet_rewards; }
if($trof_wps_faucet_timer !== NULL) {$data['timer'] = $trof_wps_faucet_timer; }



// Set unit name
$data['unit'] = 'satoshi';
if ($data["currency"] == 'DOGE')
    $data["unit"] = 'DOGE';

// Update balance
//trof_check_update_balance();
if ((time() - $data['last_balance_check'] > 60*10)   ) { 
	 trof_check_update_balance();
}

//print_r($data);
#MuliCaptcha: Firstly check chosen captcha system

$captcha = array('available' => array(), 'selected' => null);
if (($data['default_captcha'] == 'SolveMedia') && $data['solvemedia_challenge_key'] && $data['solvemedia_verification_key'] && $data['solvemedia_auth_key']) {
    $captcha['available'][] = 'SolveMedia';
}
if (($data['default_captcha'] == 'reCaptcha') && $data['recaptcha_public_key'] && $data['recaptcha_private_key']) {
    $captcha['available'][] = 'reCaptcha';
}
if (($data['default_captcha'] == 'FunCaptcha') && $data['funcaptcha_public_key'] && $data['funcaptcha_private_key']) {
    $captcha['available'][] = 'FunCaptcha';
}
if (($data['default_captcha'] == 'CoinHive') && $data['coinhive_site_key'] && $data['coinhive_secret_key']) {
//    $captcha['available'][] = 'CoinHive'; //DISABLED
}
if (($data['default_captcha'] == 'CryptoLoot') && $data['cryptoloot_site_key'] && $data['cryptoloot_secret_key']) {
    $captcha['available'][] = 'CryptoLoot';
}
if (($data['default_captcha'] == 'RainCaptcha') && $data['raincaptcha_public_key'] && $data['raincaptcha_secret_key']) {
    $captcha['available'][] = 'RainCaptcha'; 
}


#MuliCaptcha: Secondly check if user switched captcha or choose default
if (array_key_exists('cc', $_GET) && in_array($_GET['cc'], $captcha['available'])) {
    $captcha['selected'] = $captcha['available'][array_search($_GET['cc'], $captcha['available'])];
    $_SESSION["$session_prefix-selected_captcha"] = $captcha['selected'];
} elseif (array_key_exists("$session_prefix-selected_captcha", $_SESSION) && in_array($_SESSION["$session_prefix-selected_captcha"], $captcha['available'])) {
    $captcha['selected'] = $_SESSION["$session_prefix-selected_captcha"];
} else {
    if ($captcha['available'])
        $captcha['selected'] = $captcha['available'][0];
    if (in_array($data['default_captcha'], $captcha['available'])) {
        $captcha['selected'] = $data['default_captcha'];
    } else if ($captcha['available']) {
        $captcha['selected'] = $captcha['available'][0];
    }
}


#MuliCaptcha: And finally handle chosen captcha system
# -> checkCaptcha()
switch ($captcha['selected']) {
    case 'SolveMedia':
        require_once(dirname( __FILE__ ) . "/libs/solvemedialib.php");
        $data["captcha"] = solvemedia_get_html($data["solvemedia_challenge_key"], null, trof_is_ssl());
    break;
    case 'reCaptcha':
        $data["captcha"] = str_replace('<:: your_site_key ::>', $data["recaptcha_public_key"], $recaptcha_template);
    break;
    case 'FunCaptcha':
        require_once(dirname( __FILE__ ) . "/libs/funcaptcha.php");
        $funcaptcha = new FUNCAPTCHA();
        $data["captcha"] =  $funcaptcha->getFunCaptcha($data["funcaptcha_public_key"]);
    break;
   case 'CoinHive':
        $data['captcha'] = '
<div class="coinhive-captcha" style="margin-left:auto;margin-right:auto;width:304px;" data-hashes="1024" data-key="'.$data['coinhive_site_key'].'">
    <em>Loading Captcha...<br>If it doesn\'t load, please disable Adblock!</em>
</div>
<script src="https://authedmine.com/lib/captcha.min.js" async></script>';
    break;
   case 'CryptoLoot':
        $data['captcha'] = '
<div class="CRLT-captcha" style="margin-left:auto;margin-right:auto;width:304px;" data-hashes="'.$data['cryptoloot_hashes'].'" data-key="'.$data['cryptoloot_site_key'].'" >
    <em>Loading Captcha...<br>If it doesn\'t load, please disable Adblock!</em>
</div>
<script src="https://verifypow.com/lib/captcha.min.js" async></script>';
    break;	
    case 'RainCaptcha':
      $data['captcha'] = '<div style="text-align:center;"><script src="https://raincaptcha.com/base.js" type="text/javascript"></script><div id="rain-captcha" data-key="'.$data['raincaptcha_public_key'].'"></div></div>';
    break;
	
	
}

$data['captcha_info'] = $captcha;

// Check if faucet's enabled
if ($data['captcha'] && $data['apikey'] && $data['rewards'])
    $data['enabled'] = true;


// check if IP eligible
//var_dump( $sql);
$data["eligible"] = checkTimeForIP(getIP($sql), $time_left); ////TROF sql added as a parameter
$data['time_left'] = $time_left." " . __( 'minutes', 'wpbftd' );
$data['raw_rewards'] = $data['rewards'];

//print_r($data['rewards']);
// Rewards
$rewards = explode(',', $data['rewards']);
$total_weight = 0;
$nrewards = array();
foreach ($rewards as $reward) {
    $reward = explode("*", trim($reward));
    if (count($reward) < 2) {
        $reward[1] = $reward[0];
        $reward[0] = 1;
    }
    $total_weight += intval($reward[0]);
    $nrewards[] = $reward;
}
$rewards = $nrewards;
if (count($rewards) > 1) {
    $possible_rewards = array();
    foreach ($rewards as $r) {
        $chance_per = 100 * $r[0]/$total_weight;
        if ($chance_per < 0.1)
            $chance_per = '< 0.1%';
        else
            $chance_per = round(floor($chance_per*10)/10, 1).'%';

        $possible_rewards[] = $r[1]." ($chance_per)";
    }
} else {
    $possible_rewards = array($rewards[0][1]);
}

//TROF if trof_rewards_view_mode is 'range' we just going to overwrite $possible_rewards
//TODO - use trof_get_rewards_range() from common.php
if($data['trof_rewards_view_mode'] == 'range') 
{
	$a_rewards = explode(', ', $data['rewards']); //10*11-22, 20*66, 30*33-44
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
		$possible_rewards = array($a_singles[0]);
	}
	else //have several
	{
		$possible_rewards = array($a_singles[0] . '-' .  $a_singles[ count($a_singles) - 1 ] );
	}
}



if (array_key_exists('address', $_POST) && $data['enabled'] && $data['eligible']) {
    
    $address = sanitize_text_field(trim($_POST["address"]));

    if(empty($data['address']))
        $data['address'] = $address;

    $error = getClaimError($address);
    if ($error) {
        $data["error"] = "<div class=\"alert alert-danger\">{$error}</div>";
    } else {
        
        // Rand amount
        $r = mt_rand()/mt_getrandmax();
        $t = 0;
        foreach ($rewards as $reward) {
            $t += intval($reward[0])/$total_weight;
            if ($t > $r) {
                break;
            }
        }
        if (strpos($reward[1], '-') !== false) {
            $reward_range = explode('-', $reward[1]);
            $from = floatval($reward_range[0]);
            $to = floatval($reward_range[1]);
            $reward = mt_rand($from, $to);
        } else {
            $reward = floatval($reward[1]);
        }
        if ($data["currency"] == "DOGE") $reward = $reward * 100000000; 
        
        $fb = new Service($data['service'], $data["apikey"], $data["currency"], $connection_options);
        $ret = $fb->send($address, $reward, getIP());
        
        if ($ret['success']) {
//            setcookie('address', trim($_POST['address']), time() + 60*60*24*60); //TROF - doing it via javascript
            if (!empty($ret['balance'])) {
	
				$old_balance = $data['balance'];
				$min_reward = -1;
				$max_reward = -1;
				trof_get_rewards_range($data['raw_rewards'], $min_reward, $max_reward);
				if( (intval($ret['balance']) <= intval($max_reward)) && (intval($old_balance) > intval($max_reward)) ){
					trof_notify_low_balance($data['name'],$data['balance'],$max_reward);
				}
			}

            if (!empty($ret['balance'])) {
                $q = $sql->prepare("UPDATE wpf_Faucet_Settings SET `value` = ? WHERE `name` = 'balance'");

                if ($data['unit'] == 'satoshi')
                    $data['balance'] = $ret['balance'];
                else
                    $data['balance'] = $ret['balance_bitcoin'];
                $q->execute(array($data['balance']));
            }

            $sql->exec("UPDATE wpf_Faucet_Settings SET value = '' WHERE `name` = 'safety_limits_end_time' ");

            // handle refs 
			$trof_ref = ''; 
			if( (isset($_GET['r']) ) && (!isset($_POST['r']) ))
			{
				$trof_ref = $_GET['r'];
			}
			if( isset($_POST['r']) )
			{
				$trof_ref = $_POST['r'];
			}
			$trof_ref = trim(sanitize_text_field($trof_ref));
            if( (strlen($trof_ref) > 0) && ($trof_ref != $address) ) 
			{
                $q = $sql->prepare("INSERT IGNORE INTO wpf_Faucet_Refs (address) VALUES (?)");
                $q->execute(array($trof_ref)); 
//TROF for some reason nested select does not work. old DBs	?
				$q = $sql->prepare("SELECT id FROM wpf_Faucet_Refs WHERE address = ? ");
				$q->execute(array($trof_ref));				
				$ref_id = $q->fetch();
//print_r($ref_id);			
				$q = $sql->prepare("INSERT INTO wpf_Faucet_Addresses (`address`, `ref_id`, `last_used`) VALUES (?, ?, CURRENT_TIMESTAMP()) ON DUPLICATE KEY UPDATE `ref_id` = ?");
                $q->execute( array(trim($_POST['address']),trim($ref_id['id']),trim($ref_id['id']) ) );				
//				$q = $sql->prepare("INSERT IGNORE INTO wpf_Faucet_Addresses (`address`, `ref_id`, `last_used`) VALUES (?, (SELECT id FROM wpf_Faucet_Refs WHERE address = ? ), CURRENT_TIMESTAMP())");
//              $q->execute(array(trim($_POST['address']), trim($_POST['r'])));

				$refamount = floatval($data['referral'])*$reward/100;
				$q = $sql->prepare("SELECT address FROM wpf_Faucet_Refs WHERE id = (SELECT ref_id FROM wpf_Faucet_Addresses WHERE address = ?)"); //TROF - and this one does?! 
				$q->execute(array(trim($_POST['address'])));
				if ($ref = $q->fetch()) {
					if (!in_array(trim($ref[0]), $security_settings['address_ban_list'])) {
						$fb->sendReferralEarnings(trim($ref[0]), $refamount, getIP());
					}
				}
			}

            if ($data['unit'] == 'satoshi')
                $data['paid'] = $ret['html'];
            else
                $data['paid'] = $ret['html_coin'];
        } else {
            $response = json_decode($ret["response"]);
            if ($response && property_exists($response, "status") && $response->status == 450) {
                // how many minutes until next safety limits reset?
                $end_minutes  = (date("i") > 30 ? 60 : 30) - date("i");
                // what date will it be exactly?
                $end_date = date("Y-m-d H:i:s", time()+$end_minutes*60-date("s"));
                $sql->prepare("UPDATE wpf_Faucet_Settings SET value = ? WHERE `name` = 'safety_limits_end_time' ")->execute([$end_date]);
            }
            $data['error'] = $ret['html'];
        }
        if ($ret['success'] || $fb->communication_error) {
            $q = $sql->prepare("INSERT INTO wpf_Faucet_IPs (`ip`, `last_used`) VALUES (?, CURRENT_TIMESTAMP()) ON DUPLICATE KEY UPDATE `last_used` = CURRENT_TIMESTAMP()");
            $q->execute([getIP()]);
            $q = $sql->prepare("INSERT INTO wpf_Faucet_Addresses (`address`, `last_used`) VALUES (?, CURRENT_TIMESTAMP()) ON DUPLICATE KEY UPDATE `last_used` = CURRENT_TIMESTAMP()");
            $q->execute([$address]);

            // suspicious checks
            $q = $sql->query("SELECT value FROM wpf_Faucet_Settings WHERE name = 'template'");
            if ($r = $q->fetch()) {
                if (stripos(file_get_contents(dirname( __FILE__ ) . '/templates/'.$r[0].'/index.php'), 'libs/mmc.js') !== FALSE) {
                    if ($fake_address_input_used || !empty($_POST["honeypot"])) {
						if(isset($security_settings["ip_check_server"]))
						{
							suspicious($security_settings["ip_check_server"], "honeypot");
						}
                    }
                    if (empty($_SESSION["$session_prefix-mouse_movement_detected"])) {
						if(isset($security_settings["ip_check_server"]))
						{
							suspicious($security_settings["ip_check_server"], "mmc");
						}
                    }
                }
            }
        }
    }
}

if (!$data['enabled'])
    $page = 'disabled';
elseif ($data['paid'])
    $page = 'paid';
elseif ($data['eligible'] && $data['address_eligible'])
    $page = 'eligible';
else
    $page = 'visit_later';

$data['page'] = $page;

if ( ($_SERVER['PHP_SELF'] != '/wp-admin/admin-ajax.php') ////TROF ADDED  
	&& (!empty($_SERVER["HTTP_X_REQUESTED_WITH"]) 
	&& strtolower($_SERVER["HTTP_X_REQUESTED_WITH"]) === "xmlhttprequest") 
	) 
{ 

    trigger_error("AJAX call that would break session");
    die();
}

$_SESSION["$session_prefix-address_input_name"] = randHash(rand(25,35));
$data['address_input_name'] = $_SESSION["$session_prefix-address_input_name"];

$data['rewards'] = implode(', ', $possible_rewards);

$q = $sql->query("SELECT url_name, name FROM wpf_Faucet_Pages ORDER BY id");
$data["user_pages"] = $q->fetchAll();

$allowed = array("page", "name","raw_rewards", "rewards", "short", "error", "paid", "captcha_valid", "captcha", "captcha_info", "time_left", "referral", "reflink", "template", "user_pages", "timer", "unit", "address", "balance", "disable_admin_panel", "address_input_name", "block_adblock", "iframe_sameorigin_only", "currency", "button_timer", "safety_limits_end_time","trof_hide_faucet_balance","trof_rewards_view_mode","trof_exchange_list_mode","trof_allow_donations",'service');

preg_match_all('/\$data\[([\'"])(custom_(?:(?!\1).)*)\1\]/', file_get_contents( dirname( __FILE__ ) ."/templates/$template/index.php"), $matches);
foreach (array_unique($matches[2]) as $box) {
    $key = "{$box}_$template";
    if (!array_key_exists($key, $data)) {
        $data[$key] = '';
    }
    $allowed[] = $key;
}

foreach (array_keys($data) as $key) {
    if (!(in_array($key, $allowed))) {
        unset($data[$key]);
    }
}

foreach (array_keys($data) as $key) {
    if (array_key_exists($key, $data) && strpos($key, 'custom_') === 0) {
        $data[substr($key, 0, strlen($key) - strlen($template) - 1)] = $data[$key];
        unset($data[$key]);
    }
}

if (array_key_exists('p', $_GET)) {
    $q = $sql->prepare("SELECT url_name, name, html FROM wpf_Faucet_Pages WHERE url_name = ?");
    $q->execute(array($_GET['p']));
    if ($page = $q->fetch()) {
        $data['page'] = 'user_page';
        $data['user_page'] = $page;
    } else {
        $data['error'] = "<div class='alert alert-danger'>That page doesn't exist!</div>";
    }
}

$data['address'] = htmlspecialchars($data['address']);

if (!empty($_SESSION["$session_prefix-mouse_movement_detected"])) {
    unset($_SESSION["$session_prefix-mouse_movement_detected"]);
}


require_once(dirname( __FILE__ ) . '/templates/'.$template.'/index.php');

