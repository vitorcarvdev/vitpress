<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$client_plural   = $profile->get_label( 'client_plural', 'Clientes' );
$client_singular = $profile->get_label( 'client_singular', 'Cliente' );
?>
<div class="wrap hub-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( $client_plural ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=hub-empresas&action=new' ) ); ?>" class="page-title-action">
		+ Cadastrar <?php echo esc_html( $client_singular ); ?>
	</a>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['message'] ) && 'saved' === $_GET['message'] ) : ?>
		<div class="notice notice-success is-dismissible"><p>Registro salvo com sucesso!</p></div>
	<?php endif; ?>

	<form method="get">
		<input type="hidden" name="page" value="hub-empresas">
		<?php
		$list_table->search_box( 'Buscar ' . $client_plural, 'search_id' );
		$list_table->display();
		?>
	</form>
</div>
