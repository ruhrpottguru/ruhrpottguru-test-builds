<?php
/**
 * Canonical RuhrpottGuru WordPress Home.
 *
 * V79: The assigned WordPress front page is the only start page. Its Gutenberg
 * shortcodes render as magazine modules inside the normal React site shell;
 * they must never be interpreted as a dedicated category page.
 *
 * @package Ruhrpott_Guru
 */

get_header();

if (have_posts()) :
    while (have_posts()) : the_post();
        ob_start();
        the_content();
        $wp_page_content = ob_get_clean();
        ?>
        <div id="ruhrpott-wp-page-content-source" hidden inert>
            <article class="rg-wp-page-article rg-wp-home-article">
                <div class="rg-wp-page-editor-content rg-wp-home-editor-content">
                    <?php echo $wp_page_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </article>
        </div>
        <?php
        // V105: Editorial content on Home is owned entirely by the WordPress
        // page layout. Blocks/posts placed between RuhrpottGuru shortcodes are
        // rendered exactly at that position; no topic/tag/keyword based
        // automatic insertion is performed by the theme.
        ?>
        <h1 class="rpgx-home-semantic-title"><?php echo esc_html__('RuhrpottGuru – Events, Food & Freizeit im Ruhrgebiet', 'ruhrpott-guru'); ?></h1>
        <div
            id="ruhrpott-guru-root"
            data-wp-page-shell="1"
            data-wp-home-shell="1"
            data-wp-page-title=""
            aria-live="polite"
        >
            <div class="flex items-center justify-center p-12 text-slate-400 font-bold text-sm">
                RuhrpottGuru Home wird geladen …
            </div>
        </div>
        <?php
    endwhile;
endif;

get_footer();
