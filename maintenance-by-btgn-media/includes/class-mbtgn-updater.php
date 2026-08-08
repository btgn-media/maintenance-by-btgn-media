<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Self-contained update checker that pulls new versions from GitHub Releases.
 *
 * - Public repo: works out of the box.
 * - Private repo: define a fine-grained token with "Contents: read" access in
 *   wp-config.php:  define('MFE_GITHUB_TOKEN', 'github_pat_...');
 *
 * The updater downloads the .zip asset attached to a release (built by build.sh /
 * the GitHub Action) so the extracted folder name matches the plugin slug.
 */
class MBTGN_Updater {

    const REPO       = 'btgn-media/maintenance-by-btgn-media';
    const CACHE_KEY  = 'mbtgn_updater_release';
    const CACHE_TTL  = 21600; // 6 hours
    const HOMEPAGE   = 'https://github.com/btgn-media/maintenance-by-btgn-media';
    const AUTHOR     = '<a href="https://btgn.media" target="_blank" rel="noopener">#btgn.media</a>';

    protected $basename;
    protected $slug;
    protected $version;

    public static function init($plugin_file, $version) {
        $self = new self();
        $self->basename = plugin_basename($plugin_file);
        $self->slug     = dirname($self->basename);
        $self->version  = $version;

        add_filter('pre_set_site_transient_update_plugins', [$self, 'check_update']);
        add_filter('plugins_api', [$self, 'plugin_info'], 20, 3);
        add_filter('http_request_args', [$self, 'auth_asset_download'], 10, 2);
        add_action('upgrader_process_complete', [$self, 'clear_cache'], 10, 2);
        add_action('admin_post_mbtgn_check_updates', [$self, 'handle_manual_check']);
        add_filter('plugin_row_meta', [$self, 'row_meta'], 10, 2);
        add_action('admin_enqueue_scripts', [$self, 'maybe_thickbox']);

        return $self;
    }

    /**
     * Add a "View details" link (opens the info modal) and open the plugin's external
     * row-meta links (e.g. "Visit plugin site") in a new tab. The modal link stays a modal.
     */
    public function row_meta($meta, $file) {
        if ($file !== $this->basename) {
            return $meta;
        }

        foreach ($meta as $i => $html) {
            if (strpos($html, '<a ') !== false && strpos($html, 'thickbox') === false && strpos($html, 'target=') === false) {
                $meta[$i] = str_replace('<a ', '<a target="_blank" rel="noopener" ', $html);
            }
        }

        // Only add our own link if WordPress hasn't already added a details link.
        $has_details = false;
        foreach ($meta as $html) {
            if (strpos($html, 'open-plugin-details-modal') !== false) {
                $has_details = true;
                break;
            }
        }
        if (!$has_details) {
            $meta[] = sprintf(
                '<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
                esc_url(network_admin_url('plugin-install.php?tab=plugin-information&plugin=' . $this->slug . '&TB_iframe=true&width=772&height=788')),
                esc_attr(sprintf(__('More information about %s'), 'Maintenance by #btgn.media')),
                esc_attr('Maintenance by #btgn.media'),
                __('View details')
            );
        }

        return $meta;
    }

    /**
     * Ensure the thickbox assets are available on the Plugins screen so the
     * "View details" modal can open.
     */
    public function maybe_thickbox($hook) {
        if ($hook === 'plugins.php') {
            add_thickbox();
        }
    }

    /**
     * URL for the "Check for updates" button (nonce-protected).
     */
    public static function check_url() {
        return wp_nonce_url(
            admin_url('admin-post.php?action=mbtgn_check_updates'),
            'mbtgn_check_updates'
        );
    }

    /**
     * Flush both caches and force WordPress to rebuild its plugin-update list.
     */
    public function handle_manual_check() {
        if (!current_user_can('manage_options') || !check_admin_referer('mbtgn_check_updates')) {
            wp_die(esc_html__('Permission denied.', 'maintenance-by-btgn-media'));
        }

        delete_transient(self::CACHE_KEY);
        delete_site_transient('update_plugins');
        wp_update_plugins(); // rebuilds the list -> triggers check_update() with a fresh GitHub query

        $updates = get_site_transient('update_plugins');
        $available = is_object($updates) && isset($updates->response[$this->basename]);

        wp_safe_redirect(add_query_arg(
            ['page' => 'mbtgn-settings', 'mbtgn_checked' => $available ? 'available' : 'current'],
            admin_url('options-general.php')
        ));
        exit;
    }

    protected function token() {
        return (defined('MFE_GITHUB_TOKEN') && MFE_GITHUB_TOKEN) ? MFE_GITHUB_TOKEN : '';
    }

    protected function tag_to_version($tag) {
        return ltrim((string) $tag, 'vV');
    }

    protected function get_release() {
        $cached = get_transient(self::CACHE_KEY);
        if ($cached !== false) {
            return $cached ?: null;
        }

        $args = [
            'timeout' => 15,
            'headers' => [
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'maintenance-by-btgn-media-updater',
            ],
        ];
        if ($this->token()) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->token();
        }

        $res = wp_remote_get('https://api.github.com/repos/' . self::REPO . '/releases/latest', $args);

        if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) {
            // Cache the failure briefly so a broken/absent connection doesn't hammer the API.
            set_transient(self::CACHE_KEY, [], 30 * MINUTE_IN_SECONDS);
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($res), true);
        if (!is_array($data) || empty($data['tag_name'])) {
            set_transient(self::CACHE_KEY, [], 30 * MINUTE_IN_SECONDS);
            return null;
        }

        set_transient(self::CACHE_KEY, $data, self::CACHE_TTL);
        return $data;
    }

    protected function download_url($release) {
        if (!empty($release['assets']) && is_array($release['assets'])) {
            foreach ($release['assets'] as $asset) {
                if (isset($asset['name']) && substr($asset['name'], -4) === '.zip') {
                    // With a token we must hit the API asset URL (auth + octet-stream);
                    // otherwise the public browser download URL is enough.
                    return $this->token() ? $asset['url'] : $asset['browser_download_url'];
                }
            }
        }
        return isset($release['zipball_url']) ? $release['zipball_url'] : '';
    }

    public function check_update($transient) {
        if (!is_object($transient) || empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_release();
        if (!$release) {
            return $transient;
        }

        $remote = $this->tag_to_version($release['tag_name']);
        if (version_compare($remote, $this->version, '<=')) {
            return $transient;
        }

        $package = $this->download_url($release);
        if (!$package) {
            return $transient;
        }

        $transient->response[$this->basename] = (object) [
            'slug'        => $this->slug,
            'plugin'      => $this->basename,
            'new_version' => $remote,
            'url'         => 'https://github.com/' . self::REPO,
            'package'     => $package,
        ];

        return $transient;
    }

    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== $this->slug) {
            return $result;
        }

        $release = $this->get_release();
        if (!$release) {
            return $result;
        }

        $readme   = $this->parse_readme($this->get_readme());
        $sections = $readme['sections'];
        if (empty($sections['changelog'])) {
            $sections['changelog'] = wpautop(esc_html(isset($release['body']) ? $release['body'] : ''));
        }

        return (object) [
            'name'          => 'Maintenance by #btgn.media',
            'slug'          => $this->slug,
            'version'       => $this->tag_to_version($release['tag_name']),
            'author'        => self::AUTHOR,
            'homepage'      => self::HOMEPAGE,
            'requires'      => isset($readme['requires']) ? $readme['requires'] : '',
            'tested'        => isset($readme['tested']) ? $readme['tested'] : '',
            'requires_php'  => isset($readme['requires_php']) ? $readme['requires_php'] : '',
            'last_updated'  => isset($release['published_at']) ? $release['published_at'] : '',
            'download_link' => $this->download_url($release),
            'sections'      => $sections,
        ];
    }

    /**
     * Read the plugin's bundled readme.txt (no network), so the "View details" popup
     * always reflects the installed version and works offline.
     */
    protected function get_readme() {
        if (is_readable(MBTGN_PLUGIN_DIR . 'readme.txt')) {
            return (string) file_get_contents(MBTGN_PLUGIN_DIR . 'readme.txt');
        }
        return '';
    }

    /**
     * Minimal WordPress-readme.txt parser: returns header fields plus HTML sections
     * (description, installation, faq, changelog, screenshots) for plugins_api.
     */
    protected function parse_readme($text) {
        $text = str_replace(["\r\n", "\r"], "\n", (string) $text);
        $out  = ['sections' => []];

        if (preg_match('/Requires at least:\s*(.+)/i', $text, $m))  { $out['requires']     = trim($m[1]); }
        if (preg_match('/Tested up to:\s*(.+)/i', $text, $m))       { $out['tested']       = trim($m[1]); }
        if (preg_match('/Requires PHP:\s*(.+)/i', $text, $m))       { $out['requires_php'] = trim($m[1]); }

        $map = [
            'description'                => 'description',
            'installation'               => 'installation',
            'frequently asked questions' => 'faq',
            'changelog'                  => 'changelog',
            'screenshots'                => 'screenshots',
        ];

        if (preg_match_all('/^==\s*(.+?)\s*==\s*$/m', $text, $mm, PREG_OFFSET_CAPTURE)) {
            $count = count($mm[0]);
            for ($i = 0; $i < $count; $i++) {
                $title = strtolower(trim($mm[1][$i][0]));
                if (!isset($map[$title])) {
                    continue;
                }
                $start = $mm[0][$i][1] + strlen($mm[0][$i][0]);
                $end   = ($i + 1 < $count) ? $mm[0][$i + 1][1] : strlen($text);
                $body  = trim(substr($text, $start, $end - $start));
                $out['sections'][$map[$title]] = $this->format_section($body);
            }
        }

        return $out;
    }

    protected function format_section($body) {
        $lines  = explode("\n", $body);
        $html   = '';
        $inList = false;
        $para   = [];

        $flush_para = function () use (&$para, &$html) {
            if ($para) {
                $html .= '<p>' . $this->inline(implode(' ', $para)) . '</p>';
                $para = [];
            }
        };
        $close_list = function () use (&$inList, &$html) {
            if ($inList) {
                $html   .= '</ul>';
                $inList  = false;
            }
        };

        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '') {
                $flush_para();
                $close_list();
                continue;
            }
            if (preg_match('/^=\s*(.+?)\s*=$/', $t, $m)) {
                $flush_para();
                $close_list();
                $html .= '<h4>' . esc_html($m[1]) . '</h4>';
                continue;
            }
            if (preg_match('/^(?:\*|\d+\.)\s+(.*)$/', $t, $m)) {
                $flush_para();
                if (!$inList) {
                    $html  .= '<ul>';
                    $inList = true;
                }
                $html .= '<li>' . $this->inline($m[1]) . '</li>';
                continue;
            }
            $para[] = $t;
        }
        $flush_para();
        $close_list();

        return $html;
    }

    protected function inline($s) {
        $s = esc_html($s);
        $s = preg_replace('/`([^`]+)`/', '<code>$1</code>', $s);
        $s = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $s);
        return $s;
    }

    /**
     * When downloading a private release asset via the API URL, GitHub requires
     * the token and an octet-stream Accept header.
     */
    public function auth_asset_download($args, $url) {
        if ($this->token() && strpos($url, 'api.github.com/repos/' . self::REPO . '/releases/assets/') !== false) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->token();
            $args['headers']['Accept']        = 'application/octet-stream';
        }
        return $args;
    }

    public function clear_cache($upgrader, $data) {
        if (isset($data['action'], $data['type']) && $data['action'] === 'update' && $data['type'] === 'plugin') {
            delete_transient(self::CACHE_KEY);
        }
    }
}
