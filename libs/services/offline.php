<?php
namespace wpbf_bitcoin_faucet;
use \SoapClient;
use \SoapFault;

class Offline {
    protected $client;
    protected $api_key;
    protected $currency;
	public $communication_error = false; //TROF ADDED
    public function __construct() {
    }

    private function connect() {
    }

    public function send($to, $amount, $referral, $userip) {
		$resp["error_msg"] = "Temporary unavailable";
		return $resp;
    }

    public function getBalance() {
		die("Temporary unavailable");
    }
}
