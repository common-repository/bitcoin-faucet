
//-----------------------------------------------------------------------------
	function  wpbf_fetch(wpbf_ajax_method,wpbf_ajax_data)
	{
		var wpbf_ajax_url = wpbf_site_url + '/wp-admin/admin-ajax.php'; //get_site_url()
//	console.log('About to ajax to ' + ajax_url); 
		jQuery.ajax({
			url: wpbf_ajax_url, // this is the object instantiated in wp_localize_script function
			type: wpbf_ajax_method,
			async:true,//well, default
			data:{
				action: 'wpbf_bitcoin_faucet_unique_action', // this is the function in your functions.php that will be triggered
				operation: 'wpbf_bitcoin_faucet_wrap',
				r: wpbf_get_ref,
				end:'end'
			},
			beforeSend:function(xhr, settings){
				settings.data += '&' + wpbf_ajax_data;
//console.log(settings.data);			
			},
			success: function( data ){
//console.log(' ajax success'); console.log( data );
				jQuery('#wpbf_bitcoin_faucet_wrap').html(data);
				wpbf_process_load_addr()
				wpbf_process_submit_hooks();
			},
			error: function(e){
				console.error('ajax error'); console.error(e);
				jQuery('#wpbf_bitcoin_faucet_wrap').html('AJAX ERROR on ' + ajax_url);
			},
		}).always(function() { 
				jQuery('#wpbf_bitcoin_faucet_wrap').show();
			});		 
	}//wpbf_fetch
//-----------------------------------------------------------------------------	
	function wpbf_process_submit_hooks()
	{
		jQuery('#wpbf-claim-form').submit(function (e) {
			e.preventDefault();
		});		
    
		jQuery('#wpbf-claim-form input[type=submit],#wpbf-claim-form button[type=submit]').click(function (e) {
            e.preventDefault();
			wpbf_ajax_data = jQuery('#wpbf-claim-form').serialize();
//console.log(wpbf_ajax_data);			
			wpbf_process_save_addr()
			wpbf_fetch('POST',wpbf_ajax_data);
		});		
	}//wpbf_process_submit_hooks
//-----------------------------------------------------------------------------
function wpbf_set_cookie(cname, cvalue, exdays) {
    var d = new Date();
    d.setTime(d.getTime() + (exdays*24*60*60*1000));
    var expires = "expires="+ d.toUTCString();
    document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
}
//-----------------------------------------------------------------------------	
	function wpbf_process_save_addr()
	{
		var addr = jQuery('.wpbf_address').val().trim();
		if(addr != '')
		{
			localStorage.setItem('wpbf_addr', addr);
			wpbf_set_cookie('address', addr, 1000);

		}
	}
//-----------------------------------------------------------------------------	
	function wpbf_process_load_addr()
	{
		var saved_addr = localStorage.getItem('wpbf_addr');
		if(saved_addr != null)
		{
			jQuery('.wpbf_address').val(saved_addr);
			if(jQuery('#trof_reflink').length > 0)
			{
				var ref_link = jQuery('#trof_reflink').html();
				ref_link = ref_link.replace('Your_Address',saved_addr);
				jQuery('#trof_reflink').html(ref_link);
			}
		}
	}	
//-----------------------------------------------------------------------------
	jQuery(document).ready(function() {
		wpbf_process_load_addr();
		jQuery('#wpbf-claim-form').submit(function (e) {
			wpbf_process_save_addr();
		});		
		jQuery('.trof_truncate').on('click',function (e) {
			jQuery(this).removeClass('trof_truncate').prop('title', '');
		});	
	});
//-------------------------------------------------

setTimeout(function(){
	jQuery('.trof_global_loader').hide();
},9000);
	
jQuery(document).ready(function() {
		jQuery('.trof_global_loader').hide();

		jQuery('.claim-button').on('click',function (e) {
			jQuery('.trof_global_loader').show();
			setTimeout(function(){jQuery(this).prop("disabled", true);},200); //if called directly  kills submit in chrome
			if ( ('isTrusted' in e.originalEvent) && (!e.originalEvent.isTrusted)) {
				jQuery(this).removeClass('btn_wait');
				e.preventDefault();
				e.stopPropagation();
			}
		});	
		
});	
	
	
	