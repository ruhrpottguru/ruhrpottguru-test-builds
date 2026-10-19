<?php
/**
 * Ruhrpott Guru Theme Header
 *
 * @package Ruhrpott_Guru
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="dark">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="<?php echo esc_url(get_template_directory_uri() . '/favicon.svg'); ?>">
    <?php wp_head(); ?>
</head>
<body <?php body_class('bg-slate-950 text-slate-100 min-h-screen flex flex-col antialiased selection:bg-amber-500 selection:text-slate-950'); ?>>
<?php wp_body_open(); ?>
<?php if (has_custom_logo()) : ?>
    <div id="ruhrpott-wp-custom-logo-source" hidden inert>
        <?php echo get_custom_logo(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
<?php endif; ?>
