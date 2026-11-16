<?php
/**
 * Message shown by the join gate when a booking's join link cannot be followed yet, or at all.
 *
 * Override by copying to: yourtheme/plugins/events-manager/templates/event-location-join.php
 *
 * @var WP_Error $error
 * @var EM_Event|null $EM_Event
 * @var EM_Booking|null $EM_Booking
 * @var array $window Keys 'opens' and 'closes' as UTC timestamps, empty when not known.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
$code = $error->get_error_code();
?>
<div class="<?php em_template_classes( 'event-location-join' ); ?> em-event-location-join-<?php echo esc_attr( str_replace( 'em_join_', '', $code ) ); ?>">
	<?php if ( $EM_Event instanceof EM_Event ) : ?>
		<h1 class="em-event-location-join-title"><?php echo esc_html( $EM_Event->event_name ); ?></h1>
	<?php endif; ?>
	<p class="em-event-location-join-message"><?php echo esc_html( $error->get_error_message() ); ?></p>
	<?php if ( $code === 'em_join_too_early' && ! empty( $window['opens'] ) ) : ?>
		<p class="em-event-location-join-opens">
			<?php
			$opens = $EM_Event->start()->copy()->setTimestamp( $window['opens'] );
			/* translators: %s is the date and time the join link starts working, in the event's timezone */
			echo esc_html( sprintf( __( 'You can join from %s.', 'events-manager' ), $opens->i18n( em_get_option( 'dbem_date_format' ) . ' ' . em_get_option( 'dbem_time_format' ) ) . ' ' . $opens->getTimezone()->getName() ) );
			?>
		</p>
	<?php endif; ?>
	<?php if ( $EM_Event instanceof EM_Event ) : ?>
		<p class="em-event-location-join-event-link"><a href="<?php echo esc_url( $EM_Event->get_permalink() ); ?>"><?php esc_html_e( 'View event details', 'events-manager' ); ?></a></p>
	<?php endif; ?>
</div>
