jQuery(document).ready(function($){
	$('a.gravatar-profile-avatar').click(function(e){
		e.preventDefault();

		$(this).closest('.gravatar-profile-shortinfo').next('.gravatar-profile-info').fadeToggle();
	});
	$('.buttons .cmd-hide').click(function(e){
		e.preventDefault();

		$(this).closest('.gravatar-profile-info').fadeToggle();
	});
});
