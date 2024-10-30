<?php
namespace wpbf_bitcoin_faucet;
class CedsonHub {
    protected $api_key;
    protected $currency;
    protected $api_base = 'https://cedsonhub.site/api/v1/';
    public $communication_error = false;

    public $options = array(
        /* if disable_curl is set to true, it'll use PHP's fopen instead of
         * curl for connection */
        'disable_curl' => false,

        /* do not use these options unless you know what you're doing */
        'local_cafile' => false,
        'force_ipv4' => false,
        'verify_peer' => true
    );

    public function __construct($api_key, $currency = "BTC", $connection_options = null) {
        $this->api_key = $api_key;
        $this->currency = $currency;
        if($connection_options)
            $this->options = array_merge($this->options, $connection_options);
        $this->curl_warning = false;
    }

    public function __execPHP($url, $params = array()) {
        if (count($params)>0) {
            $opts = array(
                "http" => array(
                    "method" => "POST",
                    "header" => "Content-type: application/x-www-form-urlencoded\r\nReferer: ".$this->getHost()." (fopen)\r\n",
                    "content" => http_build_query($params)
                ),
                "ssl" => array(
                    "verify_peer" => $this->options['verify_peer']
                )
            );
        } else {
            $opts = array(
                "http" => array(
                    "method" => "GET",
                    "header" => "Referer: ".$this->getHost()." (fopen)\r\n"
                ),
                "ssl" => array(
                    "verify_peer" => $this->options['verify_peer'],
                )
            );
        }

        if($this->options['local_cafile']) {
            $opts["ssl"]["cafile"] = dirname(__FILE__) . '/cacert.pem';
        }
        $ctx = stream_context_create($opts);
        $fp = fopen($url, 'rb', null, $ctx);
        $response = stream_get_contents($fp);
        if($response && !$this->options['disable_curl']) {
            $this->curl_warning = true;
        }
        fclose($fp);
        return $response;
    }

    public function __exec($method, $params = array()) {
        $params = array_merge($params, array("api_key" => $this->api_key, "currency" => $this->currency));

        $this->communication_error = false;
        $url = $this->api_base . $method;

        if ($method=='balance') {
            $url.= '?api_key='.$params['api_key'];
            $params = array();
        }

        if($this->options['disable_curl']) {
            $response = $this->__execPHP($url, $params);
        } else {
            $response = $this->__execCURL($url, $params);
        }
        if($response) {
            $response = json_decode($response, true);
        }
        if(!$response) {
            $this->communication_error = true;
        }
        return $response;
    }

    private function getHost() {
        if(array_key_exists("HTTP_HOST", $_SERVER)) {
            return $_SERVER["HTTP_HOST"];
        } else {
            return "Unknown";
        }
    }

    public function __execCURL($url, $params = array()) {
        $ch = curl_init($url);
        if (count($params)>0) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->options['verify_peer']);
        curl_setopt($ch, CURLOPT_REFERER, $this->getHost()." (cURL)");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if($this->options['local_cafile']) {
            curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__) . '/cacert.pem');
        }
        if($this->options['force_ipv4']) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }

        $response = curl_exec($ch);
        if(!$response) {
            $response = $this->__execPHP($url, $params);
        }
        curl_close($ch);

        return $response;
    }

    public function send($to, $amount, $userip, $referral = 'false') {
        $amount_in_coins = 0;
        $amount_in_satoshis = 0;
        if ($amount>=100000000) {
            // split
            $amount_in_coins = floor($amount/100000000);
            $amount_in_satoshis = $amount - ($amount_in_coins*100000000);
        } else {
            $amount_in_satoshis = $amount;
        }
        $r = $this->__exec('payout', array('to' => $to, 'amount_in_coins' => $amount_in_coins, 'amount_in_satoshis' => $amount_in_satoshis));// , "referral" => $referral, "ip_address" => $userip

        if (is_array($r) && array_key_exists('to', $r) && (!empty($r['to']))) {
            return array(
                'success' => true,
                'message' => 'Payment sent to you using CedsonHub.site',
                'html' => '<div class="alert alert-success">' . htmlspecialchars($amount) . ' satoshi was sent to '.$r['to'].' on CedsonHub.site.</div>',
                'html_coin' => '<div class="alert alert-success">' . htmlspecialchars(rtrim(rtrim(sprintf("%.8f", $amount/100000000), '0'), '.')) . ' '.$this->currency.' was sent to '.$r['to'].' on CedsonHub.site.</div>',
                'balance' => $r['balance'],
                'balance_bitcoin' => $r['balance']/100000000,
                'user_hash' => $r['to'],
                'response' => json_encode($r)
            );
        } else if(is_array($r)) {
            foreach ($r as $v) {
                if (is_array($v)) {
                    foreach ($v as $vv) {
                        return array(
                            'success' => false,
                            'message' => strip_tags($vv),
                            'html' => '<div class="alert alert-danger">' . htmlspecialchars($vv) . '</div>',
                            'response' => json_encode($r)
                        );
                    }
                } else {
                    return array(
                        'success' => false,
                        'message' => strip_tags($v),
                        'html' => '<div class="alert alert-danger">' . htmlspecialchars($v) . '</div>',
                        'response' => json_encode($r)
                    );
                }
            }
        }
        return array(
            'success' => false,
            'message' => 'Unknown error.',
            'html' => '<div class="alert alert-danger">Unknown error.</div>',
            'response' => json_encode($r)
        );
    }

    public function sendReferralEarnings($to, $amount, $userip) {
        return $this->send($to, $amount, $userip, 'true');
    }

    public function getPayouts($count) {
        $r = $this->__exec('recent-payout');
        $return_array = array();
        if (is_array($r)) {
            $return_array['status'] = 200;
            $return_array['message'] = 'OK';
            $return_array['rewards'] = $r;
        }
        return $return_array;
    }

    public function getCurrencies() {
        $r = $this->__exec('currencies');
        if (empty($r['currencies'])) {
            return null;
        }
        return $r['currencies'];
    }

    public function getBalance() {
        $r = $this->__exec('balance');

        $return_array = array();
        if ((isset($r[$this->currency])) && (isset($r[$this->currency.'_S']))) {
            $return_array['status'] = 200;
            $return_array['message'] = 'OK';
            $return_array['currency'] = $this->currency;
            $return_array['balance'] = $r[$this->currency.'_S'];
            $return_array['balance_bitcoin'] = $r[$this->currency];
            return $return_array;
        } else if(is_array($r)) {
            foreach ($r as $v) {
                if (is_array($v)) {
                    foreach ($v as $vv) {
                        return array(
                            'status' => 403,
                            'message' => strip_tags($vv)
                        );
                    }
                } else {
                    return array(
                        'status' => 403,
                        'message' => strip_tags($v)
                    );
                }
            }
        }
    }
}
