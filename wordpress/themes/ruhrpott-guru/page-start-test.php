<?php
/**
 * RuhrpottGuru Start Test — stable shortcode test page.
 *
 * Uses the stable WordPress content shell. The Gutenberg page content is processed
 * normally so the four individual Home shortcodes render as real modules.
 * React provides the normal RuhrpottGuru header/footer shell; The four magazine shortcodes use the same canonical data engine as the established single Home feed.
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
            <article class="rg-wp-page-article">
                <h1 class="rg-wp-page-title"><?php echo esc_html(get_the_title()); ?></h1>
                <div class="rg-wp-page-editor-content">
                    <?php echo $wp_page_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </article>
        </div>
        <div
            id="ruhrpott-guru-root"
            data-wp-page-shell="1"
            data-wp-page-title="<?php echo esc_attr(get_the_title()); ?>"
            aria-live="polite"
        >
            <div class="flex items-center justify-center p-12 text-slate-400 font-bold text-sm">
                RuhrpottGuru Seite wird geladen …
            </div>
        </div>
        <?php
    endwhile;
endif;

get_footer();
