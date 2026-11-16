<?php
global $EM_Event;
$required = apply_filters('em_required_html','<i>*</i>');

// the editor picks a mode (no location, a physical venue, a type of its own such as URL, a virtual venue or a hybrid venue) and then a platform for virtual and hybrid venues, which get_post() maps onto location_type and the hybrid fields
$online_types = $direct_types = array();
foreach( EM_Event_Locations\Event_Locations::get_types() as $event_location_type => $EM_Event_Location_Class ){ /* @var EM_Event_Locations\Event_Location $EM_Event_Location_Class */
	if( !$EM_Event_Location_Class::is_enabled() ) continue;
	$type_option = array(
		'display-class' => 'em-event-location-type-'. $EM_Event_Location_Class::$type,
		'description' => $EM_Event_Location_Class::get_label(),
		'joinable' => $EM_Event_Location_Class::supports_join(),
		'ticket-access' => $EM_Event_Location_Class::supports_ticket_access(),
	);
	if( $EM_Event_Location_Class::is_virtual() ){
		$online_types[$EM_Event_Location_Class::$type] = $type_option;
	}else{
		$direct_types[$EM_Event_Location_Class::$type] = $type_option;
	}
}
$location_modes = array();
if( !em_get_option('dbem_require_location') ){
	$location_modes['0'] = esc_html__('No Location','events-manager');
}
if( EM_Locations::is_enabled() ){
	$location_modes['in_person'] = EM_Event::get_location_mode_label('in_person');
}
foreach( $direct_types as $direct_type => $direct_type_option ){
	$location_modes[$direct_type] = $direct_type_option['description'];
}
if( !empty($online_types) ){
	$location_modes['online'] = EM_Event::get_location_mode_label('online');
}
if( EM_Event::is_hybrid_available() ){
	$location_modes['hybrid'] = EM_Event::get_location_mode_label('hybrid');
}
$single_mode = count($location_modes) == 1;
if( $EM_Event->is_hybrid() ){
	$location_mode = 'hybrid';
}elseif( !empty($EM_Event->location_id) ){
	$location_mode = 'in_person';
}elseif( $EM_Event->has_virtual_location() ){
	$location_mode = 'online';
}elseif( $EM_Event->has_event_location() ){
	$location_mode = $EM_Event->event_location_type;
}elseif( $EM_Event->location_id === '0' || $EM_Event->location_id === 0 ){
	$location_mode = '0';
}else{
	$location_mode = '';
}
$active_online_type = $EM_Event->has_virtual_location() ? $EM_Event->event_location_type : '';
// only people who can change the email templates are told about them
$join_placeholders_missing = $venue_email_for_everyone = false;
if( em_get_option('dbem_rsvp_enabled') && current_user_can('manage_options') ){
	foreach( array('dbem_bookings_email_pending_body', 'dbem_bookings_email_confirmed_body') as $email_body_option ){
		$email_body = (string) em_get_option($email_body_option);
		if( strpos($email_body, '#_LOCATION') !== false && strpos($email_body, '{booking_in_person}') === false ){
			$venue_email_for_everyone = true;
		}
	}
	$join_placeholders_missing = true;
	foreach( EM_Booking::get_join_placeholders() as $join_placeholder ){
		if( strpos( (string) em_get_option('dbem_bookings_email_confirmed_body'), $join_placeholder ) !== false ){
			$join_placeholders_missing = false;
		}
	}
}
?>
<div class="input em-input-field em-input-field-select em-location-types <?php if( $single_mode ) echo 'em-location-types-single'; ?>">
	<label for="location-mode"><?php esc_html_e ( 'Location Type', 'events-manager')?></label>
	<select name="location_mode" id="location-mode" class="em-location-mode-select" data-active="<?php echo esc_attr($location_mode); ?>" data-active-type="<?php echo esc_attr( $EM_Event->has_event_location() ? $EM_Event->event_location_type : '' ); ?>">
		<?php foreach( $location_modes as $mode => $mode_label ): ?>
		<option value="<?php echo esc_attr($mode); ?>" <?php if( !empty($direct_types[$mode]) ) echo 'data-display-class="'. esc_attr($direct_types[$mode]['display-class']) .'"'; ?> <?php if( (string) $mode === $location_mode ) echo 'selected="selected"'; ?>><?php echo esc_html($mode_label); ?></option>
		<?php endforeach; ?>
	</select>
	<?php if( $EM_Event->has_event_location() ): ?>
		<div class="em-location-type-delete-active-alert em-notice-warning">
			<div class="warning-bold">
				<p><em><?php esc_html_e('You are switching location type, if you update this event your event previous location data will be deleted.', 'events-manager'); ?></em></p>
			</div>
			<?php $EM_Event->get_event_location()->admin_delete_warning(); ?>
		</div>
	<?php endif; ?>
</div>
<?php if( EM_Locations::is_enabled() ): ?>
<div id="em-location-data" class="em-location-data em-location-where em-location-type em-location-type-place <?php if( $single_mode ) echo 'em-location-type-single ';  em_template_classes('event-editor'); if( em_get_option( 'dbem_gmap_is_active' ) ) echo ' has-map'; ?>">
	<div id="location_coordinates" style='display: none;'>
		<input id='location-latitude' name='location_latitude' type='text' value='<?php echo esc_attr($EM_Event->get_location()->location_latitude); ?>' size='15' >
		<input id='location-longitude' name='location_longitude' type='text' value='<?php echo esc_attr($EM_Event->get_location()->location_longitude); ?>' size='15' >
	</div>
	<div class="em-location-data input">
		<?php if( em_get_option('dbem_use_select_for_locations') || !$EM_Event->can_manage('edit_locations','edit_others_locations') ) : ?>
			<div class="input em-location-data-select">
				<label for="location-select-id"><?php esc_html_e('Location','events-manager') ?> </label>
				<select name="location_id" id='location-select-id' class="em-selectize">
					<?php
					if ( $single_mode && !em_get_option('dbem_require_location') ){ // we don't consider optional locations as a type for ddm
						?>
						<option value="0"><?php esc_html_e('No Location','events-manager'); ?></option>
						<?php
					}elseif( empty(em_get_option('dbem_default_location')) ){
						?>
						<option value="0"><?php esc_html_e('Select Location','events-manager'); ?></option>
						<?php
					}
					$ddm_args = array('private'=>$EM_Event->can_manage('read_private_locations'));
					$ddm_args['owner'] = (is_user_logged_in() && !current_user_can('read_others_locations')) ? get_current_user_id() : false;
					$locations = EM_Locations::get($ddm_args);
					$selected_location = !empty($EM_Event->location_id) || !empty($EM_Event->event_id) ? $EM_Event->location_id:em_get_option('dbem_default_location');
					foreach($locations as $EM_Location) {
						$selected = ($selected_location == $EM_Location->location_id) ? "selected='selected' " : '';
						if( $selected ) $found_location = true;
				        ?>
				        <option value="<?php echo esc_attr($EM_Location->location_id) ?>" title="<?php echo esc_attr("{$EM_Location->location_latitude},{$EM_Location->location_longitude}"); ?>" <?php echo $selected ?>><?php echo esc_html($EM_Location->location_name); ?></option>
				        <?php
					}
					if( empty($found_location) && !empty($EM_Event->location_id) ){
						$EM_Location = $EM_Event->get_location();
						if( $EM_Location->post_id ){
							?>
					        <option value="<?php echo esc_attr($EM_Location->location_id) ?>" title="<?php echo esc_attr("{$EM_Location->location_latitude},{$EM_Location->location_longitude}"); ?>" selected="selected"><?php echo esc_html($EM_Location->location_name); ?></option>
					        <?php
						}
					}
					?>
				</select>
			</div>
		<?php else : ?>
			<?php
			global $EM_Location;
			if( $EM_Event->location_id !== 0 ){
				$EM_Location = $EM_Event->get_location();
			}elseif(em_get_option('dbem_default_location') > 0){
				$EM_Location = em_get_location(em_get_option('dbem_default_location'));
			}else{
				$EM_Location = new EM_Location();
			}
			?>
			<div class="em-location-data-name">
				<label for="location-name"><?php _e ( 'Location Name', 'events-manager')?> <?php echo $required; ?></label>
				<input id='location-id' name='location_id' type='hidden' value='<?php echo esc_attr($EM_Location->location_id); ?>' size='15' >
				<input id="location-name" type="text" name="location_name" class="em-selectize-autocomplete em-selectize" value="<?php echo esc_attr($EM_Location->location_name); ?>" >
				<em id="em-location-search-tip"><?php esc_html_e( 'Create a location or start typing to search a previously created location.', 'events-manager')?></em>
				<em id="em-location-reset" style="display:none;"><?php esc_html_e('You cannot edit saved locations here.', 'events-manager'); ?> <a href="#"><?php esc_html_e('Reset this form to create a location or search again.', 'events-manager')?></a></em>
		    </div>
			<div class="input em-location-data-address">
				<label for="location-address"><?php _e ( 'Address', 'events-manager')?>&nbsp;<?php echo $required; ?></label>
				<input id="location-address" type="text" name="location_address" value="<?php echo esc_attr($EM_Location->location_address); ; ?>" >
			</div>
			<div class="input em-location-data-town">
				<label for="location-town"><?php _e ( 'City/Town', 'events-manager')?> <?php echo $required; ?></label>
				<input id="location-town" type="text" name="location_town" value="<?php echo esc_attr($EM_Location->location_town); ?>" >
			</div>
			<div class="input em-location-data-state">
				<label for="location-state"><?php _e ( 'State/County', 'events-manager')?></label>
				<input id="location-state" type="text" name="location_state" value="<?php echo esc_attr($EM_Location->location_state); ?>" >
			</div>
			<div class="input em-location-data-postcode">
				<label for="location-postcode"><?php _e ( 'Postcode', 'events-manager')?></label>
				<input id="location-postcode" type="text" name="location_postcode" value="<?php echo esc_attr($EM_Location->location_postcode); ?>" >
			</div>
			<div class="input em-location-data-region">
				<label for="location-region"><?php _e ( 'Region', 'events-manager')?></label>
				<input id="location-region" type="text" name="location_region" value="<?php echo esc_attr($EM_Location->location_region); ?>" >
			</div>
			<div class="input em-location-data-country">
				<label for="location-country"><?php _e ( 'Country', 'events-manager')?> <?php echo $required; ?></label>
				<select id="location-country" name="location_country" class="em-selectize">
					<option value="0" <?php echo ( $EM_Location->location_country == '' && $EM_Location->location_id == '' && em_get_option('dbem_location_default_country') == '' ) ? 'selected="selected"':''; ?>><?php _e('none selected','events-manager'); ?></option>
					<?php foreach(em_get_countries() as $country_key => $country_name): ?>
					<option value="<?php echo esc_attr($country_key); ?>" <?php echo ( $EM_Location->location_country == $country_key || ($EM_Location->location_country == '' && $EM_Location->location_id == '' && em_get_option('dbem_location_default_country')==$country_key) ) ? 'selected="selected"':''; ?>><?php echo esc_html($country_name); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="input em-location-data-url">
				<label for="location-url"><?php esc_html_e( 'URL', 'events-manager')?></label>
				<input id="location-url" type="text" name="location_url" value="<?php echo esc_attr($EM_Location->location_url); ?>" >
			</div>
		<?php endif; ?>
	</div>
	<?php if ( em_get_option( 'dbem_gmap_is_active' ) ):?>
		<?php em_locate_template('forms/map-container.php',true); ?>
	<?php endif; ?>
</div>
<?php endif; ?>
<?php if( !empty($online_types) ): ?>
<div class="em-location-type em-location-online-types em-input-field em-input-field-select input <?php if( $single_mode ) echo 'em-location-type-single'; ?>">
	<label for="location-online-type"><?php esc_html_e('Virtual Platform', 'events-manager'); ?></label>
	<select name="location_online_type" id="location-online-type" class="em-location-online-type" data-active="<?php echo esc_attr($active_online_type); ?>">
		<?php foreach( $online_types as $online_type => $online_type_option ): ?>
		<option value="<?php echo esc_attr($online_type); ?>" data-display-class="<?php echo esc_attr($online_type_option['display-class']); ?>" data-joinable="<?php echo $online_type_option['joinable'] ? 1 : 0; ?>" data-ticket-access="<?php echo $online_type_option['ticket-access'] ? 1 : 0; ?>" <?php if( $active_online_type === $online_type ) echo 'selected="selected"'; ?>><?php echo esc_html($online_type_option['description']); ?></option>
		<?php endforeach; ?>
	</select>
	<?php if( $join_placeholders_missing ): ?>
	<div class="em-location-join-email-notice em-notice-warning" style="display:none;">
		<p><em><?php echo sprintf( esc_html__('Your confirmed booking email has no join link, so attendees with virtual access will not receive one by email. Add %1$s to it in %2$s.', 'events-manager'), '<code>#_BOOKINGJOINLINK</code>', esc_html__('Settings > Emails', 'events-manager') ); ?></em></p>
	</div>
	<?php endif; ?>
	<?php if( $venue_email_for_everyone ): ?>
	<div class="em-location-venue-email-notice em-hybrid-only em-notice-warning" style="display:none;">
		<p><em><?php echo sprintf( esc_html__('Your booking emails show the venue to every attendee, including those with virtual-only tickets. Use %1$s for the Where line in %2$s so each attendee sees the venue, their join link or both.', 'events-manager'), '<code>#_BOOKINGLOCATION</code>', esc_html__('Settings > Emails', 'events-manager') ); ?></em></p>
	</div>
	<?php endif; ?>
</div>
<?php endif; ?>
<div class="em-event-location-data">
	<?php foreach( EM_Event_Locations\Event_Locations::get_types() as $event_location_type => $EM_Event_Location_Class ): /* @var EM_Event_Locations\Event_Location $EM_Event_Location_Class */ ?>
		<?php if( $EM_Event_Location_Class::is_enabled() ): ?>
			<div class="em-location-type em-event-location-type-<?php echo esc_attr($event_location_type); ?>  <?php if( $single_mode ) echo 'em-location-type-single'; ?> input">
			<?php $EM_Event_Location_Class::load_admin_template(); ?>
			</div>
		<?php endif; ?>
	<?php endforeach; ?>
</div>
<?php if( !empty($online_types) && EM\Event\Locations\Virtual::can_events_hide_links() ): ?>
<?php $default_hide_minutes = EM\Event\Locations\Virtual::get_link_hiding_mode() === 1 ? absint( em_get_option('dbem_virtual_links_hidden_minutes', 15) ) : 0; ?>
<div class="em-location-type em-location-link-hiding <?php if( $single_mode ) echo 'em-location-type-single'; ?> input">
	<p class="em-location-hide-minutes">
		<label for="event-virtual-hide-minutes"><?php echo sprintf( esc_html__('Hide link until %s minutes before the event starts', 'events-manager'), '<input type="number" min="0" step="1" name="event_virtual_hide_minutes" id="event-virtual-hide-minutes" class="em-location-hide-minutes-input" value="'. esc_attr( (string) $EM_Event->event_virtual_hide_minutes ) .'" placeholder="'. esc_attr($default_hide_minutes) .'">' ); ?></label>
	</p>
	<p class="em-location-hide-text" style="display:none;">
		<label for="event-virtual-hide-text"><?php esc_html_e('Text shown in place of the link until then', 'events-manager'); ?></label>
		<input type="text" name="event_virtual_hide_text" id="event-virtual-hide-text" style="width:100%;" value="<?php echo esc_attr( (string) $EM_Event->event_virtual_hide_text ); ?>" placeholder="<?php echo esc_attr( em_get_option('dbem_virtual_links_hidden_text') ?: EM\Event\Locations\Virtual::get_default_link_hidden_text() ); ?>">
	</p>
</div>
<?php endif; ?>