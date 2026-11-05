<?php
/**
 * Template Name: Page Template - Ruhrpott Guru
 *
 * Normal WordPress pages render inside the RuhrpottGuru site shell only.
 * Interactive app/category pages continue to render the React application.
 *
 * @package Ruhrpott_Guru
 */

get_header();

$slug        = get_post_field('post_name', get_post());
$raw_content = get_post_field('post_content', get_post());

$app_shortcodes = array(
    'ruhrpott_app',
    'ruhrpott_veranstaltungen',
    'ruhrpott_gastronomie',
    'ruhrpott_aktivitaeten',
    'ruhrpott_highlights',
    'ruhrpott_kultur',
    'ruhrpott_guides',
    'ruhrpott_karte',
    'ruhrpott-pottquiz',
    'ruhrpott_pott_quiz',
    'ruhrpott_halloween',
    'ruhrpott_home_categories',
    'ruhrpott_home_events',
    'ruhrpott_home_food',
    'ruhrpott_home_highlights',
    'ruhrpott_home_kultur',
    'ruhrpott_home_aktiv',
);

$has_app_shortcode = false;
foreach ($app_shortcodes as $shortcode) {
    if (has_shortcode($raw_content, $shortcode)) {
        $has_app_shortcode = true;
        break;
    }
}

// Slugs that intentionally represent RuhrpottGuru app/category views.
$is_app_slug = in_array($slug, array(
    'veranstaltungen',
    'events',
    'gastronomie',
    'food',
    'highlights',
    'kultur',
    'culture',
    'guides',
    'karte',
    'pott-quiz',
    'halloween-im-pott',
    'weihnachten-im-pott',
    'martinszuege-im-pott',
), true) || strpos((string) $slug, 'aktivit') !== false;

$is_app_page = $has_app_shortcode || $is_app_slug;

if ($is_app_page) :
?>
<?php
// V37: Category shortcodes are treated as an insertion point inside a genuine
// WordPress page. Content before/after the shortcode is rendered by WordPress
// and then placed by React between the global RuhrpottGuru header/footer.
$category_shortcode_map = array(
    'ruhrpott_veranstaltungen' => array('category' => 'Veranstaltungen', 'key' => 'events'),
    'ruhrpott_gastronomie'     => array('category' => 'Gastronomie', 'key' => 'food'),
    'ruhrpott_aktivitaeten'    => array('category' => 'Aktivitäten', 'key' => 'activities'),
    'ruhrpott_highlights'      => array('category' => 'Highlights', 'key' => 'highlights'),
    'ruhrpott_kultur'          => array('category' => 'Kultur', 'key' => 'culture'),
    // V74: Dedicated category pages may use the established Home shortcodes.
    // On those known category slugs they act as the insertion point for the
    // full category shell; on ordinary pages/Home they continue to render as
    // magazine modules through their normal shortcode callbacks.
    'ruhrpott_home_events'     => array('category' => 'Veranstaltungen', 'key' => 'events'),
    'ruhrpott_home_food'       => array('category' => 'Gastronomie', 'key' => 'food'),
    'ruhrpott_home_aktiv'      => array('category' => 'Aktivitäten', 'key' => 'activities'),
    'ruhrpott_home_highlights' => array('category' => 'Highlights', 'key' => 'highlights'),
    'ruhrpott_home_kultur'     => array('category' => 'Kultur', 'key' => 'culture'),
    // V40: On WordPress Home, this shortcode is an insertion point for the
    // established React home feed (four category tiles + live previews), not
    // a replacement/static tile grid. A non-category key deliberately leaves
    // React's active category unset so the original Home logic is rendered.
    'ruhrpott_home_categories' => array('category' => 'Alle', 'key' => 'home'),
);

$category_match = null;
foreach ($category_shortcode_map as $tag => $config) {
    if (preg_match('/\\[' . preg_quote($tag, '/') . '(?:\\s[^\\]]*)?\\s*\\/?\\]/i', $raw_content, $matches, PREG_OFFSET_CAPTURE)) {
        $candidate = array(
            'tag'      => $tag,
            'config'   => $config,
            'shortcode'=> $matches[0][0],
            'offset'   => $matches[0][1],
        );
        if ($category_match === null || $candidate['offset'] < $category_match['offset']) {
            $category_match = $candidate;
        }
    }
}

if ($category_match !== null) {
    $before_raw = substr($raw_content, 0, $category_match['offset']);
    $after_raw  = substr(
        $raw_content,
        $category_match['offset'] + strlen($category_match['shortcode'])
    );

    // Gutenberg's Shortcode block wraps the literal shortcode in comments.
    // Remove only the wrapper comments adjacent to the insertion point so they
    // cannot leak into the editorial before/after fragments.
    $before_raw = preg_replace('/<!--\\s*wp:shortcode\\s*-->\\s*$/i', '', $before_raw);
    $after_raw  = preg_replace('/^\\s*<!--\\s*\\/wp:shortcode\\s*-->/i', '', $after_raw);

    $before_html = trim(apply_filters('the_content', $before_raw));
    $after_html  = trim(apply_filters('the_content', $after_raw));
    ?>
    <div id="ruhrpott-wp-category-before-source" hidden inert>
        <?php echo $before_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
    <div id="ruhrpott-wp-category-after-source" hidden inert>
        <?php echo $after_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
    <div
        id="ruhrpott-guru-root"
        data-category-shell="1"
        data-category="<?php echo esc_attr($category_match['config']['category']); ?>"
        data-category-key="<?php echo esc_attr($category_match['config']['key']); ?>"
        data-radius="2.5"
        aria-live="polite"
    >
        <?php
        // Preserve the previously released server-rendered category H1 on
        // canonical top-level category pages. React replaces this initial
        // content with CategoryView's single H1 after hydration.
        $rpg_initial_category_titles = array(
            'veranstaltungen' => 'Events',
            'food'            => 'Food & Drinks',
            'highlights'      => 'Highlights',
            'kultur'          => 'Kultur & Szene',
            'aktiv'           => 'Aktivitäten',
        );
        if (isset($rpg_initial_category_titles[$slug])) {
            echo '<h1 class="rpgx-category-initial-title">' . esc_html($rpg_initial_category_titles[$slug]) . '</h1>';
        }
        ?>
        <div class="flex items-center justify-center p-12 text-slate-400 font-bold text-sm">
            PottFuchs App wird geladen …
        </div>
    </div>
    <?php
} else {
    ?>
    <main class="flex-1 max-w-7xl w-full mx-auto px-2 sm:px-4 py-1.5 sm:py-3">
        <?php
        if (have_posts()) :
            while (have_posts()) : the_post();
                if ($has_app_shortcode) {
                    // Non-category app shortcodes retain the established rendering path.
                    the_content();
                } else {
                    // Map known legacy app page slugs to their React category/view.
                    $cat  = 'Alle';
                    $view = 'default';

                    if ($slug === 'veranstaltungen') {
                        $cat = 'Veranstaltungen';
                    } elseif ($slug === 'gastronomie') {
                        $cat = 'Gastronomie';
                    } elseif (strpos((string) $slug, 'aktivit') !== false || $slug === 'aktivitaeten') {
                        $cat = 'Aktivitäten';
                    } elseif ($slug === 'highlights') {
                        $cat = 'Highlights';
                    } elseif ($slug === 'kultur') {
                        $cat = 'Kultur';
                    } elseif ($slug === 'guides') {
                        $cat = 'Guides';
                    } elseif ($slug === 'karte') {
                        $cat  = 'Karte';
                        $view = 'karte';
                    } elseif ($slug === 'pott-quiz') {
                        $cat  = 'Quiz';
                        $view = 'quiz';
                    } elseif ($slug === 'halloween-im-pott') {
                        $cat  = 'Halloween';
                        $view = 'halloween';
                    } elseif ($slug === 'weihnachten-im-pott') {
                        $cat  = 'Weihnachten';
                        $view = 'weihnachten';
                    } elseif ($slug === 'martinszuege-im-pott') {
                        $cat  = 'Martin';
                        $view = 'martin';
                    }

                    echo do_shortcode('[ruhrpott_app category="' . esc_attr($cat) . '" view="' . esc_attr($view) . '"]');
                    if ($slug === 'weihnachten-im-pott') {
                        // The published WordPress editorial body is an earlier
                        // landing-page placeholder. Do not render it below the live
                        // results (duplicate filters, contradictory instructions).
                        // A non-JS visitor still receives a useful static fallback.
                        echo '<noscript><p><a href="/veranstaltungen/">Alle Veranstaltungen im Pott entdecken</a></p></noscript>';
                    } elseif ($slug === 'martinszuege-im-pott') {
                        // Same rule as Christmas: the editorial placeholder body is not
                        // rendered below the live results; non-JS visitors get a fallback.
                        echo '<noscript><p><a href="/veranstaltungen/">Alle Veranstaltungen im Pott entdecken</a></p></noscript>';
                    } else {
                        the_content();
                    }
                }
            endwhile;
        endif;
        ?>
    </main>
    <?php
}
?>
<?php
else :
    // Plain WordPress content page: keep the RuhrpottGuru visual shell, but do not
    // boot any location/category/data views. The React bundle reads this source
    // and places it between the standard site header and footer.
    if (have_posts()) :
        while (have_posts()) : the_post();
            ob_start();
            the_content();
            $wp_page_content = ob_get_clean();
            ?>
            <div id="ruhrpott-wp-page-content-source" hidden inert>
                <article class="rg-wp-page-article">
                    <?php $content_has_h1 = (bool) preg_match('/<h1\b/i', (string) $wp_page_content); ?>
                    <?php if ($slug !== 'route-industriekultur' && !$content_has_h1) : ?>
                        <h1 class="rg-wp-page-title"><?php echo esc_html(get_the_title()); ?></h1>
                    <?php endif; ?>
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
                    PottFuchs Seite wird geladen …
                </div>
            </div>
            <?php
        endwhile;
    endif;
endif;

get_footer();
