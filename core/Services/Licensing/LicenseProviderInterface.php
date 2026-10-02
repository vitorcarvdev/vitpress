<?php
namespace Hub\Core\Services\Licensing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface LicenseProviderInterface
 * Define o contrato para serviços de validação de licença, permitindo
 * alternar entre licenciamento local (v1.7) e remoto futuro.
 */
interface LicenseProviderInterface {

	/**
	 * Verifica se a instalação atual possui uma licença válida.
	 *
	 * @return bool
	 */
	public function isValid(): bool;

	/**
	 * Retorna o status detalhado da licença.
	 *
	 * @return array
	 */
	public function getStatus(): array;

	/**
	 * Inicia o fluxo de autorização/solicitação.
	 *
	 * @return bool|\WP_Error
	 */
	public function requestAuthorization();

	/**
	 * Valida o token e consolida a licença.
	 *
	 * @param string $token
	 * @return bool|\WP_Error
	 */
	public function authorize( string $token );
}
