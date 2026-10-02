<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Hub_Vitagencia_Updater {

    const PLUGIN_FILE = 'hub-vitagencia/hub.php';
    const API_URL     = 'https://vitads.vitagencia.com.br/api/v1/vitpress/updates/check';
    const CACHE_KEY   = 'hub_vitagencia_vitads_check';
    const FAIL_KEY    = 'hub_vitagencia_vitads_check_fail';

    private $slug;
    private $plugin_data;
    private $api_url;
    private $uuid;
    private $domain;

    public function __construct() {
        $this->slug = 'hub-vitagencia';
        $this->api_url = apply_filters( 'hub_vitagencia_update_api_url', self::API_URL );

        add_action( 'admin_init', [ $this, 'init_updater' ] );
    }

    public function init_updater() {
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $this->plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . self::PLUGIN_FILE );

        $stored = get_option( 'hub_vitagencia_install_uuid' );
        if ( is_string( $stored ) && $this->is_uuid( $stored ) ) {
            $this->uuid = strtolower( $stored );
        } elseif ( empty( $stored ) ) {
            $this->uuid = wp_generate_uuid4();
            update_option( 'hub_vitagencia_install_uuid', $this->uuid );
        }

        $host = wp_parse_url( site_url(), PHP_URL_HOST );
        $this->domain = is_string( $host ) ? strtolower( $host ) : '';

        delete_transient( 'hub_vitagencia_update_hash' );

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_updates' ] );
        add_filter( 'plugins_api', [ $this, 'plugins_api_handler' ], 10, 3 );
        add_filter( 'upgrader_pre_download', [ $this, 'download_package' ], 10, 4 );
        add_filter( 'upgrader_source_selection', [ $this, 'guard_extracted_source' ], 10, 4 );
    }

    public function check_for_updates( $transient ) {
        if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
            return $transient;
        }

        $current = $this->current_version();
        $info    = $this->fetch_update( false );
        if ( is_array( $info ) && ! empty( $info['update_available'] ) && empty( $info['package_url'] ) ) {
            $info = $this->fetch_update( true );
        }

        if ( ! is_array( $info ) || $current === '' ) {
            return $transient;
        }

        if ( ! empty( $info['update_available'] ) && ! empty( $info['package_url'] ) && version_compare( $info['version'], $current, '>' ) ) {
            $update               = new stdClass();
            $update->id           = self::PLUGIN_FILE;
            $update->slug         = $this->slug;
            $update->plugin       = self::PLUGIN_FILE;
            $update->new_version  = $info['version'];
            $update->package      = $info['package_url'];
            $update->url          = '';
            $update->requires     = $info['requires_wp'];
            $update->requires_php = $info['requires_php'];
            $update->tested       = '';
            $transient->response[ self::PLUGIN_FILE ] = $update;
            return $transient;
        }

        $no_update               = new stdClass();
        $no_update->id           = self::PLUGIN_FILE;
        $no_update->slug         = $this->slug;
        $no_update->plugin       = self::PLUGIN_FILE;
        $no_update->new_version  = $current;
        $no_update->url          = '';
        $no_update->package      = '';
        $transient->no_update[ self::PLUGIN_FILE ] = $no_update;

        return $transient;
    }

    public function plugins_api_handler( $res, $action, $args ) {
        if ( $action !== 'plugin_information' || ! is_object( $args ) || ! isset( $args->slug ) || $this->slug !== $args->slug ) {
            return $res;
        }

        $info = $this->fetch_update( false );
        if ( is_array( $info ) && ! empty( $info['update_available'] ) && empty( $info['package_url'] ) ) {
            $fresh = $this->fetch_update( true );
            if ( is_array( $fresh ) ) {
                $info = $fresh;
            }
        }

        if ( ! is_array( $info ) || empty( $info['version'] ) ) {
            return $res;
        }

        $details                = new stdClass();
        $details->name          = 'VitPress';
        $details->slug          = $this->slug;
        $details->version       = $info['version'];
        $details->requires      = $info['requires_wp'];
        $details->requires_php  = $info['requires_php'];
        $details->tested        = '';
        $details->author        = 'VitAgência + VCSIS';
        $details->author_profile = 'https://vitagencia.com.br';
        $details->download_link = $info['package_url'];
        $details->trunk         = $info['package_url'];
        $details->last_updated  = '';
        $details->sections      = [
            'description' => 'VitPress — central de gestão para os sites dos clientes.',
            'changelog'   => wpautop( $info['changelog'] !== '' ? $info['changelog'] : 'Nenhum changelog disponível.' ),
        ];

        return $details;
    }

    public function download_package( $reply, $package, $upgrader, $hook_extra ) {
        if ( ! $this->is_our_plugin( $hook_extra ) ) {
            return $reply;
        }

        $info = $this->fetch_update( true );
        if ( ! is_array( $info ) || empty( $info['update_available'] ) || empty( $info['package_url'] ) ) {
            return new WP_Error( 'vitpress_update_unavailable', 'Não foi possível verificar a atualização do VitPress.' );
        }

        if ( ! function_exists( 'download_url' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $file = download_url( $info['package_url'] );
        if ( is_wp_error( $file ) ) {
            return new WP_Error( 'vitpress_download_failed', 'Não foi possível baixar a atualização do VitPress.' );
        }

        if ( ! empty( $info['sha256'] ) ) {
            $hash = hash_file( 'sha256', $file );
            if ( ! is_string( $hash ) || strlen( $hash ) !== 64 || ! hash_equals( $info['sha256'], $hash ) ) {
                @unlink( $file );
                return new WP_Error( 'vitpress_checksum', 'O pacote do VitPress não passou na verificação.' );
            }
        }

        return $file;
    }

    public function guard_extracted_source( $source, $remote_source = '', $upgrader = null, $hook_extra = [] ) {
        if ( ! $this->is_our_plugin( $hook_extra ) || is_wp_error( $source ) ) {
            return $source;
        }

        $folder = basename( untrailingslashit( (string) $source ) );
        $main   = trailingslashit( (string) $source ) . 'hub.php';
        if ( $folder !== 'hub-vitagencia' || ! is_file( $main ) ) {
            return new WP_Error( 'vitpress_invalid_package', 'O pacote do VitPress precisa conter hub-vitagencia/hub.php.' );
        }

        return $source;
    }

    private function fetch_update( $force ) {
        if ( ! $force ) {
            $cached = get_transient( self::CACHE_KEY );
            if ( is_array( $cached ) ) {
                return $cached;
            }
            if ( get_transient( self::FAIL_KEY ) ) {
                return null;
            }
        }

        $payload = $this->request_update_data();
        if ( ! is_array( $payload ) ) {
            set_transient( self::FAIL_KEY, 1, 30 * MINUTE_IN_SECONDS );
            return null;
        }

        $cacheable = $payload;
        unset( $cacheable['package_url'] );
        set_transient( self::CACHE_KEY, $cacheable, 12 * HOUR_IN_SECONDS );
        delete_transient( self::FAIL_KEY );

        return $force || ! empty( $payload['package_url'] ) ? $payload : $cacheable;
    }

    private function request_update_data() {
        if ( ! is_string( $this->api_url ) || stripos( $this->api_url, 'https://' ) !== 0 ) {
            return null;
        }
        if ( ! $this->is_uuid( (string) $this->uuid ) || $this->domain === '' ) {
            return null;
        }

        global $wp_version;

        $response = wp_remote_post( $this->api_url, [
            'timeout'   => 10,
            'sslverify' => true,
            'headers'   => [ 'Accept' => 'application/json' ],
            'body'      => [
                'uuid'              => $this->uuid,
                'domain'            => $this->domain,
                'current_version'   => $this->current_version(),
                'wordpress_version' => isset( $wp_version ) ? (string) $wp_version : '',
                'php_version'       => phpversion(),
                'plugin_status'     => $this->plugin_status(),
            ],
        ] );

        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return null;
        }

        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $decoded ) ) {
            return null;
        }

        return $this->normalize_response( $decoded );
    }

    private function normalize_response( array $body ) {
        if ( ! array_key_exists( 'update_available', $body ) ) {
            return null;
        }

        $available = $body['update_available'] === true;
        $version   = $this->clean_version( $body['version'] ?? '' );
        $package   = $available ? $this->clean_package_url( $body['package_url'] ?? '' ) : '';
        if ( $available && ( $version === '' || $package === '' ) ) {
            return null;
        }

        $sha = strtolower( trim( (string) ( $body['sha256'] ?? '' ) ) );
        if ( $sha !== '' && preg_match( '/^[a-f0-9]{64}$/', $sha ) !== 1 ) {
            $sha = '';
        }

        $changelog = (string) ( $body['changelog'] ?? '' );
        if ( function_exists( 'wp_strip_all_tags' ) ) {
            $changelog = wp_strip_all_tags( $changelog );
        } else {
            $changelog = strip_tags( $changelog );
        }
        $changelog = trim( substr( $changelog, 0, 8000 ) );

        return [
            'update_available' => $available,
            'version'          => $version,
            'requires_wp'      => $this->clean_version( $body['requires_wp'] ?? '' ),
            'requires_php'     => $this->clean_version( $body['requires_php'] ?? '' ),
            'changelog'        => $changelog,
            'package_url'      => $package,
            'sha256'           => $sha,
        ];
    }

    private function clean_package_url( $url ) {
        $url = trim( (string) $url );
        $parts = wp_parse_url( $url );
        $api   = wp_parse_url( $this->api_url );
        if ( ! is_array( $parts ) || ! is_array( $api ) ) {
            return '';
        }
        if ( ( $parts['scheme'] ?? '' ) !== 'https' || empty( $parts['host'] ) || empty( $api['host'] ) ) {
            return '';
        }
        if ( strtolower( $parts['host'] ) !== strtolower( $api['host'] ) ) {
            return '';
        }
        if ( ( $parts['path'] ?? '' ) !== '/api/v1/vitpress/updates/download' ) {
            return '';
        }
        if ( empty( $parts['query'] ) || ! preg_match( '/(?:^|&)token=([^&]+)/', $parts['query'] ) ) {
            return '';
        }

        return $url;
    }

    private function clean_version( $version ) {
        $version = trim( (string) $version );
        if ( $version === '' ) {
            return '';
        }
        if ( preg_match( '/^[0-9A-Za-z._-]{1,32}$/', $version ) !== 1 ) {
            return '';
        }
        return $version;
    }

    private function current_version() {
        if ( is_array( $this->plugin_data ) && ! empty( $this->plugin_data['Version'] ) ) {
            return $this->clean_version( $this->plugin_data['Version'] );
        }
        if ( defined( 'HUB_VERSION' ) ) {
            return $this->clean_version( HUB_VERSION );
        }
        return '';
    }

    private function plugin_status() {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            return 'inactive';
        }
        return is_plugin_active( self::PLUGIN_FILE ) ? 'active' : 'inactive';
    }

    private function is_uuid( $value ) {
        return is_string( $value ) && preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value ) === 1;
    }

    private function is_our_plugin( $hook_extra ) {
        return is_array( $hook_extra ) && isset( $hook_extra['plugin'] ) && $hook_extra['plugin'] === self::PLUGIN_FILE;
    }
}

new Hub_Vitagencia_Updater();
