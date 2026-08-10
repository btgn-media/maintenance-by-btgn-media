<?php
if (!defined('ABSPATH')) {
    exit;
}

class MBTGN_Settings {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_init', [__CLASS__, 'register']);
        add_action('admin_bar_menu', [__CLASS__, 'admin_bar_notice'], 100);
    }

    public static function defaults() {
        return [
            'enabled'      => 0,
            'status_code'  => '503',          // 503 = maintenance, 200 = coming soon
            'mode'         => 'html',         // html | etch
            'bypass_param' => 'preview',
            'schedule_enabled' => 0,          // limit the maintenance page to a time window
            'start'        => '',             // datetime-local (site timezone), e.g. 2026-08-15T09:00
            'end'          => '',             // datetime-local (site timezone)
            'etch_page_id' => 0,
            'show_header'    => 1,            // show the theme/Etch header on the rendered page (etch mode)
            'show_footer'    => 1,            // show the theme/Etch footer on the rendered page (etch mode)
            'header_selector' => 'header',    // CSS selector hidden when show_header is off
            'footer_selector' => 'footer',    // CSS selector hidden when show_footer is off
            'headline'     => __('We’ll be back soon', 'maintenance-by-btgn-media'),
            'html'         => __("<p>Our website is currently being reworked.<br>Please check back shortly.</p>", 'maintenance-by-btgn-media'),
            'css'          => '',
            'enabled_at'   => '',
        ];
    }

    public static function get() {
        $s = get_option(MBTGN_OPTION_KEY, []);
        return wp_parse_args(is_array($s) ? $s : [], self::defaults());
    }

    public static function add_menu() {
        add_options_page(
            'Maintenance by #btgn.media',
            'Maintenance by #btgn.media',
            'manage_options',
            'mbtgn-settings',
            [__CLASS__, 'render_page']
        );
    }

    public static function register() {
        register_setting('mbtgn_group', MBTGN_OPTION_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize'],
        ]);
    }

    public static function sanitize($input) {
        $old = self::get();
        $out = self::defaults();

        $out['enabled']     = empty($input['enabled']) ? 0 : 1;
        $out['status_code'] = (isset($input['status_code']) && $input['status_code'] === '200') ? '200' : '503';
        $out['mode']        = (isset($input['mode']) && $input['mode'] === 'etch') ? 'etch' : 'html';

        $param = isset($input['bypass_param']) ? sanitize_key($input['bypass_param']) : '';
        $out['bypass_param'] = $param !== '' ? $param : 'preview';

        $out['schedule_enabled'] = empty($input['schedule_enabled']) ? 0 : 1;
        $out['start'] = self::sanitize_datetime_local(isset($input['start']) ? $input['start'] : '');
        $out['end']   = self::sanitize_datetime_local(isset($input['end']) ? $input['end'] : '');

        $out['etch_page_id'] = isset($input['etch_page_id']) ? absint($input['etch_page_id']) : 0;
        $out['show_header']     = empty($input['show_header']) ? 0 : 1;
        $out['show_footer']     = empty($input['show_footer']) ? 0 : 1;
        $out['header_selector'] = isset($input['header_selector']) ? sanitize_text_field($input['header_selector']) : 'header';
        $out['footer_selector'] = isset($input['footer_selector']) ? sanitize_text_field($input['footer_selector']) : 'footer';
        $out['headline']     = isset($input['headline']) ? sanitize_text_field($input['headline']) : '';
        $out['html']         = isset($input['html']) ? wp_kses_post($input['html']) : '';
        $out['css']          = isset($input['css']) ? wp_strip_all_tags($input['css']) : '';

        // On (re)activation or parameter change, set a new timestamp ->
        // previously distributed bypass cookies become invalid.
        if (($out['enabled'] && !$old['enabled']) || $out['bypass_param'] !== $old['bypass_param']) {
            $out['enabled_at'] = (string) time();
        } else {
            $out['enabled_at'] = $old['enabled_at'];
        }

        return $out;
    }

    /**
     * Accept only "YYYY-MM-DDTHH:MM" (HTML datetime-local); anything else becomes "".
     */
    private static function sanitize_datetime_local($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        // Some browsers include seconds; normalise to minute precision.
        if (preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})(:\d{2})?$/', $value, $m)) {
            return $m[1];
        }
        return '';
    }

    public static function render_page() {
        $s = self::get();
        $key = MBTGN_OPTION_KEY;
        $bypass_url = home_url('/?' . rawurlencode($s['bypass_param']));
        ?>
        <style>
            /* Native WordPress admin palette (#2271b1 = WP primary blue). */
            .mbtgn-wrap {
                --mbtgn-primary: #2271b1;
                --mbtgn-primary-hover: #135e96;
                --mbtgn-border: #c3c4c7;
                --mbtgn-text-soft: #646970;
                max-width: 780px;
            }
            .mbtgn-wrap h1 {
                font-size: 23px;
                font-weight: 400;
                margin: 0 0 .5rem;
            }
            .mbtgn-version {
                display: flex;
                align-items: center;
                gap: .25rem;
                color: var(--mbtgn-text-soft);
                font-size: 13px;
                margin: 0 0 1.25rem;
            }
            .mbtgn-card {
                background: #fff;
                border: 1px solid var(--mbtgn-border);
                border-radius: 6px;
                padding: 1.25rem 1.5rem;
                margin-block-end: 1rem;
            }
            .mbtgn-card > h2 {
                margin: 0 0 1.1rem;
                padding-block-end: .7rem;
                border-block-end: 1px solid #f0f0f1;
                font-size: 14px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: .02em;
                color: #1d2327;
            }
            .mbtgn-field { margin-block-end: 1.25rem; }
            .mbtgn-field:last-child { margin-block-end: 0; }
            .mbtgn-field > label.mbtgn-label,
            .mbtgn-field > span.mbtgn-label {
                display: block;
                font-weight: 600;
                margin-block-end: .4rem;
                color: #1d2327;
            }
            .mbtgn-hint {
                color: var(--mbtgn-text-soft);
                font-size: 12px;
                margin-block-start: .4rem;
                line-height: 1.5;
            }
            .mbtgn-wrap input[type="text"],
            .mbtgn-wrap input[type="datetime-local"],
            .mbtgn-wrap select,
            .mbtgn-wrap textarea {
                width: 100%;
                max-width: 480px;
                border: 1px solid #8c8f94;
                border-radius: 4px;
                padding: .4rem .6rem;
                font-size: 14px;
                box-shadow: 0 0 0 transparent;
                background: #fff;
            }
            .mbtgn-wrap textarea {
                max-width: 100%;
                font-family: Menlo, Consolas, monospace;
                line-height: 1.55;
            }
            .mbtgn-wrap input[type="text"]:focus,
            .mbtgn-wrap input[type="datetime-local"]:focus,
            .mbtgn-wrap select:focus,
            .mbtgn-wrap textarea:focus {
                border-color: var(--mbtgn-primary);
                box-shadow: 0 0 0 1px var(--mbtgn-primary);
                outline: 2px solid transparent;
            }
            /* Segmented radio choices — WP button-group feel */
            .mbtgn-choices { display: inline-flex; flex-wrap: wrap; }
            .mbtgn-choice { position: relative; }
            .mbtgn-choice input { position: absolute; opacity: 0; inset: 0; margin: 0; cursor: pointer; }
            .mbtgn-choice span {
                display: inline-block;
                border: 1px solid #8c8f94;
                margin-inline-start: -1px;
                padding: .4rem .9rem;
                font-size: 13px;
                cursor: pointer;
                background: #f6f7f7;
                color: #2c3338;
            }
            .mbtgn-choice:first-child span { border-start-start-radius: 4px; border-end-start-radius: 4px; margin-inline-start: 0; }
            .mbtgn-choice:last-child span { border-start-end-radius: 4px; border-end-end-radius: 4px; }
            .mbtgn-choice input:checked + span {
                border-color: var(--mbtgn-primary);
                background: var(--mbtgn-primary);
                color: #fff;
                position: relative;
                z-index: 1;
            }
            .mbtgn-choice input:focus-visible + span {
                box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--mbtgn-primary);
                z-index: 2;
            }
            /* Toggle switch, WP blue */
            .mbtgn-toggle { display: inline-flex; align-items: center; gap: .6rem; cursor: pointer; }
            .mbtgn-toggle input { position: absolute; opacity: 0; }
            .mbtgn-toggle .mbtgn-track {
                width: 40px; height: 22px; flex: none;
                border-radius: 999px;
                background: #8c8f94;
                position: relative;
                transition: background .15s ease;
            }
            .mbtgn-toggle .mbtgn-track::after {
                content: "";
                position: absolute;
                top: 3px; left: 3px;
                width: 16px; height: 16px;
                border-radius: 50%;
                background: #fff;
                transition: transform .15s ease;
            }
            .mbtgn-toggle input:checked + .mbtgn-track { background: var(--mbtgn-primary); }
            .mbtgn-toggle input:checked + .mbtgn-track::after { transform: translateX(18px); }
            .mbtgn-toggle input:focus-visible + .mbtgn-track {
                box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--mbtgn-primary);
            }
            .mbtgn-toggle b { font-weight: 600; color: #1d2327; }
            /* Active banner — native WP notice look */
            .mbtgn-banner {
                display: flex; flex-wrap: wrap; align-items: center; gap: .75rem;
                border: 1px solid var(--mbtgn-border);
                border-inline-start: 4px solid var(--mbtgn-primary);
                background: #fff;
                border-radius: 4px;
                padding: .85rem 1rem;
                margin-block-end: 1rem;
            }
            .mbtgn-banner code {
                background: #f6f7f7;
                border: 1px solid var(--mbtgn-border);
                border-radius: 3px;
                padding: .25rem .5rem;
                font-size: 12px;
            }
            .mbtgn-banner .mbtgn-copy { margin-inline-start: auto; }
            .mbtgn-dot {
                width: 8px; height: 8px; flex: none; border-radius: 50%;
                background: #00a32a;
            }
            .mbtgn-dot--idle { background: #dba617; }
            .mbtgn-schedule-note { color: var(--mbtgn-text-soft); font-size: 12px; }
            .mbtgn-wrap p.submit { margin: 0; padding: 0; }
            .mbtgn-advanced { margin-block-start: 1rem; }
            .mbtgn-advanced > summary {
                cursor: pointer;
                font-size: 13px;
                color: var(--mbtgn-primary);
                width: fit-content;
            }
            .mbtgn-advanced[open] > summary { margin-block-end: .25rem; }
        </style>

        <div class="wrap mbtgn-wrap">
            <h1>Maintenance <span style="color:var(--mbtgn-primary)">by #btgn.media</span></h1>

            <?php
            if (isset($_GET['mbtgn_checked'])) {
                if ($_GET['mbtgn_checked'] === 'available') {
                    echo '<div class="notice notice-success is-dismissible"><p>'
                        . esc_html__('An update is available — see the Plugins page.', 'maintenance-by-btgn-media')
                        . ' <a href="' . esc_url(admin_url('plugins.php')) . '">' . esc_html__('Go to Plugins', 'maintenance-by-btgn-media') . '</a></p></div>';
                } else {
                    echo '<div class="notice notice-info is-dismissible"><p>'
                        . esc_html__('You’re on the latest version.', 'maintenance-by-btgn-media')
                        . '</p></div>';
                }
            }
            ?>

            <p class="mbtgn-version">
                <?php
                printf(
                    /* translators: %s: version number */
                    esc_html__('Version %s', 'maintenance-by-btgn-media'),
                    esc_html(MBTGN_VERSION)
                );
                ?>
                &nbsp;·&nbsp;
                <a class="button button-small" href="<?php echo esc_url(MBTGN_Updater::check_url()); ?>"><?php esc_html_e('Check for updates', 'maintenance-by-btgn-media'); ?></a>
            </p>

            <?php
            if ($s['enabled']) :
                $state_active  = true;
                $schedule_note = '';
                if (!empty($s['schedule_enabled'])) {
                    list($start, $end) = MBTGN_Frontend::schedule_bounds($s);
                    $now = current_datetime();
                    $fmt = get_option('date_format') . ' ' . get_option('time_format');
                    if ($start && $now < $start) {
                        $state_active  = false;
                        /* translators: %s: date/time */
                        $schedule_note = sprintf(__('Scheduled to start %s.', 'maintenance-by-btgn-media'), wp_date($fmt, $start->getTimestamp()));
                    } elseif ($end && $now > $end) {
                        $state_active  = false;
                        /* translators: %s: date/time */
                        $schedule_note = sprintf(__('Window ended %s.', 'maintenance-by-btgn-media'), wp_date($fmt, $end->getTimestamp()));
                    } elseif ($end) {
                        /* translators: %s: date/time */
                        $schedule_note = sprintf(__('Active until %s.', 'maintenance-by-btgn-media'), wp_date($fmt, $end->getTimestamp()));
                    }
                }
                ?>
                <div class="mbtgn-banner">
                    <span class="mbtgn-dot<?php echo $state_active ? '' : ' mbtgn-dot--idle'; ?>" aria-hidden="true"></span>
                    <strong>
                        <?php
                        echo $state_active
                            ? esc_html__('Maintenance mode is active', 'maintenance-by-btgn-media')
                            : esc_html__('Maintenance mode scheduled (currently inactive)', 'maintenance-by-btgn-media');
                        ?>
                    </strong>
                    <?php if ($schedule_note) : ?>
                        <span class="mbtgn-schedule-note"><?php echo esc_html($schedule_note); ?></span>
                    <?php endif; ?>
                    <code id="mbtgn-bypass-url"><?php echo esc_html($bypass_url); ?></code>
                    <button type="button" class="button mbtgn-copy" id="mbtgn-copy-btn" data-copied="<?php echo esc_attr__('Copied ✓', 'maintenance-by-btgn-media'); ?>"><?php esc_html_e('Copy link', 'maintenance-by-btgn-media'); ?></button>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('mbtgn_group'); ?>

                <div class="mbtgn-card">
                    <h2><?php esc_html_e('Status & Type', 'maintenance-by-btgn-media'); ?></h2>
                    <div class="mbtgn-field">
                        <label class="mbtgn-toggle">
                            <input type="checkbox" name="<?php echo esc_attr($key); ?>[enabled]" value="1" <?php checked($s['enabled']); ?>>
                            <span class="mbtgn-track" aria-hidden="true"></span>
                            <b><?php esc_html_e('Enable maintenance mode', 'maintenance-by-btgn-media'); ?></b>
                        </label>
                    </div>
                    <div class="mbtgn-field">
                        <span class="mbtgn-label"><?php esc_html_e('Type', 'maintenance-by-btgn-media'); ?></span>
                        <div class="mbtgn-choices">
                            <label class="mbtgn-choice">
                                <input type="radio" name="<?php echo esc_attr($key); ?>[status_code]" value="503" <?php checked($s['status_code'], '503'); ?>>
                                <span><?php esc_html_e('Maintenance · HTTP 503', 'maintenance-by-btgn-media'); ?></span>
                            </label>
                            <label class="mbtgn-choice">
                                <input type="radio" name="<?php echo esc_attr($key); ?>[status_code]" value="200" <?php checked($s['status_code'], '200'); ?>>
                                <span><?php esc_html_e('Coming Soon · HTTP 200', 'maintenance-by-btgn-media'); ?></span>
                            </label>
                        </div>
                        <p class="mbtgn-hint"><?php echo wp_kses_post(__('503 tells search engines the site is temporarily offline; 200 serves the page normally (visitors without bypass still get <code>noindex</code>).', 'maintenance-by-btgn-media')); ?></p>
                    </div>
                    <div class="mbtgn-field">
                        <label class="mbtgn-label" for="mbtgn_bypass_param"><?php esc_html_e('Bypass parameter', 'maintenance-by-btgn-media'); ?></label>
                        <input type="text" id="mbtgn_bypass_param" name="<?php echo esc_attr($key); ?>[bypass_param]" value="<?php echo esc_attr($s['bypass_param']); ?>">
                        <p class="mbtgn-hint">
                            <?php
                            printf(
                                /* translators: %s: example URL */
                                wp_kses_post(__('Visiting <code>%s?parameter</code> sets a session cookie — the visitor sees the full website until the browser is closed. Changing the parameter invalidates any bypass cookies already handed out.', 'maintenance-by-btgn-media')),
                                esc_html(home_url('/'))
                            );
                            ?>
                        </p>
                    </div>
                </div>

                <div class="mbtgn-card">
                    <h2><?php esc_html_e('Schedule', 'maintenance-by-btgn-media'); ?></h2>
                    <div class="mbtgn-field">
                        <label class="mbtgn-toggle">
                            <input type="checkbox" name="<?php echo esc_attr($key); ?>[schedule_enabled]" value="1" <?php checked($s['schedule_enabled']); ?>>
                            <span class="mbtgn-track" aria-hidden="true"></span>
                            <b><?php esc_html_e('Only show during a time window', 'maintenance-by-btgn-media'); ?></b>
                        </label>
                    </div>
                    <div class="mbtgn-field" style="display:grid;gap:.75rem;grid-template-columns:1fr 1fr;max-width:480px;">
                        <div>
                            <label class="mbtgn-label" for="mbtgn_start"><?php esc_html_e('From', 'maintenance-by-btgn-media'); ?></label>
                            <input type="datetime-local" id="mbtgn_start" name="<?php echo esc_attr($key); ?>[start]" value="<?php echo esc_attr($s['start']); ?>">
                        </div>
                        <div>
                            <label class="mbtgn-label" for="mbtgn_end"><?php esc_html_e('Until', 'maintenance-by-btgn-media'); ?></label>
                            <input type="datetime-local" id="mbtgn_end" name="<?php echo esc_attr($key); ?>[end]" value="<?php echo esc_attr($s['end']); ?>">
                        </div>
                    </div>
                    <p class="mbtgn-hint">
                        <?php
                        printf(
                            /* translators: 1: timezone name, 2: current local time */
                            esc_html__('Times use the site timezone (%1$s). Current time: %2$s. Leave a field empty for an open-ended start or end.', 'maintenance-by-btgn-media'),
                            esc_html(wp_timezone_string()),
                            esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format')))
                        );
                        ?>
                    </p>
                </div>

                <div class="mbtgn-card">
                    <h2><?php esc_html_e('Appearance', 'maintenance-by-btgn-media'); ?></h2>
                    <div class="mbtgn-field">
                        <div class="mbtgn-choices">
                            <label class="mbtgn-choice">
                                <input type="radio" name="<?php echo esc_attr($key); ?>[mode]" value="html" <?php checked($s['mode'], 'html'); ?>>
                                <span><?php esc_html_e('Custom HTML & CSS', 'maintenance-by-btgn-media'); ?></span>
                            </label>
                            <label class="mbtgn-choice">
                                <input type="radio" name="<?php echo esc_attr($key); ?>[mode]" value="etch" <?php checked($s['mode'], 'etch'); ?>>
                                <span><?php esc_html_e('Existing page (any builder)', 'maintenance-by-btgn-media'); ?></span>
                            </label>
                        </div>
                    </div>

                    <div class="mbtgn-field" data-mbtgn-mode="etch">
                        <label class="mbtgn-label" for="mbtgn_etch_page"><?php esc_html_e('Page', 'maintenance-by-btgn-media'); ?></label>
                        <?php
                        wp_dropdown_pages([
                            'name'              => $key . '[etch_page_id]',
                            'id'                => 'mbtgn_etch_page',
                            'selected'          => (int) $s['etch_page_id'],
                            'show_option_none'  => __('— Select a page —', 'maintenance-by-btgn-media'),
                            'option_none_value' => 0,
                        ]);
                        ?>
                        <p class="mbtgn-hint"><?php esc_html_e('This page is shown to visitors for all URLs. The page’s builder and theme assets load as usual.', 'maintenance-by-btgn-media'); ?></p>
                    </div>

                    <div class="mbtgn-field" data-mbtgn-mode="etch">
                        <span class="mbtgn-label"><?php esc_html_e('Header & footer', 'maintenance-by-btgn-media'); ?></span>
                        <label class="mbtgn-toggle" style="margin-block-end:.6rem;">
                            <input type="checkbox" name="<?php echo esc_attr($key); ?>[show_header]" value="1" <?php checked($s['show_header']); ?>>
                            <span class="mbtgn-track" aria-hidden="true"></span>
                            <b><?php esc_html_e('Show header', 'maintenance-by-btgn-media'); ?></b>
                        </label>
                        <br>
                        <label class="mbtgn-toggle">
                            <input type="checkbox" name="<?php echo esc_attr($key); ?>[show_footer]" value="1" <?php checked($s['show_footer']); ?>>
                            <span class="mbtgn-track" aria-hidden="true"></span>
                            <b><?php esc_html_e('Show footer', 'maintenance-by-btgn-media'); ?></b>
                        </label>
                        <p class="mbtgn-hint"><?php esc_html_e('The page renders with its full design. Turn these off to hide the header and/or footer via CSS.', 'maintenance-by-btgn-media'); ?></p>

                        <details class="mbtgn-advanced">
                            <summary><?php esc_html_e('Advanced: custom CSS selectors', 'maintenance-by-btgn-media'); ?></summary>
                            <div style="margin-block-start:.85rem;display:grid;gap:.75rem;grid-template-columns:1fr 1fr;max-width:480px;">
                                <div>
                                    <label class="mbtgn-label" for="mbtgn_header_selector" style="font-size:12px;"><?php esc_html_e('Header CSS selector', 'maintenance-by-btgn-media'); ?></label>
                                    <input type="text" id="mbtgn_header_selector" name="<?php echo esc_attr($key); ?>[header_selector]" value="<?php echo esc_attr($s['header_selector']); ?>" placeholder="header">
                                </div>
                                <div>
                                    <label class="mbtgn-label" for="mbtgn_footer_selector" style="font-size:12px;"><?php esc_html_e('Footer CSS selector', 'maintenance-by-btgn-media'); ?></label>
                                    <input type="text" id="mbtgn_footer_selector" name="<?php echo esc_attr($key); ?>[footer_selector]" value="<?php echo esc_attr($s['footer_selector']); ?>" placeholder="footer">
                                </div>
                            </div>
                            <p class="mbtgn-hint"><?php esc_html_e('Only used when the matching switch is off. Defaults to the semantic <header>/<footer> elements — change them only if your header/footer use a different selector.', 'maintenance-by-btgn-media'); ?></p>
                        </details>
                    </div>

                    <div class="mbtgn-field" data-mbtgn-mode="html">
                        <label class="mbtgn-label" for="mbtgn_headline"><?php esc_html_e('Headline', 'maintenance-by-btgn-media'); ?></label>
                        <input type="text" id="mbtgn_headline" name="<?php echo esc_attr($key); ?>[headline]" value="<?php echo esc_attr($s['headline']); ?>">
                    </div>
                    <div class="mbtgn-field" data-mbtgn-mode="html">
                        <label class="mbtgn-label" for="mbtgn_html"><?php esc_html_e('Content (HTML)', 'maintenance-by-btgn-media'); ?></label>
                        <textarea id="mbtgn_html" name="<?php echo esc_attr($key); ?>[html]" rows="9"><?php echo esc_textarea($s['html']); ?></textarea>
                        <p class="mbtgn-hint"><?php echo wp_kses_post(__('ACSS utility classes (e.g. <code>text--l</code>, <code>btn--primary</code>) can be used directly — the maintenance page loads the ACSS styles.', 'maintenance-by-btgn-media')); ?></p>
                    </div>
                    <div class="mbtgn-field" data-mbtgn-mode="html">
                        <label class="mbtgn-label" for="mbtgn_css"><?php esc_html_e('Additional CSS', 'maintenance-by-btgn-media'); ?></label>
                        <textarea id="mbtgn_css" name="<?php echo esc_attr($key); ?>[css]" rows="7"><?php echo esc_textarea($s['css']); ?></textarea>
                    </div>
                </div>

                <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e('Save changes', 'maintenance-by-btgn-media'); ?></button></p>
            </form>
        </div>

        <script>
        (function () {
            // Copy button
            var btn = document.getElementById('mbtgn-copy-btn');
            if (btn) {
                btn.addEventListener('click', function () {
                    var url = document.getElementById('mbtgn-bypass-url').textContent.trim();
                    navigator.clipboard.writeText(url).then(function () {
                        var label = btn.textContent;
                        btn.textContent = btn.dataset.copied;
                        setTimeout(function () { btn.textContent = label; }, 1800);
                    });
                });
            }
            // Show/hide fields depending on appearance mode
            var radios = document.querySelectorAll('input[name="<?php echo esc_js($key); ?>[mode]"]');
            function applyMode() {
                var mode = document.querySelector('input[name="<?php echo esc_js($key); ?>[mode]"]:checked').value;
                document.querySelectorAll('[data-mbtgn-mode]').forEach(function (el) {
                    el.style.display = (el.dataset.mbtgnMode === mode) ? '' : 'none';
                });
            }
            radios.forEach(function (r) { r.addEventListener('change', applyMode); });
            applyMode();
        })();
        </script>
        <?php
    }

    public static function admin_bar_notice($bar) {
        if (!current_user_can('manage_options') || empty(self::get()['enabled'])) {
            return;
        }
        $bar->add_node([
            'id'    => 'mbtgn-active',
            'title' => '<span style="background:#d63638;color:#fff;padding:0 8px;border-radius:3px;">' . esc_html__('Maintenance mode active', 'maintenance-by-btgn-media') . '</span>',
            'href'  => admin_url('options-general.php?page=mbtgn-settings'),
        ]);
    }
}
