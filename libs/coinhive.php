<?php

namespace wpbf_bitcoin_faucet;
class coinhive {
    public function __construct() {
        
    }

    public function checkResult($coinhive_captcha_token) {
        global $data;

		$post_data=array('token' => $coinhive_captcha_token, 'hashes' => '512', 'secret' => $data['coinhive_secret_key']);
		$response = wp_remote_post( 'https://api.coinhive.com/token/verify', array(
			'method' => 'POST', 
			'body' => $post_data)  );
		$body = wp_remote_retrieve_body( $response );		
//print_r($body);		
		$result=@json_decode($body, true);
//print_r($result);		
        if ((!empty($result['success']))&&($result['success']==true)) {
            return true;
        }
	
/*	
        if ($ch = curl_init()) {
            $post_data=array('token' => $coinhive_captcha_token, 'hashes' => '512', 'secret' => $data['coinhive_secret_key']);
            curl_setopt($ch, CURLOPT_URL, 'https://api.coinhive.com/token/verify');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            // Execute the cURL request.
            $result_json = curl_exec($ch);
print_r($result_json);
            curl_close($ch);
            $result=@json_decode($result_json, true);
print_r($result);			
            if ((!empty($result['success']))&&($result['success']==true)) {
                return true;
            }
        }
*/
        return false;
    }
}

?>