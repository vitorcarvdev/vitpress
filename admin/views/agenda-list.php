<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$event_plural   = $profile->get_label( 'event_plural', 'Agenda' );
$event_singular = $profile->get_label( 'event_singular', 'Compromisso' );
?>
<div class="wrap hub-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( $event_plural ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=hub-agenda&action=new' ) ); ?>" class="page-title-action">
		+ Novo Agendamento
	</a>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['message'] ) && 'saved' === $_GET['message'] ) : ?>
		<div class="notice notice-success is-dismissible"><p>Agendamento salvo com sucesso!</p></div>
	<?php endif; ?>

	<form method="get">
		<input type="hidden" name="page" value="hub-agenda">
		<?php
		$list_table->search_box( 'Buscar ' . $event_plural, 'search_id' );
		$list_table->display();
		?>
	</form>
</div>
