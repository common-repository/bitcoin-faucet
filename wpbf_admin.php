<?php
namespace wpbf_bitcoin_faucet;
use \RecursiveIteratorIterator;
use \RecursiveDirectoryIterator;
use \PDO;
use \PDOException;

if ( ! defined( 'ABSPATH' ) ) die('nope!'); // Exit if accessed directly
$current_user = wp_get_current_user();
if(!user_can( $current_user, 'administrator' )) {
  wp_die('Admins only!');
}

require_once(dirname( __FILE__ ) . "/script/common.php");
require_once(dirname( __FILE__ ) . "/script/admin_templates.php");
//require_once(dirname( __FILE__ ) . "/libs/coolphpcaptcha.php");

global $trof_disable_admin_password;
global $trof_disable_version_check;
global $disable_admin_panel; //TROF


function regenerate_csrf_token() {
    global $session_prefix;
    $_SESSION["$session_prefix-csrftoken"] = base64_encode(openssl_random_pseudo_bytes(20));
}

function get_csrf_token() {
    global $session_prefix;
	$ret = '';
	if(isset($_SESSION["$session_prefix-csrftoken"])) //TROF - we don't need session for WP admin, do we ? 
	{
		$ret =  "<input type=\"hidden\" name=\"csrftoken\" value=\"". $_SESSION["$session_prefix-csrftoken"]. "\">";
	}
	return($ret);
}

function checkOneclickUpdatePossible($response) {
    global $version;

    $oneclick_update_possible = false;
    if (!empty($response['changelog'][$version]['hashes'])) {
        $hashes = $response['changelog'][$version]['hashes'];
        $oneclick_update_possible = class_exists("ZipArchive");
        foreach ($hashes as $file => $hash)  {
            if (strpos($file, 'templates/') === 0)
                continue;
            $oneclick_update_possible &=
                is_writable($file) &&
                sha1_file($file) === $hash;
        }
    }
    return $oneclick_update_possible;
}

function setNewPass() {
    global $sql;
    $alphabet = str_split('qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM1234567890');
    $password = '';
    for($i = 0; $i < 15; $i++)
        $password .= $alphabet[array_rand($alphabet)];
    $hash = crypt($password);
    $sql->query("REPLACE INTO wpf_Faucet_Settings VALUES ('password', '$hash')");
    return $password;
}


$template_updates = array(
    array(
        "test" => "/address_input_name/",
        "message" => "Name of the address field has to be updated. Please follow <a href='https://bitcointalk.org/index.php?topic=1094930.msg12231246#msg12231246'>these instructions</a>"
    ),
/*	//TROF we add mms.js in plugin files
    array(
        "test" => "/libs\/mmc\.js/",
        "message" => "Add <code>".htmlspecialchars('<script type="text/javascript" src="libs/mmc.js"></script>')."</code> after jQuery in <code>&lt;head&gt;</code> section."
    ),
*/
    array(
        "test" => "/honeypot/",
        "message" => "Add <code><pre>".htmlspecialchars('<input type="text" name="address" class="form-control" style="position: absolute; position: fixed; left: -99999px; top: -99999px; opacity: 0; width: 1px; height: 1px">')."<br>".htmlspecialchars('<input type="checkbox" name="honeypot" style="position: absolute; position: fixed; left: -99999px; top: -99999px; opacity: 0; width: 1px; height: 1px">')."</pre></code> near the input with name <code>".htmlspecialchars('<?php echo $data["address_input_name"]; ?>')."</code>."
    ),
    array(
        "test" => "/claim\-button/",
        "message" => "Add <code>claim-button</code> class to claim button. Without it button timer and adblock detection won't work"
    )
);

/* TROF - WP handles session by itself
if (session_id()) {
    if (empty($_SESSION["$session_prefix-csrftoken"])) {
        regenerate_csrf_token();
    }
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (empty($_POST["csrftoken"]) || $_SESSION["$session_prefix-csrftoken"] != $_POST["csrftoken"]) {
            trigger_error("CSRF failed! ");// die("CSRF failed!" . print_r($_POST,true));
echo("\n<br>session-..:"); print_r($_SESSION["$session_prefix-csrftoken"]);
echo("\n<br>csrftoken-..:"); print_r( $_POST["csrftoken"]);

echo("\n<br>POST:");print_r($_POST);
echo("\n<br>SESSION:");print_r($_SESSION);		
            $_POST = []; //TROF was []
            $_REQUEST =[]; //TROF was []
            $_SERVER["REQUEST_METHOD"] = "GET";
        }
    }
}
*/

if (!$pass) {
    // first run
	if ($display_errors) 
	{
		echo(" Creating the DB...");
	}
    $sql->query($default_data_query);
    $password = setNewPass();
	if($trof_disable_admin_password != true)
	{
		$page = str_replace('<:: content ::>', $pass_template, $master_template);
		$page = str_replace('<:: password ::>', $password, $page);
		die($page);
	}
}

if ($disable_admin_panel) {
    trigger_error("Admin panel disabled in config!");
    header("Location: index.php");
    die("Please wait...");
}

if($trof_disable_admin_password)
{
	$_SESSION["$session_prefix-logged_in"] = true;
}

/*
if (array_key_exists('p', $_GET) && $_GET['p'] == 'logout')
    $_SESSION = []; //TROF was []
*/
/*
if (array_key_exists('p', $_GET) && $_GET['p'] == 'password-reset') {
    $error = "";
    if (array_key_exists('dbpass', $_POST)) {
        $user_captcha = array_key_exists("captcha", $_POST) ? $_POST["captcha"] : "";
        $captcha = new FiabCoolCaptcha();
        $captcha->session_var = "$session_prefix-cool-php-captcha";
        if ($captcha->isValid($user_captcha)) {
            if ($_POST['dbpass'] == $dbpass) {
                $password = setNewPass();
                $page = str_replace('<:: content ::>', $pass_template, $master_template);
                $page = str_replace('<:: password ::>', $password, $page);
                die($page);
            } else {
                $error = $dbpass_error_template;
            }
        } else {
            $error = $captcha_error_template;
        }
    }
    $page = str_replace('<:: content ::>', $error.$pass_reset_template, $master_template);
    $page = str_replace("<:: csrftoken ::>", get_csrf_token(), $page);
    die($page);
}
*/

$invalid_key = false;
/*
if (array_key_exists('password', $_POST)) {
    $user_captcha = array_key_exists("captcha", $_POST) ? $_POST["captcha"] : "";
    $captcha = new FiabCoolCaptcha();
    $captcha->session_var = "$session_prefix-cool-php-captcha";
    if ($captcha->isValid($user_captcha)) {
        if ($pass[0] == crypt($_POST['password'], $pass[0])) {
            $_SESSION["$session_prefix-logged_in"] = true;
            header("Location: ?session_check=0");
            die();
        } else {
            $admin_login_template = $login_error_template.$admin_login_template;
        }
    } else {
        $admin_login_template = $captcha_error_template.$admin_login_template;
    }
}
*/
/*
if (array_key_exists("session_check", $_GET)) {
    if (array_key_exists("$session_prefix-logged_in", $_SESSION)) {
        header("Location: ?");
        die();
    } else {
        //show alert on login screen
        $admin_login_template = $session_error_template.$admin_login_template;
    }
}
*/

if (array_key_exists("$session_prefix-logged_in", $_SESSION)) { // logged in to admin page
//print_r("\nGOT SESSION, ENTERING ADMIN");
    //ajax
    if (array_key_exists("action", $_POST)) {

        header("Content-type: application/json");

        $response = array("status" => 404); //TROF

        switch ($_POST["action"]) {
            case "check_referrals":

                $referral = array_key_exists("referral", $_POST) ? trim(sanitize_text_field($_POST["referral"])) : "";

                $response["status"] = 200;
                $response["addresses"] = array(); //TROF

                if (strlen($referral) > 0) {

                    $q = $sql->prepare("SELECT `a`.`address`, `r`.`address` FROM `wpf_Faucet_Refs` `r` LEFT JOIN `wpf_Faucet_Addresses` `a` ON `r`.`id` = `a`.`ref_id` WHERE `r`.`address` LIKE ? ORDER BY `a`.`last_used` DESC");
                    $q->execute(["%".$referral."%"]);
                    while ($row = $q->fetch()) {
                        $response["addresses"][] = [
                            "address" => $row[0],
                            "referral" => $row[1],
                        ];
                    }

                }

            break;
        }

        die(json_encode($response));

    }

/*	
    if (array_key_exists('task', $_POST) && $_POST['task'] == 'oneclick-update') {
        function recurse_copy($copy_as_new,$src,$dst) {
            $dir = opendir($src);
            @mkdir($dst);
            while (false !== ( $file = readdir($dir)) ) {
                if (( $file != '.' ) && ( $file != '..' )) {
                    if ( is_dir($src . '/' . $file) ) {
                        recurse_copy($copy_as_new, $src . '/' . $file,$dst . '/' . $file);
                    }
                    else {
                        $dstfile = $dst.'/'.$file;
                        if (in_array(realpath($dstfile), $copy_as_new))
                            $dstfile .= ".new";
                        if (!copy($src . '/' . $file,$dstfile)) {
                            return false;
                        }
                    }
                }
            }
            closedir($dir);
            return true;
        }
        function rrmdir($dir) {
          if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
              if ($object != "." && $object != "..") {
                if (filetype($dir."/".$object) == "dir") rrmdir($dir."/".$object); else unlink($dir."/".$object);
              }
            }
            reset($objects);
            rmdir($dir);
          }
        }

        ini_set('display_errors', true);
        error_reporting(-1);
        $fb = new Service("faucetbox", null, null, $connection_options);
        $response = $fb->fiabVersionCheck();
        if (empty($response['version']) || $response['version'] == $version || !checkOneclickUpdatePossible($response)) {
            header("Location: ?update_status=fail");
            die();
        }

        $url = $response["url"];
        if ($url[0] == '/') $url = "https:$url";
        $url .= "?update=auto";

        if (!file_put_contents('update.zip', fopen($url, 'rb'))) {
            header("Location: ?update_status=fail");
            die();
        }

        $zip = new ZipArchive();
        if (!$zip->open('update.zip')) {
            unlink('update.zip');
            header("Location: ?update_status=fail");
            die();
        }

        if (!$zip->extractTo('./')) {
            unlink('update.zip');
            header("Location: ?update_status=fail");
            die();
        }

        $dir = trim($zip->getNameIndex(0), '/');
        $zip->close();
        unlink('update.zip');
        unlink("$dir/config.php");

        $modified_files = [];
        foreach ($response['changelog'][$version]['hashes'] as $file => $hash) {
            if (strpos($file, 'templates/') === 0 &&
               sha1_file($file) !== $hash
            ) {
                $modified_files[] = realpath($file);
            }
        }
        if (!recurse_copy($modified_files, $dir, '.')) {
            header("Location: ?update_status=fail");
            die();
        }
        rrmdir($dir);
        header("Location: ?update_status=success&new_files=".count($modified_files));
        die();
    }
*/

	$oneclick_update_alert = "";
/*
	$oneclick_update_alert = "";
    if (
        array_key_exists("update_status", $_GET) &&
        in_array($_GET["update_status"], ["success", "fail"])
    ) {
        if ($_GET["update_status"] == "success") {
            $oneclick_update_alert = $oneclick_update_success_template;
        } else {
            $oneclick_update_alert = $oneclick_update_fail_template;
        }
*/

    if (array_key_exists("encoded_data", $_POST)) {
        $data = base64_decode($_POST["encoded_data"]); //do not sanitize!
        if ($data) {
            parse_str($data, $tmp);
            $_POST = array_merge($_POST, $tmp);
        }
    }

    if (array_key_exists('get_options', $_POST)) {
		$_POST["get_options"] = sanitize_text_field($_POST["get_options"]);
        if (file_exists(dirname( __FILE__ ) . "/templates/{$_POST["get_options"]}/setup.php")) {
            require_once(dirname( __FILE__ ) . "/templates/{$_POST["get_options"]}/setup.php");
            die('<!-- TROF TEMPLATE OPTIONS START -->'.getTemplateOptions($sql, $_POST['get_options']).'<!-- TROF TEMPLATE OPTIONS END -->'); //TROF 
        } else {
            die('<p>No template defined options available.</p>');
        }
    } else if (
        array_key_exists("reset", $_POST) &&
        array_key_exists("factory_reset_confirm", $_POST) &&
        $_POST["factory_reset_confirm"] == "on"
    ) {
        $sql->exec("DELETE FROM wpf_Faucet_Settings WHERE name NOT LIKE '%key%' AND name != 'password'");
        $sql->exec($default_data_query);
    }
    $q = $sql->prepare("SELECT value FROM wpf_Faucet_Settings WHERE name = ?");
    $q->execute(array('apikey'));
    $apikey = $q->fetch();
    $apikey = $apikey[0];
    $q->execute(array('currency'));
    $currency = $q->fetch();
    $currency = $currency[0];
	setcookie('WPFB_CURRENT_CURRENCY', $currency, time() + 60*60*24*60);
    $q->execute(array('service'));
    $service = $q->fetch();
    $service = $service[0];
	setcookie('WPFB_CURRENT_SERVICE', $service, time() + 60*60*24*60);

    
    $fb = new Service($service, $apikey, $currency, $connection_options);
    $connection_error = '';
	$cryptoome_btc_warning = '';
    $curl_warning = '';
    $missing_configs_info = '';
    if (!empty($missing_configs)) {
        $list = '';
        foreach ($missing_configs as $missing_config) {
            $list .= str_replace(array("<:: config_name ::>", "<:: config_default ::>", "<:: config_description ::>"), array($missing_config['name'], $missing_config['default'], $missing_config['desc']), $missing_config_template);
        }
        $missing_configs_info = str_replace("<:: missing_configs ::>", $list, $missing_configs_template);
    }
    if ($fb->curl_warning) {
        $curl_warning = $curl_warning_template;
    }
    $currencies = array('BCH', 'BLK', 'BTC', 'BTX', 'DASH', 'DGB', 'DOGE', 'ETH', 'LTC', 'POT', 'PPC', 'XMR', 'XPM', 'ZEC');

    $send_coins_message = '';
    if (array_key_exists('send_coins', $_POST)) {

        $amount = array_key_exists('send_coins_amount', $_POST) ? intval($_POST['send_coins_amount']) : 0;
        $address = array_key_exists('send_coins_address', $_POST) ? trim(sanitize_text_field($_POST['send_coins_address'])) : '';

        $fb = new Service($service, $apikey, $currency, $connection_options);
        $ret = $fb->send($address, $amount, getIP());
		$trof_check_url = $fb->check_url; //TROF - so we check at right place
		
        if ($ret['success']) {
            $send_coins_message = str_replace(array('{{amount}}','{{address}}','{{trof_check_url}}'), array($amount,$address,$trof_check_url), $send_coins_success_template);
        } else {
            $send_coins_message = str_replace(array('{{amount}}','{{address}}','{{error}}'), array($amount,$address,$ret['message']), $send_coins_error_template);
        }

    }
	
    $changes_saved = "";
    if (array_key_exists('save_settings', $_POST)) { //Save pressed we are saving
		//firstly we drop balance - we are going to upadte ut later
		$q = $sql->query("UPDATE wpf_Faucet_Settings SET `value` = 0 WHERE name = 'balance'");

		
        $service = sanitize_text_field($_POST['service']);
        $currency = sanitize_text_field($_POST['currency']);
        $fb = new Service($service, sanitize_text_field($_POST['apikey']), $currency, $connection_options);
        $ret = $fb->getBalance();
		
//print_r($fb);

        if ($fb->communication_error) 
		{
            $connection_error = $connection_error_template;
        }
		
        //411 - invalid api key (FaucetSystem.com)
        if ($ret['status'] == 403 || $ret['status'] == 411) {
            $invalid_key = true;
        } elseif ($ret['status'] == 405) {
            $sql->query("UPDATE wpf_Faucet_Settings SET `value` = 0 WHERE name = 'balance'");
        } elseif (array_key_exists('balance', $ret)) {
            $q = $sql->prepare("UPDATE wpf_Faucet_Settings SET `value` = ? WHERE name = 'balance'");
            if ($currency != 'DOGE')
                $q->execute(array($ret['balance']));
            else
                $q->execute(array($ret['balance_bitcoin']));
        }

        $q = $sql->prepare("INSERT IGNORE INTO wpf_Faucet_Settings (`name`, `value`) VALUES (?, ?)");
        $template = $_POST["template"];
        preg_match_all('/\$data\[([\'"])(custom_(?:(?!\1).)*)\1\]/', file_get_contents(dirname( __FILE__ ) . "/templates/$template/index.php"), $matches);
        foreach ($matches[2] as $box)
            $q->execute(array("{$box}_$template", '')); 


        $sql->beginTransaction();
        $q = $sql->prepare("UPDATE wpf_Faucet_Settings SET value = ? WHERE name = ?");
//			$q = $sql->prepare("INSERT INTO wpf_Faucet_Settings (`name`, `value`) VALUES (?, ?)	ON DUPLICATE KEY UPDATE	`value`=? ");

//		$q = $sql->prepare("INSERT IGNORE INTO wpf_Faucet_Settings (`name`, `value`) VALUES (?, ?)");
        $ipq = $sql->prepare("INSERT INTO wpf_Faucet_Pages (url_name, name, html) VALUES (?, ?, ?)");
        $sql->exec("DELETE FROM wpf_Faucet_Pages");

        foreach ($_POST as $k => $v) {
			if( ($_POST['service'] == 'offline') && ($k == 'currency') ) {
				continue; //not going to save, so faucet shows correct currency even when offline
			}		
            if ($k == 'apikey' && $invalid_key)
                continue;
            if ($k == 'pages') {
                foreach ($_POST['pages'] as $p) {
                    $url_name = strtolower(preg_replace("/[^A-Za-z0-9_\-]/", '', $p["name"]));
                    $i = 0;
                    $success = false;
                    while (!$success) {
                        try {
                            if ($i)
                                $ipq->execute(array($url_name.'-'.$i, $p['name'], $p['html']));
                            else
                                $ipq->execute(array($url_name, $p['name'], $p['html']));
                            $success = true;
                        } catch(PDOException $e) {
                            $i++;
                        }
                    }
                }
                continue;
            }
            $q->execute(array($v, $k));
        }
//print_r($_POST);		  
        foreach ([	"block_adblock", 
					"iframe_sameorigin_only", 
					"nastyhosts_enabled", 
					"reverse_proxy", 
					"trof_hide_faucet_balance",
					"trof_rewards_view_mode",
					"trof_exchange_list_mode",
					] as $key) {
            if (!array_key_exists($key, $_POST)) $q->execute(array("", $key));
        }
        $sql->commit();

        $changes_saved = $changes_saved_template;
    }//save settings
    $captcha_enabled = false;
    $faucet_disabled = false;
	
    $page = str_replace('<:: content ::>', $admin_template, $master_template);

	$faucet_disabled_message = '';
	
	$trof_selected_captcha = '';
	$trof_captcha_keys = array();

	addNewSettings();	
		
    $query = $sql->query("SELECT name, value FROM wpf_Faucet_Settings");
    while ($row = $query->fetch()) {
//print_r("\nFETCHING ALL");
//print_r($row);		
		

        if ($row[0] == 'template') {
            if (file_exists(dirname( __FILE__ ) . "/templates/{$row[1]}/index.php")) {
                $current_template = $row[1];
            } else {
                $templates = glob(dirname( __FILE__ ) . "/templates/*");
                if ($templates)
                    $current_template = substr($templates[0], strlen('templates/'));
                else
                    die(str_replace("<:: content ::>", "<div class='alert alert-danger' role='alert'>No templates found! Please reinstall your faucet.</div>", $master_template));
            }
        } else {
            if (in_array($row[0], ["block_adblock", "iframe_sameorigin_only", "nastyhosts_enabled", "reverse_proxy","trof_hide_faucet_balance"])) {
                $row[1] = $row[1] == "on" ? "checked" : "";
            }
            if (in_array($row[0], ["rewards"]) && empty($row[1])) {
				$faucet_disabled_message .= $faucet_disabled_template_rewards;
                $faucet_disabled = true;
            }
            if (in_array($row[0], ["apikey"]) && empty($row[1])) {
				$faucet_disabled_message .= $faucet_disabled_template_apikey;
                $faucet_disabled = true;
            }	
//print_r($row);	

			if ($row[0] == "default_captcha") 
			{
				if (!empty($row[1]))
				{
					$trof_selected_captcha = $row[1];//save selected captcha - will use it to check all keys outside "while"
				}
			}
			if (strpos($row[0], "recaptcha_") !== false 
				|| strpos($row[0], "solvemedia_") !== false 
				|| strpos($row[0], "funcaptcha_") !== false
				|| strpos($row[0], "raincaptcha_") !== false
				|| strpos($row[0], "coinhive_") !== false
				|| strpos($row[0], "cryptoloot_") !== false
				) {
                if (!empty($row[1])) {
                    array_push($trof_captcha_keys,$row[0]);//if value exists we save key name
                }
            }	
			
/*	we do it bellow based on $trof_captcha_keys and $trof_selected_captcha	
            if (strpos($row[0], "recaptcha_") !== false || strpos($row[0], "solvemedia_") !== false || strpos($row[0], "funcaptcha_") !== false) {
                if (!empty($row[1])) {
                    $captcha_enabled = true;
                }
            }
*/			
            $page = str_replace("<:: {$row[0]} ::>", $row[1], $page);
			
			if(strpos($row['name'], "trof_exchange_list_mode") === 0)
			{
				$trof_exchange_list_mode = $row['value'];
			}
			if(strpos($row['name'], "trof_rewards_view_mode") === 0)
			{
				$trof_rewards_view_mode = $row['value'];
			}	
			if(strpos($row['name'], "trof_hide_faucet_balance") === 0)
			{
				$trof_hide_faucet_balance = $row['value'];
			}				
        }
    }//while retreeve settings
	
	
//now plenty of IFs, but let's make sure we have the right keys
	if( 	($trof_selected_captcha == "SolveMedia") 
		&& (in_array('solvemedia_challenge_key',$trof_captcha_keys))   
		&& (in_array('solvemedia_verification_key',$trof_captcha_keys))   
		&& (in_array('solvemedia_auth_key',$trof_captcha_keys))   
	)
	{
		$captcha_enabled = true;
	}
	if( 	($trof_selected_captcha == "reCaptcha") 
		&& (in_array('recaptcha_private_key',$trof_captcha_keys))   
		&& (in_array('recaptcha_public_key',$trof_captcha_keys)) 
	)
	{
		$captcha_enabled = true;
	}
	if( 	($trof_selected_captcha == "FunCaptcha") 
		&& (in_array('funcaptcha_private_key',$trof_captcha_keys))   
		&& (in_array('funcaptcha_public_key',$trof_captcha_keys)) 		
	)
	{
		$captcha_enabled = true;
	}	
	if( 	($trof_selected_captcha == "RainCaptcha") 
		&& (in_array('raincaptcha_public_key',$trof_captcha_keys))   
		&& (in_array('raincaptcha_secret_key',$trof_captcha_keys)) 		
	)
	{
		$captcha_enabled = true;
	}	
	if( 	($trof_selected_captcha == "CoinHive") 
		&& (in_array('coinhive_site_key',$trof_captcha_keys))   
		&& (in_array('coinhive_secret_key',$trof_captcha_keys)) 		
	)
	{
		$captcha_enabled = false; //DISCONTINUED
	}	
	if( 	($trof_selected_captcha == "CryptoLoot") 
		&& (in_array('cryptoloot_site_key',$trof_captcha_keys))   
		&& (in_array('cryptoloot_secret_key',$trof_captcha_keys)) 		
	)
	{
		$captcha_enabled = true;
	}	
	
/*    TROF - we add separate messages now
    $faucet_disabled_message = $faucet_disabled_template;
    if (!$faucet_disabled && $captcha_enabled) {
        $faucet_disabled_message = "";
    }
	else
	{
		$faucet_disabled_message .= $faucet_disabled_template_captcha;
	}
*/
    if (!$captcha_enabled) 
	{
		$faucet_disabled_message .= $faucet_disabled_template_captcha;
	}

    $page = str_replace("<:: faucet_disabled ::>", $faucet_disabled_message, $page);

	if( ($currency == 'BTC') && ($service !== 'cryptoo') ) 
	{
		$cryptoome_btc_warning = $cryptoome_btc_warning_template;
	}	

    $templates = '';
    foreach (glob(dirname( __FILE__ ) . "/templates/*") as $template) {
        $template = basename($template);
        if ($template == $current_template) {
            $templates .= "<option selected>$template</option>";
        } else {
            $templates .= "<option>$template</option>";
        }
    }
    $page = str_replace('<:: templates ::>', $templates, $page);
    $page = str_replace('<:: current_template ::>', $current_template, $page);


    if (file_exists(dirname( __FILE__ ) . "/templates/{$current_template}/setup.php")) {
        require_once(dirname( __FILE__ ) . "/templates/{$current_template}/setup.php");
        $page = str_replace('<:: template_options ::>', getTemplateOptions($sql, $current_template), $page);
    } else {
        $page = str_replace('<:: template_options ::>', '<p>No template defined options available.</p>', $page);
    }

    $template_string = file_get_contents(dirname( __FILE__ ) . "/templates/{$current_template}/index.php");
    $template_updates_info = '';
    foreach ($template_updates as $update) {
        if (!preg_match($update["test"], $template_string)) {
            $template_updates_info .= str_replace("<:: message ::>", $update["message"], $template_update_template);
        }
    }
    if (!empty($template_updates_info)) {
        $template_updates_info = str_replace("<:: template_updates ::>", $template_updates_info, $template_updates_template);
    }

    $q = $sql->query("SELECT name, html FROM wpf_Faucet_Pages ORDER BY id");
    $pages = '';
    $pages_nav = '';
    $i = 1;
    while ($userpage = $q->fetch()) {
        $html = htmlspecialchars($userpage['html']);
        $name = htmlspecialchars($userpage['name']);
        $pages .= str_replace(array('<:: i ::>', '<:: page_name ::>', '<:: html ::>'),
                              array($i, $name, $html), $page_form_template);
        $pages_nav .= str_replace('<:: i ::>', $i, $page_nav_template);
        ++$i;
    }
    $page = str_replace('<:: pages ::>', $pages, $page);
    $page = str_replace('<:: pages_nav ::>', $pages_nav, $page);
    $currencies_select = "";
    foreach ($currencies as $c) {
		{
			if ($currency == $c)
				$currencies_select .= "<option value='$c' selected>$c</option>";
			else
				$currencies_select .= "<option value='$c'>$c</option>";
		}
    }

    $page = str_replace('<:: currency ::>', $currency, $page);
    $page = str_replace('<:: currencies ::>', $currencies_select, $page);


    if ($invalid_key)
        $page = str_replace('<:: invalid_key ::>', $invalid_key_error_template, $page);
    else
        $page = str_replace('<:: invalid_key ::>', '', $page);

    $services = "";
	$trof_counter = 0;
    foreach($fb->getServices() as $s => $name) {
        if($s == $service) {
            $services .= "\n<option data-service-link='$trof_counter' value='$s' id='wpbf_s_$name' selected>$name</option>";
        } else {
            $services .= "\n<option data-service-link='$trof_counter' value='$s' id='wpbf_s_$name' >$name</option>";
        }
		$trof_counter++;
    }
//TROF butt-ugly, but fast. 	
	$trof_counter = 0;
    foreach($fb->trof_getServicesLinks() as $s => $trof_service_link) {
		$was_link = "data-service-link='$trof_counter'";
		$now_link = "data-service-link='$trof_service_link'";
		$services = str_replace($was_link,$now_link,$services);
		$trof_counter++;
    }	
    $page = str_replace('<:: services ::>', $services, $page);
//service descriptions
	$a_servicedescs = $fb->trof_getServicesTexts();
	$a_servicenames = array_keys($a_servicedescs);
	$hidden_decs = '';
	for($s = 0; $s < count($a_servicenames); $s++ )
	{
		$hidden_decs .= "<div id='".$a_servicenames[$s]."_desc' >".$a_servicedescs[$a_servicenames[$s]]."</div>\n";
	}
    $page = str_replace('<:: services desc ::>', "<div style='display:none;'>\n".$hidden_decs."\n</div>", $page);
	
	
//trof_hide_faucet_balance	START
//ATT! - in old DB default for trof_hide_faucet_balance is  ''
	$trof_balance_display_modes = array(
	'no' => 'Show Faucet Balance', //no - do not hde
	'yes' => 'Hide Faucet Balance', //yes - hide
	'admin' => 'Show Faucet Balance only to Admin', //yes - hide
	);

	$trof_balance_display_options = '';
    foreach($trof_balance_display_modes as $n => $mode) {
        if($n == $trof_hide_faucet_balance) {
            $trof_balance_display_options .= "\n<option  value='$n' selected='1'>$mode</option>";
        } else {
            $trof_balance_display_options .= "\n<option  value='$n'  >$mode</option>";
        }
    }	
	$page = str_replace('<:: balance-display-options ::>', $trof_balance_display_options, $page);
//trof_hide_faucet_balance	END	

//trof_rewards_view_mode	START 
//ATT! - in old DB default for trof_rewards_view_mode is  '0'
	$trof_reward_display_modes = array(
	'percents' => 'Display as probability percents',
	'range' => 'Display as range',
	);

	$trof_reward_display_options = '';
    foreach($trof_reward_display_modes as $n => $mode) {
        if($n == $trof_rewards_view_mode) {
            $trof_reward_display_options .= "\n<option  value='$n' selected='1'>$mode</option>";
        } else {
            $trof_reward_display_options .= "\n<option  value='$n'  >$mode</option>";
        }
    }	
	$page = str_replace('<:: reward-display-options ::>', $trof_reward_display_options, $page);
//trof_rewards_view_mode	END 	
	
	
//trof_exchange_list_mode	START 
	$trof_exchange_list_modes = array(
	'always' => 'Always display Faucet Exchange List',
	'rewarded' => 'Display Faucet Exchange List only when rewarded',
	'never' => 'Never display Faucet Exchange List',
	);

	$trof_exchange_list_options = '';
    foreach($trof_exchange_list_modes as $n => $mode) {
        if($n == $trof_exchange_list_mode) {
            $trof_exchange_list_options .= "\n<option  value='$n' selected='1'>$mode</option>";
			
        } else {
            $trof_exchange_list_options .= "\n<option  value='$n'  >$mode</option>";
        }
    }	
	$page = str_replace('<:: exchange-list-options ::>', $trof_exchange_list_options, $page);
//trof_exchange_list_mode	END 
//trof_exchange_list_preview	START
	$trof_exchange_list_code = trof_get_exchange_list_code(true);
	$page = str_replace('<:: exchange-list-preview ::>', $trof_exchange_list_code, $page);
//trof_exchange_list_preview	END 

    $page = str_replace('<:: page_form_template ::>',
                        json_encode($page_form_template),
                        $page);
    $page = str_replace('<:: page_nav_template ::>',
                        json_encode($page_nav_template),
                        $page);

    $new_files = [];
    foreach (new RecursiveIteratorIterator (new RecursiveDirectoryIterator (dirname( __FILE__ ) . '/templates')) as $file) {
        $file = $file->getPathname();
        if (substr($file, -4) == ".new") {
            $new_files[] = $file;
        }
    }

    if ($new_files) {
        $new_files = implode("\n", array_map(function($v) { return "<li>$v</li>"; }, $new_files));
        $new_files = str_replace("<:: new_files ::>", $new_files, $new_files_template);
    } else {
        $new_files = "";
    }
    $page = str_replace("<:: new_files ::>", $new_files, $page);

    $q = $sql->query("SELECT value != CURDATE() FROM wpf_Faucet_Settings WHERE name = 'update_last_check' ");
    $recheck_version = $q->fetch();
	
	if($trof_disable_version_check)
	{
		$recheck_version = false;
	}
	
    if ($recheck_version && $recheck_version[0]) {
        $response = $fb->fiabVersionCheck();
        $oneclick_update_possible = checkOneclickUpdatePossible($response);
        if (!$connection_error && $response['version'] && $version < intval($response["version"])) {
            $page = str_replace('<:: version_check ::>', $new_version_template, $page);
            $changelog = '';
            foreach ($response['changelog'] as $v => $changes) {
                $changelog_entries = array_map(function($entry) {
                    return "<li>$entry</li>";
                }, $changes['changelog']);
                $changelog_entries = implode("", $changelog_entries);
                if (intval($v) > $version) {
                    $changelog .= "<p>Changes in r$v (${changes['released']}): <ul>${changelog_entries}</ul></p>";
                }
            }
            $page = str_replace(array('<:: url ::>', '<:: version ::>', '<:: changelog ::>'), array($response['url'], $response['version'], $changelog), $page);
            if ($oneclick_update_possible) {
                $page = str_replace('<:: oneclick_update_button ::>', $oneclick_update_button_template, $page);
            } else {
                $page = str_replace('<:: oneclick_update_button ::>', '', $page);
            }
        } else {
            $page = str_replace('<:: version_check ::>', '', $page);
            $sql->query("UPDATE wpf_Faucet_Settings SET value = CURDATE() WHERE name = 'update_last_check' ");
        }
    } else {
        $page = str_replace('<:: version_check ::>', '', $page);
    }
    
    $page = str_replace('<:: detected_reverse_proxy_name ::>', detectRevProxyProvider(), $page);
    
  
    $page = str_replace('<:: connection_error ::>', $trof_m . $connection_error, $page);
	$page = str_replace('<:: cryptoome_btc_warning ::>', $trof_m . $cryptoome_btc_warning, $page);
    $page = str_replace('<:: curl_warning ::>', $curl_warning, $page);
    $page = str_replace('<:: send_coins_message ::>', $send_coins_message, $page);
    $page = str_replace('<:: missing_configs ::>', $missing_configs_info, $page);
    $page = str_replace('<:: template_updates ::>', $template_updates_info, $page);
    $page = str_replace('<:: changes_saved ::>', $changes_saved, $page);
    $page = str_replace('<:: oneclick_update_alert ::>', $oneclick_update_alert, $page);
    $page = str_replace("<:: csrftoken ::>", get_csrf_token(), $page);

/*
echo("session_prefix: $session_prefix"); //	echo(" S: " .  $_SESSION["$session_prefix-csrftoken"]); 
	if(isset($_SESSION["$session_prefix-csrftoken"]))
	{
//		regenerate_csrf_token();
		$page = str_replace("<:: trof_csrf ::>", $_SESSION["$session_prefix-csrftoken"], $page); //TROF
	}
*/
	$supported_services = Service::$services;
	$supported_services = trof_cryptoomize_currencies($service,$supported_services);
//print_r($supported_services);
    $page = str_replace("<:: supported_services ::>", json_encode($supported_services), $page);
    $page = str_replace("<:: fiab_version ::>", "r".$version, $page);
    die($page);
} else {
    // requested admin page without session
    $page = str_replace('<:: content ::>', $admin_login_template, $master_template);
    $page = str_replace("<:: csrftoken ::>", get_csrf_token(), $page);
    die($page);
}
