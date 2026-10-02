<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap hub-wrap">
	<div class="hub-header">
		<h1 class="wp-heading-inline">
			<?php echo esc_html( sprintf( 'Painel VitPress - %s', $profile->get_name() ) ); ?>
		</h1>
		<span class="hub-profile-badge">
			<span class="dashicons <?php echo esc_attr( $profile->get_icon() ); ?>"></span>
			Perfi: <strong><?php echo esc_html( $profile->get_name() ); ?></strong>
		</span>
	</div>
	<hr class="wp-header-end">

	<!-- Cartões KPI Simples e Limpos -->
	<div class="hub-stats-grid">
		<div class="hub-stat-card border-blue">
			<div class="hub-stat-icon"><span class="dashicons dashicons-calendar-alt"></span></div>
			<div class="hub-stat-data">
				<span class="hub-stat-value"><?php echo esc_html( $events_today ); ?></span>
				<span class="hub-stat-label"><?php echo esc_html( $profile->get_label( 'event_plural', 'Sessões Hoje' ) ); ?> Hoje</span>
			</div>
		</div>

		<div class="hub-stat-card border-green">
			<div class="hub-stat-icon"><span class="dashicons dashicons-groups"></span></div>
			<div class="hub-stat-data">
				<span class="hub-stat-value"><?php echo esc_html( $new_leads ); ?></span>
				<span class="hub-stat-label">Novos <?php echo esc_html( $profile->get_label( 'lead_plural', 'Leads' ) ); ?></span>
			</div>
		</div>

		<div class="hub-stat-card border-orange">
			<div class="hub-stat-icon"><span class="dashicons dashicons-update"></span></div>
			<div class="hub-stat-data">
				<span class="hub-stat-value"><?php echo esc_html( $pending_conversions ); ?></span>
				<span class="hub-stat-label">Conversões Pendentes</span>
			</div>
		</div>

		<div class="hub-stat-card border-purple">
			<div class="hub-stat-icon"><span class="dashicons dashicons-clock"></span></div>
			<div class="hub-stat-data">
				<span class="hub-stat-value"><?php echo esc_html( $clients_waiting ); ?></span>
				<span class="hub-stat-label"><?php echo esc_html( $profile->get_label( 'client_plural', 'Clientes' ) ); ?> Aguardando Retorno</span>
			</div>
		</div>
	</div>

	<!-- Agenda e Lista Rápida do Dia -->
	<div class="hub-dashboard-section card">
		<h2>
			<span class="dashicons dashicons-clock"></span>
			Compromissos Agendados para Hoje (<?php echo esc_html( date_i18n( 'd/m/Y' ) ); ?>)
		</h2>

		<?php if ( ! empty( $today_schedule ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 120px;">Horário</th>
						<th>Título / Paciente</th>
						<th>Status</th>
						<th>Ações</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $today_schedule as $event ) : ?>
						<tr>
							<td><strong><?php echo esc_html( date( 'H:i', strtotime( $event->event_time ) ) ); ?></strong></td>
							<td><?php echo esc_html( $event->title ); ?></td>
							<td>
								<span class="hub-status-pill status-<?php echo esc_attr( $event->status ); ?>">
									<?php echo esc_html( ucfirst( $event->status ) ); ?>
								</span>
							</td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=hub-agenda&action=edit&id=' . $event->id ) ); ?>" class="button button-small">
									Ver / Editar
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="description">Nenhum compromisso agendado para o dia de hoje.</p>
		<?php endif; ?>
	</div>
</div>
