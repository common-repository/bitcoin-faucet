<?php
//use \wpbf_bitcoin_faucet;
if ( ! defined( 'ABSPATH' ) ) die('nope!'); // Exit if accessed directly
$trof_main_url = plugin_dir_url( __FILE__ ); // trail slash
$trof_wpbf_ajax_mode = true;
$trof_wpbf_ajax_mode = false;
require_once(dirname( __FILE__ ) . "/config.php");//will send header if needed


function wpbf_bitcoin_faucet_ajax_callback() 
{
	$site_url = get_site_url(); //always!
	echo("
		<script>
			var wpbf_site_url = '".$site_url."';		
		</script>
	");
		
	include_once( dirname( __FILE__ ) . '/wpbf_index.php' );

	if($trof_wpbf_ajax_mode)
	{
		wp_die();//stupid trail zero
	}
}




function wpbf_bitcoin_faucet_shortcode(  $atts )// [WPBF]
{
	global $trof_wps_faucet_name;
	global $trof_wps_faucet_description;
	global $trof_wps_faucet_rewards;
	global $trof_wps_faucet_timer;
	$ret = '';
	
// default values for the shortcode parameters is '' - meaning DO NOT OVERWRITE DEFAULR FAUCET SETTINGS
    $a = shortcode_atts( array(
        'nm' => '', 				//faucet name. like 'my surer fauset'
        'ds' => '',					//faucet description.  like 'free is good'
		'rw' => '',					//faucet rewords.  like '10*11-22, 20*66, 30*33-44'
		'tm' => '',					//faucet timeout in minues. like '180' 
    ), $atts );

	if(isset($atts['nm'])) {$trof_wps_faucet_name = $atts['nm']; }
	if(isset($atts['ds'])) {$trof_wps_faucet_description = $atts['ds']; }
	if(isset($atts['rw'])) {$trof_wps_faucet_rewards = $atts['rw']; }
	if(isset($atts['tm'])) {$trof_wps_faucet_timer = $atts['tm']; }

	
	
	global $trof_wpbf_ajax_mode;
	
	if($trof_wpbf_ajax_mode === false)
	{
		ob_start();
		wpbf_bitcoin_faucet_ajax_callback(); 
		$ret = ob_get_clean();
		
		return($ret);
	}
	
	echo("<div id='wpbf_bitcoin_faucet_wrap' style='border:1px dotted grey;'>loading...</div>");

	$ref = '';
	if(isset($_GET['r']) && (trim($_GET['r'] != '')))
	{
		$ref = $_GET['r'];
	}

	$fetcher_str = "
	<script>
	var wpbf_get_ref = '".$ref."';
	jQuery(document).ready(function() {
		wpbf_fetch('GET','');
	});
	</script>
	";
	
	echo($fetcher_str);

}





// 'bitcoin_faucet' as in 'http://ab.tmweb.ru/wp-admin/options-general.php?page=bitcoin_faucet'
function wpbf_bitcoin_faucet_plugin_menu() {
	add_options_page( 'Bitcoin Faucet Options', 'Bitcoin Faucet', 'manage_options', 'bitcoin_faucet', 'wpbf_bitcoin_faucet_plugin_options' );
}

function wpbf_bitcoin_faucet_plugin_override_bootstrap(){ /* Override Bootstrap Reset with WP default */
	return("\n
<style>
body {
    font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif !important;
}	
</style>	
	\n");
}

function wpbf_bitcoin_faucet_plugin_options() { 
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}

	echo("<div id='wpbf_bitcoin_faucet_admin_wrap' style='border:1px dotted gray; background-color:white;'>");
	
	include_once(dirname( __FILE__ ) . '/wpbf_admin.php');
	echo('</div>'); //wpbf_bitcoin_faucet_admin_wrap
}
//admin page
add_action( 'admin_menu', 'wpbf_bitcoin_faucet_plugin_menu' );
//front ajax
add_action('wp_ajax_wpbf_bitcoin_faucet_unique_action','wpbf_bitcoin_faucet_ajax_callback');					
add_action('wp_ajax_nopriv_wpbf_bitcoin_faucet_unique_action','wpbf_bitcoin_faucet_ajax_callback');

//shortcode to insert faucet into page, so "[wpbf_bitcoin_faucet]"
add_shortcode('WPBF', 'wpbf_bitcoin_faucet_shortcode');


//MMC stuff START
function wpbf_bitcoin_faucet_mmc_ajax_callback() 
{
	global $session_prefix;
    $_SESSION["$session_prefix-mouse_movement_detected"] = true;
	die();
}
add_action('wp_ajax_wpbf_bitcoin_faucet_mmc_unique_action','wpbf_bitcoin_faucet_mmc_ajax_callback');					
add_action('wp_ajax_nopriv_wpbf_bitcoin_mmc_faucet_unique_action','wpbf_bitcoin_faucet_mmc_ajax_callback');

//MMS stuff END




function wpbf_bitcoin_faucet_add_header_sameorigin() 
{
	require_once( dirname( __FILE__ ) . "/config.php");
	global $dbdsn, $dbuser, $dbpass;
	try 
	{
		$sql = new PDO($dbdsn, $dbuser, $dbpass, array(PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));	
	} 
	catch(PDOException $e) 
	{
		die("WHOAAAA?!");
		return; //silently - the plugin may not configured yet
	}
//if we here we still alive
	try
	{
		$iframe_sameorigin_only = $sql->query("SELECT `value` FROM  `wpf_Faucet_Settings` WHERE `name` = 'iframe_sameorigin_only'")->fetch();
		if ($iframe_sameorigin_only && $iframe_sameorigin_only[0] == "on") 
		{
			header("X-Frame-Options: SAMEORIGIN");
		}
	}
	catch(PDOException $e)
	{
		//doing nothing for now
	}
}
add_action( 'send_headers', 'wpbf_bitcoin_faucet_add_header_sameorigin' );

//no kidddind, in some old version page does not have jquery if not in the menu
function wpbf_bitcoin_faucet_scripts() {
  wp_enqueue_script('jquery'); //yep, no menu - no jquery for some themes
//  wp_enqueue_script( 'bootstrap', '//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.2.0/js/bootstrap.min.js', array( 'jquery' ) );
//  wp_enqueue_script( 'bootstrap-js', '//netdna.bootstrapcdn.com/bootstrap/3.0.0/js/bootstrap.min.js', array('jquery'), true); 
}
add_action('wp_enqueue_scripts', 'wpbf_bitcoin_faucet_scripts');