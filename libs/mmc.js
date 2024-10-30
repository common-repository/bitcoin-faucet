jQuery(document).ready(function() {
	jQuery(document).mousemove(function() {
		var wpbf_mmc_ajax_url = wpbf_site_url + '/wp-admin/admin-ajax.php'; //get_site_url()
		jQuery.ajax({
			url: wpbf_mmc_ajax_url, // this is the object instantiated in wp_localize_script function
			type: 'POST',
			async:true,//well, default
			data:{
				action: 'wpbf_bitcoin_faucet_mmc_unique_action', // this is the function in your functions.php that will be triggered
				operation: 'none'
			}
		});
		jQuery(document).off("mousemove");
	});
});
