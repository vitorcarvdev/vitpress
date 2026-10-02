<?php
namespace Hub\Core\Services\WordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serviço responsável por ocultar o login padrão do WordPress e direcioná-lo para /vcsis/
 */
class HideLoginService {

	private $slug = 'vcsis';

	public function init() {
		$settings = get_option( 'hub_settings', [] );
		$wp_settings = $settings['wordpress'] ?? [];
		if ( empty( $wp_settings['hide_login'] ) ) {
			return;
		}

		// Intercepta requisições à rota autorizada
		add_action( 'init', [ $this, 'handle_vcsis_route' ], 1 );

		// Bloqueia acesso direto
		add_action( 'init', [ $this, 'block_wp_login' ], 2 );
		add_action( 'init', [ $this, 'block_wp_admin' ], 2 );

		// Filtra URLs geradas pelo WordPress
		add_filter( 'site_url', [ $this, 'filter_site_url' ], 10, 4 );
		add_filter( 'network_site_url', [ $this, 'filter_site_url' ], 10, 3 );
		add_filter( 'wp_redirect', [ $this, 'filter_redirects' ], 10, 2 );
		
		// Altera a URL no e-mail de recuperação de senha
		add_filter( 'retrieve_password_message', [ $this, 'filter_retrieve_password_message' ], 10, 4 );
	}

	/**
	 * Intercepta a rota /vcsis/ e carrega o arquivo wp-login.php internamente.
	 */
	public function handle_vcsis_route() {
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		$path        = parse_url( $request_uri, PHP_URL_PATH );
		$path        = untrailingslashit( $path );
		$home_path   = parse_url( home_url(), PHP_URL_PATH ) ?: '';
		$home_path   = untrailingslashit( $home_path );

		$relative_path = substr( $path, strlen( $home_path ) );

		if ( '/' . $this->slug === $relative_path ) {
			// Permite que o wp-login seja executado sem ser bloqueado
			$GLOBALS['pagenow'] = 'wp-login.php';

			// Simula para o WordPress que estamos no wp-login.php para evitar redirecionamentos em loop
			$_SERVER['PHP_SELF'] = $home_path . '/wp-login.php';

			// Remove filtros conflitantes se houver
			remove_action( 'init', [ $this, 'block_wp_login' ], 2 );

			// Carrega fisicamente o arquivo original
			require_once ABSPATH . 'wp-login.php';
			exit;
		}
	}

	/**
	 * Retorna 404 real.
	 */
	private function render_404() {
		global $wp_query;
		if ( ! $wp_query ) {
			$wp_query = new \WP_Query();
		}
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();

		$template = get_404_template();
		if ( $template ) {
			include $template;
		} else {
			// Fallback se o tema não tiver 404.php
			echo '<h1>404 Not Found</h1>';
		}
		exit;
	}

	/**
	 * Bloqueia o acesso direto ao wp-login.php
	 */
	public function block_wp_login() {
		$pagenow = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '';
		if ( 'wp-login.php' === $pagenow ) {
			$request_uri = $_SERVER['REQUEST_URI'] ?? '';
			$path        = parse_url( $request_uri, PHP_URL_PATH );
			$path        = untrailingslashit( $path );

			// Se o path físico terminar em wp-login.php, foi um acesso direto.
			// Verifica também document_uri para alguns ambientes FastCGI
			if ( preg_match( '/wp-login\.php$/i', $path ) || ( isset( $_SERVER['DOCUMENT_URI'] ) && preg_match( '/wp-login\.php$/i', $_SERVER['DOCUMENT_URI'] ) ) ) {
				$this->render_404();
			}
		}
	}

	/**
	 * Bloqueia o acesso ao wp-admin para não logados (evita redirecionamento).
	 */
	public function block_wp_admin() {
		if ( is_admin() && ! is_user_logged_in() && ! wp_doing_ajax() && ! wp_doing_cron() ) {
			$pagenow = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '';
			
			// Permite requisições legítimas ao admin-ajax.php e admin-post.php
			if ( in_array( $pagenow, [ 'admin-ajax.php', 'admin-post.php' ], true ) ) {
				return;
			}
			
			$this->render_404();
		}
	}

	/**
	 * Reescreve a URL gerada pelo WordPress via site_url(), capturando a action do formulário.
	 */
	public function filter_site_url( $url, $path, $scheme, $blog_id = null ) {
		if ( ! is_string( $path ) ) {
			return $url;
		}
		// Verifica se a path aponta para wp-login.php
		if ( strpos( $path, 'wp-login.php' ) === 0 ) {
			// Extrai a query se existir (ex: wp-login.php?action=lostpassword)
			$parsed_url = parse_url( $url );
			$query      = isset( $parsed_url['query'] ) ? '?' . $parsed_url['query'] : '';
			return home_url( '/' . $this->slug . '/' . $query, $scheme );
		}
		return $url;
	}

	/**
	 * Intercepta redirecionamentos (ex: ao deslogar ou ao acessar página protegida).
	 */
	public function filter_redirects( $location, $status ) {
		if ( strpos( $location, 'wp-login.php' ) !== false ) {
			$location = str_replace( 'wp-login.php', $this->slug . '/', $location );
		}
		return $location;
	}

	/**
	 * Garante que a URL no e-mail de recuperação de senha utilize a rota segura.
	 */
	public function filter_retrieve_password_message( $message, $key, $user_login, $user_data ) {
		return str_replace( 'wp-login.php', $this->slug . '/', $message );
	}
}
