<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$event_singular  = $profile->get_label( 'event_singular', 'Compromisso' );
$client_singular = $profile->get_label( 'client_singular', 'Cliente/Paciente' );
$title           = $event ? 'Editar ' . $event_singular . ': ' . esc_html( $event->title ) : 'Novo ' . $event_singular;

$statuses = isset( $profile->get_statuses()['event'] ) ? $profile->get_statuses()['event'] : array( 'agendado' => 'Agendado' );
?>
<div class="wrap hub-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=hub-agenda' ) ); ?>" class="page-title-action">&larr; Voltar para Agenda</a>
	<hr class="wp-header-end">

	<div class="card" style="max-width: 800px; margin-top: 20px;">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="hub_save_event">
			<input type="hidden" name="id" value="<?php echo $event ? esc_attr( $event->id ) : 0; ?>">
			<?php wp_nonce_field( 'hub_save_event_nonce', 'hub_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row"><label for="client_id">Vincular a um(a) <?php echo esc_html( $client_singular ); ?></label></th>
					<td>
						<select name="client_id" id="client_id" class="regular-text">
							<option value="0">-- Selecione (ou deixe avulso) --</option>
							<?php foreach ( $clients as $c ) : ?>
								<option value="<?php echo esc_attr( $c->id ); ?>" <?php selected( $event ? $event->client_id : 0, $c->id ); ?>>
									<?php echo esc_html( $c->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="title">Título do Compromisso *</label></th>
					<td><input name="title" type="text" id="title" value="<?php echo $event ? esc_attr( $event->title ) : ''; ?>" class="regular-text" placeholder="Ex: Consulta de Avaliação" required></td>
				</tr>

				<tr>
					<th scope="row"><label for="event_date">Data *</label></th>
					<td><input name="event_date" type="date" id="event_date" value="<?php echo $event ? esc_attr( $event->event_date ) : current_time( 'Y-m-d' ); ?>" class="regular-text" required></td>
				</tr>

				<tr>
					<th scope="row"><label for="event_time">Horário *</label></th>
					<td><input name="event_time" type="time" id="event_time" value="<?php echo $event ? esc_attr( $event->event_time ) : '09:00'; ?>" class="regular-text" required></td>
				</tr>

				<tr>
					<th scope="row"><label for="status">Status</label></th>
					<td>
						<select name="status" id="status" class="regular-text">
							<?php foreach ( $statuses as $slug => $label ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $event ? $event->status : '', $slug ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="notes">Observações / Pauta</label></th>
					<td><textarea name="notes" id="notes" rows="4" class="large-text"><?php echo $event ? esc_textarea( $event->notes ) : ''; ?></textarea></td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary button-large">Salvar Agendamento</button>
			</p>
		</form>
	</div>
</div>
