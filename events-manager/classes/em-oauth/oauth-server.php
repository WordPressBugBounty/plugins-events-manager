<?php
namespace EM_OAuth;

abstract class OAuth_Authorization_Server {

	protected static $option_clients = 'em_oauth_clients';
	protected static $scope = 'events-manager';
	protected static $provider_name = 'Events Manager OAuth';
	protected static $route_namespace = 'events-manager/v1';
	protected static $route_base = 'oauth';
	protected static $authorize_path = '/em-oauth/authorize';
	protected static $client_id_prefix = 'em-oauth-';
	protected static $server_enabled_filter = 'em_oauth_server_enabled';
	protected static $public_url_filter = 'em_oauth_public_url';

	public static function init() {
		if ( !static::is_enabled() ) {
			return;
		}
		add_filter( 'determine_current_user', array( static::class, 'determine_current_user' ), 30 );
		add_filter( 'rest_authentication_errors', array( static::class, 'rest_authentication_errors' ), 30 );
		add_action( 'rest_api_init', array( static::class, 'register_routes' ) );
		add_action( 'parse_request', array( static::class, 'maybe_handle_public_request' ), 1 );
	}

	public static function is_enabled() {
		return (bool) apply_filters( static::$server_enabled_filter, true );
	}

	public static function get_provider_name() {
		return static::$provider_name;
	}

	public static function register_routes() {
		register_rest_route( static::$route_namespace, '/' . static::$route_base . '/register', array(
			'methods' => 'POST',
			'callback' => array( static::class, 'register_client' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( static::$route_namespace, '/' . static::$route_base . '/token', array(
			'methods' => 'POST',
			'callback' => array( static::class, 'issue_token' ),
			'permission_callback' => '__return_true',
		) );
	}

	public static function maybe_handle_public_request() {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
		if ( strpos( $path, '/.well-known/oauth-authorization-server' ) === 0 || strpos( $path, '/.well-known/openid-configuration' ) === 0 ) {
			static::send_json( static::get_authorization_server_metadata() );
		}
		if ( strpos( $path, '/.well-known/oauth-protected-resource' ) === 0 ) {
			static::send_json( static::get_protected_resource_metadata() );
		}
		if ( $path === static::$authorize_path ) {
			static::handle_authorize();
		}
	}

	public static function determine_current_user( $user_id ) {
		if ( $user_id ) {
			return $user_id;
		}
		$token = static::get_bearer_token();
		if ( !$token ) {
			return $user_id;
		}
		$grant = static::get_access_token_grant( $token );
		if ( !$grant ) {
			return $user_id;
		}
		wp_set_current_user( absint( $grant['user_id'] ) );
		return absint( $grant['user_id'] );
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
			'em_oauth_required',
			__( 'OAuth authorization is required for this resource.', 'events-manager' ),
			array( 'status' => 401 )
		);
	}

	public static function register_client( \WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( !is_array( $params ) ) {
			$params = $request->get_params();
		}
		$redirect_uris = !empty( $params['redirect_uris'] ) && is_array( $params['redirect_uris'] ) ? $params['redirect_uris'] : array();
		$redirect_uris = array_values( array_filter( array_map( 'esc_url_raw', $redirect_uris ) ) );
		if ( empty( $redirect_uris ) ) {
			return new \WP_Error( 'invalid_redirect_uris', __( 'At least one redirect URI is required.', 'events-manager' ), array( 'status' => 400 ) );
		}
		foreach ( $redirect_uris as $redirect_uri ) {
			if ( !static::is_allowed_redirect_uri( $redirect_uri ) ) {
				return new \WP_Error( 'invalid_redirect_uri', __( 'Redirect URIs must use HTTPS or localhost.', 'events-manager' ), array( 'status' => 400 ) );
			}
		}
		$client_id = static::$client_id_prefix . wp_generate_password( 32, false, false );
		$clients = static::get_clients();
		$clients[ $client_id ] = array(
			'client_id' => $client_id,
			'client_name' => !empty( $params['client_name'] ) ? sanitize_text_field( wp_unslash( $params['client_name'] ) ) : __( 'OAuth Client', 'events-manager' ),
			'redirect_uris' => $redirect_uris,
			'created_at' => time(),
		);
		update_option( static::$option_clients, $clients, false );
		return rest_ensure_response( array(
			'client_id' => $client_id,
			'client_id_issued_at' => time(),
			'client_name' => $clients[ $client_id ]['client_name'],
			'redirect_uris' => $redirect_uris,
			'grant_types' => array( 'authorization_code' ),
			'response_types' => array( 'code' ),
			'token_endpoint_auth_method' => 'none',
		) );
	}

	public static function issue_token( \WP_REST_Request $request ) {
		if ( $request->get_param( 'grant_type' ) !== 'authorization_code' ) {
			return new \WP_Error( 'unsupported_grant_type', __( 'Only authorization_code grants are supported.', 'events-manager' ), array( 'status' => 400 ) );
		}
		$code = (string) $request->get_param( 'code' );
		$grant = $code ? get_transient( static::get_code_key( $code ) ) : false;
		if ( !is_array( $grant ) ) {
			return new \WP_Error( 'invalid_grant', __( 'The authorization code is invalid or expired.', 'events-manager' ), array( 'status' => 400 ) );
		}
		delete_transient( static::get_code_key( $code ) );
		$client_id = (string) $request->get_param( 'client_id' );
		$redirect_uri = esc_url_raw( (string) $request->get_param( 'redirect_uri' ) );
		if ( $client_id !== $grant['client_id'] || $redirect_uri !== $grant['redirect_uri'] ) {
			return new \WP_Error( 'invalid_grant', __( 'The authorization code does not match this client.', 'events-manager' ), array( 'status' => 400 ) );
		}
		$code_verifier = (string) $request->get_param( 'code_verifier' );
		if ( !static::verify_pkce( $code_verifier, $grant['code_challenge'] ) ) {
			return new \WP_Error( 'invalid_grant', __( 'The PKCE code verifier is invalid.', 'events-manager' ), array( 'status' => 400 ) );
		}
		$resource = esc_url_raw( (string) $request->get_param( 'resource' ) );
		if ( $resource && !static::same_resource( $resource, $grant['resource'] ) ) {
			return new \WP_Error( 'invalid_target', __( 'The requested resource does not match this authorization grant.', 'events-manager' ), array( 'status' => 400 ) );
		}
		$access_token = wp_generate_password( 64, false, false );
		set_transient( static::get_access_token_key( $access_token ), array(
			'user_id' => absint( $grant['user_id'] ),
			'client_id' => $client_id,
			'resource' => $grant['resource'],
			'scope' => $grant['scope'],
			'issued_at' => time(),
		), HOUR_IN_SECONDS );
		return rest_ensure_response( array(
			'access_token' => $access_token,
			'token_type' => 'Bearer',
			'expires_in' => HOUR_IN_SECONDS,
			'scope' => $grant['scope'],
		) );
	}

	protected static function handle_authorize() {
		$params = wp_unslash( $_REQUEST );
		if ( !is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( static::current_url() ) );
			exit;
		}
		$validation = static::validate_authorize_request( $params );
		if ( is_wp_error( $validation ) ) {
			wp_die( esc_html( $validation->get_error_message() ), esc_html__( 'OAuth Error', 'events-manager' ), array( 'response' => 400 ) );
		}
		if ( !empty( $_POST['em_oauth_decision'] ) ) {
			check_admin_referer( 'em_oauth_authorize' );
			if ( $_POST['em_oauth_decision'] !== 'approve' || !current_user_can( 'read' ) ) {
				static::redirect_with_error( $validation['redirect_uri'], 'access_denied', $validation['state'] );
			}
			$code = wp_generate_password( 48, false, false );
			set_transient( static::get_code_key( $code ), array(
				'client_id' => $validation['client_id'],
				'redirect_uri' => $validation['redirect_uri'],
				'code_challenge' => $validation['code_challenge'],
				'user_id' => get_current_user_id(),
				'resource' => $validation['resource'],
				'scope' => $validation['scope'],
			), 10 * MINUTE_IN_SECONDS );
			$args = array( 'code' => $code );
			if ( $validation['state'] !== '' ) {
				$args['state'] = $validation['state'];
			}
			wp_redirect( add_query_arg( $args, $validation['redirect_uri'] ) );
			exit;
		}
		static::render_authorize_page( $validation );
	}

	protected static function validate_authorize_request( $params ) {
		if ( empty( $params['response_type'] ) || $params['response_type'] !== 'code' ) {
			return new \WP_Error( 'unsupported_response_type', __( 'Only authorization code responses are supported.', 'events-manager' ) );
		}
		$client_id = !empty( $params['client_id'] ) ? sanitize_text_field( $params['client_id'] ) : '';
		$client = static::get_client( $client_id );
		if ( !$client ) {
			return new \WP_Error( 'invalid_client', __( 'The OAuth client is not registered.', 'events-manager' ) );
		}
		$redirect_uri = !empty( $params['redirect_uri'] ) ? esc_url_raw( $params['redirect_uri'] ) : '';
		if ( empty( $redirect_uri ) || !in_array( $redirect_uri, $client['redirect_uris'], true ) ) {
			return new \WP_Error( 'invalid_redirect_uri', __( 'The redirect URI is not registered for this client.', 'events-manager' ) );
		}
		$code_challenge = !empty( $params['code_challenge'] ) ? sanitize_text_field( $params['code_challenge'] ) : '';
		if ( empty( $code_challenge ) || ( !empty( $params['code_challenge_method'] ) && $params['code_challenge_method'] !== 'S256' ) ) {
			return new \WP_Error( 'invalid_request', __( 'A S256 PKCE code challenge is required.', 'events-manager' ) );
		}
		$resource = !empty( $params['resource'] ) ? esc_url_raw( $params['resource'] ) : static::get_default_resource_url();
		if ( !static::is_authorized_resource( $resource ) ) {
			return new \WP_Error( 'invalid_target', __( 'This authorization server cannot issue tokens for the requested resource.', 'events-manager' ) );
		}
		$scope = !empty( $params['scope'] ) ? sanitize_text_field( $params['scope'] ) : static::$scope;
		if ( !in_array( static::$scope, preg_split( '/\s+/', $scope ), true ) ) {
			$scope = static::$scope;
		}
		return array(
			'client_id' => $client_id,
			'client_name' => $client['client_name'],
			'redirect_uri' => $redirect_uri,
			'code_challenge' => $code_challenge,
			'resource' => static::normalize_resource_url( $resource ),
			'scope' => $scope,
			'state' => !empty( $params['state'] ) ? sanitize_text_field( $params['state'] ) : '',
		);
	}

	protected static function render_authorize_page( $request ) {
		nocache_headers();
		$user = wp_get_current_user();
		?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php esc_html_e( 'Authorize Access', 'events-manager' ); ?></title>
			<?php wp_admin_css( 'forms', true ); ?>
			<?php wp_admin_css( 'buttons', true ); ?>
			<style>
				body { background: #f0f0f1; color: #1d2327; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; }
				.em-oauth-server { background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 3px rgba(0,0,0,.08); margin: 8vh auto; max-width: 560px; padding: 24px; }
				.em-oauth-server h1 { font-size: 22px; margin-top: 0; }
				.em-oauth-server dl { display: grid; grid-template-columns: 130px 1fr; gap: 8px 14px; }
				.em-oauth-server dt { font-weight: 600; }
				.em-oauth-server dd { margin: 0; overflow-wrap: anywhere; }
				.em-oauth-actions { display: flex; gap: 8px; margin-top: 20px; }
			</style>
		</head>
		<body>
			<div class="em-oauth-server">
				<h1><?php esc_html_e( 'Authorize Events Manager access', 'events-manager' ); ?></h1>
				<p><?php echo esc_html( sprintf( __( '%s wants to access Events Manager as %s.', 'events-manager' ), $request['client_name'], $user->user_login ) ); ?></p>
				<dl>
					<dt><?php esc_html_e( 'Resource', 'events-manager' ); ?></dt>
					<dd><?php echo esc_html( $request['resource'] ); ?></dd>
					<dt><?php esc_html_e( 'Scope', 'events-manager' ); ?></dt>
					<dd><?php echo esc_html( $request['scope'] ); ?></dd>
					<dt><?php esc_html_e( 'Redirect URI', 'events-manager' ); ?></dt>
					<dd><?php echo esc_html( $request['redirect_uri'] ); ?></dd>
				</dl>
				<form method="post" class="em-oauth-actions">
					<?php foreach ( $_GET as $key => $value ) : ?>
						<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( wp_unslash( $value ) ); ?>">
					<?php endforeach; ?>
					<?php wp_nonce_field( 'em_oauth_authorize' ); ?>
					<button type="submit" name="em_oauth_decision" value="approve" class="button button-primary"><?php esc_html_e( 'Authorize', 'events-manager' ); ?></button>
					<button type="submit" name="em_oauth_decision" value="deny" class="button"><?php esc_html_e( 'Deny', 'events-manager' ); ?></button>
				</form>
			</div>
		</body>
		</html>
		<?php
		exit;
	}

	protected static function get_access_token_grant( $token ) {
		$grant = get_transient( static::get_access_token_key( $token ) );
		if ( !is_array( $grant ) || empty( $grant['user_id'] ) || empty( $grant['resource'] ) ) {
			return false;
		}
		return static::is_authorized_resource( $grant['resource'] ) ? $grant : false;
	}

	protected static function get_bearer_token() {
		$header = '';
		if ( !empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$header = wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] );
		} elseif ( !empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			$header = wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
		}
		if ( preg_match( '/^Bearer\s+(.+)$/i', $header, $matches ) ) {
			return trim( $matches[1] );
		}
		return '';
	}

	public static function authenticate_application_password() {
		if ( !function_exists( 'wp_authenticate_application_password' ) ) {
			return false;
		}
		$credentials = static::get_basic_auth_credentials();
		if ( !$credentials ) {
			return false;
		}
		if ( !apply_filters( 'em_oauth_application_password_auth_enabled', true, static::class ) ) {
			return false;
		}
		add_filter( 'wp_is_application_passwords_available', '__return_true', 99 );
		try {
			$user = wp_authenticate_application_password( null, $credentials['username'], $credentials['password'] );
		} finally {
			remove_filter( 'wp_is_application_passwords_available', '__return_true', 99 );
		}
		if ( is_wp_error( $user ) ) {
			return $user;
		}
		if ( $user instanceof \WP_User ) {
			wp_set_current_user( $user->ID );
			return true;
		}
		return false;
	}

	protected static function get_basic_auth_credentials() {
		if ( isset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ) ) {
			return array(
				'username' => wp_unslash( $_SERVER['PHP_AUTH_USER'] ),
				'password' => wp_unslash( $_SERVER['PHP_AUTH_PW'] ),
			);
		}
		$header = '';
		if ( !empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$header = wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] );
		} elseif ( !empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			$header = wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
		}
		if ( !preg_match( '/^Basic\s+(.+)$/i', $header, $matches ) ) {
			return false;
		}
		$decoded = base64_decode( trim( $matches[1] ), true );
		if ( !is_string( $decoded ) || strpos( $decoded, ':' ) === false ) {
			return false;
		}
		list( $username, $password ) = explode( ':', $decoded, 2 );
		return array(
			'username' => $username,
			'password' => $password,
		);
	}

	protected static function is_protected_request() {
		return false;
	}

	protected static function is_authorized_resource( $resource ) {
		return static::same_resource( $resource, static::get_default_resource_url() );
	}

	protected static function get_default_resource_url() {
		return static::get_public_url( rest_url( static::$route_namespace ) );
	}

	protected static function normalize_resource_url( $resource ) {
		return static::get_public_url( $resource );
	}

	protected static function send_authenticate_header() {
		header( 'WWW-Authenticate: Bearer resource_metadata="' . esc_url_raw( static::get_protected_resource_metadata_url() ) . '", scope="' . static::$scope . '"' );
	}

	protected static function send_json( $data ) {
		nocache_headers();
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	protected static function get_authorization_server_metadata() {
		return array(
			'issuer' => static::get_issuer_url(),
			'authorization_endpoint' => static::get_authorization_endpoint_url(),
			'token_endpoint' => static::get_token_endpoint_url(),
			'registration_endpoint' => static::get_registration_endpoint_url(),
			'response_types_supported' => array( 'code' ),
			'grant_types_supported' => array( 'authorization_code' ),
			'code_challenge_methods_supported' => array( 'S256' ),
			'token_endpoint_auth_methods_supported' => array( 'none' ),
			'scopes_supported' => array( static::$scope ),
			'resource_indicators_supported' => true,
		);
	}

	protected static function get_protected_resource_metadata() {
		return array(
			'resource' => static::get_default_resource_url(),
			'authorization_servers' => array( static::get_issuer_url() ),
			'scopes_supported' => array( static::$scope ),
			'bearer_methods_supported' => array( 'header' ),
			'resource_name' => get_bloginfo( 'name' ) . ' Events Manager',
		);
	}

	protected static function get_clients() {
		$clients = get_option( static::$option_clients, array() );
		return is_array( $clients ) ? $clients : array();
	}

	protected static function get_client( $client_id ) {
		$clients = static::get_clients();
		return !empty( $clients[ $client_id ] ) && is_array( $clients[ $client_id ] ) ? $clients[ $client_id ] : false;
	}

	protected static function is_allowed_redirect_uri( $redirect_uri ) {
		$scheme = wp_parse_url( $redirect_uri, PHP_URL_SCHEME );
		$host = wp_parse_url( $redirect_uri, PHP_URL_HOST );
		return $scheme === 'https' || in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true );
	}

	protected static function verify_pkce( $code_verifier, $code_challenge ) {
		if ( empty( $code_verifier ) || empty( $code_challenge ) ) {
			return false;
		}
		$hash = rtrim( strtr( base64_encode( hash( 'sha256', $code_verifier, true ) ), '+/', '-_' ), '=' );
		return hash_equals( $code_challenge, $hash );
	}

	protected static function same_resource( $a, $b ) {
		return untrailingslashit( strtolower( $a ) ) === untrailingslashit( strtolower( $b ) );
	}

	protected static function redirect_with_error( $redirect_uri, $error, $state = '' ) {
		$args = array( 'error' => $error );
		if ( $state !== '' ) {
			$args['state'] = $state;
		}
		wp_redirect( add_query_arg( $args, $redirect_uri ) );
		exit;
	}

	protected static function current_url() {
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host = !empty( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : wp_parse_url( home_url(), PHP_URL_HOST );
		$uri = !empty( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		return esc_url_raw( $scheme . $host . $uri );
	}

	protected static function get_code_key( $code ) {
		return static::$option_clients . '_code_' . hash( 'sha256', $code );
	}

	protected static function get_access_token_key( $token ) {
		return static::$option_clients . '_access_' . hash( 'sha256', $token );
	}

	public static function get_authorization_server_metadata_url() {
		return static::get_public_url( home_url( '/.well-known/oauth-authorization-server' ) );
	}

	protected static function get_protected_resource_metadata_url() {
		$path = (string) wp_parse_url( static::get_default_resource_url(), PHP_URL_PATH );
		return static::get_public_url( home_url( '/.well-known/oauth-protected-resource' . $path ) );
	}

	protected static function get_issuer_url() {
		return static::get_public_url( home_url( dirname( static::$authorize_path ) ) );
	}

	protected static function get_authorization_endpoint_url() {
		return static::get_public_url( home_url( static::$authorize_path ) );
	}

	protected static function get_token_endpoint_url() {
		return static::get_public_url( rest_url( static::$route_namespace . '/' . static::$route_base . '/token' ) );
	}

	protected static function get_registration_endpoint_url() {
		return static::get_public_url( rest_url( static::$route_namespace . '/' . static::$route_base . '/register' ) );
	}

	protected static function get_public_url( $url ) {
		if ( defined( 'EM_OAUTH_TUNNEL' ) && EM_OAUTH_TUNNEL ) {
			$url = str_replace( get_home_url(), untrailingslashit( EM_OAUTH_TUNNEL ), $url );
		}
		return apply_filters( static::$public_url_filter, $url );
	}
}
