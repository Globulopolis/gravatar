jQuery(document).ready(function($){
	$('a.gravatar-profile-avatar').click(function(e){
		e.preventDefault();

		// Prevent to show big photo if content column width is very small
		if ($(this).closest('.item').width() > '128') {
			$(this).closest('.gravatar-profile-shortinfo').next('.gravatar-profile-info').find('.right-col').hide();
		}

		$(this).closest('.gravatar-profile-shortinfo').next('.gravatar-profile-info').toggle();
	});
});
