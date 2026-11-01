<?php
/** WordPress API contract tests using deterministic stubs; no outgoing requests. */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
$GLOBALS['options'] = array( 'meu_ip_info_token' => 'ABCDEF123456' );
$GLOBALS['transients'] = array();
$GLOBALS['upstream_calls'] = array();
$GLOBALS['provider_reply'] = array( 'response' => array( 'code' => 200 ), 'body' => '{"ip":"8.8.8.8","org":"Example Provider","country":"BR","region":"Bahia","city":"Salvador","loc":"-12.97,-38.50"}' );
$GLOBALS['routes'] = array();
$GLOBALS['tests'] = 0;
$_SERVER['REMOTE_ADDR'] = '8.8.8.8';

class WP_Error {
    public $code;
    public $message;
    public $data;
    public function __construct( $code, $message, $data = array() ) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }
}
class WP_REST_Response {
    public $data;
    public $status;
    public $headers = array();
    public function __construct( $data, $status ) {
        $this->data = $data;
        $this->status = $status;
    }
    public function header( $name, $value ) { $this->headers[$name] = $value; }
}
function add_action() {}
function add_shortcode() {}
function register_setting() {}
function add_options_page() {}
function get_option( $name, $default = '' ) { return $GLOBALS['options'][$name] ?? $default; }
function wp_unslash( $x ) { return stripslashes( $x ); }
function sanitize_text_field( $x ) { return strip_tags( trim( $x ) ); }
function add_settings_error() {}
function wp_salt() { return 'test-only-nonprod-key'; }
function get_transient( $key ) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient( $key, $value, $seconds ) {
    expect_true( 600 === $seconds, 'Cache TTL must be 10 minutes' );
    $GLOBALS['transients'][$key] = $value;
}
function wp_safe_remote_get( $url, $options ) {
    $GLOBALS['upstream_calls'][] = array( $url, $options );
    return $GLOBALS['provider_reply'];
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function wp_script_is() { return false; }
function wp_style_is() { return false; }
function wp_register_script() {}
function wp_register_style() {}
function wp_enqueue_script() {}
function wp_enqueue_style() {}
function plugins_url( $path ) { return '/plugins' . $path; }
function wp_unique_id( $prefix ) {static $id = 0;return $prefix . ++$id;}
function rest_url( $path ) { return 'https://example.test/wp-json/' . $path; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return esc_attr( $s ); }
function esc_html( $s ) { return esc_attr( $s ); }
function current_user_can() { return true; }
function register_rest_route( $ns, $path, $args ) {$GLOBALS['routes'][$ns . $path] = $args;}

function expect_true( $condition, $message ) {
    if ( ! $condition ) throw new RuntimeException( $message );
}
function run_test( $name, $callback ) {
    $callback();
    $GLOBALS['tests']++;
    echo 'PASS ' . $name . PHP_EOL;
}
require dirname( __DIR__ ) . '/meu-ip-info.php';

run_test( 'Existing token is retained when admin leaves input blank', function () {
    expect_true( 'ABCDEF123456' === mii_sanitize_token( '' ), 'Token must be retained' );
    expect_true( 'ABCDEF123456' === mii_sanitize_token( 'illegal token !' ), 'Invalid token must be rejected' );
    expect_true( 'NEWTOKEN7890' === mii_sanitize_token( 'NEWTOKEN7890' ), 'Token change failed' );
} );
run_test( 'Clear token requires explicit option', function () {
    $_POST['mii_clear_token'] = '1';
    expect_true( '' === mii_sanitize_token( '' ), 'Token not cleared' );
    unset( $_POST['mii_clear_token'] );
} );
run_test( 'Shortcode does not contain token or external fetch', function () {
    $html = mii_shortcode();
    expect_true( false === strpos( $html, 'ABCDEF123456' ), 'Secret leaked to frontend' );
    expect_true( false === strpos( $html, 'ipinfo.io/json?token' ), 'Client API call must not exist' );
    expect_true( false !== strpos( $html, 'data-lookup-url' ), 'Missing REST endpoint' );
    expect_true( false !== strpos( $html, 'Consultar meu IP' ), 'Missing opt-in control' );
} );
run_test( 'Two shortcodes have separate identifiers', function () {
    $a = mii_shortcode();
    $b = mii_shortcode();
    preg_match( '/id="(mii-[^"]+)"/', $a, $aMatch );
    preg_match( '/id="(mii-[^"]+)"/', $b, $bMatch );
    expect_true( $aMatch[1] !== $bMatch[1], 'Repeated global IDs' );
} );
run_test( 'Registered endpoint only exposes self lookup', function () {
    mii_register_rest_route();
    $r = $GLOBALS['routes']['meu-ip-info/v1/lookup'];
    expect_true( 'GET' === $r['methods'], 'Only GET should be exposed' );
    expect_true( 'mii_rest_lookup' === $r['callback'], 'Unexpected route handler' );
} );
run_test( 'Only trusted REMOTE_ADDR is used; forwarded header ignored', function () {
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
    expect_true( '8.8.8.8' === mii_visitor_ip(), 'Forwarded header spoofed IP' );
    unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
} );
run_test( 'Private and reserved addresses are not sent upstream', function () {
    foreach ( array( '127.0.0.1', '10.0.0.1', '192.168.1.5', '169.254.1.1', 'not-an-ip' ) as $ip ) {
        $_SERVER['REMOTE_ADDR'] = $ip;
        expect_true( '' === mii_visitor_ip(), 'IP was not blocked: ' . $ip );
    }
    $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
} );
run_test( 'Lookup returns only sanitized allowlisted fields', function () {
    $response = mii_rest_lookup();
    expect_true( $response instanceof WP_REST_Response && 200 === $response->status, 'Lookup failed' );
    expect_true( '8.8.8.8' === $response->data['ip'], 'Incorrect IP' );
    expect_true( '-12.97' == $response->data['latitude'], 'Latitude missing' );
    expect_true( 7 === count( $response->data ), 'Unexpected fields in payload' );
    expect_true( 'private, no-store, max-age=0' === $response->headers['Cache-Control'], 'Cache control missing' );
} );
run_test( 'Provider token appears only in server header, never in URL', function () {
    expect_true( 1 === count( $GLOBALS['upstream_calls'] ), 'Expected one remote call' );
    list( $url, $opts ) = $GLOBALS['upstream_calls'][0];
    expect_true( 'https://ipinfo.io/8.8.8.8/json' === $url, 'Provider URL changed' );
    expect_true( false === strpos( $url, 'ABCDEF123456' ), 'Token leaked to URL' );
    expect_true( 'Bearer ABCDEF123456' === $opts['headers']['Authorization'], 'Bearer auth missing' );
    expect_true( 0 === $opts['redirection'] && true === $opts['sslverify'] && 8192 === $opts['limit_response_size'], 'HTTP security limits missing' );
} );
run_test( 'Same IP uses private 10-minute cache', function () {
    $before = count( $GLOBALS['upstream_calls'] );
    mii_rest_lookup();
    expect_true( $before === count( $GLOBALS['upstream_calls'] ), 'Unexpected repeated lookup' );
} );
run_test( 'Missing token returns unavailable without making request', function () {
    $GLOBALS['options']['meu_ip_info_token'] = '';
    $before = count( $GLOBALS['upstream_calls'] );
    $r = mii_rest_lookup();
    expect_true( $r instanceof WP_Error && 503 === $r->data['status'], 'Missing-token failure not handled' );
    expect_true( $before === count( $GLOBALS['upstream_calls'] ), 'Unexpected provider request' );
    $GLOBALS['options']['meu_ip_info_token'] = 'ABCDEF123456';
} );
run_test( 'Malformed provider reply fails cleanly', function () {
    $GLOBALS['transients'] = array();
    $GLOBALS['provider_reply'] = array( 'response' => array( 'code' => 200 ), 'body' => '{not-json' );
    $r = mii_rest_lookup();
    expect_true( $r instanceof WP_Error && 502 === $r->data['status'], 'Bad JSON should return 502' );
} );
run_test( 'Invalid coordinates are not passed to client', function () {
    $r = mii_normalize_info( array( 'loc' => '91,999', 'city' => '<b>Place</b>' ), '8.8.8.8' );
    expect_true( null === $r['latitude'] && null === $r['longitude'], 'Unsafe coordinates accepted' );
    expect_true( 'Place' === $r['city'], 'HTML not sanitized' );
} );

echo 'TOTAL ' . $GLOBALS['tests'] . ' PHP tests passed' . PHP_EOL;
