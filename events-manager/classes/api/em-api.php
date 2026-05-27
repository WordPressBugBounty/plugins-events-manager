<?php
namespace EM\API;

require_once EM_DIR . '/classes/api/em-api-utils.php';
require_once EM_DIR . '/classes/api/em-api-schemas.php';
require_once EM_DIR . '/classes/api/em-api-service.php';
require_once EM_DIR . '/classes/api/em-api-rest.php';
require_once EM_DIR . '/classes/api/em-api-abilities.php';
require_once EM_DIR . '/classes/em-oauth/oauth-server.php';
require_once EM_DIR . '/classes/api/em-api-mcp-oauth.php';
if ( is_admin() ) {
	require_once EM_DIR . '/classes/api/em-api-mcp-admin.php';
}

class API {

	public static function init() {
		\EM\API\REST::init();
		\EM\API\Abilities::init();
		\EM\API\MCP_OAuth::init();
		if ( is_admin() ) {
			\EM\API\MCP_Admin::init();
		}
	}
}

API::init();
