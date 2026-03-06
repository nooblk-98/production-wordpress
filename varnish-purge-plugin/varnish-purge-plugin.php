<?php
/**
 * Plugin Name: Varnish Purge Plugin
 * Description: Purges Varnish cache automatically on content updates and provides admin actions for purge all / purge specific URL.
 * Version: 1.0.0
 * Author: Lahiru
 */

if (!defined('ABSPATH')) {
    exit;
}

class Varnish_Purge_Plugin {
    const OPTION_ENDPOINT = 'vpp_varnish_endpoint';

    public function __construct() {
        add_action('admin_menu', array($this, 'register_admin_page'));
        add_action('admin_init', array($this, 'register_settings'));

        add_action('admin_post_vpp_purge_all', array($this, 'handle_purge_all'));
        add_action('admin_post_vpp_purge_url', array($this, 'handle_purge_url'));

        add_action('save_post', array($this, 'auto_purge_on_save'), 10, 3);
        add_action('deleted_post', array($this, 'auto_purge_on_delete'), 10, 1);
        add_action('trashed_post', array($this, 'auto_purge_on_delete'), 10, 1);
    }

    public function register_settings() {
        register_setting('vpp_settings_group', self::OPTION_ENDPOINT, array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_endpoint'),
            'default' => 'http://varnish:6081',
        ));
    }

    public function sanitize_endpoint($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return 'http://varnish:6081';
        }

        return untrailingslashit(esc_url_raw($value));
    }

    public function register_admin_page() {
        add_management_page(
            'Varnish Purge',
            'Varnish Purge',
            'manage_options',
            'vpp-varnish-purge',
            array($this, 'render_admin_page')
        );
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $endpoint = get_option(self::OPTION_ENDPOINT, 'http://varnish:6081');
        $message = isset($_GET['vpp_message']) ? sanitize_text_field(wp_unslash($_GET['vpp_message'])) : '';
        $type = isset($_GET['vpp_type']) ? sanitize_text_field(wp_unslash($_GET['vpp_type'])) : 'success';
        ?>
        <div class="wrap">
            <h1>Varnish Purge</h1>

            <?php if ($message !== '') : ?>
                <div class="notice notice-<?php echo esc_attr($type === 'error' ? 'error' : 'success'); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>

            <h2>Connection</h2>
            <form method="post" action="options.php">
                <?php settings_fields('vpp_settings_group'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(self::OPTION_ENDPOINT); ?>">Varnish Endpoint</label></th>
                        <td>
                            <input
                                type="text"
                                class="regular-text"
                                id="<?php echo esc_attr(self::OPTION_ENDPOINT); ?>"
                                name="<?php echo esc_attr(self::OPTION_ENDPOINT); ?>"
                                value="<?php echo esc_attr($endpoint); ?>"
                                placeholder="http://varnish:6081"
                            />
                            <p class="description">Use container DNS for Docker setup, e.g. <code>http://varnish:6081</code>.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Endpoint'); ?>
            </form>

            <hr />

            <h2>Purge All Cache</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('vpp_purge_all_action', 'vpp_nonce'); ?>
                <input type="hidden" name="action" value="vpp_purge_all" />
                <?php submit_button('Purge All Cache', 'delete'); ?>
            </form>

            <h2>Purge Specific URL</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('vpp_purge_url_action', 'vpp_nonce'); ?>
                <input type="hidden" name="action" value="vpp_purge_url" />
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="vpp_url">URL or Path</label></th>
                        <td>
                            <input type="text" class="regular-text" id="vpp_url" name="vpp_url" placeholder="/about or https://example.com/about" required />
                            <p class="description">Enter full URL or path.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Purge This URL', 'secondary'); ?>
            </form>
        </div>
        <?php
    }

    public function handle_purge_all() {
        if (!current_user_can('manage_options')) {
            wp_die('Not allowed');
        }

        check_admin_referer('vpp_purge_all_action', 'vpp_nonce');

        $result = $this->send_purge('/', true);
        $this->redirect_with_result($result, 'All cache purge requested.');
    }

    public function handle_purge_url() {
        if (!current_user_can('manage_options')) {
            wp_die('Not allowed');
        }

        check_admin_referer('vpp_purge_url_action', 'vpp_nonce');

        $input = isset($_POST['vpp_url']) ? sanitize_text_field(wp_unslash($_POST['vpp_url'])) : '';
        $path = $this->normalize_to_path($input);

        if ($path === '') {
            $this->redirect_with_result(new WP_Error('invalid_url', 'Invalid URL/path.'), 'Invalid URL/path.');
        }

        $result = $this->send_purge($path, false);
        $this->redirect_with_result($result, 'URL purge requested: ' . $path);
    }

    public function auto_purge_on_save($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if (!is_object($post) || $post->post_status !== 'publish') {
            return;
        }

        $this->purge_post_and_home($post_id);
    }

    public function auto_purge_on_delete($post_id) {
        $this->send_purge('/', false);
    }

    private function purge_post_and_home($post_id) {
        $permalink = get_permalink($post_id);
        if (is_string($permalink) && $permalink !== '') {
            $path = $this->normalize_to_path($permalink);
            if ($path !== '') {
                $this->send_purge($path, false);
            }
        }

        $this->send_purge('/', false);
    }

    private function normalize_to_path($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (strpos($value, 'http://') === 0 || strpos($value, 'https://') === 0) {
            $parts = wp_parse_url($value);
            if (!is_array($parts)) {
                return '';
            }
            $path = isset($parts['path']) ? $parts['path'] : '/';
            $query = isset($parts['query']) ? '?' . $parts['query'] : '';
            return $path . $query;
        }

        if ($value[0] !== '/') {
            $value = '/' . $value;
        }

        return $value;
    }

    private function send_purge($path, $purge_all) {
        $endpoint = get_option(self::OPTION_ENDPOINT, 'http://varnish:6081');
        $endpoint = untrailingslashit((string) $endpoint);
        $path = $this->normalize_to_path($path);

        if ($path === '') {
            $path = '/';
        }

        $url = $endpoint . $path;
        $headers = array(
            'Host' => wp_parse_url(home_url(), PHP_URL_HOST),
        );

        if ($purge_all) {
            $headers['X-Purge-All'] = '1';
        }

        return wp_remote_request($url, array(
            'method' => 'PURGE',
            'headers' => $headers,
            'timeout' => 8,
        ));
    }

    private function redirect_with_result($result, $success_message) {
        $args = array(
            'page' => 'vpp-varnish-purge',
        );

        if (is_wp_error($result)) {
            $args['vpp_type'] = 'error';
            $args['vpp_message'] = $result->get_error_message();
        } else {
            $code = (int) wp_remote_retrieve_response_code($result);
            if ($code >= 200 && $code < 300) {
                $args['vpp_type'] = 'success';
                $args['vpp_message'] = $success_message;
            } else {
                $args['vpp_type'] = 'error';
                $args['vpp_message'] = 'Purge failed with status code: ' . $code;
            }
        }

        wp_safe_redirect(add_query_arg($args, admin_url('tools.php')));
        exit;
    }
}

new Varnish_Purge_Plugin();
