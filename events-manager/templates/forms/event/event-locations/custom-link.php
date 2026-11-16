<?php
global $EM_Event;
$EM_Custom_Link = $EM_Event->has_event_location('custom_link') ? $EM_Event->get_event_location() : null;
?>
<div class="em-input-field em-input-field-text em-location-data-custom-link input">
	<label for="event_location_custom_link_url"><?php esc_html_e( 'URL', 'events-manager')?></label>
	<input id="event_location_custom_link_url" type="text" name="event_location_custom_link_url" value="<?php if( $EM_Custom_Link ) echo esc_attr($EM_Custom_Link->url); ?>" />
</div>
<div class="em-input-field em-input-field-text em-location-data-custom-link-text input">
	<label for="event_location_custom_link_text"><?php esc_html_e( 'Link Text', 'events-manager')?></label>
	<input id="event_location_custom_link_text" type="text" name="event_location_custom_link_text" value="<?php if( $EM_Custom_Link ) echo esc_attr($EM_Custom_Link->text); ?>" />
</div>
<div class="em-input-field em-input-field-checkbox em-location-data-custom-link-private em-bookings-only input">
	<input type="hidden" name="event_location_custom_link_private_form" value="1">
	<label for="event_location_custom_link_private">
		<input id="event_location_custom_link_private" type="checkbox" name="event_location_custom_link_private" value="1" <?php if( $EM_Custom_Link && !empty($EM_Custom_Link->private) ) echo 'checked="checked"'; ?>>
		<?php esc_html_e( 'Only show this link to attendees with virtual access', 'events-manager'); ?>
	</label>
	<p><em><?php esc_html_e( 'Everyone else sees the link text without the link. Attendees whose booking includes virtual access get their own join link once the booking is approved. Without bookings, the link is always shown.', 'events-manager'); ?></em></p>
</div>
