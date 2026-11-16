<?php
namespace EM\Migrate;

use WP_CLI;

/**
 * Migrates data from other event plugins into Events Manager.
 *
 * ## EXAMPLES
 *
 *     wp em migrate status tec
 *     wp em migrate run tec --user=admin
 *     wp em migrate run tec --woo_bookings=em-woocommerce --user=admin
 *     wp em migrate rollback tec --user=admin
 *     wp em migrate run csv --file=events.csv --user=admin
 *     wp em migrate run csv --file=venues.csv --type=locations --map="Site=name,Postal town=town" --user=admin
 *     wp em migrate rollback csv --user=admin
 */
class CLI {

	/**
	 * Lists the sources EM can migrate from and what each holds.
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		$rows = array();
		foreach ( Migrate::get_sources() as $source ) {
			$job = new Job( $source->get_key() );
			$rows[] = array(
				'source' => $source->get_key(),
				'name' => $source->get_name(),
				'detected' => $source->is_detected() ? 'yes' : 'no',
				'plugin_active' => $source->is_plugin_active() ? 'yes' : 'no',
				'migrated_objects' => Map::count( $source->get_key() ),
				'job' => $job->status,
			);
		}
		WP_CLI\Utils\format_items( 'table', $rows, array_keys( $rows[0] ?? array( 'source' => 1 ) ) );
	}

	/**
	 * Shows what a source holds, what has been migrated and the state of the last run.
	 *
	 * <source>
	 * : Source key, e.g. tec.
	 */
	public function status( $args, $assoc_args ) {
		$source = $this->source( $args );
		WP_CLI::log( $source->get_name() );
		foreach ( $source->get_summary() as $label => $count ) {
			WP_CLI::log( sprintf( '  %-32s %d', $label, $count ) );
		}
		foreach ( $source->get_notices() as $notice ) {
			WP_CLI::log( '  ! ' . wp_strip_all_tags( $notice['message'] ) );
		}
		$job = new Job( $source->get_key() );
		WP_CLI::log( sprintf( 'Job: %s (%s), step %s', $job->status, $job->mode, $job->step ?: '-' ) );
		WP_CLI::log( 'Mapped: ' . wp_json_encode( Map::counts_by_type( $source->get_key() ) ) );
		if ( $job->stats ) {
			WP_CLI::log( 'Stats: ' . wp_json_encode( $job->stats ) );
		}
	}

	/**
	 * Runs (or resumes) a migration to the end. Already migrated objects are skipped unless they changed at the source since.
	 *
	 * <source>
	 * : Source key, e.g. tec.
	 *
	 * [--resume]
	 * : Continue an interrupted run instead of starting over.
	 *
	 * [--<field>=<value>]
	 * : Any of the source's options, see `wp em migrate options <source>`.
	 *
	 * [--file=<path>]
	 * : CSV source: the file to import. Its type is set with --type (events, locations or organizers).
	 *
	 * [--map=<pairs>]
	 * : CSV source: columns the automatic matching misses, as "Column=field,Column=field". An empty field leaves a column out. An unknown field lists the fields.
	 *
	 * [--delimiter=<character>]
	 * : CSV source: the column separator, when it is not detected. Use tab for a tab.
	 */
	public function run( $args, $assoc_args ) {
		$source = $this->source( $args );
		$this->require_user();
		$job = new Job( $source->get_key() );
		if ( empty( $assoc_args['resume'] ) || ! $job->started ) {
			$options = array();
			foreach ( $source->get_options() as $key => $def ) {
				$options[ $key ] = array_key_exists( $key, $assoc_args ) ? $assoc_args[ $key ] : $def['default'];
				if ( 'checkbox' === $def['type'] ) {
					$options[ $key ] = filter_var( $options[ $key ], FILTER_VALIDATE_BOOLEAN );
				}
			}
			$options = Migrate::sanitize_options( $source, $options + $assoc_args );
			if ( is_wp_error( $options ) ) {
				WP_CLI::error( $options->get_error_message() );
			}
			Runner::start( $source, $options );
		} elseif ( 'running' !== $job->status ) {
			$job->status = 'running';
			$job->save();
		}
		do {
			$job = Runner::tick( $source, 30 );
		} while ( 'running' === $job->status );
		$this->report( $source, $job );
	}

	/**
	 * Removes everything the migration created and restores converted content. The source plugin's data is never touched.
	 *
	 * <source>
	 * : Source key, e.g. tec.
	 *
	 * [--yes]
	 * : Skip the confirmation.
	 *
	 * [--import=<id>]
	 * : CSV source: the import to roll back, by the id `wp em migrate status csv` shows. Without it, the latest import.
	 */
	public function rollback( $args, $assoc_args ) {
		$source = $this->source( $args );
		$this->require_user();
		if ( isset( $assoc_args['import'] ) ) {
			$job = new Job( $source->get_key() );
			$job->options = array_merge( $job->options, array( 'import' => sanitize_key( $assoc_args['import'] ) ) );
			$job->save();
		}
		$count = Map::count( $source->get_key(), null, $source->get_rollback_scope( new Job( $source->get_key() ) ) );
		WP_CLI::confirm( sprintf( 'Delete all %d objects migrated from %s?', $count, $source->get_name() ), $assoc_args );
		Runner::start_rollback( $source );
		do {
			$job = Runner::tick( $source, 30 );
		} while ( 'running' === $job->status );
		$this->report( $source, $job );
	}

	/**
	 * Lists a source's run options.
	 *
	 * <source>
	 * : Source key, e.g. tec.
	 */
	public function options( $args, $assoc_args ) {
		$source = $this->source( $args );
		$rows = array();
		foreach ( $source->get_options() as $key => $def ) {
			$rows[] = array( 'option' => $key, 'default' => is_bool( $def['default'] ) ? ( $def['default'] ? 'true' : 'false' ) : $def['default'], 'choices' => isset( $def['choices'] ) ? implode( ', ', array_keys( $def['choices'] ) ) : 'true, false', 'label' => $def['label'] );
		}
		WP_CLI\Utils\format_items( 'table', $rows, array( 'option', 'default', 'choices', 'label' ) );
	}

	protected function source( $args ) {
		$source = Migrate::get_source( $args[0] ?? '' );
		if ( ! $source ) {
			WP_CLI::error( 'Unknown source. Available: ' . implode( ', ', array_keys( Migrate::get_sources() ) ) );
		}
		return $source;
	}

	protected function require_user() {
		if ( ! current_user_can( 'manage_options' ) ) {
			WP_CLI::error( 'Run this as an administrator, e.g. --user=admin. EM checks capabilities when saving events.' );
		}
	}

	protected function report( Source $source, Job $job ) {
		foreach ( $job->stats as $step => $counts ) {
			WP_CLI::log( sprintf( '%-14s %s', $step, wp_json_encode( $counts ) ) );
		}
		if ( 'error' === $job->status ) {
			WP_CLI::error( 'Stopped with an error; fix it and run again with --resume.' );
		}
		WP_CLI::success( sprintf( '%s: %s.', $source->get_name(), 'rolled_back' === $job->status ? 'rolled back' : 'migrated' ) );
	}
}
