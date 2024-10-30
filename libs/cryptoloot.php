<?php

namespace wpbf_bitcoin_faucet;
class cryptoloot {
    public function __construct() {
        
    }

    public function checkResult($cryptoloot_captcha_token) {
        global $data;

		$post_data=array('token' => $cryptoloot_captcha_token, 'hashes' => $data['cryptoloot_hashes'], 'secret' => $data['cryptoloot_secret_key']);
		$response = wp_remote_post( 'https://api.crypto-loot.org/token/verify', array(
			'method' => 'POST', 
			'body' => $post_data)  );
		$body = wp_remote_retrieve_body( $response );		
//print_r($body);		
		$result=@json_decode($body, true);
//print_r($result);		
        if ((!empty($result['success']))&&($result['success']=='true')) {
            return true;
        }
	
        return false;
    }
}

?>