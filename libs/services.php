<?php
namespace wpbf_bitcoin_faucet;

require(dirname( __FILE__ ) ."/services/faucetbox.php");
require(dirname( __FILE__ ) ."/services/epay.php");
require(dirname( __FILE__ ) ."/services/paytoshi.php");
require(dirname( __FILE__ ) ."/services/cryptoo.php");
require(dirname( __FILE__ ) ."/services/faucetsystem.php");
require(dirname( __FILE__ ) ."/services/faucethub.php");
require(dirname( __FILE__ ) ."/services/offline.php");
require(dirname( __FILE__ ) ."/services/faucetpay.php");

class Service {
    public static $services = [
/*	
        "faucetbox" => [
            "name" => "FaucetBOX.com",
            "currencies" => [
                "BTC", "LTC", "DOGE", "PPC", "XPM", "DASH"
            ],
			"trof_link" => "",
        ],
*/		
/*		
        "paytoshi" => [
            "name" => "Paytoshi",
            "currencies" => [ "BTC" ],
			"trof_link" => "",
        ],
*/
        "cryptoo" => [
            "name" => "Cryptoo.me",
            "currencies" => [
                'BTC', 
            ],
			"trof_link" => "https://cryptoo.me/applications/?BTCREF=1GHrzqB6Ngab1gvZDd2tyTXxigziy26L6s",
			"trof_text" => "<b>Cryptoo.me :</b> Lowest fees, sign-up and daily satoshi bonuses. <a target=_new href='https://www.youtube.com/watch?v=-f5ckdopgag&list=PLRv0B44q8TR8bWrEwtMd6e17oW8wdRVIv&t=1m47s'>Video: Crypto.me API key</a>.",
        ],
/*
        "faucetsystem" => [
            "name" => "FaucetSystem.com",
            "currencies" => [ 
				'BTC', 'LTC' 
				],
			"trof_link" => "http://faucetsystem.com/u/faucets/",
			"trof_text" => "<b>FaucetSystem.com :</b> Good old solid service",
        ],
*/
/*		
        "epay" => [
            "name" => "ePay.info",
            "currencies" => [
                "BTC", "LTC", "DOGE", "DASH", "XMR", "PPC", "XPM", "ETH"
            ],
			"trof_link" => "https://myfaucet.epay.info/faucets/",
			
        ],	
*/		
/*		
        "faucethub" => [
            "name" => "FaucetHub.io",
            "currencies" => [
//                "BTC", "LTC", "DOGE"
				'BCH', 'BLK', 'BTC', 'BTX', 'DASH', 'DGB', 'DOGE', 'ETH', 'LTC', 'POT', 'PPC', 'XMR', 'XPM', 'ZEC'
            ],
			"trof_link" => "http://faucethub.io/r/166248",
			"trof_text" => "<span style='background-color:red;color:white;'> <b>FaucetHub.io :</b> <a style='color:yellow;'target=_new href='https://faucethub.io/news/post/211'>will be discontinuing the majority of it's services</a>. Use Cryptoo.me instad. </span>",
        ],
*/		
        "faucetpay" => [
            'name' => 'FaucetPay.io',
            'currencies' => [
				'BCH', 'DASH', 'DGB', 'DOGE', 'ETH', 'LTC',
			],

			"trof_link" => "https://faucetpay.io/page/faucet-admin",
			"trof_text" => "",			
			"trof_key_text" => "",
			"trof_apikey_label" => "",
        ],

        "offline" => [
            "name" => "Faucet Temporary Unavailable",
            "currencies" => [
				"", 
            ],
			"trof_link" => "",
			"trof_text" => "Faucet is not going to operate. Switch it to any other service when ready",			
			"trof_key_text" => "",	
			"trof_apikey_label" => "",			
        ],		

		
    ];
    protected $service;
    protected $api_key;
    protected $service_instance;
    protected $currency;
    public $communication_error = false;
    public $curl_warning = false;
	public $check_url; //TROF added

    public $options = array(
        /* if disable_curl is set to true, it'll use PHP's fopen instead of
         * curl for connection */
        'disable_curl' => false,

        /* do not use these options unless you know what you're doing */
        'local_cafile' => false,
        'force_ipv4' => false,
        'verify_peer' => true
    );

    public function __construct($service, $api_key, $currency = "BTC", $connection_options = null) {
        $this->service = $service;
        $this->api_key = $api_key;
        $this->currency = $currency;
        if($connection_options)
            $this->options = array_merge($this->options, $connection_options);

        switch($this->service) {
        case "faucetbox":
            $this->service_instance = new FaucetBOX($api_key, $currency, $connection_options);
            break;
        case "epay":
            $this->service_instance = new ePay($api_key, $currency);
            break;
        case "paytoshi":
            $this->service_instance = new Paytoshi($api_key, $connection_options);
            break;
        case "cryptoo":
            $this->service_instance = new Cryptoo($api_key, $currency, $connection_options);
            break;			
        case "faucetsystem":
            $this->service_instance = new FaucetSystem($api_key, $currency, $connection_options);
            break;
        case "faucethub":
            $this->service_instance = new FaucetHub($api_key, $currency, $connection_options);
            break;
        case "faucetpay":
            $this->service_instance = new FaucetPay($api_key, $currency, $connection_options);
            break;			
       case "offline":
            $this->service_instance = new Offline();
            break;			
        default:
            trigger_error("Invalid service $service");
        }
    }

    public function getServices($currency = null) {
        if(!$currency) {
            $all_services = [];
            foreach(self::$services as $service => $details) {
                $all_services[$service] = $details["name"];
            }
            return $all_services;
        }

        $services = [];
        foreach(self::$services as $service => $details) {
            if(in_array($service, $details["currencies"])) {
                $services[$service] = $details["name"];
            }
        }

        return $services;
    }
	
    public function trof_getServicesLinks() { 
        $all_services_links = [];
        foreach(self::$services as $service => $details) {
			$all_services_links[$service] = $details["trof_link"];
        }
        return $all_services_links;
    }	
	
    public function trof_getServicesTexts() { 
        $all_services_links = [];
        foreach(self::$services as $service => $details) {
			$all_services_texts[$service] = $details["trof_text"];
        }
        return $all_services_texts;
    }	
	

    public function send($to, $amount, $userip, $referral = "false") {
        switch($this->service) {
        case "faucetbox":
            $r = $this->service_instance->send($to, $amount, $referral);
            $this->check_url = "https://faucetbox.com/check/".rawurlencode($to);
            $success = $r['success'];
            $balance = $r["balance"];
            $error = $r["message"];
            $this->communication_error = $this->service_instance->communication_error;
            $this->curl_warning = $this->service_instance->curl_warning;
            break;
        case "epay":
            $r = $this->service_instance->send($to, $amount, $referral === "true", getIP());
            $this->check_url = "https://epay.info/Dashboard/".rawurlencode($to)."/";
            $success = $r['status'] > 0;
            $balance = null;
            $error = $r['error_msg'];
			$this->communication_error = $this->service_instance->communication_error;
            break;
        case "paytoshi":
            $r = $this->service_instance->send($to, $amount, $referral);
            $this->check_url = "https://paytoshi.org/".rawurlencode($to)."/balance";
            $success = $r['success'];
            $balance = null;
            $error = array_key_exists("message", $r) ? $r['message'] : null;
            $this->communication_error = $this->service_instance->communication_error;
            $this->curl_warning = $this->service_instance->curl_warning;
            break;
        case "cryptoo":
            $r = $this->service_instance->send($to, $amount, $userip, $referral);
//            $this->check_url = "https://cryptoo.me/check/".rawurlencode($to);
			$this->check_url = "https://cryptoo.me/deposits/";
            $success = $r['success'];
			$balance = array_key_exists("balance", $r) ? $r['balance'] : null; //TROF
            $error = $r["message"];
            $this->communication_error = $this->service_instance->communication_error;
            $this->curl_warning = $this->service_instance->curl_warning;
            break;			
        case "faucetsystem":
            $r = $this->service_instance->send($to, $amount, $userip, $referral);
            $this->check_url = "https://faucetsystem.com/check/".rawurlencode($to);
            $success = $r['success'];
			$balance = array_key_exists("balance", $r) ? $r['balance'] : null; //TROF
            $error = $r["message"];
            $this->communication_error = $this->service_instance->communication_error;
            $this->curl_warning = $this->service_instance->curl_warning;
            break;
        case "faucethub":
            $r = $this->service_instance->send($to, $amount, $userip, $referral);
            $this->check_url = "https://faucethub.io/check/".rawurlencode($to);
            $success = $r['success'];
            $balance = $r["balance"];
            $error = $r["message"];
            $this->communication_error = $this->service_instance->communication_error;
            $this->curl_warning = $this->service_instance->curl_warning;
            break;
        case "faucetpay":
            $r = $this->service_instance->send($to, $amount, $userip, $referral);
            $this->check_url = "https://faucetpay.io/page/user-admin/linked-addresses";
            $success = $r['success'];
            $balance = $r["balance"];
            $error = $r["message"];
            $this->communication_error = $this->service_instance->communication_error;
            $this->curl_warning = $this->service_instance->curl_warning;
            break;			
        case "offline":
			$this->check_url = "https://cryptoo.me/deposits/";
            $success = "Temporary unavailable";
			$balance = "Temporary unavailable"; //TROF
            $error = "Temporary unavailable";
            $this->communication_error = "Temporary unavailable";
            $this->curl_warning = "Temporary unavailable";
            break;				
        }

        $sname = self::$services[$this->service]["name"];
        $result = [];
        $result['success'] = $success;
        $result['response'] = json_encode($r);
        if($success) {
            $result['message'] = 'Payment sent to you using '.$sname;
            $result['html'] = '<div class="alert alert-success">' . htmlspecialchars($amount) . " satoshi was sent to you <a target=\"_blank\" href=\"".$this->check_url."\">on $sname</a>.</div>";
            $result['html_coin'] = '<div class="alert alert-success">' . htmlspecialchars(rtrim(rtrim(sprintf("%.8f", $amount/100000000), '0'), '.')) . " " . $this->currency . " was sent to you <a target=\"_blank\" href=\"$$this->check_url\">on $sname</a>.</div>";
            $result['balance'] = $balance;
            if($balance) {
                $result['balance_bitcoin'] = sprintf("%.8f", $balance/100000000);
            } else {
                $result['balance_bitcoin'] = null;
            }
        } else {
            $result['message'] = $error;
            $result['html'] = '<div class="alert alert-danger">'.htmlspecialchars($error).'</div>';
        }
        return $result;
    }

    public function sendReferralEarnings($to, $amount, $userip) {
        return $this->send($to, $amount, $userip, "true");
    }

    public function getPayouts($count) {
        switch($this->service) {
        case "faucetbox":
            return $this->service_instance->getPayouts($count);
            break;
        }
        return [];
    }

    public function getCurrencies() {
        switch($this->service) {
        case "faucetbox":
            return $this->service_instance->getCurrencies();
            break;
        }
        return self::$services[$this->service]["currencies"];
    }

    public function getBalance() {
        switch($this->service) {
        case "faucetbox":
            $balance = $this->service_instance->getBalance();
            $this->communication_error = $this->service_instance->communication_error;
            $this->curl_warning = $this->service_instance->curl_warning;
            return $balance;
        case "epay":
            $balance = $this->service_instance->getBalance();
			$this->communication_error = $this->service_instance->communication_error;
            return array("status" => $balance >= 0 ? 200 : 403, "balance" => $balance, "balance_bitcoin" => $balance/100000000);
        case "paytoshi":
            $balance = $this->service_instance->getBalance();
            if(!is_array($balance) || !array_key_exists("available_balance", $balance)) {
                return array("status" => 403);
            }
            $balance = $balance["available_balance"];
            $this->communication_error = $this->service_instance->communication_error;
            $this->curl_warning = $this->service_instance->curl_warning;
            return array(
                "status" => 200,
                "balance" => $balance,
                "balance_bitcoin" => $balance/100000000
            );
        case "cryptoo":
            $balance = $this->service_instance->getBalance();
            $this->communication_error = $this->service_instance->communication_error;
            $this->curl_warning = $this->service_instance->curl_warning;
            return $balance;			
        case "faucetsystem":
            $balance = $this->service_instance->getBalance();
            $this->communication_error = $this->service_instance->communication_error;
            $this->curl_warning = $this->service_instance->curl_warning;
            return $balance;
        case "faucethub":
            $balance = $this->service_instance->getBalance();
            $this->communication_error = $this->service_instance->communication_error;
            $this->curl_warning = $this->service_instance->curl_warning;
            return $balance;
        case "faucetpay":
            $balance = $this->service_instance->getBalance();
            $this->communication_error = $this->service_instance->communication_error;
            $this->curl_warning = $this->service_instance->curl_warning;
            return $balance;				
        case "offline":
            $balance = "Temporary unavailable";
            $this->communication_error = false;
            $this->curl_warning = '';
            return $balance;			
        }
        die("Database is broken. Please reinstall the script.");
    }

    public function fiabVersionCheck() {
        if($this->service == "faucetbox") {
            $fbox = $this->service_instance;
        } else {
            $fbox = new FaucetBOX("", "BTC", $this->options);
        }
        return $fbox->fiabVersionCheck();
    }
}
