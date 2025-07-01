<?php
/*
Plugin Name: Meu IP Info
Description: Exibe informações do IP do visitante e mapa usando ipinfo.io, com estilo moderno. Use o shortcode [meu_ip_info] em páginas ou posts.
Version: 2.1
Author: Felipe Silva
*/

if (!defined('ABSPATH')) exit;

function meu_ip_info_shortcode() {
    $token = get_option('meu_ip_info_token', '');
    if (empty($token)) {
        return '<p><strong>O token da API não está configurado. Configure em Configurações > Meu IP Info.</strong></p>';
    }

    ob_start();
    ?>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
    #ip-info {
        max-width: 500px;
        margin: 20px auto;
        padding: 25px;
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 20px;
        box-shadow: 0 8px 30px rgba(0,0,0,0.1);
        font-family: 'Poppins', sans-serif;
        text-align: center;
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.6s ease;
    }

    #ip-info.loaded {
        opacity: 1;
        transform: translateY(0);
    }

    #ip-info p {
        margin: 12px 0;
        font-size: 18px;
    }

    #ip-info p span {
        font-weight: 600;
        color: #2b67f6;
    }

    #map {
        border-radius: 15px;
        overflow: hidden;
        margin-top: 20px;
    }

    .ip-title {
        text-align: center;
        font-size: 30px;
        color: #2b67f6;
        margin-top: 30px;
        margin-bottom: 10px;
        font-weight: 600;
        font-family: 'Poppins', sans-serif;
    }

    .ip-subtitle {
        text-align: center;
        margin-top: 8px;
        color: #555;
        font-size: 15px;
        font-family: 'Poppins', sans-serif;
    }

    .copy-btn {
        margin-top: 12px;
        padding: 8px 16px;
        background-color: #2b67f6;
        color: #fff;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
    }

    .copy-btn:hover {
        background-color: #244fbe;
    }
    </style>
    <h2 class="ip-title">Confira suas informações de IP</h2>
    <div id="ip-info">
        <p><i class="fas fa-globe"></i> <strong>IP:</strong> <span id="ip"></span></p>
        <p><i class="fas fa-building"></i> <strong>Provedor:</strong> <span id="org"></span></p>
        <p><i class="fas fa-flag"></i> <strong>País:</strong> <span id="country"></span></p>
        <p><i class="fas fa-map-marker-alt"></i> <strong>Estado:</strong> <span id="region"></span></p>
        <p><i class="fas fa-city"></i> <strong>Cidade:</strong> <span id="city"></span></p>
        <button class="copy-btn" onclick="copyIp()">Copiar IP</button>
    </div>
    <div id="map" style="height: 400px; margin-top: 20px;"></div>
    <p class="ip-subtitle">Seus dados são obtidos apenas para exibição e não são armazenados.</p>
    <script>
        var meuIpInfoToken = "<?php echo esc_js($token); ?>";

        function copyIp() {
            const ip = document.getElementById("ip").textContent;
            navigator.clipboard.writeText(ip).then(() => {
                alert("IP copiado!");
            });
        }
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('meu_ip_info', 'meu_ip_info_shortcode');

function meu_ip_info_enqueue_scripts() {
    wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet/dist/leaflet.css');
    wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet/dist/leaflet.js', array(), null, true);
    wp_enqueue_script('meu-ip-info-script', plugins_url('/meu-ip-info.js', __FILE__), array('leaflet-js'), null, true);
    wp_enqueue_script('fontawesome', 'https://kit.fontawesome.com/a2e8c50f08.js');
}
add_action('wp_enqueue_scripts', 'meu_ip_info_enqueue_scripts');

function meu_ip_info_menu() {
    add_options_page('Meu IP Info', 'Meu IP Info', 'manage_options', 'meu-ip-info', 'meu_ip_info_settings_page');
}
add_action('admin_menu', 'meu_ip_info_menu');

function meu_ip_info_settings_page() {
    ?>
    <div class="wrap">
        <h1>Configuração - Meu IP Info</h1>
        <form method="post" action="options.php">
            <?php
                settings_fields('meu_ip_info_settings_group');
                do_settings_sections('meu_ip_info_settings_group');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Token do ipinfo.io</th>
                    <td><input type="text" name="meu_ip_info_token" value="<?php echo esc_attr(get_option('meu_ip_info_token')); ?>" size="50"/></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function meu_ip_info_register_settings() {
    register_setting('meu_ip_info_settings_group', 'meu_ip_info_token');
}
add_action('admin_init', 'meu_ip_info_register_settings');
?>
