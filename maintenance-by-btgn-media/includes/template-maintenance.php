<?php
if (!defined('ABSPATH')) {
    exit;
}
$mbtgn = MBTGN_Settings::get();
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($mbtgn['headline'] !== '' ? $mbtgn['headline'] . ' – ' : ''); echo esc_html(get_bloginfo('name')); ?></title>
    <?php wp_head(); // laedt u.a. die ACSS-Stylesheets ?>
    <style>
        .mbtgn-page {
            min-height: 100vh;
            min-height: 100dvh;
            display: grid;
            place-items: center;
            padding: var(--section-space-m, 4rem) var(--gutter, 1.5rem);
            text-align: center;
        }
        .mbtgn-page__inner {
            max-width: 60ch;
            display: grid;
            gap: var(--content-gap, 1.25rem);
        }
        <?php echo $mbtgn['css']; // eigenes CSS aus den Einstellungen ?>
    </style>
</head>
<body <?php body_class('mbtgn-maintenance'); ?>>
    <main class="mbtgn-page">
        <div class="mbtgn-page__inner">
            <?php if ($mbtgn['headline'] !== '') : ?>
                <h1><?php echo esc_html($mbtgn['headline']); ?></h1>
            <?php endif; ?>
            <?php echo wp_kses_post($mbtgn['html']); ?>
        </div>
    </main>
    <?php wp_footer(); ?>
</body>
</html>
