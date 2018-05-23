(function ($, Drupal) {
$('#block-mainnavigation li:first-child').addClass('opener');
$('#block-mainnavigation li:last-child').addClass('closer');
$('.front a:last-child').addClass('clear');
$('.buttons div:first-child').addClass('opener');
$('.buttons div:last-child').addClass('closer');
$('.date time:nth-child(2)').addClass('hide');
$('.datum time:nth-child(2)').addClass('hide');
})(jQuery, Drupal);
