<?php
namespace wpbf_bitcoin_faucet;

require_once(dirname( __FILE__ ) . "/script/common.php");
require_once(dirname( __FILE__ ) . "/libs/coolphpcaptcha.php");

$captcha = new FiabCoolCaptcha();
$captcha->wordsFile = '';
$captcha->session_var = "$session_prefix-cool-php-captcha";
$captcha->width = 330;
//$captcha->imageFormat = 'png';
//$captcha->lineWidth = 3;
//$captcha->scale = 3; $captcha->blur = true;
$captcha->resourcesPath = dirname(__FILE__)."/libs/cool-php-captcha/resources";

$captcha->CreateImage();
