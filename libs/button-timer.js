

function startTimer(seconds) {
    var buttons = jQuery(".claim-button");
    buttons.each(function(i, button) {
        button = jQuery(button);
        var org_text = button.text();
        if(!org_text) org_text = button.val();

        button
            .prop("disabled", true)
            .text(seconds)
            .val(seconds);

        var i = setInterval(function() {
			if(!f2_is_visible)
			{
				return; 
			}
            seconds -= 1;
            if(seconds <= 0) {
                clearInterval(i);
                button
                    .prop("disabled", false)
                    .text(org_text)
                    .val(org_text);
				jQuery('#recaptcha_area').fadeIn();
				jQuery('.g-recaptcha').fadeIn();
            } else {
                button.text(seconds).val(seconds);
            }
        }, 1000);

        window.disableButtonTimer = function() {
            clearInterval(i);
        };
    });
}

var f2_is_visible = true;

function f2_getHiddenProp(){
    var prefixes = ['webkit','moz','ms','o'];
    if ('hidden' in document) return 'hidden';
    for (var i = 0; i < prefixes.length; i++){
        if ((prefixes[i] + 'Hidden') in document) 
            return prefixes[i] + 'Hidden';
    }
    return null;
}

function f2_isHidden() {
    var prop = f2_getHiddenProp();
    if (!prop) return false;
    return document[prop];
}
var f2_visProp = f2_getHiddenProp();
if (f2_visProp) {
  var evtname = f2_visProp.replace(/[H|h]idden/,'') + 'visibilitychange';
  document.addEventListener(evtname, f2_visChange);
}
function f2_visChange() {
	if (f2_isHidden())
	{
		f2_is_visible = false;
	}
    else
	{
		f2_is_visible = true;
	}
}
