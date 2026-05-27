<?php
namespace EM\API;

/**
 * Minimal AI/MCP setup meta box rendered on Events > Settings > General.
 *
 * Three steps:
 *   1. WordPress 7.0+ check (Abilities API available).
 *   2. MCP Adapter plugin installed + active.
 *   3. MCP Server URL ready when both above are met.
 *
 * Anything beyond this lives in the customer-facing docs at
 * https://wp-events-plugin.com/documentation/ai/.
 */
class MCP_Admin {

	const PAGE_SLUG = 'events-manager-mcp';
	const ADAPTER_REPO = 'WordPress/mcp-adapter';
	const ADAPTER_PLUGIN = 'mcp-adapter/mcp-adapter.php';
	const DOCS_URL = 'https://wp-events-plugin.com/documentation/ai/';

	protected static $notice = null;
	protected static $notice_type = 'success';

	public static function init() {
		add_action( 'admin_init', array( static::class, 'maybe_handle_action' ), 1 );
		add_action( 'em_settings_general_footer', array( static::class, 'render_settings_box' ) );
	}

	public static function maybe_handle_action() {
		if ( empty( $_POST['em_mcp_action'] ) || !current_user_can( 'manage_options' ) ) {
			return;
		}
		remove_action( 'admin_init', 'em_options_save' );
		static::handle_action( sanitize_key( wp_unslash( $_POST['em_mcp_action'] ) ) );
	}

	public static function render_settings_box() {
		if ( !current_user_can( 'manage_options' ) ) {
			return;
		}
		$adapter   = static::get_adapter_state();
		$abilities = function_exists( 'wp_register_ability' );
		$mcp_url   = static::get_public_url( rest_url( 'mcp/mcp-adapter-default-server' ) );
		$oauth     = static::get_oauth_state();
		$ready     = $abilities && $adapter['active'];
		?>
		<div class="postbox em-mcp-wrap" id="em-opt-mcp">
			<div class="handlediv" title="<?php esc_attr_e( 'Click to toggle', 'events-manager' ); ?>"><br /></div>
			<h3><span><?php esc_html_e( 'AI / MCP Setup', 'events-manager' ); ?></span></h3>
			<div class="inside">
				<style>
					.em-mcp-grid { display: flex; flex-direction: column; gap: 12px; margin: 18px 0; max-width: 800px; }
					.em-mcp-card { background: #fff; border: 1px solid #dcdcde; border-radius: 4px; padding: 14px; border-left: 5px solid #8c8f94; }
					.em-mcp-card h2 { margin-top: 0; }
					.em-mcp-card.is-ready { border-left-color: #00a32a; }
					.em-mcp-card.is-warning { border-left-color: #dba617; }
					.em-mcp-card.is-error { border-left-color: #d63638; }
					.em-mcp-pill { border-radius: 999px; display: inline-block; font-size: 12px; font-weight: 700; margin-bottom: 10px; padding: 5px 10px; text-transform: uppercase; }
					.em-mcp-pill.is-ready { background: #d9f2e3; color: #005c12; }
					.em-mcp-pill.is-warning { background: #fff4cb; color: #704d00; }
					.em-mcp-pill.is-error { background: #f8d7da; color: #8a2424; }
					.em-mcp-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
					.em-mcp-actions form { margin: 0; }
					.em-mcp-url-row { align-items: stretch; display: flex; gap: 8px; margin-top: 10px; }
					.em-mcp-url-row textarea { background: #f6f7f7; flex: 1; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px; min-height: 40px; min-width: 0; padding: 8px 10px; resize: vertical; }
					.em-mcp-url-row .button { flex-shrink: 0; }
					.em-mcp-copy-row { align-items: center; display: flex; gap: 8px; justify-content: flex-end; margin: 0 0 8px; }
				</style>

				<p>
					<?php esc_html_e( 'Connect Events Manager to MCP-compatible AI clients (Claude, Cursor, ChatGPT custom connectors, VS Code, Codex) by exposing Events Manager Abilities through the WordPress MCP Adapter.', 'events-manager' ); ?>
					<?php
					echo sprintf(
						/* translators: %s: link to documentation page */
						esc_html__( 'For more information, read our %s page.', 'events-manager' ),
						'<a href="' . esc_url( static::DOCS_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'documentation', 'events-manager' ) . '</a>'
					);
					?>
				</p>
				<?php wp_nonce_field( 'em_mcp_wizard_action', 'em_mcp_nonce', false ); ?>
				<?php static::render_notice(); ?>

				<div class="em-mcp-grid">
					<?php static::render_step_wordpress( $abilities ); ?>
					<?php static::render_step_adapter( $adapter ); ?>
					<?php static::render_step_url( $ready, $mcp_url, $oauth ); ?>
				</div>

				<?php static::render_copy_script(); ?>
			</div>
		</div>
		<?php
	}

	protected static function render_step_wordpress( $abilities ) {
		$class = $abilities ? 'is-ready' : 'is-error';
		$label = $abilities ? __( 'Ready', 'events-manager' ) : __( 'Blocked', 'events-manager' );
		?>
		<div class="em-mcp-card <?php echo esc_attr( $class ); ?>">
			<span class="em-mcp-pill <?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></span>
			<h2><?php esc_html_e( '1. WordPress 7.0+', 'events-manager' ); ?></h2>
			<p><?php echo esc_html( sprintf( __( 'WordPress version: %s', 'events-manager' ), get_bloginfo( 'version' ) ) ); ?></p>
			<p><?php echo $abilities ? esc_html__( 'The Abilities API is available.', 'events-manager' ) : esc_html__( 'The Abilities API is not available. Update to WordPress 7.0 or later.', 'events-manager' ); ?></p>
		</div>
		<?php
	}

	protected static function render_step_adapter( $adapter ) {
		$class = $adapter['active'] ? 'is-ready' : ( $adapter['installed'] ? 'is-warning' : 'is-error' );
		?>
		<div class="em-mcp-card <?php echo esc_attr( $class ); ?>">
			<span class="em-mcp-pill <?php echo esc_attr( $class ); ?>"><?php echo esc_html( $adapter['label'] ); ?></span>
			<h2><?php esc_html_e( '2. MCP Adapter', 'events-manager' ); ?></h2>
			<p><?php esc_html_e( 'Install and activate the official WordPress MCP Adapter so AI clients can discover Events Manager abilities.', 'events-manager' ); ?></p>
			<?php if ( !empty( $adapter['version'] ) ) : ?>
				<p><?php echo esc_html( sprintf( __( 'Detected version: %s', 'events-manager' ), $adapter['version'] ) ); ?></p>
			<?php endif; ?>
			<div class="em-mcp-actions">
				<?php if ( !$adapter['installed'] ) : ?>
					<?php if ( current_user_can( 'install_plugins' ) ) : ?>
						<?php static::render_action_button( 'install_adapter', __( 'Install MCP Adapter', 'events-manager' ), 'button button-primary' ); ?>
					<?php endif; ?>
					<a class="button" href="https://github.com/WordPress/mcp-adapter/releases" target="_blank" rel="noopener"><?php esc_html_e( 'Manual download', 'events-manager' ); ?></a>
				<?php elseif ( !$adapter['active'] ) : ?>
					<?php if ( current_user_can( 'activate_plugins' ) ) : ?>
						<?php static::render_action_button( 'activate_adapter', __( 'Activate MCP Adapter', 'events-manager' ), 'button button-primary' ); ?>
					<?php endif; ?>
				<?php else : ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'View plugins', 'events-manager' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	protected static function render_step_url( $ready, $mcp_url, $oauth ) {
		if ( $ready ) {
			$class = 'is-ready';
			$label = __( 'Ready', 'events-manager' );
		} else {
			$class = 'is-warning';
			$label = __( 'Pending', 'events-manager' );
		}
		?>
		<div class="em-mcp-card em-mcp-url <?php echo esc_attr( $class ); ?>">
			<span class="em-mcp-pill <?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></span>
			<h2><?php esc_html_e( '3. MCP Server URL', 'events-manager' ); ?></h2>
			<?php if ( $ready ) : $url_id = wp_unique_id( 'em-mcp-url-' ); ?>
				<p><?php esc_html_e( 'Paste this URL into MCP clients that support remote server URLs and browser approval.', 'events-manager' ); ?></p>
				<div class="em-mcp-url-row">
					<textarea id="<?php echo esc_attr( $url_id ); ?>" readonly rows="2"><?php echo esc_textarea( $mcp_url ); ?></textarea>
					<button type="button" class="button" data-em-mcp-copy-target="#<?php echo esc_attr( $url_id ); ?>"><?php esc_html_e( 'Copy', 'events-manager' ); ?></button>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'Complete steps 1 and 2 first. The MCP Server URL will appear here once WordPress 7.0+ and the MCP Adapter are both active.', 'events-manager' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	protected static function handle_action( $action ) {
		if ( !check_admin_referer( 'em_mcp_wizard_action', 'em_mcp_nonce' ) ) {
			static::set_notice( __( 'Security check failed. Please reload the page and try again.', 'events-manager' ), 'error' );
			return;
		}
		switch ( $action ) {
			case 'install_adapter':
				static::install_adapter();
				break;
			case 'activate_adapter':
				static::activate_adapter();
				break;
		}
	}

	protected static function install_adapter() {
		if ( !current_user_can( 'install_plugins' ) ) {
			static::set_notice( __( 'You need permission to install plugins before the MCP Adapter can be installed.', 'events-manager' ), 'error' );
			return;
		}
		if ( !apply_filters( 'em_mcp_adapter_install_enabled', true ) ) {
			static::set_notice( __( 'MCP Adapter installation is disabled on this site.', 'events-manager' ), 'error' );
			return;
		}
		$adapter = static::get_adapter_state();
		if ( $adapter['installed'] ) {
			static::activate_adapter();
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$download_url = static::get_adapter_download_url();
		$upgrader     = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
		$result       = $upgrader->install( $download_url );

		if ( is_wp_error( $result ) ) {
			static::set_notice( $result->get_error_message(), 'error' );
			return;
		}
		if ( !$result ) {
			static::set_notice( __( 'The MCP Adapter could not be installed. Please try the manual upload flow from the GitHub release ZIP.', 'events-manager' ), 'error' );
			return;
		}
		wp_clean_plugins_cache( false );
		static::activate_adapter( sprintf( __( 'Installed MCP Adapter from %s.', 'events-manager' ), esc_url( $download_url ) ) );
	}

	protected static function activate_adapter( $prefix = '' ) {
		if ( !current_user_can( 'activate_plugins' ) ) {
			static::set_notice( __( 'You need permission to activate plugins before the MCP Adapter can be activated.', 'events-manager' ), 'error' );
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$adapter = static::get_adapter_state( true );
		if ( !$adapter['installed'] || empty( $adapter['plugin_file'] ) ) {
			static::set_notice( __( 'The MCP Adapter is not installed yet.', 'events-manager' ), 'error' );
			return;
		}
		if ( $adapter['active'] ) {
			static::set_notice( __( 'The MCP Adapter is already active.', 'events-manager' ) );
			return;
		}
		$result = activate_plugin( $adapter['plugin_file'] );
		if ( is_wp_error( $result ) ) {
			static::set_notice( $result->get_error_message(), 'error' );
			return;
		}
		$message  = $prefix ? $prefix . ' ' : '';
		$message .= __( 'MCP Adapter activated.', 'events-manager' );
		static::set_notice( $message );
	}

	protected static function render_action_button( $action, $label, $class = 'button' ) {
		?>
		<button type="submit" name="em_mcp_action" value="<?php echo esc_attr( $action ); ?>" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></button>
		<?php
	}

	protected static function render_copyable_textarea( $value, $class = 'large-text code', $rows = 8 ) {
		$id = wp_unique_id( 'em-mcp-copy-' );
		?>
		<div class="em-mcp-copy-row">
			<button type="button" class="button" data-em-mcp-copy-target="#<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Copy', 'events-manager' ); ?></button>
		</div>
		<textarea id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $class ); ?>" readonly rows="<?php echo absint( $rows ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
		<?php
	}

	protected static function render_notice() {
		if ( !static::$notice ) return;
		$class = static::$notice_type === 'error' ? 'notice-error' : ( static::$notice_type === 'warning' ? 'notice-warning' : 'notice-success' );
		echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . wp_kses_post( static::$notice ) . '</p></div>';
	}

	protected static function render_copy_script() {
		?>
		<script>
			(function() {
				var buttons = document.querySelectorAll('[data-em-mcp-copy-target]');
				buttons.forEach(function(button) {
					button.addEventListener('click', function() {
						var target = document.querySelector(button.getAttribute('data-em-mcp-copy-target'));
						if (!target) return;
						target.focus();
						target.select();
						if (navigator.clipboard && window.isSecureContext) {
							navigator.clipboard.writeText(target.value).then(function() {
								button.textContent = '<?php echo esc_js( __( 'Copied', 'events-manager' ) ); ?>';
							});
							return;
						}
						try {
							if (document.execCommand('copy')) {
								button.textContent = '<?php echo esc_js( __( 'Copied', 'events-manager' ) ); ?>';
							}
						} catch (e) {}
					});
				});
			})();
		</script>
		<?php
	}

	protected static function set_notice( $message, $type = 'success' ) {
		static::$notice      = $message;
		static::$notice_type = $type;
	}

	protected static function get_adapter_state( $refresh = false ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		if ( $refresh ) {
			wp_clean_plugins_cache( false );
		}
		$plugin_file = static::find_adapter_plugin_file();
		$installed   = !empty( $plugin_file );
		$active      = $installed && ( is_plugin_active( $plugin_file ) || ( is_multisite() && is_plugin_active_for_network( $plugin_file ) ) );
		$plugins     = get_plugins();
		$version     = $installed && !empty( $plugins[ $plugin_file ]['Version'] ) ? $plugins[ $plugin_file ]['Version'] : '';
		return array(
			'installed'   => $installed,
			'active'      => $active,
			'plugin_file' => $plugin_file,
			'version'     => $version,
			'label'       => $active ? __( 'Active', 'events-manager' ) : ( $installed ? __( 'Installed', 'events-manager' ) : __( 'Missing', 'events-manager' ) ),
		);
	}

	protected static function find_adapter_plugin_file() {
		$plugins = get_plugins();
		if ( isset( $plugins[ static::ADAPTER_PLUGIN ] ) ) {
			return static::ADAPTER_PLUGIN;
		}
		foreach ( $plugins as $plugin_file => $plugin_data ) {
			$name = isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : '';
			if ( strpos( $plugin_file, 'mcp-adapter/' ) === 0 || stripos( $name, 'MCP Adapter' ) !== false ) {
				return $plugin_file;
			}
		}
		return '';
	}

	protected static function get_adapter_download_url() {
		$filtered = apply_filters( 'em_mcp_adapter_download_url', '' );
		if ( $filtered ) return esc_url_raw( $filtered );

		$fallback = 'https://github.com/WordPress/mcp-adapter/releases/latest/download/mcp-adapter.zip';
		$response = wp_remote_get( 'https://api.github.com/repos/' . static::ADAPTER_REPO . '/releases/latest', array(
			'timeout' => 10,
			'headers' => array( 'Accept' => 'application/vnd.github+json' ),
		) );
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return $fallback;
		}
		$release = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $release['assets'] ) || !is_array( $release['assets'] ) ) {
			return $fallback;
		}
		foreach ( $release['assets'] as $asset ) {
			if ( !empty( $asset['browser_download_url'] ) && preg_match( '/\.zip$/', $asset['browser_download_url'] ) ) {
				return esc_url_raw( $asset['browser_download_url'] );
			}
		}
		return $fallback;
	}

	protected static function get_oauth_state() {
		$available = (bool) apply_filters( 'em_mcp_oauth_available', false );
		$provider  = apply_filters( 'em_mcp_oauth_provider', '' );
		$setup_url = apply_filters( 'em_mcp_oauth_setup_url', '' );
		$state     = array(
			'available' => $available,
			'provider'  => is_string( $provider ) ? $provider : '',
			'setup_url' => is_string( $setup_url ) ? esc_url_raw( $setup_url ) : '',
		);
		$state = apply_filters( 'em_mcp_oauth_state', $state );
		return wp_parse_args( is_array( $state ) ? $state : array(), array(
			'available' => false,
			'provider'  => '',
			'setup_url' => '',
		) );
	}

	protected static function get_public_url( $url ) {
		if ( defined( 'EM_OAUTH_TUNNEL' ) && EM_OAUTH_TUNNEL ) {
			$url = str_replace( get_home_url(), untrailingslashit( EM_OAUTH_TUNNEL ), $url );
		}
		return apply_filters( 'em_mcp_public_url', $url );
	}
}
