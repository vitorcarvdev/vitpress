<?php
namespace Hub\Core\Services\WordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serviço responsável por desativar comentários, pingbacks e remover a interface do painel do WordPress,
 * mantendo as avaliações de produtos (WooCommerce) operacionais.
 */
class DisableCommentsService {

	public function init() {
		$settings = get_option( 'hub_settings', [] );
		$wp_settings = $settings['wordpress'] ?? [];
		if ( empty( $wp_settings['disable_comments'] ) ) {
			return;
		}

		// Filtra para forçar o fechamento de comentários e pings nos posts
		add_filter( 'comments_open', [ $this, 'close_comments' ], 20, 2 );
		add_filter( 'pings_open', [ $this, 'close_comments' ], 20, 2 );

		// Remove os menus administrativos de Comentários
		add_action( 'admin_menu', [ $this, 'remove_admin_menus' ] );
		
		// Remove o suporte nativo no backend (metaboxes, etc)
		add_action( 'admin_init', [ $this, 'remove_comments_support' ] );
		
		// Oculta o ícone de atalho da barra superior administrativa
		add_action( 'wp_before_admin_bar_render', [ $this, 'remove_admin_bar_links' ] );

		// Esvazia os arrays de comentários existentes e remove script do frontend
		add_filter( 'comments_array', [ $this, 'hide_existing_comments' ], 10, 2 );
		add_action( 'wp_enqueue_scripts', [ $this, 'remove_reply_script' ] );

		// Bloqueia a submissão no formulário clássico
		add_action( 'pre_comment_on_post', [ $this, 'block_comment_submission' ] );

		// Impede inserção de comentários pela API REST
		add_filter( 'rest_pre_insert_comment', [ $this, 'block_rest_comments' ], 10, 2 );
	}

	/**
	 * Retorna 'false' forçando fechamento, mas excetua o post_type 'product'.
	 */
	public function close_comments( $open, $post_id ) {
		$post = get_post( $post_id );
		if ( $post && 'product' === $post->post_type ) {
			return $open;
		}
		return false;
	}

	/**
	 * Remove as páginas do menu administrativo do WordPress.
	 */
	public function remove_admin_menus() {
		remove_menu_page( 'edit-comments.php' );
		remove_submenu_page( 'options-general.php', 'options-discussion.php' );
	}

	/**
	 * Remove a capacidade do WordPress em carregar metaboxes ou colunas de listagem
	 * para todos os custom post types, exceto produtos do WooCommerce.
	 */
	public function remove_comments_support() {
		$post_types = get_post_types();
		foreach ( $post_types as $post_type ) {
			if ( 'product' === $post_type ) {
				continue;
			}
			if ( post_type_supports( $post_type, 'comments' ) ) {
				remove_post_type_support( $post_type, 'comments' );
				remove_post_type_support( $post_type, 'trackbacks' );
			}
		}
	}

	/**
	 * Limpa o atalho "Comentários" da barra preta superior logada.
	 */
	public function remove_admin_bar_links() {
		global $wp_admin_bar;
		if ( is_object( $wp_admin_bar ) ) {
			$wp_admin_bar->remove_menu( 'comments' );
		}
	}

	/**
	 * Retorna um array vazio para não exibir comentários já existentes de posts do passado.
	 */
	public function hide_existing_comments( $comments, $post_id ) {
		$post = get_post( $post_id );
		if ( $post && 'product' === $post->post_type ) {
			return $comments;
		}
		return [];
	}

	/**
	 * Remove o arquivo comment-reply.js para não ficar pesado no frontend.
	 */
	public function remove_reply_script() {
		wp_deregister_script( 'comment-reply' );
	}

	/**
	 * Se por ventura um spammer bater diretamente via script no wp-comments-post.php.
	 */
	public function block_comment_submission( $post_id ) {
		$post = get_post( $post_id );
		if ( $post && 'product' === $post->post_type ) {
			return; // Avaliações WooCommerce estão liberadas
		}
		wp_die( 'Comentários estão desativados neste site.' );
	}

	/**
	 * Trava a inserção de novos comentários também via REST API.
	 */
	public function block_rest_comments( $prepared_comment, $request ) {
		if ( isset( $prepared_comment['comment_post_ID'] ) ) {
			$post = get_post( $prepared_comment['comment_post_ID'] );
			if ( $post && 'product' === $post->post_type ) {
				return $prepared_comment;
			}
		}
		return new \WP_Error( 'rest_comment_disabled', 'Comentários estão desativados.', [ 'status' => 403 ] );
	}

	/**
	 * Ação única de fechamento massivo, acionada pelas rotinas de Settings (OFF -> ON).
	 */
	public static function apply_mass_closing() {
		global $wpdb;
		// Apenas posts e páginas, omitindo expressamente "product".
		$wpdb->query( "UPDATE {$wpdb->posts} SET comment_status = 'closed', ping_status = 'closed' WHERE post_type IN ('post', 'page') AND (comment_status != 'closed' OR ping_status != 'closed')" );
	}
}
