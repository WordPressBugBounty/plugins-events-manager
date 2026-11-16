<?php
namespace EM\Migrate;

/**
 * Events > Migrate: what each detected source holds, run options, live progress and rollback.
 */
class Admin {

	const PAGE = 'events-manager-migrate';

	public static function init() {
		add_filter( 'em_create_events_submenu', array( static::class, 'submenu' ) );
		add_action( 'em_options_page_panel_admin_tools', array( static::class, 'admin_tools_box' ) );
		add_action( 'admin_notices', array( static::class, 'detected_notice' ) );
	}

	public static function submenu( $plugin_pages ) {
		// a source that needs nothing on the site, such as a CSV file, is opened from Settings > Admin Tools and does not put the page in the menu
		$found = array_filter( Migrate::get_available_sources(), function( Source $source ) {
			return ! $source->is_always_available();
		} );
		if ( $found || ( isset( $_GET['page'] ) && static::PAGE === $_GET['page'] ) ) {
			$title = $found ? __( 'Migrate to Events Manager', 'events-manager' ) : __( 'Import into Events Manager', 'events-manager' );
			$label = $found ? __( 'Migrate', 'events-manager' ) : __( 'Import', 'events-manager' );
			$plugin_pages['migrate'] = add_submenu_page( 'edit.php?post_type=' . EM_POST_TYPE_EVENT, $title, $label, 'manage_options', static::PAGE, array( static::class, 'page' ) );
		}
		return $plugin_pages;
	}

	public static function url( $source = null ) {
		return add_query_arg( array_filter( array( 'post_type' => EM_POST_TYPE_EVENT, 'page' => static::PAGE, 'source' => $source ) ), admin_url( 'edit.php' ) );
	}

	/**
	 * Offers the migration on every admin screen while another calendar's data is on the site and nothing has been migrated from it, until it is dismissed.
	 */
	public static function detected_notice() {
		if ( ! Migrate::can_migrate() || get_option( 'dbem_migrate_notice_dismissed' ) || ( isset( $_GET['page'] ) && static::PAGE === $_GET['page'] ) ) {
			return;
		}
		if ( isset( $_GET['em_migrate_dismiss'] ) && check_admin_referer( 'em_migrate_dismiss' ) ) {
			update_option( 'dbem_migrate_notice_dismissed', 1 );
			return;
		}
		foreach ( Migrate::get_sources() as $source ) {
			if ( ! $source->is_detected() || Map::count( $source->get_key() ) ) {
				continue;
			}
			$name = esc_html( $source->get_name() );
			if ( $source->is_plugin_active() ) {
				$message = sprintf( esc_html__( 'Events Manager can copy your events, venues, organizers, tickets and attendees from %1$s. Nothing in %1$s is changed, so you can check the result before switching it off.', 'events-manager' ), $name );
			} else {
				$message = sprintf( esc_html__( 'Events Manager found events from %s on this site and can copy them, with their venues, organizers, tickets and attendees.', 'events-manager' ), $name );
			}
			$dismiss = wp_nonce_url( add_query_arg( 'em_migrate_dismiss', 1 ), 'em_migrate_dismiss' );
			echo '<div class="notice notice-info em-migrate-notice"><p><strong>' . sprintf( esc_html__( 'Moving from %s?', 'events-manager' ), $name ) . '</strong> ' . $message . '</p>';
			echo '<p><a class="button button-primary" href="' . esc_url( static::url( $source->get_key() ) ) . '">' . esc_html__( 'Migrate to Events Manager', 'events-manager' ) . '</a> <a class="button button-link" href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'events-manager' ) . '</a></p></div>';
			return;
		}
	}

	public static function admin_tools_box() {
		$sources = Migrate::get_sources();
		if ( ! $sources || ! Migrate::can_migrate() ) {
			return;
		}
		// imports first, then the plugins that can be migrated from
		uasort( $sources, function( Source $a, Source $b ) {
			return (int) $b->has_admin_setup() <=> (int) $a->has_admin_setup();
		} );
		?>
		<table class="form-table">
			<tr class="em-header"><td colspan="2">
				<h4><?php esc_html_e( 'Import and Migrate', 'events-manager' ); ?></h4>
				<p><?php esc_html_e( 'Import events, locations or organizers from a CSV file, or copy your events, venues, organizers, tickets and attendees from another events plugin. Either can be rolled back, and the original data is left untouched.', 'events-manager' ); ?></p>
			</td></tr>
			<tr><td colspan="2">
				<?php foreach ( $sources as $source ) : ?>
					<a class="button-secondary" href="<?php echo esc_url( static::url( $source->get_key() ) ); ?>"><?php echo esc_html( sprintf( $source->has_admin_setup() ? __( 'Import a %s', 'events-manager' ) : __( 'Migrate from %s', 'events-manager' ), $source->get_name() ) ); ?></a>
				<?php endforeach; ?>
			</td></tr>
		</table>
		<?php
	}

	public static function page() {
		if ( ! Migrate::can_migrate() ) {
			wp_die( esc_html__( 'You do not have permission to migrate data.', 'events-manager' ) );
		}
		Map::install();
		$sources = Migrate::get_sources();
		$key = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : key( Migrate::get_available_sources() ?: $sources );
		$source = Migrate::get_source( $key );
		// a tab for each source with something to offer here, and for the one that was asked for
		$tabs = Migrate::get_available_sources() + array_filter( array( $key => $source ) );
		?>
		<div class="wrap em-migrate">
			<h1><?php $source && $source->has_admin_setup() ? esc_html_e( 'Import into Events Manager', 'events-manager' ) : esc_html_e( 'Migrate to Events Manager', 'events-manager' ); ?></h1>
			<?php if ( count( $tabs ) > 1 ) : ?>
				<nav class="nav-tab-wrapper">
					<?php foreach ( $tabs as $s ) : ?>
						<a class="nav-tab <?php echo $s === $source ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( static::url( $s->get_key() ) ); ?>"><?php echo esc_html( $s->get_name() ); ?></a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
			<?php if ( ! $source ) : ?>
				<p><?php esc_html_e( 'No supported plugin data was found on this site.', 'events-manager' ); ?></p>
			<?php else : static::source_panel( $source ); endif; ?>
		</div>
		<?php
	}

	protected static function source_panel( Source $source ) {
		$job = new Job( $source->get_key() );
		$mapped = Map::counts_by_type( $source->get_key() );
		$state = Migrate::job_response( $source, $job );
		if ( $source->has_admin_setup() ) {
			static::setup_panel( $source, $job, $state );
			return;
		}
		?>
		<div id="em-migrate" data-source="<?php echo esc_attr( $source->get_key() ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'em_migrate' ) ); ?>" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
			<p><?php echo esc_html( sprintf( __( 'Copies your events, venues, organizers, tickets and attendees from %s into Events Manager. Nothing in the original plugin is changed or deleted, so you can run this, check the result, and roll it back or run it again.', 'events-manager' ), $source->get_name() ) ); ?></p>
			<?php foreach ( $source->get_notices() as $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> inline"><p><?php echo wp_kses_post( $notice['message'] ); ?></p></div>
			<?php endforeach; ?>
			<div class="em-migrate-columns" style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start">
				<div class="postbox" style="flex:1 1 320px;max-width:520px;padding:0 16px 8px">
					<h2><?php echo esc_html( sprintf( __( 'Found in %s', 'events-manager' ), $source->get_name() ) ); ?></h2>
					<table class="widefat striped">
						<tbody>
						<?php foreach ( $source->get_summary() as $label => $count ) : ?>
							<tr><td><?php echo esc_html( $label ); ?></td><td style="text-align:right"><?php echo esc_html( number_format_i18n( $count ) ); ?></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php if ( $mapped ) : ?>
						<p class="description"><?php echo esc_html( sprintf( __( 'Already migrated: %s', 'events-manager' ), static::describe_counts( $mapped ) ) ); ?></p>
					<?php endif; ?>
				</div>
				<div class="postbox" style="flex:1 1 320px;max-width:620px;padding:0 16px 12px">
					<h2><?php esc_html_e( 'Options', 'events-manager' ); ?></h2>
					<form id="em-migrate-options">
						<?php foreach ( $source->get_options() as $key => $def ) :
							$value = array_key_exists( $key, $job->options ) ? $job->options[ $key ] : $def['default']; ?>
							<p>
								<?php if ( 'checkbox' === $def['type'] ) : ?>
									<label><input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $value ); ?>> <?php echo esc_html( $def['label'] ); ?></label>
								<?php else : ?>
									<label for="em-migrate-<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $def['label'] ); ?></strong></label><br>
									<select name="<?php echo esc_attr( $key ); ?>" id="em-migrate-<?php echo esc_attr( $key ); ?>">
										<?php foreach ( $def['choices'] as $ck => $cl ) : ?>
											<option value="<?php echo esc_attr( $ck ); ?>" <?php selected( $value, $ck ); ?>><?php echo esc_html( $cl ); ?></option>
										<?php endforeach; ?>
									</select>
								<?php endif; ?>
								<?php if ( ! empty( $def['description'] ) ) : ?>
									<br><span class="description"><?php echo esc_html( $def['description'] ); ?></span>
								<?php endif; ?>
							</p>
						<?php endforeach; ?>
						<p>
							<button type="button" class="button button-primary" data-task="start"><?php echo $mapped ? esc_html__( 'Run again (sync changes)', 'events-manager' ) : esc_html__( 'Start migration', 'events-manager' ); ?></button>
							<button type="button" class="button" data-task="resume" style="display:none"><?php esc_html_e( 'Resume', 'events-manager' ); ?></button>
							<button type="button" class="button" data-task="stop" style="display:none"><?php esc_html_e( 'Pause', 'events-manager' ); ?></button>
							<button type="button" class="button button-link-delete" data-task="rollback" <?php echo $mapped ? '' : 'style="display:none"'; ?> data-confirm="<?php echo esc_attr( sprintf( __( 'Delete everything migrated from %s (events, locations, tickets, bookings, terms) and restore converted content? The original data is not affected.', 'events-manager' ), $source->get_name() ) ); ?>"><?php esc_html_e( 'Roll back', 'events-manager' ); ?></button>
						</p>
					</form>
				</div>
			</div>
			<?php
			static::progress_panel( $state );
	}

	/**
	 * A source's own setup in place of the summary and options boxes, see Source::admin_setup().
	 */
	protected static function setup_panel( Source $source, Job $job, array $state ) {
		?>
		<div id="em-migrate" data-own-setup="1" data-source="<?php echo esc_attr( $source->get_key() ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'em_migrate' ) ); ?>" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
			<?php
			$source->admin_setup( $job );
			static::progress_panel( $state );
	}

	/**
	 * The progress box, the state it starts from and the script that drives a run, shared by both panels. Closes the #em-migrate wrapper its caller opened.
	 */
	protected static function progress_panel( array $state ) {
		?>
			<div class="postbox em-migrate-progress" style="padding:0 16px 12px;max-width:1164px">
				<h2><?php esc_html_e( 'Progress', 'events-manager' ); ?> <span class="em-migrate-status" style="font-weight:normal"></span></h2>
				<ol class="em-migrate-steps" style="display:flex;flex-wrap:wrap;gap:6px 22px;margin-left:0;list-style-position:inside"></ol>
				<div class="em-migrate-stats"></div>
				<div class="em-migrate-report"></div>
				<details open><summary><?php esc_html_e( 'Log', 'events-manager' ); ?></summary>
					<pre class="em-migrate-log" style="max-height:320px;overflow:auto;white-space:pre-wrap;background:#f6f7f7;padding:8px;font-size:12px"></pre>
				</details>
			</div>
			<script type="application/json" id="em-migrate-state"><?php echo wp_json_encode( $state ); ?></script>
		</div>
		<script>
		( function () {
			var root = document.getElementById( 'em-migrate' );
			if ( ! root ) { return; }
			var state = JSON.parse( document.getElementById( 'em-migrate-state' ).textContent );
			var busy = false;
			// a source with its own setup redraws the page when a run ends, since what it offers next depends on the result
			var own = !! root.dataset.ownSetup;
			var labels = <?php echo wp_json_encode( array(
				'running' => __( 'Running…', 'events-manager' ),
				'done' => __( 'Finished', 'events-manager' ),
				'rolled_back' => __( 'Rolled back', 'events-manager' ),
				'idle' => __( 'Paused', 'events-manager' ),
				'error' => __( 'Stopped on an error', 'events-manager' ),
				'never' => __( 'Not started', 'events-manager' ),
				'start' => __( 'Start migration', 'events-manager' ),
				'rerun' => __( 'Run again (sync changes)', 'events-manager' ),
			) ); ?>;
			function q( sel ) { return root.querySelector( sel ); }
			// WP's .button sets display, which beats the hidden attribute
			function show( task, visible ) { var b = q( '[data-task="' + task + '"]' ); if ( b ) { b.style.display = visible ? '' : 'none'; } }
			function esc( s ) { var d = document.createElement( 'div' ); d.textContent = s == null ? '' : String( s ); return d.innerHTML; }
			function render( s ) {
				state = s;
				var running = s.status === 'running';
				q( '.em-migrate-status' ).textContent = ': ' + ( s.started ? ( labels[ s.status ] || s.status ) : labels.never ) + ( running && s.step_label ? ': ' + s.step_label : '' );
				var steps = s.steps || {}, keys = Object.keys( steps ), current = keys.indexOf( s.step ), html = '';
				keys.forEach( function ( k, i ) {
					var mark = ( s.status === 'done' || s.status === 'rolled_back' || ( current > -1 && i < current ) ) ? '✓ ' : ( i === current && running ? '▸ ' : '' );
					html += '<li style="' + ( i === current && running ? 'font-weight:600' : '' ) + '">' + mark + esc( steps[ k ] ) + '</li>';
				} );
				q( '.em-migrate-steps' ).innerHTML = s.started ? html : '';
				var stats = '';
				Object.keys( s.stats || {} ).forEach( function ( step ) {
					var parts = [];
					Object.keys( s.stats[ step ] ).forEach( function ( k ) { parts.push( esc( k.replace( /_/g, ' ' ) ) + ': ' + esc( s.stats[ step ][ k ] ) ); } );
					stats += '<p><strong>' + esc( ( steps[ step ] || step ) ) + '</strong>: ' + parts.join( ', ' ) + '</p>';
				} );
				q( '.em-migrate-stats' ).innerHTML = stats;
				var report = '';
				if ( s.report ) {
					report = '<h3>' + esc( s.report.summary ) + '</h3>';
					if ( s.report.rows && s.report.rows.length ) {
						report += '<table class="widefat striped"><thead><tr>' + s.report.columns.map( function ( c ) { return '<th>' + esc( c ) + '</th>'; } ).join( '' ) + '</tr></thead><tbody>';
						s.report.rows.forEach( function ( r ) { report += '<tr>' + r.map( function ( c ) { return '<td>' + esc( c ) + '</td>'; } ).join( '' ) + '</tr>'; } );
						report += '</tbody></table>';
					}
					if ( s.report.footnote ) { report += '<p class="description">' + esc( s.report.footnote ) + '</p>'; }
				}
				q( '.em-migrate-report' ).innerHTML = report;
				root.querySelectorAll( '[data-hide-running]' ).forEach( function ( el ) { el.style.display = running ? 'none' : ''; } );
				q( '.em-migrate-log' ).textContent = ( s.log || [] ).map( function ( l ) { return l.t + ( l.level === 'error' ? ' ⚠ ' : '   ' ) + ( l.step ? '[' + l.step + '] ' : '' ) + l.message; } ).join( '\n' );
				show( 'start', ! running );
				show( 'stop', running );
				show( 'resume', ! running && ( s.status === 'idle' || s.status === 'error' ) && !! s.started );
				show( 'rollback', ! running && Object.keys( s.mapped || {} ).length > 0 );
				if ( ! own ) { q( '[data-task="start"]' ).textContent = Object.keys( s.mapped || {} ).length ? labels.rerun : labels.start; }
				if ( running && ! busy ) { setTimeout( function () { call( 'tick' ); }, 300 ); }
			}
			function call( task ) {
				busy = true;
				var body = new FormData();
				body.append( 'action', 'em_migrate' );
				body.append( 'task', task );
				body.append( 'source', root.dataset.source );
				body.append( 'nonce', root.dataset.nonce );
				if ( task === 'start' ) {
					new FormData( q( '#em-migrate-options' ) ).forEach( function ( v, k ) { body.append( 'options[' + k + ']', v ); } );
				}
				fetch( root.dataset.ajax, { method: 'POST', body: body, credentials: 'same-origin' } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( r ) {
						busy = false;
						if ( ! r || ! r.success ) { alert( ( r && r.data && r.data.message ) || 'Error' ); return; }
						if ( own && r.data.status !== 'running' ) { window.location.reload(); return; }
						render( r.data );
					} )
					.catch( function ( e ) { busy = false; q( '.em-migrate-status' ).textContent = ': ' + e.message + ', retrying…'; setTimeout( function () { call( 'tick' ); }, 3000 ); } );
			}
			root.addEventListener( 'click', function ( e ) {
				var b = e.target.closest( '[data-task]' );
				if ( ! b || busy ) { return; }
				if ( b.dataset.confirm && ! window.confirm( b.dataset.confirm ) ) { return; }
				call( b.dataset.task );
			} );
			render( state );
		} )();
		</script>
		<?php
	}

	public static function describe_counts( array $counts ) {
		$parts = array();
		foreach ( $counts as $type => $n ) {
			$parts[] = number_format_i18n( $n ) . ' ' . str_replace( '_', ' ', $type );
		}
		return implode( ', ', $parts );
	}
}
