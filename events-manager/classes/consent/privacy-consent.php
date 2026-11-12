<?php
namespace EM\Consent;

class Privacy extends Consent {
	
	public static $options = array(
		'remember' => 'dbem_data_privacy_consent_remember',
		'label' => 'dbem_data_privacy_consent_text',
		'param' => 'data_privacy_consent',
		'meta_key' => 'em_data_privacy_consent',
	);
	
	public static $prefix = 'privacy_consent';
	public static $required = true;
	
	public static function init() {
		parent::init();
		if( is_admin() ) {
			include('privacy-consent-admin.php');
		}
		add_filter('em_bookings_table_cols_template', [ static::class, 'em_bookings_table_cols_template' ], 10, 2);
		add_filter('em_bookings_table_rows_col_' . static::$prefix, [ static::class, 'em_bookings_table_rows_col' ], 10, 3);
		add_action('em_person_display_summary_bottom', [ static::class, 'em_person_display_summary_bottom' ]);
		add_filter('em_person_get_summary', [ static::class, 'em_person_get_summary' ], 10, 2);
	}

	/**
	 * When consent was given for a booking, falling back to the booker's account for bookings made before it was stored on the booking.
	 * @param \EM_Booking $EM_Booking
	 * @return string|null
	 */
	public static function get_booking_consent( $EM_Booking ) {
		$consent = $EM_Booking->booking_meta['registration'][ static::$options['meta_key'] ] ?? null;
		return $consent ?: $EM_Booking->get_user_meta( static::$options['meta_key'] );
	}

	/**
	 * Formats a stored consent value as a date, or No when consent wasn't given.
	 * @param string|null $consent
	 * @return string
	 */
	public static function format_consent( $consent ) {
		if( !$consent ) return __('No', 'events-manager');
		if( !preg_match('/^\d{4}-\d{2}-\d{2}/', $consent) ) return __('Yes', 'events-manager');
		return \EM_DateTime::create( $consent, 'UTC' )->setTimezone()->formatDefault();
	}

	public static function em_bookings_table_cols_template( $template, $EM_Bookings_Table ) {
		if( !em_get_option('dbem_data_' . static::$prefix . '_bookings') ) return $template;
		$template[ static::$prefix ] = esc_html__('Privacy Consent', 'events-manager');
		return $template;
	}

	public static function em_bookings_table_rows_col( $val, $EM_Object, $EM_Bookings_Table ) {
		$EM_Booking = $EM_Bookings_Table->get_item_objects( $EM_Object )['EM_Booking'] ?? null;
		return $EM_Booking ? static::format_consent( static::get_booking_consent( $EM_Booking ) ) : $val;
	}

	public static function em_person_display_summary_bottom( $EM_Person ) {
		if( !em_get_option('dbem_data_' . static::$prefix . '_bookings') ) return;
		?>
		<tr>
			<th><?php esc_html_e('Privacy Consent','events-manager'); ?> : </th>
			<td><?php echo esc_html( static::format_consent( $EM_Person->{ static::$options['meta_key'] } ) ); ?></td>
		</tr>
		<?php
	}

	public static function em_person_get_summary( $summary, $EM_Person ) {
		$summary['privacy_consent_given'] = array( 'name' => __('Privacy Consent', 'events-manager'), 'value' => $EM_Person->{ static::$options['meta_key'] } );
		return $summary;
	}
	
	public static function get_error_booking() {
		return esc_html__('You must allow us to collect and store your data in order for us to process your booking.', 'events-manager');
	}
	
	public static function get_error_cpt() {
		return esc_html__('Please check the consent box so we can collect and store your submitted information.', 'events-manager');
	}
	
	public static function get_label() {
		$label = em_get_option( static::$options['label'] );
		// buddyboss fix since bb v1.6.0
		if( has_filter( 'the_privacy_policy_link', 'bp_core_change_privacy_policy_link_on_private_network') ) $bb_fix = remove_filter('the_privacy_policy_link', 'bp_core_change_privacy_policy_link_on_private_network', 999999);
		// replace privacy policy text %s with link to policy page
		if( function_exists('get_the_privacy_policy_link') ) $label = sprintf($label, get_the_privacy_policy_link());
		// buddyboss unfix since bb v1.6.0
		if( !empty($bb_fix) ) add_filter( 'the_privacy_policy_link', 'bp_core_change_privacy_policy_link_on_private_network', 999999, 2 );
		return $label;
	}
}

Privacy::init();