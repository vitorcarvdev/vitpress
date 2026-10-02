<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lead_plural   = $profile->get_label( 'lead_plural', 'persons' );
$lead_singular = $profile->get_label( 'lead_singular', 'Lead' );
?>
<div class="wrap hub-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( $lead_plural ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=hub-persons&action=new' ) ); ?>" class="page-title-action">
		+ Cadastrar <?php echo esc_html( $lead_singular ); ?>
	</a>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['message'] ) && 'saved' === $_GET['message'] ) : ?>
		<div class="notice notice-success is-dismissible"><p>Registro salvo com sucesso!</p></div>
	<?php endif; ?>

	<form method="get">
		<input type="hidden" name="page" value="hub-persons">
		<?php
		$list_table->search_box( 'Buscar ' . $lead_plural, 'search_id' );
		$list_table->display();
		?>
	</form>
</div>
