<?php
if (!defined('ABSPATH')) {
    exit;
}

class MBTGN_Frontend {

    const COOKIE_NAME = 'mbtgn_bypass';

    public static function init() {
        add_action('init', [__CLASS__, 'maybe_set_bypass_cookie'], 1);
        add_action('template_redirect', [__CLASS__, 'maybe_show_maintenance'], 0);
        add_action('pre_get_posts', [__CLASS__, 'maybe_force_etch_page'], 1);
        add_action('wp_head', [__CLASS__, 'maybe_hide_chrome_css'], 999);
        add_filter('wp_robots', [__CLASS__, 'maybe_noindex']);
    }

    private static function settings() {
        return MBTGN_Settings::get();
    }

    private static function is_active() {
        $s = self::settings();
        return !empty($s['enabled']) && self::within_schedule($s);
    }

    /**
     * Parse the schedule window into DateTimeImmutable bounds (site timezone).
     * Returns [start|null, end|null].
     */
    public static function schedule_bounds($s) {
        $tz    = wp_timezone();
        $start = null;
        $end   = null;
        if (!empty($s['start'])) {
            $d = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $s['start'], $tz);
            $start = $d ?: null;
        }
        if (!empty($s['end'])) {
            $d = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $s['end'], $tz);
            $end = $d ?: null;
        }
        return [$start, $end];
    }

    /**
     * When scheduling is enabled, the maintenance page only shows within the window.
     * Empty start or end means an open-ended bound.
     */
    private static function within_schedule($s) {
        if (empty($s['schedule_enabled'])) {
            return true;
        }
        list($start, $end) = self::schedule_bounds($s);
        $now = current_datetime();
        if ($start && $now < $start) {
            return false;
        }
        if ($end && $now > $end) {
            return false;
        }
        return true;
    }

    /**
     * Bypass-Parameter erkannt -> Session-Cookie setzen und Parameter aus der URL entfernen.
     */
    public static function maybe_set_bypass_cookie() {
        if (!self::is_active()) {
            return;
        }
        $s = self::settings();
        $param = $s['bypass_param'];
        if ($param === '' || !isset($_GET[$param])) {
            return;
        }

        $token = self::cookie_token();
        // Session-Cookie (expires = 0): verschwindet beim Schliessen des Browsers.
        setcookie(self::COOKIE_NAME, $token, [
            'expires'  => 0,
            'path'     => COOKIEPATH ? COOKIEPATH : '/',
            'domain'   => COOKIE_DOMAIN,
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE_NAME] = $token;

        // Parameter aus URL entfernen, damit er nicht geteilt/geloggt wird.
        $url = remove_query_arg($param);
        wp_safe_redirect($url);
        exit;
    }

    private static function cookie_token() {
        $s = self::settings();
        return hash_hmac('sha256', $s['bypass_param'] . '|' . $s['enabled_at'], wp_salt('auth'));
    }

    private static function has_bypass() {
        if (is_user_logged_in() && current_user_can('edit_posts')) {
            return true;
        }
        return isset($_COOKIE[self::COOKIE_NAME])
            && hash_equals(self::cookie_token(), (string) $_COOKIE[self::COOKIE_NAME]);
    }

    private static function is_excluded_request() {
        // Login, Admin, AJAX, Cron, CLI, REST-Auth-Flows nie blockieren.
        if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('WP_CLI') && WP_CLI)) {
            return true;
        }
        global $pagenow;
        if (in_array($pagenow, ['wp-login.php', 'wp-register.php'], true)) {
            return true;
        }
        return false;
    }

    private static function should_block() {
        return self::is_active() && !self::is_excluded_request() && !self::has_bypass();
    }

    private static function etch_mode_active() {
        if (!self::should_block()) {
            return false;
        }
        $s = self::settings();
        return $s['mode'] === 'etch'
            && !empty($s['etch_page_id'])
            && get_post_status((int) $s['etch_page_id']) === 'publish';
    }

    /**
     * Modus "Etch-Seite": Query so frueh umbiegen, dass WordPress die gewaehlte Seite
     * ganz normal durch Theme/Etch/FSE rendert (inkl. Header/Footer-Template-Parts und
     * ACSS-Assets). Header/Footer werden bei Bedarf nur per CSS ausgeblendet.
     */
    public static function maybe_force_etch_page($query) {
        if (!$query->is_main_query() || !self::should_block()) {
            return;
        }
        $s = self::settings();
        if ($s['mode'] !== 'etch' || empty($s['etch_page_id'])) {
            return;
        }
        $page_id = (int) $s['etch_page_id'];
        if (get_post_status($page_id) !== 'publish') {
            return;
        }

        $query->init();
        $query->set('page_id', $page_id);
        $query->is_page = true;
        $query->is_singular = true;
        $query->is_home = false;
        $query->is_archive = false;
        $query->is_search = false;
        $query->is_404 = false;
    }

    /**
     * Modus "Eigenes HTML/CSS": eigenes Minimal-Template mit wp_head/wp_footer,
     * damit ACSS-Styles geladen werden.
     */
    public static function maybe_show_maintenance() {
        if (!self::should_block()) {
            return;
        }
        if (self::etch_mode_active()) {
            // Seite wird durch das Theme normal gerendert (Etch/FSE-Header/-Footer + ACSS);
            // hier nur die Status-Header setzen.
            self::send_status_headers();
            return;
        }

        self::send_status_headers();
        include MBTGN_PLUGIN_DIR . 'includes/template-maintenance.php';
        exit;
    }

    /**
     * Blendet im Etch-Modus Header und/oder Footer per CSS aus, wenn die jeweiligen
     * Schalter deaktiviert sind. Standard-Selektoren sind die semantischen Landmarken
     * (header/footer) und die FSE-Header/Footer-Template-Parts.
     */
    public static function maybe_hide_chrome_css() {
        if (!self::etch_mode_active()) {
            return;
        }
        $s = self::settings();
        $rules = [];

        if (empty($s['show_header'])) {
            $sel = trim($s['header_selector']) !== '' ? $s['header_selector'] : 'header';
            $rules[] = self::sanitize_selector($sel);
        }
        if (empty($s['show_footer'])) {
            $sel = trim($s['footer_selector']) !== '' ? $s['footer_selector'] : 'footer';
            $rules[] = self::sanitize_selector($sel);
        }

        $rules = array_filter($rules);
        if (!$rules) {
            return;
        }

        echo "\n<style id=\"mfe-hide-chrome\">" . implode(',', $rules) . "{display:none !important}</style>\n";
    }

    private static function sanitize_selector($selector) {
        // Nur uebliche CSS-Selektor-Zeichen zulassen; keine Klammern/HTML.
        return trim(preg_replace('/[^a-zA-Z0-9 ,.:#>_\[\]="\'\-]/', '', (string) $selector));
    }

    private static function send_status_headers() {
        $s = self::settings();
        if ($s['status_code'] === '503') {
            status_header(503);
            header('Retry-After: 3600');
        } else {
            status_header(200);
        }
        nocache_headers();
    }

    public static function maybe_noindex($robots) {
        if (self::is_active() && !self::has_bypass() && !self::is_excluded_request()) {
            $robots['noindex'] = true;
            $robots['nofollow'] = true;
        }
        return $robots;
    }
}
