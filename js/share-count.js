jQuery(document).ready(function($){
	var toggle = function() { $(this).parent().toggleClass('open'); };
	$('.share-button').click(toggle);
	
});