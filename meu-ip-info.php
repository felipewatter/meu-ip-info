<?php
/**
 * Plugin Name: Meu IP Info
 * Description: Exibe informações aproximadas do IP mediante solicitação, sem expor o token ipinfo.io ao navegador.
 * Version: 2.2.0
 * Requires PHP: 7.4
 * Author: Felipe Silva
 * License: MIT
 * Text Domain: meu-ip-info
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MII_VERSION', '2.2.0' );
define( 'MII_OPTION_TOKEN', 'meu_ip_info_token' );
define( 'MII_CACHE_SECONDS', 600 );

/**
 * O valor salvo nunca aparece no HTML do visitante ou no campo de senha do admin.
 * A opção antiga permanece para preservar instalações que já configuraram o token.
 */
function mii_sanitize_token( $input ) {
    $current = (string) get_option( MII_OPTION_TOKEN, '' );

    // A atualização via options.php já foi protegida por nonce e capability pelo WordPress.
    if ( isset( $_POST['mii_clear_token'] ) && '1' === (string) wp_unslash( $_POST['mii_clear_token'] ) ) {
        return '';
    }
    if ( ! is_string( $input ) || '' === trim( $input ) ) {
        return $current;
    }
    $value = trim( wp_unslash( $input ) );
    if ( ! preg_match( '/^[A-Za-z0-9_-]{6,200}$/D', $value ) ) {
        add_settings_error( MII_OPTION_TOKEN, 'mii_invalid_token', 'Token inválido. Mantenha o token anterior.', 'error' );
        return $current;
    }
    return $value;
}

function mii_register_settings() {
    register_setting(
        'meu_ip_info_settings_group',
        MII_OPTION_TOKEN,
        array(
            'type'              => 'string',
            'sanitize_callback' => 'mii_sanitize_token',
            'default'           => '',
        )
    );
}
add_action( 'admin_init', 'mii_register_settings' );

function mii_add_settings_menu() {
    add_options_page( 'Meu IP Info', 'Meu IP Info', 'manage_options', 'meu-ip-info', 'mii_render_settings' );
}
add_action( 'admin_menu', 'mii_add_settings_menu' );

function mii_render_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html( 'Você não tem permissão para acessar esta página.' ) );
    }
    $configured = '' !== (string) get_option( MII_OPTION_TOKEN, '' );
    ?>
    <div class="wrap">
      <h1>Meu IP Info — Configurações</h1>
      <p>O token é utilizado apenas pelo servidor e nunca é enviado ao navegador.</p>
      <p><strong>Estado:</strong> <?php echo esc_html( $configured ? 'Token configurado' : 'Token não configurado' ); ?></p>
      <form method="post" action="options.php">
        <?php settings_fields( 'meu_ip_info_settings_group' ); ?>
        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label for="mii-token">Token do ipinfo.io</label></th>
            <td>
              <input id="mii-token" type="password" name="<?php echo esc_attr( MII_OPTION_TOKEN ); ?>"
                value="" autocomplete="new-password" size="48" />
              <p class="description">Deixe em branco para manter o token salvo. Nunca exiba o segredo no código da página.</p>
            </td>
          </tr>
          <tr><th scope="row">Remover token</th><td>
            <label><input type="checkbox" name="mii_clear_token" value="1" /> Remover a configuração atual</label>
          </td></tr>
        </table>
        <?php submit_button(); ?>
      </form>
    </div>
    <?php
}

/** O proxy não confia em X-Forwarded-For ou outros cabeçalhos enviados pelo visitante. */
function mii_visitor_ip() {
    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    if ( false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
        return '';
    }
    return $ip;
}

/** Apenas campos conhecidos são retornados; resposta upstream nunca é repassada integralmente. */
function mii_normalize_info( $data, $visitor_ip ) {
    if ( ! is_array( $data ) || ! is_string( $visitor_ip ) ) {
        return null;
    }
    $fields = array( 'org', 'country', 'region', 'city' );
    $result = array( 'ip' => $visitor_ip );
    foreach ( $fields as $field ) {
        $result[ $field ] = isset( $data[ $field ] ) && is_string( $data[ $field ] )
            ? substr( sanitize_text_field( $data[ $field ] ), 0, 160 )
            : '';
    }
    $result['latitude']  = null;
    $result['longitude'] = null;
    if ( isset( $data['loc'] ) && is_string( $data['loc'] ) ) {
        $coords = explode( ',', $data['loc'] );
        if ( 2 === count( $coords ) && is_numeric( trim( $coords[0] ) ) && is_numeric( trim( $coords[1] ) ) ) {
            $latitude = (float) trim( $coords[0] );
            $longitude = (float) trim( $coords[1] );
            if ( is_finite( $latitude ) && is_finite( $longitude ) &&
                $latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180 ) {
                $result['latitude'] = $latitude;
                $result['longitude'] = $longitude;
            }
        }
    }
    return $result;
}

function mii_rest_lookup() {
    $token = (string) get_option( MII_OPTION_TOKEN, '' );
    if ( '' === $token ) {
        return new WP_Error( 'mii_unavailable', 'Consulta temporariamente indisponível.', array( 'status' => 503 ) );
    }
    $ip = mii_visitor_ip();
    if ( '' === $ip ) {
        return new WP_Error( 'mii_no_ip', 'Endereço IP público não identificado. Redes locais não são consultadas.', array( 'status' => 422 ) );
    }

    // Hash com chave do WordPress: a chave de cache não revela IP em texto claro.
    $key = 'mii_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ), 0, 48 );
    $cached = get_transient( $key );
    if ( is_array( $cached ) ) {
        $response = new WP_REST_Response( $cached, 200 );
        $response->header( 'Cache-Control', 'private, no-store, max-age=0' );
        return $response;
    }

    // O visitante só pode consultar o próprio endereço; nunca aceita IP em parâmetro.
    // URL fixa de provedor, sem redirecionamento; segredo apenas no cabeçalho HTTP.
    $url = 'https://ipinfo.io/' . rawurlencode( $ip ) . '/json';
    $upstream = wp_safe_remote_get(
        $url,
        array(
            'timeout'             => 6,
            'redirection'         => 0,
            'sslverify'           => true,
            'limit_response_size' => 8192,
            'headers'             => array(
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ),
        )
    );

    if ( is_wp_error( $upstream ) || 200 !== (int) wp_remote_retrieve_response_code( $upstream ) ) {
        return new WP_Error( 'mii_provider_error', 'Não foi possível consultar o serviço de IP.', array( 'status' => 502 ) );
    }
    $body = wp_remote_retrieve_body( $upstream );
    if ( ! is_string( $body ) || strlen( $body ) > 8192 ) {
        return new WP_Error( 'mii_invalid_response', 'Resposta inválida do serviço.', array( 'status' => 502 ) );
    }
    $data = json_decode( $body, true );
    $info = mii_normalize_info( $data, $ip );
    if ( null === $info ) {
        return new WP_Error( 'mii_invalid_response', 'Resposta inválida do serviço.', array( 'status' => 502 ) );
    }
    // O cache é transitório (10 min) para reduzir consultas e o uso da cota.
    set_transient( $key, $info, MII_CACHE_SECONDS );
    $response = new WP_REST_Response( $info, 200 );
    $response->header( 'Cache-Control', 'private, no-store, max-age=0' );
    return $response;
}

function mii_register_rest_route() {
    register_rest_route(
        'meu-ip-info/v1',
        '/lookup',
        array(
            'methods'             => 'GET',
            'callback'            => 'mii_rest_lookup',
            // Endpoint público; IP é obtido somente da conexão do próprio visitante.
            'permission_callback' => '__return_true',
        )
    );
}
add_action( 'rest_api_init', 'mii_register_rest_route' );

function mii_shortcode() {
    if ( ! wp_script_is( 'mii-frontend', 'registered' ) ) {
        wp_register_script( 'mii-frontend', plugins_url( 'meu-ip-info.js', __FILE__ ), array(), MII_VERSION, true );
    }
    if ( ! wp_style_is( 'mii-frontend', 'registered' ) ) {
        wp_register_style( 'mii-frontend', plugins_url( 'meu-ip-info.css', __FILE__ ), array(), MII_VERSION );
    }
    wp_enqueue_script( 'mii-frontend' );
    wp_enqueue_style( 'mii-frontend' );

    $id = wp_unique_id( 'mii-' );
    $rest_url = rest_url( 'meu-ip-info/v1/lookup' );
    ob_start();
    ?>
    <section id="<?php echo esc_attr( $id ); ?>" class="mii-widget"
        data-lookup-url="<?php echo esc_url( $rest_url ); ?>"
        aria-label="Consulta de informações aproximadas do IP">
      <h2 class="mii-title">Confira suas informações de IP</h2>
      <p class="mii-disclosure">Ao selecionar “Consultar meu IP”, seu endereço público será enviado pelo
        servidor deste site ao ipinfo.io para identificar localização aproximada e provedor.
        O resultado é mantido em cache temporário por até 10 minutos; o plugin não mantém histórico.
        O mapa utiliza OpenStreetMap somente após sua confirmação.</p>
      <button type="button" class="mii-button mii-lookup">Consultar meu IP</button>
      <p class="mii-status" role="status" aria-live="polite"></p>
      <dl class="mii-details" hidden>
        <div><dt>Endereço IP</dt><dd data-field="ip"></dd></div>
        <div><dt>Provedor</dt><dd data-field="org"></dd></div>
        <div><dt>País</dt><dd data-field="country"></dd></div>
        <div><dt>Estado</dt><dd data-field="region"></dd></div>
        <div><dt>Cidade</dt><dd data-field="city"></dd></div>
      </dl>
      <button type="button" class="mii-button mii-copy" hidden>Copiar IP</button>
      <button type="button" class="mii-button mii-map" hidden>Carregar mapa aproximado (OpenStreetMap)</button>
      <div class="mii-map-container" hidden></div>
      <p class="mii-note">A geolocalização por IP é estimada e não representa sua localização exata.</p>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode( 'meu_ip_info', 'mii_shortcode' );
