<?php
namespace EM\API;

class MCP_OAuth extends \EM_OAuth\OAuth_Authorization_Server {

	protected static $option_clients = 'em_mcp_oauth_clients';
	protected static $scope = 'events-manager:mcp';
	protected static $provider_name = 'Events Manager MCP OAuth';
	protected static $route_namespace = 'events-manager/v1';
	protected static $route_base = 'mcp/oauth';
	protected static $authorize_path = '/em-mcp/oauth/authorize';
	protected static $client_id_prefix = 'em-mcp-';
	protected static $server_enabled_filter = 'em_mcp_oauth_server_enabled';
	protected static $public_url_filter = 'em_mcp_public_url';

	public static function init() {
		parent::init();
		if ( !static::is_enabled() ) {
			return;
		}
		add_filter( 'em_mcp_oauth_available', '__return_true' );
		add_filter( 'em_mcp_oauth_provider', array( static::class, 'get_provider_name' ) );
		add_filter( 'em_mcp_oauth_setup_url', array( static::class, 'get_authorization_server_metadata_url' ) );
	}

	public static function rest_authentication_errors( $result ) {
		if ( !empty( $result ) || !static::is_protected_request() || is_user_logged_in() ) {
			return $result;
		}
		$app_password_result = static::authenticate_application_password();
		if ( $app_password_result === true ) {
			return $result;
		}
		if ( is_wp_error( $app_password_result ) ) {
			return $app_password_result;
		}
		static::send_authenticate_header();
		return new \WP_Error(
			'em_mcp_oauth_required',
			__( 'OAuth authorization is required for this MCP server.', 'events-manager' ),
			array( 'status' => 401 )
		);
	}

	protected static function is_protected_request() {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
		$resource_path = (string) wp_parse_url( static::get_default_resource_url(), PHP_URL_PATH );
		return $resource_path && strpos( $path, $resource_path ) !== false;
	}

	protected static function get_default_resource_url() {
		return static::get_public_url( rest_url( 'mcp/mcp-adapter-default-server' ) );
	}

	protected static function get_protected_resource_metadata() {
		return array(
			'resource' => static::get_default_resource_url(),
			'authorization_servers' => array( static::get_issuer_url() ),
			'scopes_supported' => array( static::$scope ),
			'bearer_methods_supported' => array( 'header' ),
			'resource_name' => get_bloginfo( 'name' ) . ' Events Manager MCP',
		);
	}
}
