<?php
namespace EM\Migrate;

/**
 * A plugin whose data can be migrated into Events Manager. Register one with the `em_migrate_sources` filter.
 *
 * A source reads its data straight from the database, so it works whether or not the original plugin is still active.
 * Every object it creates is recorded in the Map, which the Runner uses to resume, skip unchanged objects on a re-run and roll back.
 */
abstract class Source {

	abstract public function get_key();

	abstract public function get_name();

	/**
	 * Whether this site holds data from the source plugin, active or not.
	 */
	abstract public function is_detected();

	/**
	 * Label => count of what would be migrated, for the overview screen.
	 */
	abstract public function get_summary();

	/**
	 * Ordered step key => label. Steps run in this order and each may depend on the ones before it.
	 */
	abstract public function get_steps();

	/**
	 * Processes one batch of a step.
	 *
	 * @param string $step
	 * @param mixed  $cursor Whatever the previous batch returned, null on the first batch.
	 * @param int    $limit  Batch size hint.
	 * @param Job    $job
	 * @return array{done: bool, cursor: mixed}
	 */
	abstract public function run_step( $step, $cursor, $limit, Job $job );

	/**
	 * Options shown before a run, key => array( label, type (checkbox|select), default, choices, description ).
	 */
	public function get_options() {
		return array();
	}

	/**
	 * Adds run settings that are more than a checkbox or a select, such as a file and its column mapping. Return a WP_Error to refuse the run.
	 *
	 * @param array $options The sanitized get_options() values.
	 * @param array $input   Everything the request or command line passed.
	 * @return array|\WP_Error
	 */
	public function prepare_options( array $options, array $input ) {
		return $options;
	}

	/**
	 * Whether the source is offered on every site, not only where its data is detected. Such a source is opened from Settings > Admin Tools and gets a tab on Events > Migrate, but does not put that page in the menu.
	 */
	public function is_always_available() {
		return false;
	}

	/**
	 * Whether the source draws its own setup on Events > Migrate, in admin_setup(), in place of the summary and options boxes.
	 */
	public function has_admin_setup() {
		return false;
	}

	/**
	 * Outputs the source's setup inside the #em-migrate wrapper. The script on the page drives a form with the id em-migrate-options and buttons with data-task set to start, resume, stop or rollback.
	 */
	public function admin_setup( Job $job ) {}

	/**
	 * Prefix of the mapped source ids a rollback removes and the progress screen counts, or null for everything from this source. A source that keeps separate runs apart returns the prefix of the job's run.
	 */
	public function get_rollback_scope( Job $job ) {
		return null;
	}

	/**
	 * A result table for the progress screen: array( summary, columns (labels), rows (arrays of cells), footnote ), or null for none.
	 */
	public function get_report( Job $job ) {
		return null;
	}

	/**
	 * Warnings to show before migrating (missing EM settings, unsupported data and so on).
	 */
	public function get_notices() {
		return array();
	}

	/**
	 * Maps an old front-end URL of the source plugin to its new EM URL. Only called on a 404 and only while the source plugin is inactive.
	 *
	 * @param string $path  Request path without home path, no leading or trailing slash.
	 * @param array  $query Query vars.
	 * @return string|null
	 */
	public function resolve_redirect( $path, $query ) {
		return null;
	}

	/**
	 * Whether the original plugin is still running, which means its own URLs still work and redirects stay off.
	 */
	public function is_plugin_active() {
		return false;
	}
}
