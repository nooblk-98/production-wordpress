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
    const VARNISH_ENDPOINT = 'http://varnish:6081';
    const LOG_LIMIT = 50;

    public function __construct() {
        add_action('admin_menu', array($this, 'register_admin_page'));
        add_action('admin_bar_menu', array($this, 'register_admin_bar'), 100);

        add_action('admin_post_vpp_purge_all', array($this, 'handle_purge_all'));
        add_action('admin_post_vpp_purge_url', array($this, 'handle_purge_url'));
        add_action('admin_post_vpp_test_connection', array($this, 'handle_test_connection'));
        add_action('admin_post_vpp_clear_log', array($this, 'handle_clear_log'));

        add_action('save_post', array($this, 'auto_purge_on_save'), 10, 3);
        add_action('deleted_post', array($this, 'auto_purge_on_delete'), 10, 1);
        add_action('trashed_post', array($this, 'auto_purge_on_delete'), 10, 1);
    }



    private function check_connection($endpoint) {
        $response = wp_remote_head($endpoint, array(
            'timeout' => 3,
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        return $code >= 200 && $code < 500;
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

        $message = isset($_GET['vpp_message']) ? sanitize_text_field(wp_unslash($_GET['vpp_message'])) : '';
        $type = isset($_GET['vpp_type']) ? sanitize_text_field(wp_unslash($_GET['vpp_type'])) : 'success';
        $connection_status = $this->check_connection(self::VARNISH_ENDPOINT);
        $opcache_status = $this->get_opcache_status_label();
        $log_entries = $this->get_purge_log();
        ?>
        <div class="wrap">
            <h1>Varnish Purge</h1>

            <?php if ($message !== '') : ?>
                <div class="notice notice-<?php echo esc_attr($type === 'error' ? 'error' : 'success'); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>
            <style>
                .vpp-grid {
                    display: grid;
                    grid-template-columns: minmax(260px, 1fr) minmax(260px, 1fr);
                    gap: 16px;
                    margin-top: 16px;
                }
                .vpp-card {
                    background: #fff;
                    border: 1px solid #c3c4c7;
                    border-radius: 6px;
                    padding: 16px;
                }
                .vpp-card h2 {
                    margin-top: 0;
                    font-size: 16px;
                }
                .vpp-inline {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    flex-wrap: wrap;
                }
                .vpp-status-ok {
                    color: #1a7f37;
                    font-weight: 600;
                }
                .vpp-status-bad {
                    color: #d63638;
                    font-weight: 600;
                }
                .vpp-muted {
                    color: #646970;
                    font-size: 12px;
                }
                .vpp-log-controls {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    flex-wrap: wrap;
                    margin-bottom: 10px;
                }
                .vpp-log-filter {
                    max-width: 320px;
                }
                .vpp-log-summary {
                    cursor: pointer;
                }
                @media (max-width: 900px) {
                    .vpp-grid { grid-template-columns: 1fr; }
                }
            </style>

            <div class="vpp-grid">
                <div class="vpp-card">
                    <h2>Connection</h2>
                    <div class="vpp-inline">
                        <div><strong>Endpoint:</strong> <code><?php echo esc_html(self::VARNISH_ENDPOINT); ?></code></div>
                        <div>
                            <?php if ($connection_status) : ?>
                                <span class="vpp-status-ok">&#10003; Connected</span>
                            <?php else : ?>
                                <span class="vpp-status-bad">&#10007; Not Connected</span>
                            <?php endif; ?>
                        </div>
                        <div><strong>OPcache:</strong> <?php echo esc_html($opcache_status); ?></div>
                    </div>
                    <p class="vpp-muted">Checks that Varnish responds to a HEAD request.</p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('vpp_test_connection_action', 'vpp_nonce'); ?>
                        <input type="hidden" name="action" value="vpp_test_connection" />
                        <?php submit_button('Test Connection', 'secondary'); ?>
                    </form>
                </div>

                <div class="vpp-card">
                    <h2>Purge All Cache</h2>
                    <p class="vpp-muted">Ban everything for this host.</p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('vpp_purge_all_action', 'vpp_nonce'); ?>
                        <input type="hidden" name="action" value="vpp_purge_all" />
                        <?php submit_button('Purge All Cache', 'delete'); ?>
                    </form>
                </div>

                <div class="vpp-card">
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
            </div>

            <details open>
                <summary class="vpp-log-summary"><h2 style="display:inline;">Recent Purge Log</h2></summary>
                <div class="vpp-log-controls">
                    <input type="search" class="regular-text vpp-log-filter" id="vpp-log-filter" placeholder="Filter by path/status/message" aria-label="Filter purge log" />
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('vpp_clear_log_action', 'vpp_nonce'); ?>
                        <input type="hidden" name="action" value="vpp_clear_log" />
                        <?php submit_button('Clear Log', 'secondary', 'submit', false); ?>
                    </form>
                </div>
                <?php if (empty($log_entries)) : ?>
                    <p>No purge activity recorded yet.</p>
                <?php else : ?>
                    <table class="widefat striped" id="vpp-log-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Path</th>
                                <th>All</th>
                                <th>Status</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($log_entries as $entry) : ?>
                                <tr>
                                    <td><?php echo esc_html($entry['time']); ?></td>
                                    <td><code><?php echo esc_html($entry['path']); ?></code></td>
                                    <td><?php echo !empty($entry['purge_all']) ? 'Yes' : 'No'; ?></td>
                                    <td><?php echo esc_html($entry['status']); ?></td>
                                    <td><?php echo esc_html($entry['message']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <script>
                        (function () {
                            var input = document.getElementById('vpp-log-filter');
                            var table = document.getElementById('vpp-log-table');
                            if (!input || !table) { return; }
                            input.addEventListener('input', function () {
                                var q = input.value.toLowerCase();
                                var rows = table.tBodies[0].rows;
                                for (var i = 0; i < rows.length; i++) {
                                    var text = rows[i].innerText.toLowerCase();
                                    rows[i].style.display = text.indexOf(q) !== -1 ? '' : 'none';
                                }
                            });
                        })();
                    </script>
                <?php endif; ?>
            </details>
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

    public function handle_test_connection() {
        if (!current_user_can('manage_options')) {
            wp_die('Not allowed');
        }

        check_admin_referer('vpp_test_connection_action', 'vpp_nonce');

        $ok = $this->check_connection(self::VARNISH_ENDPOINT);
        if ($ok) {
            $this->log_purge_result('connection', false, null);
            $this->redirect_with_result(null, 'Connection successful.');
        }

        $error = new WP_Error('connection_failed', 'Connection failed.');
        $this->log_purge_result('connection', false, $error);
        $this->redirect_with_result($error, 'Connection failed.');
    }

    public function handle_clear_log() {
        if (!current_user_can('manage_options')) {
            wp_die('Not allowed');
        }

        check_admin_referer('vpp_clear_log_action', 'vpp_nonce');

        update_option('vpp_purge_log', array(), false);
        $this->redirect_with_result(null, 'Purge log cleared.');
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
        $endpoint = self::VARNISH_ENDPOINT;
        $path = $this->normalize_to_path($path);

        if ($path === '') {
            $path = '/';
        }

        $url = $endpoint . $path;
        $headers = array(
            'Host' => $this->get_purge_host_header(),
        );

        if ($purge_all) {
            $headers['X-Purge-All'] = '1';
        }

        $result = wp_remote_request($url, array(
            'method' => 'PURGE',
            'headers' => $headers,
            'timeout' => 8,
        ));
        $opcache_note = $this->reset_opcache_if_available();
        $this->log_purge_result($path, $purge_all, $result, $opcache_note);
        return $result;
    }

    private function get_purge_host_header() {
        $parts = wp_parse_url(home_url());
        if (!is_array($parts)) {
            return '';
        }

        $host = isset($parts['host']) ? $parts['host'] : '';
        $port = isset($parts['port']) ? (int) $parts['port'] : 0;

        if ($host === '') {
            return '';
        }

        if ($port > 0) {
            return $host . ':' . $port;
        }

        return $host;
    }

    private function redirect_with_result($result, $success_message) {
        $args = array(
            'page' => 'vpp-varnish-purge',
        );

        if ($result === null) {
            $args['vpp_type'] = 'success';
            $args['vpp_message'] = $success_message;
        } elseif (is_wp_error($result)) {
            $args['vpp_type'] = 'error';
            $args['vpp_message'] = $result->get_error_message();
        } else {
            $code = (int) wp_remote_retrieve_response_code($result);
            $body = wp_remote_retrieve_body($result);
            $status_text = (string) wp_remote_retrieve_response_message($result);
            $varnish_message = $this->extract_varnish_message($body);

            if ($status_text === '') {
                $status_text = 'Response';
            }

            if ($code >= 200 && $code < 300) {
                $args['vpp_type'] = 'success';
                if ($varnish_message !== '') {
                    $args['vpp_message'] = $success_message . ' (' . $code . ' ' . $status_text . ' - ' . $varnish_message . ')';
                } else {
                    $args['vpp_message'] = $success_message . ' (' . $code . ' ' . $status_text . ')';
                }
            } else {
                $args['vpp_type'] = 'error';
                if ($varnish_message !== '') {
                    $args['vpp_message'] = 'Purge failed (' . $code . ' ' . $status_text . ' - ' . $varnish_message . ')';
                } else {
                    $args['vpp_message'] = 'Purge failed with status code: ' . $code;
                }
            }
        }

        wp_safe_redirect(add_query_arg($args, admin_url('tools.php')));
        exit;
    }

    public function register_admin_bar($wp_admin_bar) {
        if (!is_admin_bar_showing() || !current_user_can('manage_options')) {
            return;
        }

        $last = $this->get_last_purge_entry();
        if (!$last) {
            $title = 'Varnish: No recent purge';
        } else {
            $title = 'Varnish: ' . $last['status'];
        }

        $wp_admin_bar->add_node(array(
            'id' => 'vpp-varnish-status',
            'title' => esc_html($title),
            'href' => admin_url('tools.php?page=vpp-varnish-purge'),
        ));
    }

    private function log_purge_result($path, $purge_all, $result, $opcache_note = '') {
        $entry = array(
            'time' => wp_date('Y-m-d H:i:s'),
            'path' => $path,
            'purge_all' => $purge_all ? 1 : 0,
            'status' => '',
            'message' => '',
        );

        if (is_wp_error($result)) {
            $entry['status'] = 'Error';
            $entry['message'] = $result->get_error_message();
        } elseif ($result !== null) {
            $code = (int) wp_remote_retrieve_response_code($result);
            $status_text = (string) wp_remote_retrieve_response_message($result);
            if ($status_text === '') {
                $status_text = 'Response';
            }
            $entry['status'] = $code . ' ' . $status_text;
            $entry['message'] = $this->extract_varnish_message(wp_remote_retrieve_body($result));
        } else {
            $entry['status'] = 'OK';
            $entry['message'] = 'Connection successful.';
        }

        if ($opcache_note !== '') {
            $entry['message'] = trim($entry['message'] . ' (OPcache: ' . $opcache_note . ')');
        }

        $log = $this->get_purge_log();
        array_unshift($log, $entry);
        $log = array_slice($log, 0, self::LOG_LIMIT);
        update_option('vpp_purge_log', $log, false);
    }

    private function get_purge_log() {
        $log = get_option('vpp_purge_log', array());
        if (!is_array($log)) {
            return array();
        }
        return $log;
    }

    private function get_last_purge_entry() {
        $log = $this->get_purge_log();
        if (empty($log)) {
            return null;
        }
        return $log[0];
    }

    private function reset_opcache_if_available() {
        if (!function_exists('opcache_reset')) {
            return 'unavailable';
        }

        if (function_exists('opcache_get_status')) {
            $status = opcache_get_status(false);
            if (is_array($status) && empty($status['opcache_enabled'])) {
                return 'disabled';
            }
        }

        $ok = @opcache_reset();
        return $ok ? 'cleared' : 'failed';
    }

    private function get_opcache_status_label() {
        if (!function_exists('opcache_get_status')) {
            return 'Unavailable';
        }

        $status = opcache_get_status(false);
        if (!is_array($status)) {
            return 'Unavailable';
        }

        if (empty($status['opcache_enabled'])) {
            return 'Disabled';
        }

        return 'Enabled';
    }

    private function extract_varnish_message($body) {
        $body = trim((string) $body);
        if ($body === '') {
            return '';
        }

        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $body, $matches)) {
            $text = trim(wp_strip_all_tags($matches[1]));
            // Clean up "Error XXX " prefix from Varnish responses
            $text = preg_replace('/^Error\s+\d+\s+/i', '', $text);
            return trim($text);
        }

        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $matches)) {
            $text = trim(wp_strip_all_tags($matches[1]));
            // Clean up "Error XXX " prefix from Varnish responses
            $text = preg_replace('/^Error\s+\d+\s+/i', '', $text);
            return trim($text);
        }

        return '';
    }
}

new Varnish_Purge_Plugin();


