<?php
/**
 * Event editor fields shared by online venue providers.
 *
 * @var string $EM_Event_Location_Class An EM_Event_Locations\Online_Venue subclass.
 */
global $EM_Event;
$type = $EM_Event_Location_Class::$type;
$name = 'event_location_' . $type;
$EM_Event_Location = $EM_Event->has_event_location( $type ) ? $EM_Event->get_event_location() : new $EM_Event_Location_Class( $EM_Event );
$accounts = $EM_Event_Location_Class::get_accounts();
$capabilities = $EM_Event_Location_Class::get_capabilities();
$session = $EM_Event->event_id && $EM_Event->has_event_location( $type ) ? $EM_Event_Location->get_session() : null;
?>
<div class="em-online-venue em-online-venue-<?php echo esc_attr( $type ); ?>">
	<?php if ( empty( $accounts ) ) : ?>
		<p class="em-notice-warning">
			<?php
			/* translators: %s is the provider name, such as Microsoft Teams */
			echo esc_html( sprintf( __( 'No %s account is connected yet.', 'events-manager' ), $EM_Event_Location_Class::get_label() ) );
			if ( current_user_can( 'manage_options' ) ) {
				echo ' <a href="' . esc_url( admin_url( 'admin.php?page=events-manager-options' ) ) . '">' . esc_html__( 'Connect an account', 'events-manager' ) . '</a>';
			}
			?>
		</p>
	<?php else : ?>
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>[fields]" value="1">
		<?php if ( count( $accounts ) > 1 ) : ?>
			<div class="em-input-field em-input-field-select input">
				<label for="<?php echo esc_attr( $name ); ?>-account"><?php esc_html_e( 'Host account', 'events-manager' ); ?></label>
				<select id="<?php echo esc_attr( $name ); ?>-account" name="<?php echo esc_attr( $name ); ?>[account]">
					<?php foreach ( $accounts as $account_id => $account_label ) : ?>
						<option value="<?php echo esc_attr( $account_id ); ?>" <?php selected( (string) $EM_Event_Location->account, (string) $account_id ); ?>><?php echo esc_html( $account_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php else : ?>
			<p class="em-online-venue-account"><?php echo esc_html( sprintf( __( 'Hosted by %s', 'events-manager' ), current( $accounts ) ) ); ?></p>
		<?php endif; ?>
		<?php foreach ( $EM_Event_Location_Class::settings_fields() as $field_name => $field ) :
			$field_id = $name . '-' . $field_name;
			$field_input = $name . '[settings][' . $field_name . ']';
			$value = $EM_Event_Location->get_setting( $field_name );
			?>
			<?php if ( $field['type'] === 'checkbox' ) : ?>
				<div class="em-input-field em-input-field-checkbox input">
					<label for="<?php echo esc_attr( $field_id ); ?>">
						<input type="checkbox" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_input ); ?>" value="1" <?php checked( ! empty( $value ) ); ?>>
						<?php echo esc_html( $field['label'] ); ?>
					</label>
				</div>
			<?php elseif ( $field['type'] === 'select' ) : ?>
				<div class="em-input-field em-input-field-select input">
					<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
					<select id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_input ); ?>">
						<?php foreach ( $field['options'] as $option_value => $option_label ) : ?>
							<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( (string) $value, (string) $option_value ); ?>><?php echo esc_html( $option_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php else : ?>
				<div class="em-input-field em-input-field-text input">
					<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
					<input type="text" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_input ); ?>" value="<?php echo esc_attr( (string) $value ); ?>">
				</div>
			<?php endif; ?>
		<?php endforeach; ?>
		<?php if ( empty( $capabilities['public_only'] ) ) : ?>
			<div class="em-input-field em-input-field-checkbox em-bookings-only input">
				<label for="<?php echo esc_attr( $name ); ?>-public-link">
					<input type="checkbox" id="<?php echo esc_attr( $name ); ?>-public-link" name="<?php echo esc_attr( $name ); ?>[public_link]" value="1" <?php checked( ! empty( $EM_Event_Location->public_link ) ); ?>>
					<?php esc_html_e( 'Show the join link publicly, for example on the event page and in calendar feeds', 'events-manager' ); ?>
				</label>
			</div>
		<?php else : ?>
			<p class="em-online-venue-public"><?php echo esc_html( sprintf( __( 'Everything on %s is public, so the link appears on the event page and in calendar feeds.', 'events-manager' ), $EM_Event_Location_Class::get_label() ) ); ?></p>
		<?php endif; ?>
		<?php if ( $session ) : ?>
			<div class="em-online-venue-status">
				<?php if ( $session->remote_id && $session->join_url ) : ?>
					<p><?php esc_html_e( 'Link', 'events-manager' ); ?>: <a href="<?php echo esc_url( $session->join_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $session->join_url ); ?></a></p>
				<?php elseif ( $session->queue === 'create' && $session->not_before && ! $session->last_error ) : ?>
					<p><?php echo esc_html( sprintf( __( '%1$s will be set up automatically on %2$s.', 'events-manager' ), $EM_Event_Location_Class::get_label(), get_date_from_gmt( $session->not_before, em_get_option( 'dbem_date_format' ) . ' ' . em_get_option( 'dbem_time_format' ) ) ) ); ?></p>
				<?php elseif ( $session->queue && ! $session->last_error ) : ?>
					<p><?php esc_html_e( 'Waiting to sync with the provider.', 'events-manager' ); ?></p>
				<?php endif; ?>
				<?php if ( $session->last_error ) : ?>
					<p class="em-notice-error"><?php echo esc_html( sprintf( __( 'Last sync failed: %s', 'events-manager' ), $session->last_error ) ); ?></p>
				<?php endif; ?>
				<?php do_action( 'em_online_venue_admin_status', $session, $EM_Event_Location ); ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
