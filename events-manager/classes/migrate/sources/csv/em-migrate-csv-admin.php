<?php
namespace EM\Migrate\Sources\CSV;

use EM\Migrate\Job;
use EM\Migrate\Map;
use EM\Migrate\Migrate;
use EM\Migrate\Runner;

/**
 * The CSV tab of Events > Migrate: upload a file, match its columns to fields, preview the first rows, then run. Earlier imports are listed so each can be rolled back on its own.
 */
class Admin {

	const NONCE = 'em_migrate_csv';
	const EXTENSIONS = array( 'csv', 'txt', 'tsv' );

	protected static function url() {
		return \EM\Migrate\Admin::url( CSV::KEY );
	}

	protected static function notice_key() {
		return 'em_migrate_csv_notice_' . get_current_user_id();
	}

	/**
	 * Handles the tab's own forms before the page is drawn, then redirects so a reload never repeats an upload or a rollback.
	 */
	public static function handle_request() {
		if ( empty( $_POST['em_migrate_csv_action'] ) || ! Migrate::can_migrate() ) {
			return;
		}
		check_admin_referer( static::NONCE );
		$source = Migrate::get_source( CSV::KEY );
		$job = new Job( CSV::KEY );
		$error = null;
		if ( 'running' === $job->status ) {
			$error = __( 'An import is running. Wait for it to finish, or pause it first.', 'events-manager' );
		} else {
			switch ( sanitize_key( wp_unslash( $_POST['em_migrate_csv_action'] ) ) ) {
				case 'upload':
					$error = static::upload();
					break;
				case 'preview':
					$error = static::save_mapping( $source );
					break;
				case 'discard':
					CSV::discard_pending();
					break;
				case 'rollback':
					$import = sanitize_key( wp_unslash( $_POST['import'] ?? '' ) );
					$history = CSV::get_history();
					if ( $import && isset( $history[ $import ] ) ) {
						$job->options = array( 'import' => $import, 'type' => $history[ $import ]['type'] ?? 'events', 'name' => $history[ $import ]['name'] ?? '' );
						$job->save();
						Runner::start_rollback( $source );
					}
					break;
			}
		}
		if ( $error ) {
			set_transient( static::notice_key(), is_wp_error( $error ) ? $error->get_error_message() : $error, MINUTE_IN_SECONDS );
		}
		wp_safe_redirect( static::url() );
		exit;
	}

	/**
	 * @return \WP_Error|null
	 */
	protected static function upload() {
		$file = isset( $_FILES['em_migrate_csv_file'] ) ? $_FILES['em_migrate_csv_file'] : null;
		if ( ! $file || ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new \WP_Error( 'em_csv_upload', __( 'The file did not upload. Choose a CSV file and try again.', 'events-manager' ) );
		}
		$name = sanitize_file_name( wp_unslash( $file['name'] ) );
		if ( ! in_array( strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ), static::EXTENSIONS, true ) ) {
			return new \WP_Error( 'em_csv_extension', __( 'That is not a CSV file. In your spreadsheet program, save or export the sheet as CSV first.', 'events-manager' ) );
		}
		$pending = CSV::create_pending( $file['tmp_name'], $name, sanitize_key( wp_unslash( $_POST['type'] ?? 'events' ) ) );
		return is_wp_error( $pending ) ? $pending : null;
	}

	/**
	 * Keeps the mapping and options chosen so far with the uploaded file, so the preview reflects them.
	 *
	 * @return \WP_Error|null
	 */
	protected static function save_mapping( CSV $source ) {
		$pending = CSV::get_pending();
		if ( ! $pending ) {
			return new \WP_Error( 'em_csv_no_file', __( 'Upload a file first.', 'events-manager' ) );
		}
		$input = wp_unslash( $_POST );
		$type = sanitize_key( $input['type'] ?? '' );
		if ( $type !== $pending['type'] && array_key_exists( $type, Fields::types() ) ) {
			$pending['type'] = $type;
			$pending['map'] = Fields::auto_map( $type, $pending['headers'] );
		} else {
			$map = CSV::read_map( $pending, $input );
			if ( is_wp_error( $map ) ) {
				return $map;
			}
			$pending['map'] = $map;
		}
		$pending['settings'] = Migrate::sanitize_fields( $source, $input );
		update_option( CSV::PENDING_OPTION, $pending, false );
		return null;
	}

	public static function setup( CSV $source, Job $job ) {
		$notice = get_transient( static::notice_key() );
		if ( $notice ) {
			delete_transient( static::notice_key() );
			echo '<div class="notice notice-error inline"><p>' . esc_html( $notice ) . '</p></div>';
		}
		$pending = 'running' === $job->status ? null : CSV::get_pending();
		echo '<div class="em-migrate-setup">';
		echo '<p>' . esc_html__( 'Imports events, locations or organizers from a CSV file, one row for each. A repeating event is one row too. Run it, check the result, and roll the import back if it is not what you wanted.', 'events-manager' ) . '</p>';
		if ( $pending ) {
			static::mapping_form( $source, $pending );
		} else {
			static::upload_form();
			static::history( $job );
			// the page script drives these two; a form with this id is where it reads run options from
			echo '<form id="em-migrate-options"><p>';
			echo '<button type="button" class="button" data-task="resume" style="display:none">' . esc_html__( 'Resume', 'events-manager' ) . '</button> ';
			echo '<button type="button" class="button" data-task="stop" style="display:none">' . esc_html__( 'Pause', 'events-manager' ) . '</button>';
			echo '</p></form>';
		}
		echo '</div>';
	}

	protected static function type_select( $selected, $attributes = '' ) {
		$html = '<select name="type" ' . $attributes . '>';
		foreach ( Fields::types() as $key => $label ) {
			$html .= '<option value="' . esc_attr( $key ) . '" ' . selected( $selected, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		return $html . '</select>';
	}

	protected static function upload_form() {
		?>
		<div class="postbox" style="padding:0 16px 12px;max-width:1164px" data-hide-running>
			<h2><?php esc_html_e( 'Upload a file', 'events-manager' ); ?></h2>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( static::url() ); ?>">
				<?php wp_nonce_field( static::NONCE ); ?>
				<input type="hidden" name="em_migrate_csv_action" value="upload">
				<p>
					<label><?php esc_html_e( 'This file holds', 'events-manager' ); ?> <?php echo static::type_select( 'events' ); ?></label>
					<input type="file" name="em_migrate_csv_file" accept=".csv,.txt,.tsv,text/csv" required>
				</p>
				<p class="description"><?php esc_html_e( 'The first row holds the column names. On the next screen you match each column to a field and see how the first rows will import, before anything is saved. A file made for the CSV importer of The Events Calendar works as it is.', 'events-manager' ); ?></p>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Upload and match columns', 'events-manager' ); ?></button></p>
			</form>
			<details>
				<summary><?php esc_html_e( 'Columns for repeating events', 'events-manager' ); ?></summary>
				<p><?php esc_html_e( 'Add any of these columns to an events file. A row with none of them filled in is a single event.', 'events-manager' ); ?></p>
				<table class="widefat striped" style="max-width:860px">
					<tbody>
						<tr><td><code>Repeats</code></td><td><?php esc_html_e( 'daily, weekly, monthly or yearly', 'events-manager' ); ?></td></tr>
						<tr><td><code>Repeat interval</code></td><td><?php esc_html_e( '2 for every second day, week, month or year. Leave empty for every one.', 'events-manager' ); ?></td></tr>
						<tr><td><code>Repeat on weekdays</code></td><td><?php esc_html_e( 'For weekly: one or more, such as Mon, Wed. For monthly: one, used with the week of the month.', 'events-manager' ); ?></td></tr>
						<tr><td><code>Repeat on week of the month</code></td><td><?php esc_html_e( 'For monthly: first, second, third, fourth or last.', 'events-manager' ); ?></td></tr>
						<tr><td><code>Repeat until</code></td><td><?php esc_html_e( 'The last date the event can fall on.', 'events-manager' ); ?></td></tr>
						<tr><td><code>Repeat on these dates</code></td><td><?php esc_html_e( 'A list of further dates, such as 2027-03-11|2027-03-18, used alone or with a rule.', 'events-manager' ); ?></td></tr>
						<tr><td><code>Repeat on day of the month</code>, <code>Repeat a number of times</code></td><td><?php esc_html_e( 'Events Manager has no rule for these yet. Such rows are imported as a list of dates, or skipped if you choose that, and the result report names each one.', 'events-manager' ); ?></td></tr>
					</tbody>
				</table>
			</details>
		</div>
		<?php
	}

	protected static function mapping_form( CSV $source, array $pending ) {
		$fields = Fields::get( $pending['type'] );
		$settings = isset( $pending['settings'] ) && is_array( $pending['settings'] ) ? $pending['settings'] : array();
		// only the options that mean something for this kind of file
		$relevant = array( 'events' => array( 'status', 'date_order', 'unsupported_recurrence', 'skip_duplicates', 'images' ), 'locations' => array( 'status', 'skip_duplicates', 'images' ), 'organizers' => array( 'images' ) );
		$options = array_intersect_key( $source->get_options(), array_flip( $relevant[ $pending['type'] ] ) );
		foreach ( $options as $key => $def ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				$settings[ $key ] = $def['default'];
			}
		}
		$first = File::read( $pending['file'], $pending['delimiter'], 0, 1, 1 );
		$sample = is_wp_error( $first ) || ! $first['rows'] ? array() : reset( $first['rows'] );
		$missing = Fields::missing_required( $pending['type'], $pending['map'] );
		$preview = $missing ? array() : $source->preview( $pending, $pending['map'], $settings );
		?>
		<form id="em-migrate-options" method="post" action="<?php echo esc_url( static::url() ); ?>" data-hide-running>
			<?php wp_nonce_field( static::NONCE ); ?>
			<input type="hidden" name="em_migrate_csv_action" value="preview">
			<div class="postbox" style="padding:0 16px 12px;max-width:1164px">
				<h2><?php esc_html_e( '1. Match columns', 'events-manager' ); ?></h2>
				<p>
					<strong><?php echo esc_html( $pending['name'] ); ?></strong>,
					<?php echo esc_html( sprintf( _n( '%s row', '%s rows', $pending['rows'], 'events-manager' ), number_format_i18n( $pending['rows'] ) ) ); ?>.
					<label><?php esc_html_e( 'This file holds', 'events-manager' ); ?> <?php echo static::type_select( $pending['type'], 'onchange="this.form.submit()"' ); ?></label>
					<button type="submit" class="button-link" name="em_migrate_csv_action" value="discard" formnovalidate><?php esc_html_e( 'Choose another file', 'events-manager' ); ?></button>
				</p>
				<table class="widefat striped" style="max-width:960px">
					<thead><tr><th><?php esc_html_e( 'Column in the file', 'events-manager' ); ?></th><th><?php esc_html_e( 'First row', 'events-manager' ); ?></th><th><?php esc_html_e( 'Import as', 'events-manager' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $pending['headers'] as $i => $header ) : ?>
						<tr>
							<td><?php echo '' === $header ? '<em>' . esc_html( sprintf( __( 'Column %d', 'events-manager' ), $i + 1 ) ) . '</em>' : esc_html( $header ); ?></td>
							<td><?php echo esc_html( wp_html_excerpt( isset( $sample[ $i ] ) ? $sample[ $i ] : '', 70, '…' ) ); ?></td>
							<td>
								<select name="map_<?php echo (int) $i; ?>">
									<option value=""><?php esc_html_e( 'Do not import', 'events-manager' ); ?></option>
									<?php foreach ( $fields as $key => $field ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $pending['map'][ $i ] ?? '', $key ); ?>><?php echo esc_html( $field['label'] . ( ! empty( $field['required'] ) ? ' *' : '' ) ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description"><?php esc_html_e( 'Fields marked * are needed. A field can be given to one column only.', 'events-manager' ); ?></p>
				<?php if ( $missing ) : ?>
					<div class="notice notice-warning inline"><p><?php echo esc_html( sprintf( __( 'No column is matched to: %s.', 'events-manager' ), implode( ', ', $missing ) ) ); ?></p></div>
				<?php endif; ?>
			</div>
			<div class="postbox" style="padding:0 16px 12px;max-width:1164px">
				<h2><?php esc_html_e( '2. Options', 'events-manager' ); ?></h2>
				<?php foreach ( $options as $key => $def ) : ?>
					<p>
						<?php if ( 'checkbox' === $def['type'] ) : ?>
							<label><input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $settings[ $key ] ); ?>> <?php echo esc_html( $def['label'] ); ?></label>
						<?php else : ?>
							<label for="em-migrate-<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $def['label'] ); ?></strong></label><br>
							<select name="<?php echo esc_attr( $key ); ?>" id="em-migrate-<?php echo esc_attr( $key ); ?>">
								<?php foreach ( $def['choices'] as $choice => $label ) : ?>
									<option value="<?php echo esc_attr( $choice ); ?>" <?php selected( $settings[ $key ], $choice ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						<?php endif; ?>
						<?php if ( ! empty( $def['description'] ) ) : ?>
							<br><span class="description"><?php echo esc_html( $def['description'] ); ?></span>
						<?php endif; ?>
					</p>
				<?php endforeach; ?>
			</div>
			<div class="postbox" style="padding:0 16px 12px;max-width:1164px">
				<h2><?php esc_html_e( '3. Preview', 'events-manager' ); ?></h2>
				<?php if ( $preview ) : ?>
					<p class="description"><?php esc_html_e( 'How the first rows will import with the columns and options as last previewed. Nothing has been saved yet.', 'events-manager' ); ?></p>
					<table class="widefat striped em-migrate-preview">
						<thead><tr>
							<th><?php esc_html_e( 'Row', 'events-manager' ); ?></th>
							<?php foreach ( array_keys( $preview[0]['cells'] ) as $label ) : ?>
								<th><?php echo esc_html( $label ); ?></th>
							<?php endforeach; ?>
							<th><?php esc_html_e( 'Result', 'events-manager' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $preview as $entry ) : ?>
							<tr>
								<td><?php echo (int) $entry['row']; ?></td>
								<?php foreach ( array_keys( $preview[0]['cells'] ) as $label ) : ?>
									<td><?php echo esc_html( wp_html_excerpt( isset( $entry['cells'][ $label ] ) ? $entry['cells'][ $label ] : '', 90, '…' ) ); ?></td>
								<?php endforeach; ?>
								<td>
									<?php if ( $entry['problem'] ) : ?>
										<strong style="color:#b32d2e"><?php esc_html_e( 'Will be skipped', 'events-manager' ); ?></strong><br><?php echo esc_html( $entry['problem'] ); ?>
									<?php else : ?>
										<?php esc_html_e( 'Will be imported', 'events-manager' ); ?>
										<?php foreach ( $entry['notes'] as $note ) : ?>
											<br><span class="description"><?php echo esc_html( $note ); ?></span>
										<?php endforeach; ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p><?php esc_html_e( 'Match the needed columns, then update the preview.', 'events-manager' ); ?></p>
				<?php endif; ?>
				<p>
					<button type="submit" class="button"><?php esc_html_e( 'Update preview', 'events-manager' ); ?></button>
					<button type="button" class="button button-primary" data-task="start"><?php esc_html_e( 'Start import', 'events-manager' ); ?></button>
				</p>
			</div>
		</form>
		<p>
			<button type="button" class="button" data-task="resume" style="display:none"><?php esc_html_e( 'Resume', 'events-manager' ); ?></button>
			<button type="button" class="button" data-task="stop" style="display:none"><?php esc_html_e( 'Pause', 'events-manager' ); ?></button>
		</p>
		<?php
	}

	protected static function history( Job $job ) {
		$history = array_reverse( CSV::get_history(), true );
		if ( ! $history ) {
			return;
		}
		$types = Fields::types();
		$statuses = array( 'running' => __( 'Not finished', 'events-manager' ), 'done' => __( 'Finished', 'events-manager' ), 'rolled_back' => __( 'Rolled back', 'events-manager' ) );
		?>
		<div class="postbox" style="padding:0 16px 12px;max-width:1164px">
			<h2><?php esc_html_e( 'Imports', 'events-manager' ); ?></h2>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'File', 'events-manager' ); ?></th>
					<th><?php esc_html_e( 'Holds', 'events-manager' ); ?></th>
					<th><?php esc_html_e( 'Started', 'events-manager' ); ?></th>
					<th><?php esc_html_e( 'Rows imported', 'events-manager' ); ?></th>
					<th><?php esc_html_e( 'Rows skipped', 'events-manager' ); ?></th>
					<th><?php esc_html_e( 'Status', 'events-manager' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $history as $import => $entry ) :
					$import = (string) $import;
					$objects = Map::count( CSV::KEY, null, $import . ':' );
					?>
					<tr>
						<td><?php echo esc_html( $entry['name'] ?? '' ); ?></td>
						<td><?php echo esc_html( $types[ $entry['type'] ?? '' ] ?? '' ); ?></td>
						<td><?php echo esc_html( ! empty( $entry['started'] ) ? get_date_from_gmt( $entry['started'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '' ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) ( $entry['imported'] ?? 0 ) ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) ( $entry['skipped'] ?? 0 ) ) ); ?></td>
						<td><?php echo esc_html( $statuses[ $entry['status'] ?? '' ] ?? '' ); ?></td>
						<td>
							<?php if ( $objects ) : ?>
								<form method="post" action="<?php echo esc_url( static::url() ); ?>" data-hide-running onsubmit="return window.confirm( this.dataset.confirm );" data-confirm="<?php echo esc_attr( sprintf( __( 'Delete everything the import of %s created (events, locations, organizers, categories, tags and downloaded images)? Items that were already on the site are not affected.', 'events-manager' ), $entry['name'] ?? '' ) ); ?>">
									<?php wp_nonce_field( static::NONCE ); ?>
									<input type="hidden" name="em_migrate_csv_action" value="rollback">
									<input type="hidden" name="import" value="<?php echo esc_attr( $import ); ?>">
									<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Roll back', 'events-manager' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
