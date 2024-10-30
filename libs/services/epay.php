<?php
namespace wpbf_bitcoin_faucet;
use \SoapClient;
use \SoapFault;

class ePay {
    protected $client;
    protected $api_key;
    protected $currency;
	public $communication_error = false; //TROF ADDED
    public function __construct($api_key, $currency) {
        $this->api_key = $api_key;
        $this->currency = $currency;
        $this->client = null;
    }

    private function connect() {
        $this->client = new SoapClient('https://api.epay.info/?wsdl', array('cache_wsdl' => WSDL_CACHE_NONE, 'exceptions' => true)); //TROF added , array('cache_wsdl' => WSDL_CACHE_NONE) - damn php7
    }

    private function translateStatus($st) {
        if($st > 0) {
            return "";
        } else if ($st === -2) {
            return "Wrong API code";
        } else if ($st === -3) {
            return "Not enough balance";
        } else if ($st === -4) {
            return "API error: one of mandatory parameters is missing";
        } else if ($st === -5) {
            return "API error: payment is sooner than the calculated time out";
        } else if ($st === -6) {
            return "API error: ACL is active and server IP address is not authorized";
        } else if ($st === -7) {
            return "API error: proxy detected";
        } else if ($st === -8) {
            return "API error: user country is blocked.";
        } else if ($st === -10) {
            return "API error: daily budget reached";
        } else if ($st === -11) {
            return "API error: time-frame limit reached";
        } else {
            return "API error code: $st";
        }
    }
    public function send($to, $amount, $referral, $userip) {
		try //TROF try-catch added
		{		
			if(!$this->client) $this->connect();
			if($referral)
				$resp = $this->client->send($this->api_key, $to, $amount, 2, 'Referral earnings.', $userip);
			else
				$resp = $this->client->send($this->api_key, $to, $amount, 1, null, $userip);
			$resp["error_msg"] = $this->translateStatus($resp["status"]);
			return $resp;
		}
		catch(SoapFault $e) 
		{
			global $display_errors;
			if(true || $display_errors)
			{
				echo("ePay SOAR Error. Details: ".$e->getMessage());
			}
			$resp["error_msg"] = $e->getMessage();
			$this->communication_error = true;
		}
		
    }

    public function getBalance() {
		try //TROF try-catch added
		{
			if(!extension_loaded('soap')) 
			{
				echo("<br>ePay.info API requires SOAP, but the weird happened. No SOAP on this PHP ? Whaaa?!");
				echo("<br>Please <a target=_new href='http://php.net/manual/soap.installation.php'>Install</a> or enable.");
				echo("<br>You also may <a href='javascript:window.location.reload(true)'>reload this page</a> and select different service.");
				die();
			}
			else //we good
			{

				try //TROF try-catch added
				{			
					if(!$this->client) $this->connect();
					{

						$ret = $this->client->f_balance($this->api_key, 1);
						return $ret;
					}
				} 
				catch(SoapFault $e) 
				{
					global $display_errors;
					if($display_errors)
					{
						echo("ePay SOAR Error. Details: ".$e->getMessage());
					}
					$this->communication_error = true;
				}
			}
		}
		catch(Exception  $e) //catching all. well, suppose to...
		{
			if($display_errors)
			{			
				echo("Unexpected Error. Details: ".$e->getMessage());
			}
		}
    }
}
