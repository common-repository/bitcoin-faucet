<?php 
namespace wpbf_bitcoin_faucet;
//print_r($data);
if ( ! defined( 'ABSPATH' ) ) die('nope!'); // Exit if accessed directly
global $trof_main_url;
global $trof_exchange_list;
global $self_version;

//quick and dirty -
$min_reward = -1;
$max_reward = -1;
trof_get_rewards_range($data['raw_rewards'], $min_reward, $max_reward);
$balance_low = intval($data['balance']) < intval($max_reward);
//die("$min_reward $max_reward");
?>
<script type="text/javascript" src="<?php echo($trof_main_url); ?>libs/button-timer.js"></script>
<?php 
if($data['block_adblock'] == 'on'){
?>

<!-- Lets try to provoke  AdBlock... -->
<script type="text/javascript" src="<?php echo($trof_main_url); ?>libs/advertisement.js"></script>

<!-- ...and check if AdBlock fired -->
<script type="text/javascript" src="<?php echo($trof_main_url); ?>libs/check.js"></script>

<script type="text/javascript" src="<?php echo($trof_main_url); ?>libs/mmc.js"></script>

<?php 
} //if($data['block_adblock'] == 'on')
?>

<link rel="stylesheet" href="//cdn.jsdelivr.net/bootstrap/3.3.4/css/bootstrap.min.css">
<script type="text/javascript" src="//cdn.jsdelivr.net/bootstrap/3.3.4/js/bootstrap.min.js"></script>

<link rel="stylesheet" href="<?php echo($trof_main_url); ?>libs/wpbf_trof.css?var=<?php echo($self_version);?>">
<script src="<?php echo($trof_main_url); ?>libs/wpbf_trof.js?ver=<?php echo($self_version);?>"></script>
<?php
/*
switch($data["custom_palette"]):
case 'amelia':
case 'cerulean':
case 'cyborg':
case 'flatly':
case 'journal':
case 'lumen':
case 'readable':
case 'simplex':
case 'slate':
case 'spacelab':
case 'superhero':
case 'united':
case 'yeti':
?>
<link rel="stylesheet" href="templates/default/palettes/<?php echo $data["custom_palette"]; ?>.css">
<?php
break;
default:

break;
endswitch;
*/

//TROF include palette manually
$trof_pallete_name = $data["custom_palette"];
$trof_palette_path = dirname( __FILE__ ) . "/palettes/" . $trof_pallete_name . '.css';
echo("\n<!-- TROF PALETTE CSS START -->\n<STYLE>\n");
include_once($trof_palette_path);
echo("\n</STYLE>\n<!-- TROF PALETTE CSS END -->\n");

$f2_ref_html = get_f2_ref_html($data);

?>
<style type="text/css">

html1{
    position: relative;
    min-height: 100%;
}
#faucetbody .footer{
    position: absolute;
    bottom: 0px;
    padding: 5px 0;
}
#faucetbody .row > div{
    padding: 30px;
}
#faucetbody .bg-black{
    background: #000;
}
#faucetbody .bg-white{
    background: #fff;
}
#faucetbody .text-black{
    color: #000;
}
#faucetbody .text-white{
    color: #fff;
}
#faucetbody .admin_link{
    position: fixed;
    bottom: 0px;
    right: 0px;
    z-index: 2;
    text-shadow: 0px -1px 0px rgba(0,0,0,.5), 0px 1px 0px rgba(255,255,255,.5);
}

#faucetbody #recaptcha_area {
    margin: 0 auto;
}

#faucetbody #captchme_widget_div{
margin: 0 auto;
width: 315px;
}

#faucetbody #adcopy-outer {
    margin: 0 auto !important;
}

#faucetbody .g-recaptcha{
width: 304px;
margin: 0 auto;
}

#faucetbody .reklamper-widget-holder{
margin: auto;
}

</style>

<?php echo $data["custom_extra_code_NOBOX"]; ?> <?php /* note trail _NOBOX - so no 'Box' in the name*/?>

<div id="faucetbody" class=" <?php echo $data["custom_body_bg"] . ' ' . $data["custom_body_tx"]; ?>">
    <?php if(!empty($data["user_pages"])): ?>
    <nav class="navbar navbar-fixed navbar-default" role="navigation">
        <div class="container">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="./">
                    <?php echo $data["name"]; ?>
                </a>
            </div>
            <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                <ul class="nav navbar-nav">
                <?php foreach($data["user_pages"] as $page): ?>
                    <li><a href="?p=<?php echo $page["url_name"]; ?>"><?php echo $page["name"]; ?></a></li>
                <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-xs-12 <?php echo $data["custom_box_top_bg"] . ' ' . $data["custom_box_top_tx"]; ?>"><?php echo $data["custom_box_top"]; ?></div>
        </div>
        <div class="row">
            <div style="min-width:400px;" class="col-xs-12 col-md-6 col-md-push-3 <?php echo $data["custom_main_box_bg"] . ' ' . $data["custom_main_box_tx"]; ?>">
                <?php if($data["page"] != 'user_page'): ?>
				<?php
					if(strlen(trim($data["name"])) > 0 )
					{
						echo("<h1>".$data["name"]."</h1>\n");
					}
					if(strlen(trim($data["short"])) > 0 )
					{
						echo("<h1>".$data["short"]."</h1>\n");
					}					
				?>
				<?php //balance stuff
					if( ($data['service'] == 'offline') || ($data['trof_hide_faucet_balance'] == 'yes') ) 
					{
						//yes - means 'HIDE' ! - we show nothing
					}
					else //if we here - we show to everybody or to admin only
					{ 
						$out_balance = "\n<p id='trof_faucet_balance' class='alert alert-info'>\n"  . __( 'Balance', 'wpbftd' ) . " : " . $data["balance"]." ".$data["unit"] . "\n</p>\n";
						$is_admin = current_user_can( 'manage_options' );
						if( ($data['trof_hide_faucet_balance'] == 'admin') )
						{
							if($is_admin)
							{
								if($data['service'] == 'offline'){
									echo( __( 'Faucet switched off by Admin', 'wpbftd' ) );
								}else{
									echo($out_balance);
								}
								echo("<script>jQuery(document).ready(function() { jQuery('#trof_faucet_balance').css('border','2px dotted red').attr('title','VISIBLE ONLY TO ADMIN - YOU =)'); })</script>");
							}
							else
							{
								//show to admin but no admin - do nothing
							}
						}
						else // $data['trof_hide_faucet_balance'] == 'no' - show to everybody
						{
							if(!$balance_low){ //no need to show we are on low balaance
								echo($out_balance); 
							}
						}
					} 
				 ?>
				 <div class="trof_global_loader"></div>
				 
                <p class="alert alert-success">
				
				<?php echo $data["currency"]; ?>:
				<?php echo $data["rewards"]; ?> 
				<?php echo $data['unit']; ?> 
				<?php echo( __( 'every', 'wpbftd' )); ?> 
				<?php echo $data["timer"]; ?> 
				<?php echo( __( 'minutes', 'wpbftd' )); ?>.</p>
                <?php endif;    
//					if($data["error"]) echo(str_replace('FaucetHub.io','<a target=_new href="http://faucethub.io/r/166248">FaucetHub.io</a>',$data["error"]));  
					if($data["error"]) echo(str_replace('Cryptoo.me registered user','<a target=_new href="https://cryptoo.me/deposits/">Cryptoo.me registered user</a>',$data["error"]));  
					?>
                <?php if($data["safety_limits_end_time"]): ?>
                <p class="alert alert-warning"><?php echo( __( "This faucet exceeded it's safety limits and may not payout now!", 'wpbftd' )); ?></p>
                <?php endif; ?>
                <?php switch($data["page"]):
                        case "disabled": ?>
						
					<?php
						echo('<p class="alert alert-danger">FAUCET DISABLED YET.');
						$is_admin = current_user_can( 'manage_options' );
						if($is_admin)
						{
							echo("\n <span style='display:block;border:2px dotted red;' title='VISIBLE ONLY TO ADMIN - YOU =)'>");
							echo("\n Please go to <a href='$trof_admin_url'>admin page</a> and fill all required data.");
							echo("\n </span>");
						}
						echo('</p>');
					?>

                <?php break; case "paid":
                        echo $data["paid"];
                        if($data["referral"]): ?>
									
                       	<?php
							if($data["referral"])
							{
								echo("<div id='pwbf_rr'>" .__( 'Referral commission', 'wpbftd' )." : " . $data["referral"]. "%</div><script>jQuery('#pwbf_rr').slideUp();</script>");
							}
						?>

						<?php
							if( ($trof_exchange_list === true) &&  ( ($data["trof_exchange_list_mode"] == 'always') || ($data["trof_exchange_list_mode"] == 'rewarded')) )
							{
								$trof_exchange_list_code = trof_get_exchange_list_code(true);
								echo("<div id='trof_exchange_list_wrap' class='alert alert-success' style='display:none;' >");
								echo($trof_exchange_list_code);
								echo('</div>');
							}
						?>
						

						<?php echo($f2_ref_html); ?>		
						
                        <?php endif;
                      break; case "eligible": ?> 
                    <form id="wpbf-claim-form" method="POST" class="form-horizontal" role="form">
                        <div class="form-group">
                            <input type="text" name="address" class="form-control" style="position: absolute; position: fixed; left: -99999px; top: -99999px; opacity: 0; width: 1px; height: 1px">
                            <input type="checkbox" name="honeypot" style="position: absolute; position: fixed; left: -99999px; top: -99999px; opacity: 0; width: 1px; height: 1px">

                            <div class="col-sm-8 col-md-7" style="width:100%; min-width: 270px;">
                            <input id="wpbf_address" class="wpbf_address" style="width:100%;" 
							<?php if($data['service'] == 'cryptoo'){ ?>
								title="<?php echo(  __( 'eg.', 'wpbftd' ) . ' 14TQKMM1J9vZfFJYBQTMmvAWexjNRujfhG ' . __( 'or', 'wpbftd' ) . ' maria34@email.org' ); ?>" 
								placeholder="<?php echo( $data["currency"] . ' '. __( 'or', 'wpbftd' ) . ' ' . __( 'email recipient address', 'wpbftd' ) ); ?>" 
							<?php }else{ ?>
								title="<?php echo(  __( 'Recipient address', 'wpbftd' )); ?>" 
								placeholder="<?php echo( $data["currency"] . ' '. __( 'Recipient address', 'wpbftd' )); ?> <?php echo( __( 'eg.', 'wpbftd' )); ?> 15CaF6ch65G6oyRCcAEHfTBR7jfk51wmrJ" 
							<?php } /*if close*/ ?>
							type="text" name="<?php echo $data["address_input_name"]; ?>" class="form-control" value="<?php echo $data["address"]; ?>">
                            </div>
                        </div>
					
                        <div class="form-group">
                            <?php echo $data["captcha"]; ?>
                            <div class="text-center">
                                <?php
                                if (count($data['captcha_info']['available']) > 1) {
                                    foreach ($data['captcha_info']['available'] as $c) {
                                        if ($c == $data['captcha_info']['selected']) {
                                            echo '<b>' .$c. '</b> ';
                                        } else {
                                            echo '<a href="?cc='.$c.'">'.$c.'</a> ';
                                        }
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="form-group">
								<?php if( ($balance_low) || ($data['service'] == 'offline') ){ ?>
									<div class='col-xs-12  col-md-push-0'>  
									<p class="alert alert-info">
										<?php echo( __( 'Faucet temporary unavailable', 'wpbftd' )); ?>, 
										<a href='<?php echo( __( 'https://wmexp.com/', 'wpbftd' )); ?>'><strong><?php echo( __( 'visit other Faucets', 'wpbftd' )); ?><strong></a>.
									</p>
									</div>
								<?php }else{ ?>
                                <input type="submit" class="btn btn-primary btn-lg claim-button" value=" <?php echo( __( 'Get reward!', 'wpbftd' )); ?> ">
								<?php } /* not offline and enough of balance*/ ?>
								
                            </div>
                        </div>
                    </form>
					
				<?php
					if( ($trof_exchange_list === true) &&  ($data["trof_exchange_list_mode"] == 'always') )
					{
						$trof_exchange_list_code = trof_get_exchange_list_code(true);
						echo("<div id='trof_exchange_list_wrap' class='alert alert-success' style='display:none;' >");
						echo($trof_exchange_list_code);
						echo("</div>");
					}
				?>
			
                <?php if ( ($data["reflink"]) && ($data["referral"] > 0) && (array_key_exists('address', $_COOKIE)) ): ?>
					<?php echo($f2_ref_html); ?>
                <?php endif; ?>
				
                <?php break; case "visit_later": ?>
                    <p class="alert alert-info"><?php echo( __( 'You have to wait', 'wpbftd' )); ?> <?php echo $data["time_left"]; ?></p>
					<?php
					if( ($trof_exchange_list === true) &&  ($data["trof_exchange_list_mode"] == 'always') )
					{
						$trof_exchange_list_code = trof_get_exchange_list_code(true);
						echo("<div id='trof_exchange_list_wrap' class='alert alert-success' style='display:none;' >");
						echo($trof_exchange_list_code);
						echo("</div>");
					}					
					?>
					<?php echo($f2_ref_html); ?>
                <?php break; case "user_page": ?>
                <?php echo $data["user_page"]["html"]; ?>
                <?php break; endswitch; ?>
            </div>
			
            <div class="col-xs-6 col-md-3 col-md-pull-6 <?php echo $data["custom_box_left_bg"] . ' ' . $data["custom_box_left_tx"]; ?>"><?php echo $data["custom_box_left"]; ?></div>
			
            <div class="col-xs-6 col-md-3 <?php echo $data["custom_box_right_bg"] . ' ' . $data["custom_box_right_tx"]; ?>"><?php echo $data["custom_box_right"]; ?></div>

        </div>
	
	
        <div class="row">
            <div class="col-xs-12 <?php echo $data["custom_box_bottom_bg"] . ' ' . $data["custom_box_bottom_tx"]; ?>"><?php echo $data["custom_box_bottom"]; ?></div>
        </div>
		
		<div id='trof_extra_footer' style='display:none;'><?php /*echo $data["cus    tom_visitor_hint_NOBOX"]; */?></div>


<?php /*		
        <div class="row">			
            <?php if(!$data['disable_admin_panel'] && $data["custom_admin_link"] == 'true'): ?>
            <div class="admin_link"><a href="<?php echo($trof_admin_url); ?>">Admin Panel</a></div>
            <?php endif; ?>
        </div>
*/ ?>
    </div>
	
	
    <?php if($data['button_timer']): ?>
    <script> 
		jQuery(document).ready(function() {
			startTimer(<?php echo $data['button_timer']; ?>); 
		});
	</script>
    <?php endif; ?>

	
    <?php if($data['block_adblock'] == 'on'): ?>
<?php /*	
    <script>document.write('<div id="tester" style="display: none">an advertisement</div>');</script>
    <script type="text/javascript" src="libs/check.js"></script>
*/?>    
	<?php endif; ?>	
	
</div>


