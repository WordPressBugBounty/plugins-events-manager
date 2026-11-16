<?php
/**
 * Join page that embeds an online venue in an iframe once the join gate has authorized the booking.
 *
 * Override by copying to: yourtheme/plugins/events-manager/templates/event-location-embed.php
 *
 * @var EM_Event $EM_Event
 * @var EM_Booking $EM_Booking
 * @var string $embed_url
 * @var EM_Event_Locations\Online_Venue $EM_Event_Location
 */
if ( ! defined( 'ABSPATH' ) ) exit;
status_header( 200 );
header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $EM_Event->event_name ); ?></title>
	<style>
		html, body { margin: 0; height: 100%; background: #111; color: #eee; font-family: system-ui, sans-serif; }
		.em-event-location-embed { display: flex; flex-direction: column; height: 100%; }
		.em-event-location-embed header { padding: 12px 16px; font-size: 16px; }
		.em-event-location-embed iframe { flex: 1; width: 100%; border: 0; }
	</style>
</head>
<body>
	<div class="em-event-location-embed">
		<header><?php echo esc_html( $EM_Event->event_name ); ?></header>
		<iframe src="<?php echo esc_url( $embed_url ); ?>" title="<?php echo esc_attr( $EM_Event->event_name ); ?>" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
	</div>
</body>
</html>
