<?php

/**
 * V299 – Halloween full-radius candidate feed + cross-source seasonal dedupe.
 * V298 – Event fallback relevance hardening.
 *
 * Original ET4/ODF and approved event images remain untouched and Event-Ort
 * keeps its existing priority. Symbolfotos are accepted only for a deterministic
 * event theme and a matching Pexels alt description. Unknown/unsafe themes fall
 * back to Event-Ort or the neutral ticket placeholder. Legacy Symbolfotos are
 * semantically revalidated in the list presentation. Event sorting, radius,
 * event sync and rights logic are intentionally unchanged.
 */
/**
 * V259 – Accessibility hardening based on the Eye-Able WCAG report.
 *
 * 1) Guarantee a valid page language for every theme-rendered document.
 * 2) Keep category/navigation emoji as Unicode instead of WordPress-generated
 *    <img class="emoji"> elements, because those decorative icons already have
 *    adjacent text labels and must not create meaningless short alt texts.
 */
function ruhrpott_guru_v259_language_attributes($output, $doctype = 'html') {
    $output = trim((string) $output);
    if (preg_match('/\blang\s*=\s*["\'][^"\']+["\']/i', $output)) {
        return $output;
    }
    $language = trim((string) get_bloginfo('language'));
    if ($language === '' || !preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $language)) {
        $language = 'de-DE';
    }
    return 'lang="' . esc_attr($language) . '"' . ($output !== '' ? ' ' . $output : '');
}
add_filter('language_attributes', 'ruhrpott_guru_v259_language_attributes', 20, 2);

function ruhrpott_guru_v259_disable_frontend_emoji_images() {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
}
add_action('init', 'ruhrpott_guru_v259_disable_frontend_emoji_images', 20);


/**
 * V110 – High-resolution editorial thumbnails on the Home.
 *
 * Gutenberg's "Latest Posts" block can retain a small thumbnail size while the
 * card itself spans almost the full mobile width. On the RuhrpottGuru Home we
 * therefore render the featured image from WordPress' "large" derivative.
 */
function ruhrpott_guru_home_editorial_large_featured_images($parsed_block) {
    if (
        is_front_page()
        && isset($parsed_block['blockName'])
        && $parsed_block['blockName'] === 'core/latest-posts'
        && !empty($parsed_block['attrs']['displayFeaturedImage'])
    ) {
        $parsed_block['attrs']['featuredImageSizeSlug'] = 'large';
        $parsed_block['attrs']['featuredImageSizeWidth'] = 1200;
        $parsed_block['attrs']['featuredImageSizeHeight'] = 675;
    }
    return $parsed_block;
}
add_filter('render_block_data', 'ruhrpott_guru_home_editorial_large_featured_images', 20, 1);


/**
 * V111 – Make editorial featured images clickable on the Home.
 *
 * The Gutenberg "Latest Posts" block links the title, but depending on block
 * settings the featured image itself can remain plain. On the RuhrpottGuru
 * Home we wrap each featured image with the same post URL as its title.
 */
function ruhrpott_guru_home_link_editorial_featured_images($block_content, $block) {
    if (
        !is_front_page()
        || empty($block['blockName'])
        || $block['blockName'] !== 'core/latest-posts'
        || stripos($block_content, 'wp-block-latest-posts__featured-image') === false
    ) {
        return $block_content;
    }

    return preg_replace_callback(
        '~<li\b[^>]*>(.*?)</li>~is',
        function ($match) {
            $item = $match[0];

            if (!preg_match(
                '~<a\b(?=[^>]*wp-block-latest-posts__post-title)(?=[^>]*href=["\']([^"\']+)["\'])[^>]*>~i',
                $item,
                $title_link
            )) {
                return $item;
            }

            $url = esc_url($title_link[1]);
            if (!$url) {
                return $item;
            }

            return preg_replace_callback(
                '~(<div\b[^>]*class=["\'][^"\']*wp-block-latest-posts__featured-image[^"\']*["\'][^>]*>)(.*?)(</div>)~is',
                function ($image_box) use ($url) {
                    $inner = $image_box[2];

                    if (preg_match('~<a\b[^>]*>\s*(?:<picture\b[^>]*>)?\s*<img\b~is', $inner)) {
                        return $image_box[0];
                    }

                    $linked = preg_replace(
                        '~(<img\b[^>]*>)~i',
                        '<a class="rg-editorial-image-link" href="' . esc_attr($url) . '" aria-label="' . esc_attr__('Beitrag öffnen', 'ruhrpott-guru') . '">$1</a>',
                        $inner,
                        1
                    );

                    return $image_box[1] . $linked . $image_box[3];
                },
                $item,
                1
            );
        },
        $block_content
    );
}
add_filter('render_block', 'ruhrpott_guru_home_link_editorial_featured_images', 30, 2);


/**
 * V225 – RuhrpottGuru activity-spots editorial banner.
 *
 * The WordPress article "Die 10 besten Aktivitätsspots im Revier" keeps its
 * content and link, but its featured image is rendered from the theme-owned
 * panorama that was approved for the Home editorial banner. This avoids any
 * dependency on the old collage crop and keeps the same visual on the article
 * itself wherever WordPress renders the featured image.
 */
function ruhrpott_guru_v225_is_activityspots_post($post_id) {
    if (!$post_id) {
        return false;
    }
    $title = trim(wp_strip_all_tags((string) get_the_title($post_id)));
    return strcasecmp($title, 'Die 10 besten Aktivitätsspots im Revier') === 0;
}

function ruhrpott_guru_v225_activityspots_banner_url() {
    return get_template_directory_uri() . '/assets/images/aktivitaetsspots-banner-v225.webp';
}

function ruhrpott_guru_v225_activityspots_featured_image($html, $post_id, $post_thumbnail_id, $size, $attr) {
    if (!ruhrpott_guru_v225_is_activityspots_post($post_id)) {
        return $html;
    }

    $url = esc_url(ruhrpott_guru_v225_activityspots_banner_url());
    $alt = esc_attr__('Aktivitäten im Ruhrgebiet', 'ruhrpott-guru');
    $class = 'wp-post-image rg-v225-activityspots-banner';
    if (is_array($attr) && !empty($attr['class'])) {
        $class .= ' ' . sanitize_html_class(str_replace(' ', '-', (string) $attr['class']));
    }

    return '<img src="' . $url . '" class="' . esc_attr($class) . '" alt="' . $alt . '" loading="lazy" decoding="async" width="2172" height="724">';
}
add_filter('post_thumbnail_html', 'ruhrpott_guru_v225_activityspots_featured_image', 40, 5);

/**
 * Gutenberg Latest Posts can retain attachment markup independently of the
 * normal post-thumbnail filter. Replace only the matching Home card image so
 * the approved banner is guaranteed to appear immediately on the homepage.
 */
function ruhrpott_guru_v225_activityspots_home_latest_posts($block_content, $block) {
    if (
        !is_front_page()
        || empty($block['blockName'])
        || $block['blockName'] !== 'core/latest-posts'
        || stripos($block_content, 'Die 10 besten Aktivitätsspots im Revier') === false
    ) {
        return $block_content;
    }

    $banner = esc_url(ruhrpott_guru_v225_activityspots_banner_url());

    return preg_replace_callback(
        '~<li\b[^>]*>(.*?)</li>~is',
        function ($match) use ($banner) {
            $item = $match[0];
            if (stripos(wp_strip_all_tags($item), 'Die 10 besten Aktivitätsspots im Revier') === false) {
                return $item;
            }

            return preg_replace(
                '~<img\b[^>]*>~i',
                '<img src="' . esc_attr($banner) . '" class="wp-post-image rg-v225-activityspots-banner" alt="' . esc_attr__('Aktivitäten im Ruhrgebiet', 'ruhrpott-guru') . '" loading="lazy" decoding="async" width="2172" height="724">',
                $item,
                1
            );
        },
        $block_content
    );
}
add_filter('render_block', 'ruhrpott_guru_v225_activityspots_home_latest_posts', 45, 2);




/**
 * V226 – Semantic heading hierarchy on the Home without visual changes.
 *
 * - The page itself gets one H1 in front-page.php.
 * - Category module titles remain the established H2 headings.
 * - Editorial post teasers become H3 headings. Latest Posts normally renders
 *   its post title as a bare link, while Query Loop/Post Title defaults to H2.
 *   Normalize both variants to H3 only on the front page.
 */
function ruhrpott_guru_v226_home_editorial_heading_levels($parsed_block) {
    if (
        is_front_page()
        && !empty($parsed_block['blockName'])
        && $parsed_block['blockName'] === 'core/post-title'
    ) {
        if (empty($parsed_block['attrs']) || !is_array($parsed_block['attrs'])) {
            $parsed_block['attrs'] = array();
        }
        $parsed_block['attrs']['level'] = 3;
    }
    return $parsed_block;
}
add_filter('render_block_data', 'ruhrpott_guru_v226_home_editorial_heading_levels', 60, 1);

function ruhrpott_guru_v226_home_latest_posts_h3($block_content, $block) {
    if (
        !is_front_page()
        || empty($block['blockName'])
        || $block['blockName'] !== 'core/latest-posts'
        || stripos($block_content, 'wp-block-latest-posts__post-title') === false
    ) {
        return $block_content;
    }

    $pattern = <<<'RPG_REGEX'
~(<a\b(?=[^>]*class=["'][^"']*wp-block-latest-posts__post-title[^"']*["'])[^>]*>.*?</a>)~is
RPG_REGEX;

    return preg_replace(
        $pattern,
        '<h3 class="rg-home-editorial-title">$1</h3>',
        $block_content
    );
}
add_filter('render_block', 'ruhrpott_guru_v226_home_latest_posts_h3', 60, 2);

/**
 * Ruhrpott Guru Theme Functions & Definitions
 *
 * @package Ruhrpott_Guru
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once get_template_directory() . '/inc/rpg-pexels-secret.php';
require_once get_template_directory() . '/inc/rpg-et4-secret.php';
require_once get_template_directory() . '/inc/food-place-identity.php';


/**
 * V35 Category Web UX.
 * - Own-category overview cards use meaningful subcategory badges instead of redundant category badges.
 * - Events, Highlights and Culture show safe official web links on overview cards when already present in source data.
 * - Detail overlays resolve missing official web presence via source URL, Wikidata P856 and identity-matched eT4 Event/POI data.
 * - Food-only menu/reservation/price behavior is not copied to other categories.
 * - V70 scene filter disabled; Home OSM previews capped at 20 and fetched directly in the browser to bypass hosting egress failures.
 */

/**
 * V24 Food official web presence resolver.
 * - Food card website/menu spacing increased for mobile readability.
 * - Official web links resolve from OSM, Wikidata P856, destination.one/eT4 Gastro,
 *   strict identity-verified domain fallback, then official social profile.
 * - Search/review portals are never presented as the venue website.
 * - V23 overlay-close semantics and all Event/Share/Calendar behavior retained.
 */

/**
 * V23 Food detail overlay semantics correction.
 * - Detail view is an overlay, not a navigated subpage: top-right close control restored.
 * - V22 Food overview polish, friendlier price copy and lower technical sources retained.
 * Data/event/share behavior remains unchanged from V21.
 */


/**
 * V21 share-link migration.
 *
 * V20 used long query-string deep links and included a generic `category`
 * parameter. Depending on WordPress/plugin routing, an externally opened URL
 * could be canonicalized or reinterpreted before React consumed it. V21 uses
 * a compact fragment-only URL (#rg/...), which never reaches WordPress and
 * therefore cannot participate in server-side redirect loops.
 *
 * Keep this early redirect so links already shared from V20 continue to work.
 */
function ruhrpott_guru_migrate_legacy_share_link() {
    if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) return;
    if (!isset($_GET['rg_item']) && !isset($_GET['rg_title'])) return;

    $item = isset($_GET['rg_item']) ? sanitize_text_field(wp_unslash($_GET['rg_item'])) : '';
    if ($item === '' && isset($_GET['rg_title'])) {
        $item = sanitize_text_field(wp_unslash($_GET['rg_title']));
    }
    if ($item === '') return;

    $category = isset($_GET['rgc']) ? sanitize_key(wp_unslash($_GET['rgc'])) : '';
    if ($category === '' && isset($_GET['category'])) {
        $category = sanitize_key(wp_unslash($_GET['category']));
    }
    $category_map = array(
        'e' => 'e', 'events' => 'e', 'veranstaltungen' => 'e',
        'f' => 'f', 'food' => 'f', 'gastronomie' => 'f',
        'h' => 'h', 'highlights' => 'h', 'sehenswuerdigkeiten' => 'h',
        'c' => 'c', 'culture' => 'c', 'kultur' => 'c',
    );
    $code = isset($category_map[$category]) ? $category_map[$category] : 'a';

    $lat = isset($_GET['rg_lat']) ? (float) $_GET['rg_lat'] : NAN;
    $lng = isset($_GET['rg_lng']) ? (float) $_GET['rg_lng'] : NAN;
    if (isset($_GET['rgp'])) {
        $parts = explode(',', sanitize_text_field(wp_unslash($_GET['rgp'])), 2);
        if (count($parts) === 2) {
            $lat = (float) $parts[0];
            $lng = (float) $parts[1];
        }
    }
    $city = isset($_GET['rg_city']) ? sanitize_text_field(wp_unslash($_GET['rg_city'])) : '';
    if ($city === '' && isset($_GET['rgn'])) {
        $city = sanitize_text_field(wp_unslash($_GET['rgn']));
    }

    $fragment = '#rg/' . rawurlencode($code) . '/' . rawurlencode($item);
    if (is_finite($lat) && is_finite($lng) && abs($lat) <= 90 && abs($lng) <= 180) {
        $fragment .= '/' . number_format($lat, 5, '.', '') . '/' . number_format($lng, 5, '.', '');
        if ($city !== '') $fragment .= '/' . rawurlencode($city);
    }

    wp_safe_redirect(home_url('/') . $fragment, 302, 'RuhrpottGuru');
    exit;
}
add_action('template_redirect', 'ruhrpott_guru_migrate_legacy_share_link', 0);

/**
 * Food filter V2 is reversible without a code rollback.
 * Open any normal WordPress page once with ?rg_food_filter=off to show raw
 * Food data, or ?rg_food_filter=on to re-enable the filter. The setting is
 * stored in a same-origin cookie and therefore also reaches REST requests.
 */
function ruhrpott_guru_food_filter_toggle() {
    if (!isset($_GET['rg_food_filter'])) return;
    $mode = strtolower(trim((string) $_GET['rg_food_filter']));
    if (!in_array($mode, array('on', 'off'), true)) return;

    $path = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
    $domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
    setcookie('rg_food_filter', $mode, time() + 30 * DAY_IN_SECONDS, $path, $domain, is_ssl(), true);
    $_COOKIE['rg_food_filter'] = $mode;

    $target = remove_query_arg('rg_food_filter');
    wp_safe_redirect($target);
    exit;
}
add_action('template_redirect', 'ruhrpott_guru_food_filter_toggle', 1);

function ruhrpott_guru_food_filter_enabled() {
    return !isset($_COOKIE['rg_food_filter']) || strtolower((string) $_COOKIE['rg_food_filter']) !== 'off';
}

function ruhrpott_guru_prepare_profile_data($data, $query_profile) {
    if ($query_profile !== 'food-baseline') {
        return array('data' => $data, 'meta' => null);
    }
    return ruhrpott_guru_food_filter_data($data, ruhrpott_guru_food_filter_enabled());
}

/**
 * Apply the exact same Food Filter V2 to browser-direct Overpass fallback data.
 *
 * The mobile resilience layer can fetch Overpass directly when the server-side
 * transport is temporarily unavailable. Without this parity endpoint those
 * direct results bypassed the established Food identity filter and could
 * re-introduce locally confirmed closed/renamed/duplicate records.
 */
function ruhrpott_guru_register_food_filter_rest() {
    register_rest_route('ruhrpottguru/v1', '/food-filter', array(
        'methods' => 'POST',
        'callback' => 'ruhrpott_guru_food_filter_rest',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'ruhrpott_guru_register_food_filter_rest');

function ruhrpott_guru_food_filter_rest(WP_REST_Request $request) {
    $params = $request->get_json_params();
    $data = is_array($params) && isset($params['data']) && is_array($params['data']) ? $params['data'] : null;

    if (!is_array($data) || !isset($data['elements']) || !is_array($data['elements'])) {
        return new WP_REST_Response(array(
            'ok' => false,
            'errorType' => 'invalid-response',
            'message' => 'Food filter payload has no elements array',
        ), 400);
    }

    // This is a pure transform endpoint, but keep input bounded so it cannot
    // be abused as an unbounded O(n²) identity-resolution service.
    if (count($data['elements']) > 2000) {
        return new WP_REST_Response(array(
            'ok' => false,
            'errorType' => 'payload-too-large',
            'message' => 'Food filter payload exceeds 2000 elements',
        ), 413);
    }

    $prepared = ruhrpott_guru_food_filter_data($data, ruhrpott_guru_food_filter_enabled());
    return new WP_REST_Response(array(
        'ok' => true,
        'data' => $prepared['data'],
        'meta' => $prepared['meta'],
    ), 200);
}

/**
 * Theme Setup
 */
function ruhrpott_guru_setup() {
    // Add default posts and comments RSS feed links to head.
    add_theme_support('automatic-feed-links');

    // Let WordPress manage the document title.
    add_theme_support('title-tag');

    // Enable support for Post Thumbnails on posts and pages.
    add_theme_support('post-thumbnails');

    // Expose the standard WordPress logo control under Design -> Anpassen -> Website-Identität.
    // The React header reads this logo from the server-rendered DOM and falls back to
    // the built-in RuhrpottGuru wordmark when no custom logo is configured.
    add_theme_support('custom-logo', array(
        'height'               => 80,
        'width'                => 320,
        'flex-height'          => true,
        'flex-width'           => true,
        'unlink-homepage-logo' => true,
    ));

    // Register Navigation Menus
    register_nav_menus(array(
        'primary' => __('Hauptmenü (Header)', 'ruhrpott-guru'),
        'burger'  => __('Mobilmenü (Burger-Menü)', 'ruhrpott-guru'),
        'footer'  => __('Fußzeile (Footer)', 'ruhrpott-guru'),
    ));

    // Switch default core markup to output valid HTML5.
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ));

    // Support Block Editor / Gutenberg Styles for posts and pages
    add_theme_support('editor-styles');
    add_editor_style('style.css');
}
add_action('after_setup_theme', 'ruhrpott_guru_setup');



/**
 * V77 – Resolve the real WordPress category page by shortcode.
 *
 * The Events Calendar owns /events/ as a virtual archive. If the editor-created
 * RuhrpottGuru Events page also used the slug "events", WordPress could route
 * the menu click to the calendar archive before our page template ever ran.
 */
function ruhrpott_guru_find_page_with_shortcode($shortcode) {
    static $cache = array();
    $shortcode = sanitize_key($shortcode);
    if ($shortcode === '') return 0;
    if (isset($cache[$shortcode])) return (int) $cache[$shortcode];

    $ids = get_posts(array(
        'post_type'        => 'page',
        'post_status'      => array('publish', 'private', 'draft'),
        'posts_per_page'   => -1,
        'fields'           => 'ids',
        'orderby'          => 'ID',
        'order'            => 'ASC',
        'suppress_filters' => false,
    ));
    foreach ((array) $ids as $page_id) {
        $content = (string) get_post_field('post_content', $page_id);
        if (has_shortcode($content, $shortcode)) {
            $cache[$shortcode] = (int) $page_id;
            return (int) $page_id;
        }
    }
    $cache[$shortcode] = 0;
    return 0;
}

function ruhrpott_guru_migrate_events_category_slug_v77() {
    if (get_option('ruhrpott_guru_events_slug_v77') === 'done') return;

    $page_id = ruhrpott_guru_find_page_with_shortcode('ruhrpott_home_events');
    if (!$page_id) return;

    $page = get_post($page_id);
    if ($page && $page->post_type === 'page' && $page->post_name === 'events') {
        $target = 'veranstaltungen';
        $existing = get_page_by_path($target, OBJECT, 'page');
        if ($existing && (int) $existing->ID !== (int) $page_id) {
            $target = 'events-guide';
        }
        wp_update_post(array('ID' => $page_id, 'post_name' => $target));
        clean_post_cache($page_id);
    }

    update_option('ruhrpott_guru_events_slug_v77', 'done', false);
}
add_action('init', 'ruhrpott_guru_migrate_events_category_slug_v77', 30);

/**
 * Return the WordPress-managed footer category links for the React footer.
 *
 * The footer menu remains editable in WordPress. Category identity is derived
 * primarily from the shortcode on the linked page, then from conservative
 * slug/title fallbacks. The current RuhrpottGuru category pages are exposed to the React footer.
 *
 * @return array<int,array{title:string,url:string,category:string}>
 */
function ruhrpott_guru_get_footer_category_menu_items() {
    $locations = get_nav_menu_locations();
    if (empty($locations['footer'])) {
        return array();
    }

    $menu_items = wp_get_nav_menu_items($locations['footer']);
    if (empty($menu_items) || is_wp_error($menu_items)) {
        return array();
    }

    $shortcode_map = array(
        // Current WordPress category pages.
        'ruhrpott_home_events'     => 'events',
        'ruhrpott_home_food'       => 'food',
        'ruhrpott_home_highlights' => 'highlights',
        'ruhrpott_home_kultur'     => 'culture',
        'ruhrpott_home_aktiv'      => 'activities',
        // Legacy aliases stay readable during the migration.
        'ruhrpott_veranstaltungen' => 'events',
        'ruhrpott_gastronomie'     => 'food',
        'ruhrpott_highlights'      => 'highlights',
        'ruhrpott_kultur'          => 'culture',
        'ruhrpott_aktivitaeten'    => 'activities',
    );

    $result        = array();
    $front_page_id = (int) get_option('page_on_front');
    $home_url      = trailingslashit(home_url('/'));
    $seen          = array();

    foreach ($menu_items as $item) {
        $category = '';
        $slug     = '';
        $page_id  = 0;

        if (!empty($item->object_id) && $item->object === 'page') {
            $page_id = (int) $item->object_id;
            $slug    = (string) get_post_field('post_name', $page_id);

            // V79: The assigned WordPress front page is a real navigation item,
            // not a category. The Home page may contain all five Home shortcodes,
            // so shortcode inspection must never classify it as Events/Food/etc.
            if ($front_page_id && $page_id === $front_page_id) {
                $category = 'home';
            } else {
                $content = (string) get_post_field('post_content', $page_id);
                foreach ($shortcode_map as $shortcode => $key) {
                    if (has_shortcode($content, $shortcode)) {
                        $category = $key;
                        break;
                    }
                }
            }
        }

        if ($category === '') {
            $item_url = !empty($item->url) ? trailingslashit((string) $item->url) : '';
            if ($item_url !== '' && untrailingslashit($item_url) === untrailingslashit($home_url)) {
                $category = 'home';
            }
        }

        if ($category === '') {
            $needle = strtolower(remove_accents(trim($slug . ' ' . wp_strip_all_tags($item->title))));
            if (preg_match('/\b(events?|veranstaltungen?)\b/', $needle)) {
                $category = 'events';
            } elseif (preg_match('/\b(food|drinks?|gastronomie|essen|trinken)\b/', $needle)) {
                $category = 'food';
            } elseif (preg_match('/\b(highlights?|sehenswuerdigkeiten?|sehenswurdigkeiten?)\b/', $needle)) {
                $category = 'highlights';
            } elseif (preg_match('/\b(kultur|szene|culture)\b/', $needle)) {
                $category = 'culture';
            } elseif (preg_match('/\b(aktiv|aktivitaeten|activities|sport|freizeit)\b/', $needle)) {
                $category = 'activities';
            }
        }

        // The retired Start/Start-Test pages are no longer part of mobile nav.
        if ($category !== 'home' && in_array(sanitize_title($slug), array('start', 'start-test'), true)) {
            continue;
        }

        if (!in_array($category, array('home', 'events', 'food', 'highlights', 'culture', 'activities'), true)) {
            continue;
        }

        // One tab per logical destination. This also protects against old duplicate
        // menu entries while the WordPress menu is being cleaned up.
        if (isset($seen[$category])) {
            continue;
        }
        $seen[$category] = true;

        $resolved_url = $item->url;
        if ($category === 'home') {
            $resolved_url = home_url('/');
        } elseif ($category === 'events') {
            $event_page_id = ruhrpott_guru_find_page_with_shortcode('ruhrpott_home_events');
            if ($event_page_id) {
                $event_permalink = get_permalink($event_page_id);
                if ($event_permalink) $resolved_url = $event_permalink;
            }
        }

        $result[] = array(
            'title'    => $category === 'home' ? 'Home' : ($category === 'activities' ? 'Aktiv' : wp_strip_all_tags($item->title)),
            'url'      => esc_url_raw($resolved_url),
            'category' => $category,
        );
    }

    return array_values($result);
}

/**
 * Ensure proper Permissions-Policy for Geolocation (self only, never blocked with geolocation=())
 */
function ruhrpott_guru_security_headers() {
    if (!headers_sent()) {
        header('Permissions-Policy: geolocation=(self)');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Content-Type-Options: nosniff');
    }
}
add_action('send_headers', 'ruhrpott_guru_security_headers');

/**
 * V242 – Reversible, windowed event-card media diversity.
 *
 * This is intentionally display-only: exact/original event media and the stored
 * rpg_event_media assignment are never rewritten. The switch can be disabled
 * from RP Redaktion -> Event-Bilder to restore the v240 card behaviour instantly.
 */
function rpg_v241_event_media_diversity_enabled() {
    return get_option('rpg_event_media_diversity_v241', '1') !== '0';
}

/**
 * V243 – Reversible event-type scan badges on event cards.
 * Pure presentation: no event classification is written back to the database.
 */
function rpg_v243_event_type_badges_enabled() {
    return get_option('rpg_event_type_badges_v243', '1') !== '0';
}

/**
 * V264 — postcode location resolver.
 *
 * The browser never talks to the public geocoder directly. A five-digit German
 * postcode is resolved server-side to one stable coordinate, cached, and then
 * handed to the existing lat/lng + radius pipeline exactly like GPS or a city
 * centre. This keeps every category loader and ranking path unchanged.
 */
function ruhrpott_guru_normalize_city_key_v322($value) {
    $value = trim((string) $value);
    if ($value === '') return '';
    $value = remove_accents(mb_strtolower($value, 'UTF-8'));
    $value = str_replace('ß', 'ss', $value);
    return preg_replace('/[^a-z0-9]+/', '', $value);
}

function ruhrpott_guru_match_metropole_city_v322($value) {
    $key = ruhrpott_guru_normalize_city_key_v322($value);
    if ($key === '') return '';
    $aliases = array(
        'frondenberg' => 'Fröndenberg/Ruhr', 'frondenbergruhr' => 'Fröndenberg/Ruhr',
        'froendenberg' => 'Fröndenberg/Ruhr', 'froendenbergruhr' => 'Fröndenberg/Ruhr',
        'voerde' => 'Voerde (Niederrhein)', 'voerdeniederrhein' => 'Voerde (Niederrhein)',
        'wetter' => 'Wetter (Ruhr)', 'wetterruhr' => 'Wetter (Ruhr)',
        'mulheim' => 'Mülheim an der Ruhr', 'mulheimruhr' => 'Mülheim an der Ruhr', 'mulheimanderruhr' => 'Mülheim an der Ruhr',
        'muelheim' => 'Mülheim an der Ruhr', 'muelheimruhr' => 'Mülheim an der Ruhr', 'muelheimanderruhr' => 'Mülheim an der Ruhr'
    );
    if (isset($aliases[$key])) return $aliases[$key];
    foreach (ruhrpott_guru_event_municipalities_v114() as $city) {
        if (ruhrpott_guru_normalize_city_key_v322($city) === $key) return $city;
    }
    return '';
}

function ruhrpott_guru_nominatim_metropole_city_v322($address) {
    if (!is_array($address)) return '';
    foreach (array('city','town','village','municipality','suburb') as $field) {
        if (!empty($address[$field])) {
            $city = ruhrpott_guru_match_metropole_city_v322($address[$field]);
            if ($city !== '') return $city;
        }
    }
    return '';
}

function ruhrpott_guru_register_postcode_location_rest() {
    register_rest_route('ruhrpottguru/v1', '/postcode-location', array(
        'methods' => 'POST',
        'callback' => 'ruhrpott_guru_postcode_location_rest',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'ruhrpott_guru_register_postcode_location_rest');

function ruhrpott_guru_postcode_location_rest(WP_REST_Request $request) {
    $params = $request->get_json_params();
    if (!is_array($params)) $params = array();

    $postcode = isset($params['postcode']) ? preg_replace('/\\D+/', '', (string) $params['postcode']) : '';
    if (!preg_match('/^\\d{5}$/', $postcode)) {
        return new WP_REST_Response(array(
            'ok' => false,
            'errorType' => 'invalid-postcode',
            'message' => 'Bitte gib eine gültige 5-stellige PLZ ein.',
        ), 400);
    }

    $cache_key = 'rg_postcode_location_v322_' . $postcode;
    $cached = get_transient($cache_key);
    if (is_array($cached) && !empty($cached['ok'])) {
        $cached['cache'] = 'HIT';
        return new WP_REST_Response($cached, 200);
    }

    if (get_transient('rg_postcode_location_miss_v322_' . $postcode)) {
        return new WP_REST_Response(array(
            'ok' => false,
            'errorType' => 'postcode-not-found',
            'message' => 'Für diese PLZ wurde im PottFuchs-Gebiet kein Mittelpunkt gefunden.',
        ), 404);
    }

    $query = array(
        'postalcode' => $postcode,
        'countrycodes' => 'de',
        'format' => 'jsonv2',
        'addressdetails' => 1,
        'limit' => 5,
    );
    $url = add_query_arg($query, 'https://nominatim.openstreetmap.org/search');

    // Public Nominatim asks clients to stay below one request per second. The
    // postcode cache means this path is normally hit only once per PLZ; the
    // tiny global spacing below protects concurrent cold lookups as well.
    $last_call = (float) get_transient('rg_postcode_nominatim_last_v264');
    $now = microtime(true);
    if ($last_call > 0 && ($now - $last_call) < 1.05) {
        $wait_us = (int) ceil((1.05 - ($now - $last_call)) * 1000000);
        if ($wait_us > 0 && $wait_us <= 1100000) usleep($wait_us);
    }
    set_transient('rg_postcode_nominatim_last_v264', microtime(true), 10);

    $response = wp_remote_get($url, array(
        'timeout' => 8,
        'redirection' => 2,
        'user-agent' => 'RuhrpottGuru/264 (+https://ruhrpottguru.de)',
        'headers' => array(
            'Accept' => 'application/json',
            'Accept-Language' => 'de',
            'Referer' => home_url('/'),
        ),
    ));
    if (is_wp_error($response)) {
        return new WP_REST_Response(array(
            'ok' => false,
            'errorType' => 'postcode-provider-error',
            'message' => 'Die PLZ konnte gerade nicht aufgelöst werden. Bitte versuche es noch einmal.',
        ), 503);
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    $rows = json_decode(wp_remote_retrieve_body($response), true);
    if ($status < 200 || $status >= 300 || !is_array($rows)) {
        return new WP_REST_Response(array(
            'ok' => false,
            'errorType' => 'postcode-provider-error',
            'message' => 'Die PLZ konnte gerade nicht aufgelöst werden. Bitte versuche es noch einmal.',
        ), 503);
    }

    $best = null;
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $lat = isset($row['lat']) ? (float) $row['lat'] : NAN;
        $lng = isset($row['lon']) ? (float) $row['lon'] : NAN;
        if (!is_finite($lat) || !is_finite($lng)) continue;
        // Keep the same broad RuhrpottGuru service envelope already used by
        // the existing OSM loaders. The postcode path must not create a second
        // geographic policy with different behaviour.
        if ($lat < 50.3 || $lat > 52.5 || $lng < 5.2 || $lng > 9.3) continue;

        $address = isset($row['address']) && is_array($row['address']) ? $row['address'] : array();
        $row_postcode = isset($address['postcode']) ? preg_replace('/\\D+/', '', (string) $address['postcode']) : '';
        $metropole_city = ruhrpott_guru_nominatim_metropole_city_v322($address);
        if ($metropole_city === '') continue;
        $candidate = array(
            'lat' => $lat,
            'lng' => $lng,
            'city' => $metropole_city,
            'exact' => ($row_postcode === $postcode),
        );
        if ($best === null || (!$best['exact'] && $candidate['exact'])) $best = $candidate;
        if ($candidate['exact']) break;
    }

    if ($best === null) {
        set_transient('rg_postcode_location_miss_v322_' . $postcode, 1, 6 * HOUR_IN_SECONDS);
        return new WP_REST_Response(array(
            'ok' => false,
            'errorType' => 'postcode-not-found',
            'message' => 'Für diese PLZ wurde im PottFuchs-Gebiet kein Mittelpunkt gefunden.',
        ), 404);
    }

    $payload = array(
        'ok' => true,
        'postcode' => $postcode,
        'lat' => round((float) $best['lat'], 7),
        'lng' => round((float) $best['lng'], 7),
        'city' => (string) ($best['city'] ?? ''),
        'source' => 'nominatim-osm',
        'attribution' => '© OpenStreetMap-Mitwirkende',
        'cache' => 'MISS',
    );
    set_transient($cache_key, $payload, 90 * DAY_IN_SECONDS);
    return new WP_REST_Response($payload, 200);
}


/**
 * V326 — server-side Geoapify address resolver.
 *
 * The browser sends only the user-entered address parts to RuhrpottGuru. The
 * provider key remains server-side in the non-autoloaded option
 * `rpg_geoapify_api_key`. No Geoapify script, cookie or map is loaded in the
 * visitor's browser. The EU-focused Geoapify endpoint is used deliberately.
 */
function ruhrpott_guru_register_address_location_rest_v326() {
    register_rest_route('ruhrpottguru/v1', '/address-location', array(
        'methods' => 'POST',
        'callback' => 'ruhrpott_guru_address_location_rest_v326',
        'permission_callback' => '__return_true',
    ));
    // Refresh the public visitor nonce outside cacheable HTML. This endpoint
    // returns no address data and never calls the geocoding provider.
    register_rest_route('ruhrpottguru/v1', '/address-location-token', array(
        'methods' => 'GET',
        'callback' => 'ruhrpott_guru_address_location_token_rest_v347',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'ruhrpott_guru_register_address_location_rest_v326');

/** A fresh, uncached address nonce for visitors of cacheable WP pages. */
function ruhrpott_guru_address_location_token_rest_v347() {
    $response = new WP_REST_Response(array(
        'ok' => true,
        'token' => wp_create_nonce('rpg_address_lookup_v326'),
    ), 200);
    $response->header('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
    $response->header('Pragma', 'no-cache');
    $response->header('Expires', '0');
    return $response;
}

function ruhrpott_guru_clean_address_part_v326($value, $max = 120) {
    $value = sanitize_text_field((string) $value);
    $value = preg_replace('/\s+/u', ' ', trim($value));
    if (function_exists('mb_substr')) return mb_substr($value, 0, $max, 'UTF-8');
    return substr($value, 0, $max);
}

function ruhrpott_guru_geoapify_result_city_v326($row) {
    if (!is_array($row)) return '';
    foreach (array('city','municipality','town','village') as $field) {
        if (!empty($row[$field])) {
            $city = ruhrpott_guru_match_metropole_city_v322($row[$field]);
            if ($city !== '') return $city;
        }
    }
    return '';
}

function ruhrpott_guru_address_location_rest_v326(WP_REST_Request $request) {
    $token = (string) $request->get_header('X-RPG-Address-Token');
    if ($token === '' || !wp_verify_nonce($token, 'rpg_address_lookup_v326')) {
        return new WP_REST_Response(array('ok'=>false,'errorType'=>'invalid-request','message'=>'Die Adresssuche konnte nicht gestartet werden. Bitte lade die Seite neu und versuche es noch einmal.'), 403);
    }

    $params = $request->get_json_params();
    if (!is_array($params)) $params = array();

    $street = ruhrpott_guru_clean_address_part_v326($params['street'] ?? '', 120);
    $house = ruhrpott_guru_clean_address_part_v326($params['housenumber'] ?? '', 30);
    $postcode = preg_replace('/\D+/', '', (string) ($params['postcode'] ?? ''));
    $city_input = ruhrpott_guru_clean_address_part_v326($params['city'] ?? '', 100);

    if ($street === '') {
        return new WP_REST_Response(array('ok'=>false,'errorType'=>'street-required','message'=>'Für eine Adresssuche gib bitte eine Straße ein.'), 400);
    }
    if ($postcode !== '' && !preg_match('/^\d{5}$/', $postcode)) {
        return new WP_REST_Response(array('ok'=>false,'errorType'=>'invalid-postcode','message'=>'Bitte gib eine gültige 5-stellige PLZ ein.'), 400);
    }
    if ($postcode === '' && $city_input === '') {
        return new WP_REST_Response(array('ok'=>false,'errorType'=>'city-or-postcode-required','message'=>'Bitte ergänze zur Straße eine PLZ oder einen Ort.'), 400);
    }

    $expected_city = $city_input !== '' ? ruhrpott_guru_match_metropole_city_v322($city_input) : '';
    if ($city_input !== '' && $expected_city === '') {
        return new WP_REST_Response(array('ok'=>false,'errorType'=>'outside-area','message'=>'Diese Adresse liegt außerhalb des PottFuchs-Gebiets. Bitte wähle eine Adresse innerhalb der Metropole Ruhr.'), 400);
    }

    $api_key = trim((string) get_option('rpg_geoapify_api_key', ''));
    if ($api_key === '') {
        return new WP_REST_Response(array('ok'=>false,'errorType'=>'provider-not-configured','message'=>'Die Adresssuche ist gerade nicht verfügbar.'), 503);
    }

    $query = array(
        'street' => $street,
        'format' => 'json',
        'limit' => 5,
        'lang' => 'de',
        'filter' => 'countrycode:de',
        'apiKey' => $api_key,
    );
    if ($house !== '') $query['housenumber'] = $house;
    if ($postcode !== '') $query['postcode'] = $postcode;
    if ($expected_city !== '') $query['city'] = $expected_city;
    elseif ($city_input !== '') $query['city'] = $city_input;

    $url = add_query_arg($query, 'https://api-eu.geoapify.com/v1/geocode/search');
    $response = wp_remote_get($url, array(
        'timeout' => 8,
        'redirection' => 2,
        'user-agent' => 'RuhrpottGuru/326 (+https://ruhrpottguru.de)',
        'headers' => array('Accept'=>'application/json','Accept-Language'=>'de'),
    ));

    if (is_wp_error($response)) {
        return new WP_REST_Response(array('ok'=>false,'errorType'=>'provider-error','message'=>'Die Adresse konnte gerade nicht aufgelöst werden. Bitte versuche es noch einmal.'), 503);
    }
    $status = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    $rows = is_array($body) && isset($body['results']) && is_array($body['results']) ? $body['results'] : array();
    if ($status < 200 || $status >= 300) {
        return new WP_REST_Response(array('ok'=>false,'errorType'=>'provider-error','message'=>'Die Adresse konnte gerade nicht aufgelöst werden. Bitte versuche es noch einmal.'), 503);
    }

    $best = null;
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $lat = isset($row['lat']) ? (float) $row['lat'] : NAN;
        $lng = isset($row['lon']) ? (float) $row['lon'] : NAN;
        if (!is_finite($lat) || !is_finite($lng)) continue;

        $result_city = ruhrpott_guru_geoapify_result_city_v326($row);
        if ($result_city === '') continue;
        if ($expected_city !== '' && $result_city !== $expected_city) continue;

        $result_postcode = isset($row['postcode']) ? preg_replace('/\D+/', '', (string) $row['postcode']) : '';
        if ($postcode !== '' && $result_postcode !== '' && $result_postcode !== $postcode) continue;

        $score = 0;
        if ($expected_city !== '' && $result_city === $expected_city) $score += 10;
        if ($postcode !== '' && $result_postcode === $postcode) $score += 8;
        if ($house !== '' && isset($row['housenumber']) && trim((string)$row['housenumber']) === $house) $score += 6;
        if (!empty($row['street']) && ruhrpott_guru_normalize_city_key_v322($row['street']) === ruhrpott_guru_normalize_city_key_v322($street)) $score += 4;
        if (isset($row['rank']['confidence'])) $score += max(0, min(3, (float)$row['rank']['confidence'] * 3));

        $candidate = array('row'=>$row,'lat'=>$lat,'lng'=>$lng,'city'=>$result_city,'postcode'=>$result_postcode,'score'=>$score);
        if ($best === null || $candidate['score'] > $best['score']) $best = $candidate;
    }

    if ($best === null) {
        return new WP_REST_Response(array('ok'=>false,'errorType'=>'not-found','message'=>'Die Adresse konnte nicht eindeutig gefunden werden. Bitte prüfe Straße, Hausnummer, PLZ und Ort.'), 404);
    }

    $row = $best['row'];
    $result_street = !empty($row['street']) ? sanitize_text_field((string)$row['street']) : $street;
    $result_house = !empty($row['housenumber']) ? sanitize_text_field((string)$row['housenumber']) : $house;
    $result_postcode = $best['postcode'] !== '' ? $best['postcode'] : $postcode;
    $label = trim($result_street . ($result_house !== '' ? ' ' . $result_house : ''));
    $label .= ($label !== '' ? ' · ' : '') . trim($result_postcode . ' ' . $best['city']);

    $payload = array(
        'ok' => true,
        'lat' => round((float)$best['lat'], 7),
        'lng' => round((float)$best['lng'], 7),
        'city' => $best['city'],
        'postcode' => $result_postcode,
        'street' => $result_street,
        'housenumber' => $result_house,
        'label' => $label,
        'source' => 'geoapify-eu',
    );
    return new WP_REST_Response($payload, 200);
}


// V310: canonical detail head-context hardening.
//
// The Places backend renders successful /ort/ and /veranstaltung/ detail URLs
// through the active theme's direct-detail shortcode and then calls wp_head().
// WordPress still considers those rewrite-less virtual requests a 404 internally,
// which caused Core to inject a second, contradictory 404 <title>. The backend
// has already resolved the record and sent HTTP 200 before wp_head() runs, so at
// this exact point it is safe to clear only the stale 404 query flag and suppress
// Core's duplicate title tag. The backend remains the single SEO source for the
// canonical title, description, canonical URL, Open Graph, Twitter and JSON-LD.
// Missing/non-public records never call wp_head() and therefore remain real 404s.
function rpg_v310_prepare_canonical_detail_head() {
    if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) return;
    if (!class_exists('RuhrpottGuru_Places_Backend') || !shortcode_exists('ruhrpott_app')) return;

    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $path = trim((string) wp_parse_url($request_uri, PHP_URL_PATH), '/');
    if (!preg_match('~^(?:ort|veranstaltung)/\d+/[^/]+/[^/]+/?$~i', $path)) return;

    global $wp_query;
    if ($wp_query instanceof WP_Query && is_404()) {
        $wp_query->is_404 = false;
    }

    // The successful backend detail renderer already emitted the one canonical
    // title before invoking wp_head(). Prevent WordPress Core from adding another.
    remove_action('wp_head', '_wp_render_title_tag', 1);
    remove_action('wp_head', '_block_template_render_title_tag', 1);
}
add_action('wp_head', 'rpg_v310_prepare_canonical_detail_head', 0);

// V310: deterministic descriptions for Home and the five canonical category pages.
function rpg_v310_page_meta_description() {
    if (is_admin()) return;

    if (is_front_page()) {
        echo "\n<meta name=\"description\" content=\"" . esc_attr('PottFuchs zeigt dir Events, Food, Sehenswürdigkeiten, Kultur und Aktivitäten im Ruhrgebiet – passend zu deinem Standort und Radius.') . "\">\n";
        return;
    }

    if (!is_page()) return;
    $slug = (string) get_post_field('post_name', get_queried_object_id());
    $descriptions = array(
        'veranstaltungen' => 'Events im Ruhrgebiet: aktuelle Veranstaltungen, Termine und Tipps passend zu deinem Standort und Radius.',
        'food' => 'Restaurants, Cafés, Imbisse, Bars und besondere Food-Spots im Ruhrgebiet – passend zu deinem Standort und Radius.',
        'highlights' => 'Sehenswürdigkeiten und besondere Orte im Ruhrgebiet entdecken – sortiert nach Entfernung rund um deinen Standort.',
        'kultur' => 'Kultur im Ruhrgebiet entdecken: Museen, Theater, Ausstellungen und Kulturorte passend zu deinem Standort.',
        'aktiv' => 'Freizeit und Aktivitäten im Ruhrgebiet entdecken – von Outdoor-Zielen bis zu besonderen Erlebnissen in deiner Nähe.',
    );
    if (!isset($descriptions[$slug])) return;
    echo "\n<meta name=\"description\" content=\"" . esc_attr($descriptions[$slug]) . "\">\n";
}
add_action('wp_head', 'rpg_v310_page_meta_description', 2);

/**
 * Enqueue Scripts and Styles
 */
function ruhrpott_guru_scripts() {
    // V98: RuhrpottGuru keeps its own readable React source, renders free Gutenberg Home copy in place and restores editorial inserts. React itself
    // comes from WordPress core via wp-element; there is no AI-Studio bundle.
    wp_enqueue_style(
        'ruhrpott-guru-style',
        get_stylesheet_uri(),
        array(),
        file_exists(get_stylesheet_directory() . '/style.css') ? filemtime(get_stylesheet_directory() . '/style.css') : time()
    );

    // Keep the existing visual base while the new source-owned UI progressively
    // moves styles into rpg-react-source.css. No legacy JS is executed.
    $base_css = get_template_directory() . '/css/ruhrpott-app.css';
    if (file_exists($base_css)) {
        wp_enqueue_style('ruhrpott-visual-base', get_template_directory_uri() . '/css/ruhrpott-app.css', array(), filemtime($base_css));
    }
    $react_css = get_template_directory() . '/css/rpg-react-source.css';
    wp_enqueue_style('ruhrpott-react-source', get_template_directory_uri() . '/css/rpg-react-source.css', array('ruhrpott-guru-style'), file_exists($react_css) ? filemtime($react_css) : time());

    // Burger drawer still uses Lucide markup from footer.php. This is an icon
    // library only and is unrelated to the former AI-Studio application.
    $icons_file = get_template_directory() . '/js/rpg-icons-local.js';
    wp_enqueue_script(
        'rpg-icons-local',
        get_template_directory_uri() . '/js/rpg-icons-local.js',
        array(),
        file_exists($icons_file) ? filemtime($icons_file) : '113',
        true
    );

    $src = get_template_directory() . '/src/react-app/';
    $uri = get_template_directory_uri() . '/src/react-app/';
    $version = function($file) use ($src) {
        return file_exists($src . $file) ? filemtime($src . $file) : time();
    };

    wp_enqueue_script('rpg-react-runtime', $uri . 'runtime.js', array('wp-element'), $version('runtime.js'), true);
    wp_enqueue_script('rpg-react-api', $uri . 'api.js', array('rpg-react-runtime'), $version('api.js'), true);
    wp_enqueue_script('rpg-react-location', $uri . 'location.js', array('rpg-react-runtime','rpg-react-api'), $version('location.js'), true);
    wp_enqueue_script('rpg-react-components', $uri . 'components.js', array('rpg-react-runtime','rpg-react-api'), $version('components.js'), true);
    wp_enqueue_script('rpg-react-place-events', $uri . 'place-events.js', array('rpg-react-components'), $version('place-events.js'), true);
    wp_enqueue_script('rpg-react-views', $uri . 'views.js', array('rpg-react-place-events','rpg-react-location'), $version('views.js'), true);

    // Preserve this historic handle name deliberately: Places Backend uses it
    // only as an attachment point for its cache-reset inline script. The file
    // behind the handle is now our readable source entry point.
    wp_enqueue_script('ruhrpott-compiled-js', $uri . 'main.js', array('rpg-react-views'), $version('main.js'), true);

    $ruhrpott_runtime_settings = array(
        'root' => esc_url_raw(rest_url()),
        'homeUrl' => esc_url_raw(home_url('/')),
        'industrialRoutePageUrl' => esc_url_raw(home_url('/route-industriekultur/')),
        'buedchenRadarUrl' => esc_url_raw(rest_url('ruhrpottguru/v1/places/buedchen')),
        'buedchenSubmitUrl' => esc_url_raw(rest_url('ruhrpottguru/v1/places/buedchen/submit')),
        'placeContributionUrl' => esc_url_raw(rest_url('ruhrpottguru/v1/places/contribute')),
        'communityStorySubmitUrl' => esc_url_raw(rest_url('ruhrpottguru/v1/places/community-story')),
        'communityStoriesBaseUrl' => esc_url_raw(rest_url('ruhrpottguru/v1/places/')),
        'buedchenTileBaseUrl' => esc_url_raw(admin_url('admin-ajax.php?action=rg_places_map_tile')),
        'buedchenPageUrl' => esc_url_raw(home_url('/buedchen-radar/')),
        'wurstUrl' => esc_url_raw(rest_url('ruhrpottguru/v1/places/wurst')),
        'wurstSubmitUrl' => esc_url_raw(rest_url('ruhrpottguru/v1/places/wurst/submit')),
        'wurstAjaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
        'wurstPageUrl' => esc_url_raw(home_url('/imbissbuden/')),
        'pottQuizPageUrl' => esc_url_raw(home_url('/pott-quiz/')),
        'halloweenPageUrl' => esc_url_raw(home_url('/halloween-im-pott/')),
        'martinPageUrl' => esc_url_raw(home_url('/martinszuege-im-pott/')),
        'cityScopeCities' => ruhrpott_guru_city_scope_cities(),
        'nightUrl' => esc_url_raw(rest_url('ruhrpottguru/v1/places/night')),
        'nightSubmitUrl' => esc_url_raw(rest_url('ruhrpottguru/v1/places/night/submit')),
        'nightPageUrl' => esc_url_raw(home_url('/unser-pott-bei-nacht/')),
        'nonce' => wp_create_nonce('wp_rest'),
        'postcodeLocationUrl' => esc_url_raw(rest_url('ruhrpottguru/v1/postcode-location')),
        'addressLocationUrl' => esc_url_raw(rest_url('ruhrpottguru/v1/address-location')),
        'addressLocationTokenUrl' => esc_url_raw(rest_url('ruhrpottguru/v1/address-location-token')),
        'overpassProxyUrl' => esc_url_raw(rest_url('ruhrpottguru/v1/overpass')),
        'osmPoolUrl' => esc_url_raw(rest_url('ruhrpottguru/v1/osm-pool')),
        'osmTransportVersion' => 'source-react-v98',
        'eventDataUrl' => esc_url_raw(rest_url('ruhrpottguru/v1/events')),
        'eventDataVersion' => 'v23-home-10km-original-media',
        'eventMediaUrl' => esc_url_raw(rest_url('ruhrpottguru/v1/event-media')),
        'eventMediaDiversityUrl' => esc_url_raw(rest_url('ruhrpottguru/v1/event-media-diversity')),
        'eventMediaDiversityEnabled' => rpg_v241_event_media_diversity_enabled(),
        'eventMediaDiversityVersion' => 'v298-semantic-fallback',
        'eventTypeBadgesEnabled' => rpg_v243_event_type_badges_enabled(),
        'eventTypeBadgesVersion' => 'v243-structured-scan-badges',
        'globalSearchUrl' => esc_url_raw(rest_url('ruhrpottguru/v1/global-search')),
        'globalSearchPageUrl' => esc_url_raw(home_url('/suche/')),
        'eventMediaVersion' => 'v263-event-direct-e2e-parity',
        'foodFilterEnabled' => ruhrpott_guru_food_filter_enabled(),
        'foodFilterUrl' => esc_url_raw(rest_url('ruhrpottguru/v1/food-filter')),
        'foodFilterVersion' => 'v2-direct-parity-v1',
        'foodDistanceVersion' => 'v2-display-rounded-10m',
        'foodMediaUrl' => esc_url_raw(rest_url('ruhrpottguru/v1/food-media')),
        'foodInfoUrl' => esc_url_raw(rest_url('ruhrpottguru/v1/food-info')),
        'foodInfoVersion' => 'v3-official-food-facets',
        'placeInfoUrl' => esc_url_raw(rest_url('ruhrpottguru/v1/place-info')),
        'placeInfoVersion' => 'v1-events-poi-official-website',
        'foodMediaVersion' => 'v11-original-place-media',
        'pexelsSymbolicEnabled' => ruhrpott_guru_get_pexels_api_key() !== '',
        'reactSourceVersion' => 'v299-halloween-full-radius-dedupe',
    );
    $ruhrpott_runtime_settings = apply_filters('ruhrpott_runtime_settings', $ruhrpott_runtime_settings);
    wp_localize_script('rpg-react-runtime', 'ruhrpottSettings', $ruhrpott_runtime_settings);

    $food_cache_salt = 'source-react-v97-' . (ruhrpott_guru_food_filter_enabled() ? 'food-on' : 'food-off');
    $cache_reset_js = '(function(){try{var k="rg_source_react_cache_salt",v=' . wp_json_encode($food_cache_salt) . ',p=localStorage.getItem(k);if(p!==v){for(var i=sessionStorage.length-1;i>=0;i--){var n=sessionStorage.key(i)||"";if(n.indexOf("rg_snap_")===0||n.indexOf("ruhrpott_osm_")===0||n.indexOf("rg_home_pool_")===0){sessionStorage.removeItem(n);}}localStorage.setItem(k,v);}}catch(e){}})();';
    wp_add_inline_script('ruhrpott-compiled-js', $cache_reset_js, 'before');
}
add_action('wp_enqueue_scripts', 'ruhrpott_guru_scripts');


/**
 * RuhrpottGuru OSM Acquisition Pool V2.
 *
 * The handover UI/bundle stays unchanged. Legacy browser queries are accepted
 * only as an intent signal, parsed into one of Food/Highlights/Culture and then
 * translated into fixed server-owned Overpass templates.
 *
 * 5 km and smaller requests share ONE combined upstream pool. Only genuine
 * empty results may trigger the existing client radius escalation. Provider
 * errors stay provider errors. Empty responses are cached briefly.
 */
function ruhrpott_guru_register_overpass_proxy() {
    register_rest_route('ruhrpottguru/v1', '/osm-pool', array(
        'methods' => 'POST',
        'callback' => 'ruhrpott_guru_osm_pool',
        'permission_callback' => '__return_true',
    ));

    // Highlights is intentionally isolated from Food/Culture. It has its own
    // compact acquisition query and cache so an empty/slow Highlights lookup
    // can never destabilize the already-working Food transport.
    register_rest_route('ruhrpottguru/v1', '/highlights-osm', array(
        'methods' => 'POST',
        'callback' => 'ruhrpott_guru_highlights_osm',
        'permission_callback' => '__return_true',
    ));


    // V69: lightweight Home-only OSM preview. This endpoint is deliberately
    // separate from the full category loader: one category, broad bounded candidate pool,
    // no scene scoring and no client radius cascade. Full category pages keep
    // using the established canonical loader and can fetch additional results.
    register_rest_route('ruhrpottguru/v1', '/home-osm-preview', array(
        'methods' => 'POST',
        'callback' => 'ruhrpott_guru_home_osm_preview',
        'permission_callback' => '__return_true',
    ));

    $compat = array(
        'methods' => 'POST',
        'callback' => 'ruhrpott_guru_overpass_compat',
        'permission_callback' => '__return_true',
    );
    foreach (array('v1', 'v2', 'v3', 'v4', 'v5') as $version) {
        register_rest_route('ruhrpottguru/' . $version, '/overpass', $compat);
    }

    // Same-origin raw compatibility targets for the historic bundle's direct
    // mirror fallback. The bundle URLs are rewritten to these endpoints, so
    // mobile browsers never call public Overpass hosts directly.
    $raw_compat = array(
        'methods' => 'POST',
        'callback' => 'ruhrpott_guru_overpass_raw_compat',
        'permission_callback' => '__return_true',
    );
    register_rest_route('ruhrpottguru/v1', '/overpass-direct-1', $raw_compat);
    register_rest_route('ruhrpottguru/v1', '/overpass-direct-2', $raw_compat);
    register_rest_route('ruhrpottguru/v1', '/overpass-diagnostic', array(
        'methods' => 'POST',
        'callback' => 'ruhrpott_guru_overpass_diagnostic',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'ruhrpott_guru_register_overpass_proxy');

function ruhrpott_guru_osm_allowed_categories() {
    return array('food', 'highlights', 'culture', 'activities');
}

function ruhrpott_guru_osm_normalize_categories($raw_categories) {
    $allowed = ruhrpott_guru_osm_allowed_categories();
    $categories = is_array($raw_categories) ? $raw_categories : array();
    $normalized = array();
    foreach ($categories as $category) {
        $category = sanitize_key((string) $category);
        if (in_array($category, $allowed, true)) {
            $normalized[$category] = true;
        }
    }
    $result = array_keys($normalized);
    sort($result, SORT_STRING);
    return $result;
}

function ruhrpott_guru_osm_bbox($lat, $lng, $radius_km) {
    $lat_delta = $radius_km / 111.32;
    $cos_lat = cos(deg2rad($lat));
    $lng_delta = $radius_km / (111.32 * max(0.2, abs($cos_lat)));
    return array(
        max(-90, $lat - $lat_delta),
        max(-180, $lng - $lng_delta),
        min(90, $lat + $lat_delta),
        min(180, $lng + $lng_delta),
    );
}

function ruhrpott_guru_osm_category_filters($category) {
    if ($category === 'food') {
        return array(
            '["amenity"~"^(fast_food|restaurant|cafe|pub|bar|biergarten|food_court|ice_cream)$"]',
            '["craft"~"^(brewery|roastery)$"]',
        );
    }

    if ($category === 'culture') {
        return array(
            '["amenity"~"^(theatre|cinema|arts_centre|community_centre|planetarium|library|events_venue|cultural_center|nightclub|concert_hall|music_venue)$"]',
            '["tourism"~"^(museum|gallery|artwork)$"]',
            '["building"~"^(theatre|museum)$"]',
        );
    }

    if ($category === 'activities') {
        return array(
            '["leisure"~"^(swimming_pool|water_park|bowling_alley|skate_park|ice_rink|miniature_golf|disc_golf_course|marina|slipway|escape_game|trampoline_park|amusement_park|animal_park)$"]',
            '["sport"~"^(climbing|bouldering|swimming|skateboarding|water_ski|canoeing|kayak|sailing|rowing|equestrian|karting|billiards|bowling|padel|ice_skating)$"]',
            '["amenity"~"^(public_bath|swimming_pool|sauna)$"]',
            '["tourism"~"^(zoo|theme_park)$"]',
            '["zoo"]',
        );
    }

    return array(
        '["tourism"~"^(attraction|viewpoint)$"]',
        '["historic"~"^(mine|industrial|castle|manor|ruins|archaeological_site|city_gate|fort|fortification|monument)$"]',
        '["man_made"~"^(headframe|spoil_heap|water_tower|tower|lighthouse|windmill)$"]',
        '["building"~"^(castle|monument)$"]',
        '["leisure"~"^(park|garden|nature_reserve)$"]',
    );
}

function ruhrpott_guru_build_osm_pool_query($lat, $lng, $radius_km, $categories) {
    $bbox = ruhrpott_guru_osm_bbox($lat, $lng, $radius_km);
    $bbox_text = implode(',', array_map(function($value) {
        return number_format((float) $value, 6, '.', '');
    }, $bbox));

    $selectors = array();
    foreach ($categories as $category) {
        foreach (ruhrpott_guru_osm_category_filters($category) as $filter) {
            $selectors[$filter] = true;
        }
    }

    $body = '';
    foreach (array_keys($selectors) as $filter) {
        $body .= 'nwr' . $filter . '(' . $bbox_text . ');';
    }

    return '[out:json][timeout:9];(' . $body . ');out center 1500;';
}


/**
 * V69 Home OSM preview.
 *
 * The magazine Home renders only a handful of cards, but its candidate pool must not
 * be truncated to the old OSM-era 20 POIs. Fetch a broad bounded pool (up to 500)
 * for exactly one category and rank/filter in the browser. There is no
 * 5 -> 10 -> 25 -> 50 km retry chain here. A category failure is isolated and
 * cannot delay the other Home modules. Full category pages remain unchanged.
 */
function ruhrpott_guru_home_osm_preview_query($lat, $lng, $radius_km, $category, $limit = 500) {
    $bbox = ruhrpott_guru_osm_bbox($lat, $lng, $radius_km + 0.15);
    $bbox_text = implode(',', array_map(function($value) {
        return number_format((float) $value, 6, '.', '');
    }, $bbox));
    $limit = max(1, min(500, (int) $limit));
    $body = '';
    foreach (ruhrpott_guru_osm_category_filters($category) as $filter) {
        // Only named POIs can become useful Home cards. Filtering them at
        // Overpass level keeps both response size and downstream work small.
        $body .= 'nwr' . $filter . '["name"](' . $bbox_text . ');';
    }
    return '[out:json][timeout:6];(' . $body . ');out center ' . $limit . ';';
}

function ruhrpott_guru_home_osm_preview(WP_REST_Request $request) {
    $params = $request->get_json_params();
    if (!is_array($params)) $params = array();

    $lat = isset($params['lat']) ? (float) $params['lat'] : NAN;
    $lng = isset($params['lng']) ? (float) $params['lng'] : NAN;
    $requested_radius = isset($params['radiusKm']) ? (float) $params['radiusKm'] : 5.0;
    $category = isset($params['category']) ? sanitize_key((string) $params['category']) : '';
    $limit = isset($params['limit']) ? (int) $params['limit'] : 500;

    if (!is_finite($lat) || !is_finite($lng) || $lat < 50.3 || $lat > 52.5 || $lng < 5.2 || $lng > 9.3) {
        return new WP_REST_Response(array('ok'=>false,'errorType'=>'invalid-location','message'=>'Location outside RuhrpottGuru service area'), 400);
    }
    if (!in_array($category, ruhrpott_guru_osm_allowed_categories(), true)) {
        return new WP_REST_Response(array('ok'=>false,'errorType'=>'invalid-category','message'=>'Unsupported Home OSM category'), 400);
    }
    $allowed_radii = array(1.0, 2.5, 5.0, 10.0, 25.0, 50.0);
    $radius = null;
    foreach ($allowed_radii as $candidate) {
        if (abs($requested_radius - $candidate) < 0.05) { $radius = $candidate; break; }
    }
    if ($radius === null) $radius = 5.0;
    $limit = max(1, min(500, $limit));

    // Home preview uses exactly the selected radius. There is intentionally
    // no automatic 5 -> 10 -> 25 -> 50 km expansion on Home; deeper/further
    // results belong to the full category page.
    $acquisition_radius = $radius;
    $cache_lat = round($lat, 3);
    $cache_lng = round($lng, 3);
    $cache_key = 'rg_home_preview_v83_pool_' . sha1(
        number_format($cache_lat,3,'.','') . '|' .
        number_format($cache_lng,3,'.','') . '|' .
        $category . '|' . number_format($acquisition_radius,1,'.','') . '|' . $limit
    );
    $cached = get_transient($cache_key);
    if (is_array($cached) && isset($cached['elements']) && is_array($cached['elements'])) {
        return new WP_REST_Response(array(
            'ok'=>true,
            'elements'=>array_slice($cached['elements'],0,$limit),
            'category'=>$category,
            'requestedRadiusKm'=>$radius,
            'effectiveRadiusKm'=>$acquisition_radius,
            'cache'=>'HIT',
        ), 200);
    }

    $query = ruhrpott_guru_home_osm_preview_query($cache_lat, $cache_lng, $acquisition_radius, $category, $limit);

    // Independent category previews use short budgets. There is deliberately no
    // three-provider / multi-radius chain on Home.
    $provider_map = array(
        'food' => array(
            array('url'=>'https://overpass.private.coffee/api/interpreter','timeout'=>5),
            array('url'=>'https://overpass-api.de/api/interpreter','timeout'=>4),
        ),
        'highlights' => array(
            array('url'=>'https://maps.mail.ru/osm/tools/overpass/api/interpreter','timeout'=>5),
            array('url'=>'https://overpass.private.coffee/api/interpreter','timeout'=>4),
        ),
        'culture' => array(
            array('url'=>'https://overpass-api.de/api/interpreter','timeout'=>5),
            array('url'=>'https://overpass.private.coffee/api/interpreter','timeout'=>4),
        ),
        'activities' => array(
            array('url'=>'https://overpass.private.coffee/api/interpreter','timeout'=>5),
            array('url'=>'https://maps.mail.ru/osm/tools/overpass/api/interpreter','timeout'=>4),
        ),
    );
    $providers = $provider_map[$category];
    $attempts = array();
    $last_error = 'network-error';
    $last_message = 'Home OSM preview unavailable';

    foreach ($providers as $provider) {
        $started = microtime(true);
        $response = wp_remote_post($provider['url'], array(
            'timeout'=>(int)$provider['timeout'],
            'redirection'=>1,
            'headers'=>array(
                'Accept'=>'application/json',
                'User-Agent'=>'RuhrpottGuru/6.9-home-preview (+https://ruhrpottguru.de)',
                'Referer'=>home_url('/'),
            ),
            'body'=>array('data'=>$query),
        ));
        $duration = (int)round((microtime(true)-$started)*1000);
        if (is_wp_error($response)) {
            $last_message = $response->get_error_message();
            $last_error = stripos($last_message,'timed out') !== false ? 'timeout' : 'network-error';
            $attempts[] = array('upstream'=>$provider['url'],'durationMs'=>$duration,'status'=>0,'error'=>$last_message);
            continue;
        }
        $status = (int)wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            $last_error='http-error'; $last_message='Provider returned HTTP '.$status;
            $attempts[] = array('upstream'=>$provider['url'],'durationMs'=>$duration,'status'=>$status,'error'=>$last_message);
            continue;
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $validation = ruhrpott_guru_validate_osm_payload($data);
        if (!$validation['valid']) {
            $last_error = $validation['errorType'] ?? 'invalid-response';
            $last_message = $validation['message'] ?? 'Invalid OSM preview response';
            $attempts[] = array('upstream'=>$provider['url'],'durationMs'=>$duration,'status'=>$status,'error'=>$last_message);
            continue;
        }
        $elements = array_values(array_filter($data['elements'], function($element) use ($category) {
            if (!is_array($element) || empty($element['tags']['name'])) return false;
            if ($category === 'highlights') return ruhrpott_guru_highlights_element_candidate($element);
            return ruhrpott_guru_osm_element_matches_category($element, $category);
        }));
        $elements = array_slice($elements, 0, $limit);
        // Empty is authoritative for this one bounded preview; cache only briefly.
        set_transient($cache_key, array('elements'=>$elements), empty($elements) ? 2 * MINUTE_IN_SECONDS : 20 * MINUTE_IN_SECONDS);
        return new WP_REST_Response(array(
            'ok'=>true,
            'elements'=>$elements,
            'category'=>$category,
            'requestedRadiusKm'=>$radius,
            'effectiveRadiusKm'=>$acquisition_radius,
            'cache'=>'MISS',
            'upstream'=>$provider['url'],
            'attempts'=>$attempts,
        ), 200);
    }

    return new WP_REST_Response(array('ok'=>false,'errorType'=>$last_error,'message'=>$last_message,'category'=>$category,'attempts'=>$attempts), 502);
}

function ruhrpott_guru_validate_osm_payload($data) {
    if (!is_array($data) || !isset($data['elements']) || !is_array($data['elements'])) {
        return array(
            'valid' => false,
            'errorType' => 'invalid-response',
            'message' => 'Overpass response has no elements array',
        );
    }

    $remark = isset($data['remark']) ? trim((string) $data['remark']) : '';
    if ($remark !== '') {
        $lower = strtolower($remark);
        return array(
            'valid' => false,
            'errorType' => (strpos($lower, 'timeout') !== false || strpos($lower, 'timed out') !== false) ? 'timeout' : 'invalid-response',
            'message' => 'Overpass runtime remark: ' . $remark,
        );
    }

    return array(
        'valid' => true,
        'empty' => count($data['elements']) === 0,
    );
}

function ruhrpott_guru_osm_element_matches_category($element, $category) {
    if (!is_array($element)) return false;
    $tags = isset($element['tags']) && is_array($element['tags']) ? $element['tags'] : array();
    $amenity = isset($tags['amenity']) ? (string) $tags['amenity'] : '';
    $tourism = isset($tags['tourism']) ? (string) $tags['tourism'] : '';
    $historic = isset($tags['historic']) ? (string) $tags['historic'] : '';
    $man_made = isset($tags['man_made']) ? (string) $tags['man_made'] : '';
    $leisure = isset($tags['leisure']) ? (string) $tags['leisure'] : '';
    $craft = isset($tags['craft']) ? (string) $tags['craft'] : '';
    $building = isset($tags['building']) ? (string) $tags['building'] : '';

    if ($category === 'food') {
        return preg_match('/^(fast_food|restaurant|cafe|pub|bar|biergarten|food_court|ice_cream)$/i', $amenity)
            || preg_match('/^(brewery|roastery)$/i', $craft);
    }
    if ($category === 'culture') {
        return preg_match('/^(theatre|cinema|arts_centre|community_centre|planetarium|library|events_venue|cultural_center|nightclub|concert_hall)$/i', $amenity)
            || preg_match('/^(museum|gallery)$/i', $tourism)
            || preg_match('/^(theatre|museum)$/i', $building);
    }
    if ($category === 'activities') {
        $sport = isset($tags['sport']) ? (string) $tags['sport'] : '';
        return preg_match('/^(swimming_pool|water_park|bowling_alley|skate_park|ice_rink|miniature_golf|disc_golf_course|marina|slipway|escape_game|trampoline_park|amusement_park|animal_park)$/i', $leisure)
            || preg_match('/^(climbing|bouldering|swimming|skateboarding|water_ski|canoeing|kayak|sailing|rowing|equestrian|karting|billiards|bowling|padel|ice_skating)$/i', $sport)
            || preg_match('/^(public_bath|swimming_pool|sauna)$/i', $amenity)
            || preg_match('/^(zoo|theme_park)$/i', $tourism)
            || isset($tags['zoo']);
    }
    if ($category === 'highlights') {
        return preg_match('/^(attraction|viewpoint)$/i', $tourism)
            || preg_match('/^(mine|industrial|castle|manor|ruins|archaeological_site|city_gate|fort|fortification|monument)$/i', $historic)
            || preg_match('/^(headframe|spoil_heap|water_tower|tower|lighthouse|windmill)$/i', $man_made)
            || preg_match('/^(castle|monument)$/i', $building)
            || preg_match('/^(park|garden|nature_reserve)$/i', $leisure);
    }
    return false;
}


/**
 * Highlights-only OSM acquisition V1.
 *
 * This path is deliberately separate from the shared Food/Culture pool. The
 * legacy UI still owns exact Haversine filtering and presentation, but the
 * server acquires a small superset once and reuses it for 5/10 km requests.
 */
function ruhrpott_guru_highlights_query($lat, $lng, $radius_km) {
    $bbox = ruhrpott_guru_osm_bbox($lat, $lng, $radius_km + 0.2);
    $bbox_text = implode(',', array_map(function($value) {
        return number_format((float) $value, 6, '.', '');
    }, $bbox));

    // Keep this aligned with the actual v63 highlight intelligence. Fetch only
    // POIs that can realistically become a RuhrpottGuru Highlight. Generic
    // Food/Culture selectors are intentionally absent.
    $filters = array(
        '["tourism"~"^(attraction|viewpoint)$"]',
        '["historic"~"^(mine|industrial|castle|manor|ruins|archaeological_site|city_gate|fort|fortification|monument)$"]',
        '["heritage"]',
        '["man_made"~"^(headframe|spoil_heap|water_tower|tower|lighthouse|windmill)$"]',
        '["building"~"^(castle|monument)$"]',
        '["leisure"~"^(park|garden|nature_reserve)$"]',
    );

    $body = '';
    foreach ($filters as $filter) {
        $body .= 'nwr' . $filter . '(' . $bbox_text . ');';
    }
    return '[out:json][timeout:7];(' . $body . ');out center 900;';
}

function ruhrpott_guru_highlights_element_candidate($element) {
    if (!is_array($element)) return false;
    $tags = isset($element['tags']) && is_array($element['tags']) ? $element['tags'] : array();
    $name = isset($tags['name']) ? trim((string) $tags['name']) : '';
    if ($name === '') return false; // cards without a usable name are not useful

    $tourism = isset($tags['tourism']) ? (string) $tags['tourism'] : '';
    $historic = isset($tags['historic']) ? (string) $tags['historic'] : '';
    $heritage = isset($tags['heritage']) ? (string) $tags['heritage'] : '';
    $man_made = isset($tags['man_made']) ? (string) $tags['man_made'] : '';
    $building = isset($tags['building']) ? (string) $tags['building'] : '';
    $leisure = isset($tags['leisure']) ? (string) $tags['leisure'] : '';

    return preg_match('/^(attraction|viewpoint)$/i', $tourism)
        || preg_match('/^(mine|industrial|castle|manor|ruins|archaeological_site|city_gate|fort|fortification|monument)$/i', $historic)
        || $heritage !== ''
        || preg_match('/^(headframe|spoil_heap|water_tower|tower|lighthouse|windmill)$/i', $man_made)
        || preg_match('/^(castle|monument)$/i', $building)
        || preg_match('/^(park|garden|nature_reserve)$/i', $leisure);
}

function ruhrpott_guru_highlights_cache_coord($value) {
    return round((float) $value, 3); // ~110 m; absorbs ordinary GPS drift
}

function ruhrpott_guru_highlights_cache_key($lat, $lng, $acquisition_radius_km) {
    return 'rg_highlights_v1_' . sha1(
        number_format(ruhrpott_guru_highlights_cache_coord($lat), 3, '.', '') . '|' .
        number_format(ruhrpott_guru_highlights_cache_coord($lng), 3, '.', '') . '|' .
        number_format((float) $acquisition_radius_km, 1, '.', '')
    );
}

function ruhrpott_guru_highlights_osm(WP_REST_Request $request) {
    $params = $request->get_json_params();
    if (!is_array($params)) $params = array();
    $lat = isset($params['lat']) ? (float) $params['lat'] : NAN;
    $lng = isset($params['lng']) ? (float) $params['lng'] : NAN;
    $requested_radius = isset($params['radiusKm']) ? (float) $params['radiusKm'] : 0;

    if (!is_finite($lat) || !is_finite($lng) || $lat < 50.3 || $lat > 52.5 || $lng < 5.2 || $lng > 9.3) {
        return new WP_REST_Response(array('ok'=>false,'errorType'=>'invalid-location','message'=>'Location outside RuhrpottGuru service area'), 400);
    }
    $allowed = array(5.0, 10.0, 25.0, 50.0);
    $radius_ok = false;
    foreach ($allowed as $candidate) {
        if (abs($requested_radius - $candidate) < 0.05) { $requested_radius = $candidate; $radius_ok = true; break; }
    }
    if (!$radius_ok) {
        return new WP_REST_Response(array('ok'=>false,'errorType'=>'invalid-request','message'=>'Unsupported Highlights radius'), 400);
    }

    // One 10 km acquisition serves both 5 km and 10 km client steps. This means
    // a genuine 5 km empty result does NOT trigger another live network request.
    $acquisition_radius = $requested_radius <= 10.0 ? 10.0 : $requested_radius;
    $cache_lat = ruhrpott_guru_highlights_cache_coord($lat);
    $cache_lng = ruhrpott_guru_highlights_cache_coord($lng);
    $cache_key = ruhrpott_guru_highlights_cache_key($lat, $lng, $acquisition_radius);
    $now = time();

    $cached = get_transient($cache_key);
    if (is_array($cached) && isset($cached['data']) && is_array($cached['data'])) {
        $validation = ruhrpott_guru_validate_osm_payload($cached['data']);
        $fetched_at = isset($cached['fetchedAt']) ? (int) $cached['fetchedAt'] : 0;
        $age = $fetched_at > 0 ? max(0, $now - $fetched_at) : DAY_IN_SECONDS + 1;
        if ($validation['valid'] && $age <= 30 * MINUTE_IN_SECONDS) {
            return new WP_REST_Response(array(
                'ok'=>true,
                'data'=>$cached['data'],
                '_rg'=>array('category'=>'highlights','cache'=>'HIT','cacheAgeMs'=>$age*1000,'requestedRadiusKm'=>$requested_radius,'acquisitionRadiusKm'=>$acquisition_radius,'elementCount'=>count($cached['data']['elements']))
            ), 200);
        }
    }

    $query = ruhrpott_guru_highlights_query($cache_lat, $cache_lng, $acquisition_radius);

    // Highlights has a strict total latency budget. Two short attempts are much
    // safer than letting the historic client chain 5->10->25->50 through long
    // provider timeouts.
    $providers = array(
        array('url'=>'https://overpass.private.coffee/api/interpreter','timeout'=>6),
        array('url'=>'https://maps.mail.ru/osm/tools/overpass/api/interpreter','timeout'=>5),
    );
    $attempts = array();
    $last_error_type = 'network-error';
    $last_message = 'Highlights OSM provider unavailable';

    foreach ($providers as $provider) {
        $started = microtime(true);
        $response = wp_remote_post($provider['url'], array(
            'timeout'=>(int) $provider['timeout'],
            'redirection'=>1,
            'headers'=>array(
                'Accept'=>'application/json',
                'User-Agent'=>'RuhrpottGuru/4.3-highlights-only (+https://ruhrpottguru.de)',
                'Referer'=>home_url('/'),
            ),
            'body'=>array('data'=>$query),
        ));
        $duration_ms = (int) round((microtime(true)-$started)*1000);
        if (is_wp_error($response)) {
            $last_message = $response->get_error_message();
            $last_error_type = stripos($last_message,'timed out') !== false ? 'timeout' : 'network-error';
            $attempts[] = array('upstream'=>$provider['url'],'status'=>0,'durationMs'=>$duration_ms,'error'=>$last_message);
            continue;
        }
        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            $last_error_type = 'http-error';
            $last_message = 'Highlights provider returned HTTP ' . $status;
            $attempts[] = array('upstream'=>$provider['url'],'status'=>$status,'durationMs'=>$duration_ms,'error'=>$last_message);
            continue;
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $validation = ruhrpott_guru_validate_osm_payload($data);
        if (!$validation['valid']) {
            $last_error_type = isset($validation['errorType']) ? $validation['errorType'] : 'invalid-response';
            $last_message = isset($validation['message']) ? $validation['message'] : 'Invalid Highlights response';
            $attempts[] = array('upstream'=>$provider['url'],'status'=>$status,'durationMs'=>$duration_ms,'error'=>$last_message);
            continue;
        }

        $data['elements'] = array_values(array_filter($data['elements'], 'ruhrpott_guru_highlights_element_candidate'));
        $attempts[] = array('upstream'=>$provider['url'],'status'=>$status,'durationMs'=>$duration_ms,'elementCount'=>count($data['elements']));
        set_transient($cache_key, array('data'=>$data,'fetchedAt'=>$now,'lat'=>$lat,'lng'=>$lng), 30 * MINUTE_IN_SECONDS);
        return new WP_REST_Response(array(
            'ok'=>true,
            'data'=>$data,
            '_rg'=>array('category'=>'highlights','cache'=>'MISS','requestedRadiusKm'=>$requested_radius,'acquisitionRadiusKm'=>$acquisition_radius,'elementCount'=>count($data['elements']),'upstream'=>$provider['url'],'attempts'=>$attempts)
        ), 200);
    }

    // If a previous Highlights response exists, keep it visible on provider
    // failure for up to 24h. Never turn provider failure into authoritative empty.
    if (is_array($cached) && isset($cached['data']) && is_array($cached['data'])) {
        $validation = ruhrpott_guru_validate_osm_payload($cached['data']);
        $fetched_at = isset($cached['fetchedAt']) ? (int) $cached['fetchedAt'] : 0;
        $age = $fetched_at > 0 ? max(0, $now-$fetched_at) : DAY_IN_SECONDS+1;
        if ($validation['valid'] && empty($validation['empty']) && $age <= DAY_IN_SECONDS) {
            return new WP_REST_Response(array(
                'ok'=>true,
                'data'=>$cached['data'],
                '_rg'=>array('category'=>'highlights','cache'=>'STALE','liveFailure'=>$last_error_type,'cacheAgeMs'=>$age*1000,'requestedRadiusKm'=>$requested_radius,'acquisitionRadiusKm'=>$acquisition_radius,'elementCount'=>count($cached['data']['elements']),'attempts'=>$attempts)
            ), 200);
        }
    }

    return new WP_REST_Response(array('ok'=>false,'errorType'=>$last_error_type,'message'=>$last_message,'attempts'=>$attempts), 502);
}

function ruhrpott_guru_parse_legacy_overpass_query($query) {
    $query = is_string($query) ? $query : '';
    if ($query === '' || strlen($query) > 100000) return null;

    if (!preg_match('/around\s*:\s*([0-9.]+)\s*,\s*(-?[0-9.]+)\s*,\s*(-?[0-9.]+)/i', $query, $match)) {
        return null;
    }

    $radius_km = ((float) $match[1]) / 1000.0;
    $lat = (float) $match[2];
    $lng = (float) $match[3];
    if (!is_finite($lat) || !is_finite($lng) || $lat < 50.3 || $lat > 52.5 || $lng < 5.2 || $lng > 9.3) {
        return null;
    }

    $lower = strtolower($query);

    // The historic engine also has one broad fallback query containing several
    // category families. Preserve that behavior without forwarding raw QL.
    $is_generic_combined =
        strpos($lower, 'fast_food|restaurant') !== false &&
        strpos($lower, 'theatre|cinema|arts_centre') !== false &&
        strpos($lower, 'headframe|spoil_heap') !== false &&
        strpos($lower, 'sports_centre|swimming_pool') !== false;

    if ($is_generic_combined) {
        $category = 'all';
    } elseif (
        strpos($lower, 'concert_hall') !== false ||
        strpos($lower, 'community_centre') !== false ||
        strpos($lower, 'arts_centre') !== false ||
        strpos($lower, 'planetarium') !== false ||
        strpos($lower, 'theatre|cinema') !== false
    ) {
        $category = 'culture';
    } elseif (
        strpos($lower, 'spoil_heap') !== false ||
        strpos($lower, 'archaeological_site') !== false ||
        strpos($lower, 'headframe') !== false ||
        strpos($lower, 'viewpoint') !== false ||
        strpos($lower, '["historic"') !== false
    ) {
        $category = 'highlights';
    } elseif (
        strpos($lower, 'fast_food') !== false ||
        strpos($lower, 'restaurant') !== false ||
        strpos($lower, 'biergarten') !== false ||
        strpos($lower, 'food_court') !== false ||
        strpos($lower, 'ice_cream') !== false ||
        strpos($lower, 'craft"="brewery') !== false ||
        strpos($lower, 'craft"="roastery') !== false
    ) {
        $category = 'food';
    } elseif (
        strpos($lower, 'sports_centre') !== false ||
        strpos($lower, 'sports_hall') !== false ||
        strpos($lower, 'public_bath') !== false ||
        strpos($lower, 'sport"~') !== false ||
        strpos($lower, 'sport"=') !== false ||
        strpos($lower, 'marina|slipway') !== false ||
        strpos($lower, 'animal_park') !== false ||
        strpos($lower, 'nwr["zoo"]') !== false
    ) {
        $category = 'activities';
    } else {
        return null;
    }

    $allowed_radii = array(1.0, 2.5, 5.0, 10.0, 25.0, 50.0);
    $normalized_radius = null;
    foreach ($allowed_radii as $allowed_radius) {
        if (abs($radius_km - $allowed_radius) < 0.05) {
            $normalized_radius = $allowed_radius;
            break;
        }
    }
    if ($normalized_radius === null) return null;

    return array(
        'lat' => $lat,
        'lng' => $lng,
        'requestedRadiusKm' => $normalized_radius,
        'category' => $category,
    );
}

function ruhrpott_guru_overpass_compat(WP_REST_Request $request) {
    $params = $request->get_json_params();
    $query = is_array($params) && isset($params['query']) ? (string) $params['query'] : '';
    if ($query === '') {
        $form_query = $request->get_param('data');
        if (is_string($form_query)) $query = $form_query;
    }

    $parsed = ruhrpott_guru_parse_legacy_overpass_query($query);
    if ($parsed === null) {
        return new WP_REST_Response(array(
            'ok' => false,
            'errorType' => 'invalid-request',
            'message' => 'Query is not an approved RuhrpottGuru OSM category request',
        ), 400);
    }

    // Highlights is intentionally isolated. Events and the already-working
    // Food path are not changed by the Highlights repair.
    if ($parsed['category'] === 'highlights') {
        $pool_radius = $parsed['requestedRadiusKm'] < 5.0 ? 5.0 : $parsed['requestedRadiusKm'];
        $pool_categories = array('highlights');
        $pool_request = new WP_REST_Request('POST', '/ruhrpottguru/v1/highlights-osm');
        $pool_request->set_header('Content-Type', 'application/json');
        $pool_request->set_body(wp_json_encode(array(
            'lat' => $parsed['lat'],
            'lng' => $parsed['lng'],
            'radiusKm' => $pool_radius,
        )));
        $pool_response = ruhrpott_guru_highlights_osm($pool_request);
    } else {
        // Preserve v65 exactly for Food/Culture/Activities.
        $pool_radius = $parsed['requestedRadiusKm'] <= 5.0 ? 5.0 : $parsed['requestedRadiusKm'];
        if ($parsed['category'] === 'all') {
            $pool_categories = array('food', 'highlights', 'culture', 'activities');
        } elseif ($parsed['category'] === 'activities') {
            $pool_categories = array('activities');
        } else {
            $pool_categories = $pool_radius <= 5.0
                ? array('food', 'highlights', 'culture')
                : array($parsed['category']);
        }
        $pool_request = new WP_REST_Request('POST', '/ruhrpottguru/v1/osm-pool');
        $pool_request->set_header('Content-Type', 'application/json');
        $pool_request->set_body(wp_json_encode(array(
            'lat' => $parsed['lat'],
            'lng' => $parsed['lng'],
            'radiusKm' => $pool_radius,
            'categories' => $pool_categories,
        )));
        $pool_response = ruhrpott_guru_osm_pool($pool_request);
    }
    if (!($pool_response instanceof WP_REST_Response)) {
        return $pool_response;
    }

    $status = $pool_response->get_status();
    $payload = $pool_response->get_data();
    if ($status >= 200 && $status < 300 && is_array($payload) && !empty($payload['ok']) && isset($payload['data']['elements']) && is_array($payload['data']['elements'])) {
        if ($parsed['category'] !== 'all') {
            $payload['data']['elements'] = array_values(array_filter($payload['data']['elements'], function($element) use ($parsed) {
                if ($parsed['category'] === 'highlights') return ruhrpott_guru_highlights_element_candidate($element);
                return ruhrpott_guru_osm_element_matches_category($element, $parsed['category']);
            }));
        }
        $category_empty = count($payload['data']['elements']) === 0;
        if (!isset($payload['_rg']) || !is_array($payload['_rg'])) $payload['_rg'] = array();
        if ($category_empty && (
            (isset($payload['_rg']['cache']) && $payload['_rg']['cache'] === 'STALE') ||
            !empty($payload['_rg']['liveFailure'])
        )) {
            return new WP_REST_Response(array(
                'ok' => false,
                'errorType' => 'provider-stale-empty',
                'message' => 'Live OSM refresh failed; stale pool cannot confirm this category is empty',
            ), 503);
        }
        $payload['_rg']['compat'] = 'legacy-query-translated';
        $payload['_rg']['requestedRadiusKm'] = $parsed['requestedRadiusKm'];
        $payload['_rg']['poolRadiusKm'] = $pool_radius;
        $payload['_rg']['category'] = $parsed['category'];
        $payload['_rg']['elementCount'] = count($payload['data']['elements']);

        // Historic v63 bundle explicitly requires this TOP-LEVEL flag before it
        // accepts an authoritative empty result. Keep it in sync with _rg.empty.
        $payload['emptyConfirmed'] = $category_empty;
        $payload['cached'] = isset($payload['_rg']['cache']) && $payload['_rg']['cache'] !== 'MISS';
        $payload['mirror'] = isset($payload['_rg']['upstream']) ? $payload['_rg']['upstream'] : 'wordpress-osm-pool';
    }

    return new WP_REST_Response($payload, $status);
}

function ruhrpott_guru_overpass_diagnostic(WP_REST_Request $request) {
    $query = $request->get_param('data');
    if (!is_string($query) || !preg_match('/around\s*:\s*([0-9.]+)\s*,\s*(-?[0-9.]+)\s*,\s*(-?[0-9.]+)/i', $query, $match)) {
        return new WP_REST_Response(array('elements' => array(), 'remark' => 'Invalid diagnostic request'), 400);
    }
    $radius_km = ((float) $match[1]) / 1000.0;
    $lat = (float) $match[2];
    $lng = (float) $match[3];
    $allowed = array(1.0, 2.5, 5.0, 10.0, 25.0, 50.0);
    $normalized = null;
    foreach ($allowed as $candidate) {
        if (abs($radius_km - $candidate) < 0.05) { $normalized = $candidate; break; }
    }
    if ($normalized === null) return new WP_REST_Response(array('elements' => array(), 'remark' => 'Unsupported diagnostic radius'), 400);

    $pool_request = new WP_REST_Request('POST', '/ruhrpottguru/v1/osm-pool');
    $pool_request->set_header('Content-Type', 'application/json');
    $pool_request->set_body(wp_json_encode(array(
        'lat' => $lat,
        'lng' => $lng,
        'radiusKm' => $normalized <= 5.0 ? 5.0 : $normalized,
        'categories' => array('food', 'highlights', 'culture', 'activities'),
    )));
    $pool = ruhrpott_guru_osm_pool($pool_request);
    if (!($pool instanceof WP_REST_Response)) return $pool;
    $payload = $pool->get_data();
    if ($pool->get_status() >= 200 && $pool->get_status() < 300 && is_array($payload) && !empty($payload['ok']) && isset($payload['data']['elements'])) {
        return new WP_REST_Response($payload['data'], 200);
    }
    return new WP_REST_Response(array(
        'elements' => array(),
        'remark' => is_array($payload) && isset($payload['message']) ? (string) $payload['message'] : 'OSM diagnostic provider unavailable',
    ), 502);
}

function ruhrpott_guru_overpass_raw_compat(WP_REST_Request $request) {
    // Historic v63 contains a browser-direct mirror fallback. The public mirror
    // URLs are rewritten to this same-origin endpoint and deliberately fail
    // fast. There is exactly ONE live OSM acquisition path: /v1/overpass ->
    // /v1/osm-pool -> server-side Overpass. No duplicate browser retries.
    return new WP_REST_Response(array(
        'elements' => array(),
        'remark' => 'Direct browser Overpass fallback disabled; use WordPress OSM pool',
    ), 503);
}

function ruhrpott_guru_osm_cache_coord($value) {
    return round((float) $value, 3);
}

function ruhrpott_guru_osm_region_coord($value) {
    return round((float) $value, 2);
}

function ruhrpott_guru_osm_distance_km($lat1, $lng1, $lat2, $lng2) {
    $earth = 6371.0;
    $dlat = deg2rad($lat2 - $lat1);
    $dlng = deg2rad($lng2 - $lng1);
    $a = sin($dlat / 2) * sin($dlat / 2)
       + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
       * sin($dlng / 2) * sin($dlng / 2);
    return $earth * 2 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));
}

function ruhrpott_guru_osm_pool_cache_key($lat, $lng, $radius_km, $categories) {
    return 'rg_osm_pool_v3_' . sha1(
        number_format(ruhrpott_guru_osm_cache_coord($lat), 3, '.', '') . '|' .
        number_format(ruhrpott_guru_osm_cache_coord($lng), 3, '.', '') . '|' .
        number_format($radius_km, 1, '.', '') . '|' .
        implode(',', $categories)
    );
}

function ruhrpott_guru_osm_region_key($lat, $lng, $radius_km, $categories) {
    return 'rg_osm_region_v3_' . sha1(
        number_format($lat, 2, '.', '') . '|' .
        number_format($lng, 2, '.', '') . '|' .
        number_format($radius_km, 1, '.', '') . '|' .
        implode(',', $categories)
    );
}

function ruhrpott_guru_osm_find_nearby_success($lat, $lng, $radius_km, $categories) {
    $base_lat = ruhrpott_guru_osm_region_coord($lat);
    $base_lng = ruhrpott_guru_osm_region_coord($lng);
    $best = null;

    // Search the current ~1 km cell plus its eight neighbors. This is used only
    // after a live provider failure, never to assert that a category is empty.
    for ($lat_step = -1; $lat_step <= 1; $lat_step++) {
        for ($lng_step = -1; $lng_step <= 1; $lng_step++) {
            $cell_lat = round($base_lat + ($lat_step * 0.01), 2);
            $cell_lng = round($base_lng + ($lng_step * 0.01), 2);
            $entry = get_transient(ruhrpott_guru_osm_region_key($cell_lat, $cell_lng, $radius_km, $categories));
            if (!is_array($entry) || !isset($entry['data']) || !is_array($entry['data'])) continue;
            $validation = ruhrpott_guru_validate_osm_payload($entry['data']);
            if (!$validation['valid'] || !empty($validation['empty'])) continue;
            $fetched_at = isset($entry['fetchedAt']) ? (int) $entry['fetchedAt'] : 0;
            if ($fetched_at <= 0 || (time() - $fetched_at) > DAY_IN_SECONDS) continue;
            $source_lat = isset($entry['lat']) ? (float) $entry['lat'] : $cell_lat;
            $source_lng = isset($entry['lng']) ? (float) $entry['lng'] : $cell_lng;
            $distance = ruhrpott_guru_osm_distance_km($lat, $lng, $source_lat, $source_lng);
            if ($distance > 2.0) continue;
            if ($best === null || $distance < $best['distanceKm'] || ($distance === $best['distanceKm'] && $fetched_at > $best['fetchedAt'])) {
                $best = array(
                    'data' => $entry['data'],
                    'fetchedAt' => $fetched_at,
                    'distanceKm' => $distance,
                    'sourceLat' => $source_lat,
                    'sourceLng' => $source_lng,
                );
            }
        }
    }
    return $best;
}

function ruhrpott_guru_osm_pool(WP_REST_Request $request) {
    $params = $request->get_json_params();
    if (!is_array($params)) $params = array();

    $lat = isset($params['lat']) ? (float) $params['lat'] : NAN;
    $lng = isset($params['lng']) ? (float) $params['lng'] : NAN;
    $radius_km = isset($params['radiusKm']) ? (float) $params['radiusKm'] : 0;
    $categories = ruhrpott_guru_osm_normalize_categories(isset($params['categories']) ? $params['categories'] : array());

    if (!is_finite($lat) || !is_finite($lng) || $lat < 50.3 || $lat > 52.5 || $lng < 5.2 || $lng > 9.3) {
        return new WP_REST_Response(array(
            'ok' => false,
            'errorType' => 'invalid-location',
            'message' => 'Location outside RuhrpottGuru service area',
        ), 400);
    }

    $allowed_radii = array(1.0, 2.5, 5.0, 10.0, 25.0, 50.0);
    $radius_ok = false;
    foreach ($allowed_radii as $allowed_radius) {
        if (abs($radius_km - $allowed_radius) < 0.001) {
            $radius_km = $allowed_radius;
            $radius_ok = true;
            break;
        }
    }
    if (!$radius_ok || empty($categories)) {
        return new WP_REST_Response(array(
            'ok' => false,
            'errorType' => 'invalid-request',
            'message' => 'Invalid radius or categories',
        ), 400);
    }

    // GPS coordinates can naturally move by tens of metres between reloads.
    // Snap acquisition to ~110 m cells and pad the query by 200 m so the exact
    // Haversine filtering in the existing v63 browser UI remains authoritative.
    $cache_lat = ruhrpott_guru_osm_cache_coord($lat);
    $cache_lng = ruhrpott_guru_osm_cache_coord($lng);
    $acquisition_radius_km = $radius_km + 0.2;
    $query = ruhrpott_guru_build_osm_pool_query($cache_lat, $cache_lng, $acquisition_radius_km, $categories);
    $cache_key = ruhrpott_guru_osm_pool_cache_key($lat, $lng, $radius_km, $categories);

    $now = time();
    $cached = get_transient($cache_key);
    $stale_candidate = null;
    if (is_array($cached) && isset($cached['data']) && is_array($cached['data'])) {
        $validation = ruhrpott_guru_validate_osm_payload($cached['data']);
        $fetched_at = isset($cached['fetchedAt']) ? (int) $cached['fetchedAt'] : 0;
        $age = $fetched_at > 0 ? max(0, $now - $fetched_at) : DAY_IN_SECONDS + 1;
        if ($validation['valid']) {
            $is_empty = !empty($validation['empty']);
            // OSM POIs do not need a fresh provider hit on every mobile reload.
            // Keep successful pools fresh for 30 minutes; confirmed empty stays short.
            $fresh_ttl = $is_empty ? 3 * MINUTE_IN_SECONDS : 30 * MINUTE_IN_SECONDS;
            if ($age <= $fresh_ttl) {
                return new WP_REST_Response(array(
                    'ok' => true,
                    'data' => $cached['data'],
                    '_rg' => array(
                        'upstream' => 'wordpress-transient-cache',
                        'cache' => 'HIT',
                        'cacheAgeMs' => $age * 1000,
                        'radiusKm' => $radius_km,
                        'acquisitionRadiusKm' => $acquisition_radius_km,
                        'cacheLat' => $cache_lat,
                        'cacheLng' => $cache_lng,
                        'categories' => $categories,
                        'empty' => $is_empty,
                    ),
                ), 200);
            }
            if (!$is_empty && $age <= DAY_IN_SECONDS) {
                $stale_candidate = array('data' => $cached['data'], 'age' => $age, 'source' => 'exact');
            }
        } else {
            delete_transient($cache_key);
        }
    }

    $lock_key = 'rg_osm_pool_lock_v3_' . sha1($cache_key);
    $owns_lock = add_option($lock_key, $now, '', 'no');
    if (!$owns_lock) {
        $lock_started = (int) get_option($lock_key, 0);
        if ($lock_started > 0 && ($now - $lock_started) > 20) {
            delete_option($lock_key);
            $owns_lock = add_option($lock_key, $now, '', 'no');
        }
    }

    if (!$owns_lock) {
        // Never make a second mobile request wait on Overpass when a known-good
        // previous result exists. Serve it immediately while the other request refreshes.
        if ($stale_candidate !== null) {
            return new WP_REST_Response(array(
                'ok' => true,
                'data' => $stale_candidate['data'],
                '_rg' => array(
                    'upstream' => 'wordpress-transient-cache',
                    'cache' => 'STALE',
                    'cacheAgeMs' => $stale_candidate['age'] * 1000,
                    'liveFailure' => 'refresh-in-flight',
                    'radiusKm' => $radius_km,
                    'categories' => $categories,
                    'empty' => false,
                    'coalesced' => true,
                ),
            ), 200);
        }
        $nearby = ruhrpott_guru_osm_find_nearby_success($lat, $lng, $radius_km, $categories);
        if ($nearby !== null) {
            return new WP_REST_Response(array(
                'ok' => true,
                'data' => $nearby['data'],
                '_rg' => array(
                    'upstream' => 'wordpress-nearby-cache',
                    'cache' => 'STALE_NEARBY',
                    'cacheAgeMs' => max(0, $now - $nearby['fetchedAt']) * 1000,
                    'liveFailure' => 'refresh-in-flight',
                    'nearbyDistanceKm' => round($nearby['distanceKm'], 3),
                    'radiusKm' => $radius_km,
                    'categories' => $categories,
                    'empty' => false,
                    'coalesced' => true,
                ),
            ), 200);
        }

        // Cold concurrent requests may share the result of the first acquisition.
        for ($wait_step = 0; $wait_step < 50; $wait_step++) {
            usleep(200000); // max ~10s
            $wait_cached = get_transient($cache_key);
            if (!is_array($wait_cached) || !isset($wait_cached['data']) || !is_array($wait_cached['data'])) continue;
            $wait_validation = ruhrpott_guru_validate_osm_payload($wait_cached['data']);
            if (!$wait_validation['valid']) continue;
            $wait_fetched_at = isset($wait_cached['fetchedAt']) ? (int) $wait_cached['fetchedAt'] : time();
            $wait_age = max(0, time() - $wait_fetched_at);
            return new WP_REST_Response(array(
                'ok' => true,
                'data' => $wait_cached['data'],
                '_rg' => array(
                    'upstream' => 'wordpress-transient-cache',
                    'cache' => 'HIT',
                    'cacheAgeMs' => $wait_age * 1000,
                    'radiusKm' => $radius_km,
                    'categories' => $categories,
                    'empty' => !empty($wait_validation['empty']),
                    'coalesced' => true,
                ),
            ), 200);
        }

        return new WP_REST_Response(array(
            'ok' => false,
            'errorType' => 'provider-busy',
            'message' => 'OSM pool refresh is still in flight',
        ), 503);
    }

    // Even if a PHP request terminates unexpectedly, do not leave a lock that
    // poisons the next reload for 20 seconds.
    register_shutdown_function(function() use ($lock_key) {
        delete_option($lock_key);
    });

    // Use independent current public instances. Do NOT use the historic
    // lz4/z.overpass-api.de hosts: they are known to accept requests and then hang.
    $mirrors = array(
        array('url' => 'https://overpass.private.coffee/api/interpreter', 'timeout' => 10),
        array('url' => 'https://overpass-api.de/api/interpreter', 'timeout' => 9),
        array('url' => 'https://maps.mail.ru/osm/tools/overpass/api/interpreter', 'timeout' => 7),
    );

    $attempts = array();
    $last_error_type = 'network-error';
    $last_message = 'No Overpass provider succeeded';

    foreach ($mirrors as $mirror_config) {
        $mirror = $mirror_config['url'];
        $started = microtime(true);
        $response = wp_remote_post($mirror, array(
            'timeout' => (int) $mirror_config['timeout'],
            'redirection' => 1,
            'headers' => array(
                'Accept' => 'application/json',
                'User-Agent' => 'RuhrpottGuru/4.2-osm-pool-repeat-safe (+https://ruhrpottguru.de)',
                'Referer' => home_url('/'),
            ),
            'body' => array('data' => $query),
        ));
        $duration_ms = (int) round((microtime(true) - $started) * 1000);

        if (is_wp_error($response)) {
            $last_message = $response->get_error_message();
            $last_error_type = stripos($last_message, 'timed out') !== false ? 'timeout' : 'network-error';
            $attempts[] = array('upstream' => $mirror, 'status' => 0, 'durationMs' => $duration_ms, 'error' => $last_message);
            continue;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            $last_error_type = 'http-error';
            $last_message = $mirror . ' returned HTTP ' . $status;
            $attempts[] = array('upstream' => $mirror, 'status' => $status, 'durationMs' => $duration_ms, 'error' => $last_message);
            continue;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $validation = ruhrpott_guru_validate_osm_payload($data);
        if (!$validation['valid']) {
            $last_error_type = isset($validation['errorType']) ? $validation['errorType'] : 'invalid-response';
            $last_message = isset($validation['message']) ? $validation['message'] : 'Invalid Overpass response';
            $attempts[] = array('upstream' => $mirror, 'status' => $status, 'durationMs' => $duration_ms, 'error' => $last_message);
            continue;
        }

        $element_count = count($data['elements']);
        $is_empty = $element_count === 0;
        $attempts[] = array('upstream' => $mirror, 'status' => $status, 'durationMs' => $duration_ms, 'elementCount' => $element_count);

        set_transient($cache_key, array(
            'data' => $data,
            'fetchedAt' => $now,
            'lat' => $lat,
            'lng' => $lng,
        ), $is_empty ? 5 * MINUTE_IN_SECONDS : DAY_IN_SECONDS);

        // Keep one nearby last-known-good pool so ordinary GPS drift cannot turn
        // a successful first page load into three blank OSM modules on reload.
        if (!$is_empty) {
            $region_lat = ruhrpott_guru_osm_region_coord($lat);
            $region_lng = ruhrpott_guru_osm_region_coord($lng);
            set_transient(ruhrpott_guru_osm_region_key($region_lat, $region_lng, $radius_km, $categories), array(
                'data' => $data,
                'fetchedAt' => $now,
                'lat' => $lat,
                'lng' => $lng,
            ), DAY_IN_SECONDS);
        }

        delete_option($lock_key);
        return new WP_REST_Response(array(
            'ok' => true,
            'data' => $data,
            '_rg' => array(
                'upstream' => $mirror,
                'cache' => 'MISS',
                'elementCount' => $element_count,
                'radiusKm' => $radius_km,
                'acquisitionRadiusKm' => $acquisition_radius_km,
                'cacheLat' => $cache_lat,
                'cacheLng' => $cache_lng,
                'categories' => $categories,
                'empty' => $is_empty,
                'attempts' => $attempts,
            ),
        ), 200);
    }

    // Exact stale data remains the first fallback after a refresh failure.
    if ($stale_candidate !== null) {
        delete_option($lock_key);
        return new WP_REST_Response(array(
            'ok' => true,
            'data' => $stale_candidate['data'],
            '_rg' => array(
                'upstream' => 'wordpress-transient-cache',
                'cache' => 'STALE',
                'cacheAgeMs' => $stale_candidate['age'] * 1000,
                'liveFailure' => $last_error_type,
                'radiusKm' => $radius_km,
                'categories' => $categories,
                'empty' => false,
                'attempts' => $attempts,
            ),
        ), 200);
    }

    // If GPS moved into a neighbouring cache cell, keep the last successful OSM
    // pool visible instead of blanking all three modules just because a public
    // provider is temporarily unavailable.
    $nearby = ruhrpott_guru_osm_find_nearby_success($lat, $lng, $radius_km, $categories);
    if ($nearby !== null) {
        delete_option($lock_key);
        return new WP_REST_Response(array(
            'ok' => true,
            'data' => $nearby['data'],
            '_rg' => array(
                'upstream' => 'wordpress-nearby-cache',
                'cache' => 'STALE_NEARBY',
                'cacheAgeMs' => max(0, $now - $nearby['fetchedAt']) * 1000,
                'liveFailure' => $last_error_type,
                'nearbyDistanceKm' => round($nearby['distanceKm'], 3),
                'radiusKm' => $radius_km,
                'categories' => $categories,
                'empty' => false,
                'attempts' => $attempts,
            ),
        ), 200);
    }

    delete_option($lock_key);
    return new WP_REST_Response(array(
        'ok' => false,
        'errorType' => $last_error_type,
        'message' => $last_message,
        'attempts' => $attempts,
    ), 502);
}

/**
 * Filter script tags to add type="module" for the compiled React bundle
 */
function ruhrpott_script_loader_tag_module($tag, $handle, $src) {
    if ('ruhrpott-compiled-js' === $handle) {
        if (strpos($tag, 'type="module"') !== false) {
            return $tag;
        }
        return '<script type="module" src="' . esc_url($src) . '" id="' . esc_attr($handle) . '-js"></script>' . "\n";
    }
    return $tag;
}
add_filter('script_loader_tag', 'ruhrpott_script_loader_tag_module', 10, 3);

/**
 * Register Custom Post Types: Angebote & Orte (ruhrpott_angebot)
 */
function ruhrpott_register_cpt_angebot() {
    $labels = array(
        'name'                  => _x('Angebote & Orte', 'Post Type General Name', 'ruhrpott-guru'),
        'singular_name'         => _x('Angebot / Ort', 'Post Type Singular Name', 'ruhrpott-guru'),
        'menu_name'             => __('Ruhrpott Angebote', 'ruhrpott-guru'),
        'add_new'               => __('Neuer Ort / Angebot', 'ruhrpott-guru'),
        'add_new_item'          => __('Neues Angebot hinzufügen', 'ruhrpott-guru'),
        'edit_item'             => __('Angebot bearbeiten', 'ruhrpott-guru'),
        'all_items'             => __('Alle Angebote', 'ruhrpott-guru'),
    );

    $args = array(
        'label'                 => __('Angebot', 'ruhrpott-guru'),
        'description'           => __('Gastronomie, Zechen, Kultur-Tipps, Parks & Imbisse', 'ruhrpott-guru'),
        'labels'                => $labels,
        'supports'              => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'),
        'taxonomies'            => array('ruhrpott_stadt', 'ruhrpott_kategorie'),
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'menu_icon'             => 'dashicons-location-alt',
        'show_in_rest'          => true,
        'has_archive'           => true,
    );
    register_post_type('ruhrpott_angebot', $args);
}
add_action('init', 'ruhrpott_register_cpt_angebot', 0);

/**
 * Register Taxonomies (Städte & Kategorien)
 */
function ruhrpott_register_taxonomies() {
    // Taxonomie: Ruhrgebiet Stadt
    register_taxonomy('ruhrpott_stadt', array('ruhrpott_angebot'), array(
        'labels' => array(
            'name' => 'Städte (Revier)',
            'singular_name' => 'Stadt',
        ),
        'hierarchical' => true,
        'show_ui' => true,
        'show_in_rest' => true,
    ));

    // Taxonomie: Kategorie
    register_taxonomy('ruhrpott_kategorie', array('ruhrpott_angebot'), array(
        'labels' => array(
            'name' => 'Kategorien',
            'singular_name' => 'Kategorie',
        ),
        'hierarchical' => true,
        'show_ui' => true,
        'show_in_rest' => true,
    ));
}
add_action('init', 'ruhrpott_register_taxonomies', 0);

/**
 * Register Shortcodes for WordPress Navigation Pages
 */

// Master Shortcode: [ruhrpott_app category="Gastronomie" view="default" post_id="123"]
function ruhrpott_app_shortcode($atts) {
    $a = shortcode_atts(array(
        'category'     => 'Alle',
        'view'         => 'default',
        'radius'       => '2.5',
        'hide_header'  => 'false',
        'hide_footer'  => 'false',
        'post_id'      => '',
        'top_title'    => '',
        'top_text'     => '',
        'bottom_title' => '',
        'bottom_text'  => '',
        'bottom_url'   => '',
        'direct_token'  => '',
        'direct_category' => '',
        'direct_overview' => '',
    ), $atts);

    $cat = esc_attr($a['category']);
    $view = esc_attr($a['view']);
    $radius = esc_attr($a['radius']);
    $hide_header = esc_attr($a['hide_header']);
    $hide_footer = esc_attr($a['hide_footer']);
    $direct_token = esc_attr($a['direct_token']);
    $direct_category = esc_attr($a['direct_category']);
    $direct_overview = esc_url($a['direct_overview']);

    // P0: Read-only, backend-owned HTML preview for successful canonical detail URLs.
    // The backend supplies this only for the exact already-resolved event token.
    // Normal WordPress pages and internal detail layers are unaffected.
    $direct_initial_html = ($view === 'direct-detail' && preg_match('/^[ep][1-9][0-9]*$/', $direct_token))
        ? (string) apply_filters('rpg_direct_detail_initial_html', '', $direct_token)
        : '';
    $has_direct_initial_html = trim($direct_initial_html) !== '';

    // Determine target post ID for top box (explicit post_id or active singular post)
    $wp_top_title = esc_attr($a['top_title']);
    $wp_top_text = esc_attr($a['top_text']);
    $wp_top_image = '';
    $wp_top_url = '';
    $wp_top_date = '';

    $target_post_id = !empty($a['post_id']) ? $a['post_id'] : (is_singular() ? get_the_ID() : 0);
    if ($target_post_id) {
        if (!is_numeric($target_post_id)) {
            $page_obj = get_page_by_path($target_post_id, OBJECT, array('post', 'page'));
            if ($page_obj) $target_post_id = $page_obj->ID;
        }
        $post_obj = get_post($target_post_id);
        if ($post_obj) {
            if (empty($wp_top_title)) {
                $wp_top_title = esc_attr(get_the_title($post_obj->ID));
            }
            if (empty($wp_top_text)) {
                $excerpt = get_the_excerpt($post_obj->ID);
                if (empty($excerpt)) {
                    $excerpt = wp_trim_words(strip_shortcodes($post_obj->post_content), 35, '...');
                }
                $wp_top_text = esc_attr($excerpt);
            }
            $wp_top_image = esc_url(get_the_post_thumbnail_url($post_obj->ID, 'large') ?: '');
            $wp_top_url = esc_url(get_permalink($post_obj->ID));
            $wp_top_date = esc_attr(get_the_date('d.m.Y', $post_obj->ID));
        }
    }

    // Fetch latest WP Post for bottom slot if not explicitly provided
    $wp_bottom_title = esc_attr($a['bottom_title']);
    $wp_bottom_text = esc_attr($a['bottom_text']);
    $wp_bottom_url = esc_attr($a['bottom_url']);

    if (empty($wp_bottom_title)) {
        $recent_posts = get_posts(array('numberposts' => 1, 'post_status' => 'publish'));
        if (!empty($recent_posts)) {
            $recent = $recent_posts[0];
            $wp_bottom_title = esc_attr(get_the_title($recent->ID));
            $wp_bottom_text = esc_attr(get_the_excerpt($recent->ID) ?: wp_trim_words(strip_shortcodes($recent->post_content), 25, '...'));
            $wp_bottom_url = esc_url(get_permalink($recent->ID));
        }
    }

    ob_start();
    ?>
    <?php if ($has_direct_initial_html): ?>
    <div id="rpgx-direct-ssr" class="rpgx-direct-ssr">
        <?php echo wp_kses_post($direct_initial_html); ?>
    </div>
    <?php endif; ?>
    <div id="ruhrpott-guru-root"
         <?php if ($has_direct_initial_html): ?>data-rpg-direct-ssr="1"<?php endif; ?>
         data-category="<?php echo $cat; ?>" 
         data-view="<?php echo $view; ?>" 
         data-radius="<?php echo $radius; ?>" 
         data-hide-header="<?php echo $hide_header; ?>" 
         data-hide-footer="<?php echo $hide_footer; ?>"
         data-direct-token="<?php echo $direct_token; ?>"
         data-direct-category="<?php echo $direct_category; ?>"
         data-direct-overview="<?php echo $direct_overview; ?>"
         data-wp-title="<?php echo $wp_top_title; ?>"
         data-wp-text="<?php echo $wp_top_text; ?>"
         data-wp-image="<?php echo $wp_top_image; ?>"
         data-wp-url="<?php echo $wp_top_url; ?>"
         data-wp-date="<?php echo $wp_top_date; ?>"
         data-bottom-title="<?php echo $wp_bottom_title; ?>"
         data-bottom-text="<?php echo $wp_bottom_text; ?>"
         data-bottom-url="<?php echo $wp_bottom_url; ?>"
         class="w-full min-h-[600px] bg-slate-950 text-slate-100 rounded-2xl overflow-visible shadow-2xl p-2 sm:p-4 my-4">
        <div class="flex items-center justify-center p-12 text-slate-400 font-bold text-sm" data-rpg-initial-loader>
            <i data-lucide="loader-2" class="w-6 h-6 animate-spin text-amber-500 mr-2"></i>
            PottFuchs App wird geladen (OpenData & OSM Data API)...
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('ruhrpott_app', 'ruhrpott_app_shortcode');

// Individual Navigation Shortcodes
add_shortcode('ruhrpott_veranstaltungen', function() {
    return ruhrpott_app_shortcode(array('category' => 'Veranstaltungen'));
});

add_shortcode('ruhrpott_gastronomie', function() {
    return ruhrpott_app_shortcode(array('category' => 'Gastronomie'));
});

add_shortcode('ruhrpott_highlights', function() {
    return ruhrpott_app_shortcode(array('category' => 'Highlights'));
});

add_shortcode('ruhrpott_kultur', function() {
    return ruhrpott_app_shortcode(array('category' => 'Kultur'));
});

add_shortcode('ruhrpott_aktivitaeten', function() {
    return ruhrpott_app_shortcode(array('category' => 'Aktivitäten'));
});

add_shortcode('ruhrpott_guides', function() {
    return ruhrpott_app_shortcode(array('category' => 'Guides'));
});

add_shortcode('ruhrpott_karte', function() {
    return ruhrpott_app_shortcode(array('view' => 'karte'));
});

// V169: Dedicated Pott-Quiz page shortcode. Keep the legacy underscore tag
// as a backwards-compatible alias so existing pages/bookmarks do not break.
add_shortcode('ruhrpott-pottquiz', function() {
    return ruhrpott_app_shortcode(array('view' => 'quiz'));
});

add_shortcode('ruhrpott_pott_quiz', function() {
    return ruhrpott_app_shortcode(array('view' => 'quiz'));
});

/**
 * V276 – Seasonal special: Halloween im Pott.
 *
 * Theme-only view: it reuses the existing event endpoint, location state and
 * detail overlay. No Places Backend write path or worker is touched.
 */
add_shortcode('ruhrpott_halloween', function() {
    return ruhrpott_app_shortcode(array('view' => 'halloween'));
});

/**
 * Ensure the seasonal landing page exists after the theme update. Existing
 * pages are never overwritten. The one-time option prevents a deliberately
 * deleted page from being recreated on every request.
 */
function ruhrpott_guru_v276_ensure_halloween_page() {
    if (get_option('rpg_v276_halloween_page_checked')) return;
    $existing = get_page_by_path('halloween-im-pott', OBJECT, 'page');
    if (!$existing) {
        wp_insert_post(array(
            'post_title'   => 'Halloween im Pott',
            'post_name'    => 'halloween-im-pott',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[ruhrpott_halloween]',
        ));
    }
    update_option('rpg_v276_halloween_page_checked', '1', false);
}
add_action('init', 'ruhrpott_guru_v276_ensure_halloween_page', 30);

/**
 * V352 – Cities that currently have active events without coordinates (for
 * example approved municipal calendars). The backend returns such events only
 * when their city is selected, so the Martin special asks for these cities
 * explicitly. Derived from the event table, cached for six hours, no manual list.
 */
function ruhrpott_guru_city_scope_cities() {
    $cache_key = 'rpg_city_scope_cities_v1';
    $cached = get_transient($cache_key);
    if (is_array($cached)) return $cached;
    global $wpdb;
    $cities = array();
    $table = $wpdb->prefix . 'rg_events';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
        $rows = $wpdb->get_col(
            "SELECT DISTINCT city FROM {$table}
             WHERE status = 'active' AND city <> '' AND (lat IS NULL OR lng IS NULL)
               AND (end_at >= UTC_TIMESTAMP() OR (end_at IS NULL AND start_at >= UTC_TIMESTAMP()))
             ORDER BY city ASC LIMIT 20"
        );
        if (is_array($rows)) {
            foreach ($rows as $city) {
                $city = trim((string) $city);
                if ($city !== '') $cities[] = $city;
            }
        }
    }
    set_transient($cache_key, $cities, 6 * HOUR_IN_SECONDS);
    return $cities;
}

/**
 * V352 – Seasonal special: Martinszüge & Martinsfeste. Existing pages are never
 * overwritten; the one-time option keeps a deliberately deleted page deleted.
 * The body is only an editorial placeholder (not rendered), the excerpt is the
 * meta description source like on the other special pages.
 */
function ruhrpott_guru_v352_ensure_martin_page() {
    if (get_option('rpg_v352_martin_page_checked')) return;
    $existing = get_page_by_path('martinszuege-im-pott', OBJECT, 'page');
    if (!$existing) {
        wp_insert_post(array(
            'post_title'   => 'Martinszüge & Martinsfeste im Pott',
            'post_name'    => 'martinszuege-im-pott',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_excerpt' => 'Martinszüge, Martinsumzüge, Laternenumzüge, Martinsmärkte und Martinikirmes im Ruhrgebiet: alle Termine rund um den Martinstag auf einen Blick.',
            'post_content' => '<p>Martinszüge und Martinsfeste im Ruhrgebiet. Die Terminliste lädt die aktuellen PottFuchs-Events.</p>',
        ));
    }
    update_option('rpg_v352_martin_page_checked', '1', false);
}
add_action('init', 'ruhrpott_guru_v352_ensure_martin_page', 30);


/**
 * V39 – Modular WordPress Home page.
 *
 * These shortcodes intentionally render as ordinary WordPress page content.
 * They do NOT replace the current live / React home route. Dynamic live modules
 * are hydrated only when their explicit V39 placeholder is present.
 */
function ruhrpott_guru_home_category_items() {
    $items = function_exists('ruhrpott_guru_get_footer_category_menu_items')
        ? ruhrpott_guru_get_footer_category_menu_items()
        : array();

    if (!empty($items)) {
        return $items;
    }

    return array(
        array('title' => 'Events',          'url' => home_url('/veranstaltungen/'), 'category' => 'events'),
        array('title' => 'Food & Drinks',   'url' => home_url('/gastronomie/'),     'category' => 'food'),
        array('title' => 'Highlights',       'url' => home_url('/highlights/'),      'category' => 'highlights'),
        array('title' => 'Kultur & Szene',  'url' => home_url('/kultur/'),          'category' => 'culture'),
        array('title' => 'Aktiv',            'url' => home_url('/aktivitaeten/'),     'category' => 'activities'),
    );
}

function ruhrpott_guru_home_term_ids($slugs = array(), $names = array()) {
    $ids = array();
    foreach ((array) $slugs as $slug) {
        $term = get_category_by_slug($slug);
        if ($term && !is_wp_error($term)) {
            $ids[] = (int) $term->term_id;
        }
    }
    foreach ((array) $names as $name) {
        $term = get_term_by('name', $name, 'category');
        if ($term && !is_wp_error($term)) {
            $ids[] = (int) $term->term_id;
        }
    }
    return array_values(array_unique(array_filter($ids)));
}

function ruhrpott_guru_home_categories_shortcode() {
    $items = ruhrpott_guru_home_category_items();
    $meta = array(
        'events'     => array('emoji' => '📅', 'subtitle' => 'Konzerte, Märkte, Festivals & mehr'),
        'food'       => array('emoji' => '🍔', 'subtitle' => 'Essen, Trinken & echte Pott-Adressen'),
        'highlights' => array('emoji' => '👀', 'subtitle' => 'Must-Sees, Industriekultur & Natur'),
        'culture'    => array('emoji' => '🎨', 'subtitle' => 'Museen, Bühnen, Streetart & Szene'),
    );

    ob_start();
    ?>
    <section class="rg-home-module rg-home-categories" aria-label="PottFuchs Kategorien">
        <div class="rg-home-category-grid">
            <?php foreach ($items as $item) :
                $key = isset($item['category']) ? sanitize_key($item['category']) : '';
                if (!isset($meta[$key])) continue;
                $page_id = url_to_postid($item['url']);
                $image = $page_id ? get_the_post_thumbnail_url($page_id, 'large') : '';
                $style = $image ? ' style="--rg-home-tile-image:url(\'' . esc_url($image) . '\')"' : '';
            ?>
                <a class="rg-home-category-tile rg-home-category-<?php echo esc_attr($key); ?>" href="<?php echo esc_url($item['url']); ?>"<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                    <span class="rg-home-category-shade" aria-hidden="true"></span>
                    <span class="rg-home-category-emoji" aria-hidden="true"><?php echo esc_html($meta[$key]['emoji']); ?></span>
                    <span class="rg-home-category-copy">
                        <strong><?php echo esc_html($item['title']); ?></strong>
                        <small><?php echo esc_html($meta[$key]['subtitle']); ?></small>
                    </span>
                    <span class="rg-home-category-arrow" aria-hidden="true">→</span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode('ruhrpott_home_categories', 'ruhrpott_guru_home_categories_shortcode');

function ruhrpott_guru_home_guru_tipp_shortcode() {
    $term_ids = ruhrpott_guru_home_term_ids(array('guru-tipp', 'guru-tip'), array('Guru-Tipp', 'Guru Tipp'));
    // V41: Editorial modules are opt-in. If no matching editorial category exists,
    // render nothing instead of falling back to an unrelated latest post.
    if (empty($term_ids)) return '';

    $args = array(
        'numberposts' => 1,
        'post_status' => 'publish',
        'post_type'   => 'post',
        'category__in' => $term_ids,
    );
    $posts = get_posts($args);
    if (empty($posts)) return '';

    $post = $posts[0];
    $image = get_the_post_thumbnail_url($post->ID, 'large');
    $excerpt = get_the_excerpt($post->ID);
    if (!$excerpt) {
        $excerpt = wp_trim_words(wp_strip_all_tags(strip_shortcodes($post->post_content)), 32, ' …');
    }

    ob_start();
    ?>
    <section class="rg-home-module rg-home-guru-tip" aria-label="Guru-Tipp des Tages">
        <a class="rg-home-feature" href="<?php echo esc_url(get_permalink($post->ID)); ?>">
            <?php if ($image) : ?>
                <img src="<?php echo esc_url($image); ?>" alt="" loading="lazy" decoding="async">
            <?php endif; ?>
            <span class="rg-home-feature-overlay" aria-hidden="true"></span>
            <span class="rg-home-feature-content">
                <span class="rg-home-kicker">📰 Guru-Tipp des Tages</span>
                <strong><?php echo esc_html(get_the_title($post->ID)); ?></strong>
                <?php if ($excerpt) : ?><span class="rg-home-feature-excerpt"><?php echo esc_html($excerpt); ?></span><?php endif; ?>
                <span class="rg-home-feature-link">Artikel lesen →</span>
            </span>
        </a>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode('ruhrpott_guru_tipp', 'ruhrpott_guru_home_guru_tipp_shortcode');

function ruhrpott_guru_home_naehkaestchen_shortcode($atts) {
    $atts = shortcode_atts(array('count' => 2), $atts, 'ruhrpott_naehkaestchen');
    $count = max(1, min(4, (int) $atts['count']));
    $term_ids = ruhrpott_guru_home_term_ids(
        array('ausm-naehkaestchen', 'aus-dem-naehkaestchen', 'naehkaestchen', 'pott-gefluester', 'interviews'),
        array("Aus'm Nähkästchen", 'Aus dem Nähkästchen', 'Nähkästchen', 'Pott-Geflüster', 'Interviews')
    );

    if (empty($term_ids)) return '';

    $posts = get_posts(array(
        'numberposts' => $count,
        'post_status' => 'publish',
        'post_type'   => 'post',
        'category__in' => $term_ids,
    ));
    if (empty($posts)) return '';

    ob_start();
    ?>
    <section class="rg-home-module rg-home-naehkaestchen" aria-label="Aus'm Nähkästchen">
        <div class="rg-home-section-head">
            <div><span class="rg-home-kicker">🎤 Aus'm Nähkästchen</span><h2>Geschichten aus'm Revier</h2></div>
        </div>
        <div class="rg-home-story-grid">
            <?php foreach ($posts as $post) :
                $image = get_the_post_thumbnail_url($post->ID, 'medium_large');
                $excerpt = get_the_excerpt($post->ID);
                if (!$excerpt) $excerpt = wp_trim_words(wp_strip_all_tags(strip_shortcodes($post->post_content)), 18, ' …');
            ?>
                <article class="rg-home-story-card">
                    <a href="<?php echo esc_url(get_permalink($post->ID)); ?>">
                        <?php if ($image) : ?><img src="<?php echo esc_url($image); ?>" alt="" loading="lazy" decoding="async"><?php endif; ?>
                        <span class="rg-home-story-body">
                            <strong><?php echo esc_html(get_the_title($post->ID)); ?></strong>
                            <?php if ($excerpt) : ?><small><?php echo esc_html($excerpt); ?></small><?php endif; ?>
                            <span>Weiterlesen →</span>
                        </span>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode('ruhrpott_naehkaestchen', 'ruhrpott_guru_home_naehkaestchen_shortcode');


/**
 * V97 – Editorial Home inserts used by the source-owned React Home.
 *
 * These helpers deliberately read genuine WordPress posts instead of baking
 * article copy into React. This keeps the editorial modules portable and
 * editable in WordPress while letting React place them between live modules.
 */



/**
 * V41 – Individual magazine-style Home category modules.
 *
 * [ruhrpott_home_categories] remains untouched as the established complete
 * React Home feed/fallback. These four shortcodes are independent Gutenberg
 * modules and are hydrated from the same read-only Home API/data snapshots.
 */
function ruhrpott_guru_home_category_shortcode($category_key) {
    $category_key = sanitize_key($category_key);
    $allowed = array('events', 'food', 'highlights', 'culture', 'activities');
    if (!in_array($category_key, $allowed, true)) return '';

    $menu_items = ruhrpott_guru_home_category_items();
    $menu_item = null;
    foreach ($menu_items as $item) {
        if (isset($item['category']) && sanitize_key($item['category']) === $category_key) {
            $menu_item = $item;
            break;
        }
    }

    $meta = array(
        'events' => array(
            'title' => 'Events',
            'subtitle' => 'Konzerte, Märkte, Festivals & mehr',
            'emoji' => '📅',
        ),
        'food' => array(
            'title' => 'Food & Drinks',
            'subtitle' => 'Gastronomie, Cafés, Buden & Bars',
            'emoji' => '🍔',
        ),
        'highlights' => array(
            'title' => 'Highlights',
            'subtitle' => 'Besondere Orte, Industriekultur & Ausblicke',
            'emoji' => '👀',
        ),
        'culture' => array(
            'title' => 'Kultur & Szene',
            'subtitle' => 'Museen, Streetart, Theater & Kleinkunst',
            'emoji' => '🎨',
        ),
        'activities' => array(
            'title' => 'Aktivitäten',
            'subtitle' => 'Action, Wasser, Outdoor & besondere Erlebnisse',
            'emoji' => '🚴',
        ),
    );

    $current = $meta[$category_key];
    $title = !empty($menu_item['title']) ? $menu_item['title'] : $current['title'];
    $url = !empty($menu_item['url']) ? $menu_item['url'] : home_url('/');

    ob_start();
    ?>
    <section
        class="rg-home-module rg-home-category-module rg-home-category-module-<?php echo esc_attr($category_key); ?>"
        data-rg-home-category="<?php echo esc_attr($category_key); ?>"
        data-rg-home-url="<?php echo esc_url(home_url('/')); ?>"
        aria-label="<?php echo esc_attr($title); ?>"
    >
        <div class="rg-home-category-section-head">
            <div class="rg-home-category-section-title">
                <h2><?php echo esc_html($title); ?></h2>
                <p><?php echo esc_html($current['subtitle']); ?></p>
            </div>
            <div class="rg-home-category-section-actions">
                <button type="button" class="rg-home-live-location rg-home-location-trigger" data-rg-home-location data-rg-open-location aria-label="Standort ändern"></button>
                <a class="rg-home-all-button" href="<?php echo esc_url($url); ?>">Alle ansehen <span aria-hidden="true">→</span></a>
            </div>
        </div>
        <div class="rg-home-category-module-body" data-rg-home-category-body>
            <div class="rg-home-live-loading"><span class="rg-home-spinner" aria-hidden="true"></span>Live-Daten werden geladen …</div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

add_shortcode('ruhrpott_home_events', function() {
    return ruhrpott_guru_home_category_shortcode('events');
});
add_shortcode('ruhrpott_home_food', function() {
    return ruhrpott_guru_home_category_shortcode('food');
});
add_shortcode('ruhrpott_home_highlights', function() {
    return ruhrpott_guru_home_category_shortcode('highlights');
});
add_shortcode('ruhrpott_home_kultur', function() {
    return ruhrpott_guru_home_category_shortcode('culture');
});
add_shortcode('ruhrpott_home_aktiv', function() {
    return ruhrpott_guru_home_category_shortcode('activities');
});


/**
 * V89: Official Route Industriekultur collection page.
 * The page is created once if it does not exist; existing editorial content is
 * never overwritten. Data hydration comes from Places Backend 1.2.2.
 */
function ruhrpott_guru_ensure_route_industriekultur_page() {
    $slug = 'route-industriekultur';
    $page = get_page_by_path($slug, OBJECT, 'page');
    if (!$page) {
        $id = wp_insert_post(array(
            'post_title'   => 'Route der Industriekultur',
            'post_name'    => $slug,
            'post_content' => '[ruhrpott_route_industriekultur]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ), true);
        if (!is_wp_error($id) && $id) update_option('rg_route_industriekultur_page_id', (int)$id, false);
        return;
    }
    update_option('rg_route_industriekultur_page_id', (int)$page->ID, false);
    if (trim((string)$page->post_content) === '') {
        wp_update_post(array('ID'=>$page->ID,'post_content'=>'[ruhrpott_route_industriekultur]'));
    }
}
add_action('init', 'ruhrpott_guru_ensure_route_industriekultur_page', 40);

function ruhrpott_guru_route_industriekultur_shortcode() {
    ob_start();
    ?>
    <section class="rg-route57" data-rg-industrial-route aria-label="Route der Industriekultur">
        <header class="rg-route57-hero">
            <span class="rg-route57-kicker">🏭 57 offizielle Primärstandorte</span>
            <h1>Route der Industriekultur</h1>
            <p>27 Ankerpunkte, 17 Panoramen und 13 Siedlungen quer durch das Ruhrgebiet. Nach deinem Standort sortiert, soweit ein Ort bereits mit unserer Places-Datenbank verknüpft ist.</p>
            <div class="rg-route57-stats" data-rg-route-stats>Route wird geladen …</div>
        </header>
        <div class="rg-route57-controls">
            <div class="rg-route57-filters" role="group" aria-label="Typ filtern">
                <button type="button" class="is-active" data-rg-route-filter="all">Alle 57</button>
                <button type="button" data-rg-route-filter="anchor">Ankerpunkte</button>
                <button type="button" data-rg-route-filter="panorama">Panoramen</button>
                <button type="button" data-rg-route-filter="settlement">Siedlungen</button>
            </div>
            <label class="rg-route57-city-label">
                <span>Stadt</span>
                <select data-rg-route-city><option value="">Alle Städte</option></select>
            </label>
        </div>
        <div class="rg-route57-grid" data-rg-route-grid>
            <div class="rg-route57-loading"><span class="rg-home-spinner" aria-hidden="true"></span> Route Industriekultur wird geladen …</div>
        </div>
        <footer class="rg-route57-source">
            <span>Grundlage: die 57 Primärstandorte der offiziellen Route Industriekultur des Regionalverbands Ruhr.</span>
            <a href="https://www.route-industriekultur.ruhr/" target="_blank" rel="noopener noreferrer">Offizielle Route öffnen ↗</a>
        </footer>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode('ruhrpott_route_industriekultur', 'ruhrpott_guru_route_industriekultur_shortcode');


/**
 * V176: Unser Pott bei Nacht.
 * Uses the existing RuhrpottGuru location snapshot; no second automatic GPS prompt.
 */
function ruhrpott_guru_ensure_night_page() {
    $slug='unser-pott-bei-nacht';
    $page=get_page_by_path($slug,OBJECT,'page');
    if(!$page){
        $id=wp_insert_post(array(
            'post_title'=>'Unser Pott bei Nacht',
            'post_name'=>$slug,
            'post_content'=>'[ruhrpott_nacht]',
            'post_status'=>'publish',
            'post_type'=>'page',
        ),true);
        if(!is_wp_error($id)&&$id)update_option('rg_night_page_id',(int)$id,false);
        return;
    }
    update_option('rg_night_page_id',(int)$page->ID,false);
    if(trim((string)$page->post_content)==='')wp_update_post(array('ID'=>$page->ID,'post_content'=>'[ruhrpott_nacht]'));
}
add_action('init','ruhrpott_guru_ensure_night_page',42);

function ruhrpott_guru_night_assets() {
    if(!is_singular())return;
    global $post;
    if(!$post || !has_shortcode((string)$post->post_content,'ruhrpott_nacht'))return;
    $css=get_template_directory().'/css/rpg-night.css';
    $js=get_template_directory().'/js/rpg-night.js';
    wp_enqueue_style('rpg-night',get_template_directory_uri().'/css/rpg-night.css',array('ruhrpott-guru-style'),file_exists($css)?filemtime($css):'178');
    wp_enqueue_script('rpg-night',get_template_directory_uri().'/js/rpg-night.js',array('ruhrpott-compiled-js'),file_exists($js)?filemtime($js):'183',true);
}
add_action('wp_enqueue_scripts','ruhrpott_guru_night_assets',30);

function ruhrpott_guru_night_shortcode() {
    ob_start(); ?>
    <section class="rg-night" data-rg-night>
        <header class="rg-night-hero">
            <span class="rg-night-kicker">🌙 Ruhrgebiet nach Sonnenuntergang</span>
            <h1>Unser Pott bei Nacht</h1>
            <p>Landmarken, Industriekultur, Aussicht und besondere Orte rund um deinen Standort – mit echten Nachtfotos, sobald sie für eine Location freigegeben wurden.</p>
            <div class="rpgx-wurst-location-card rg-night-location-card">
                <div><span>📍</span><div><small>Mein Standort</small><strong data-rg-night-location>Standort wird übernommen …</strong></div></div>
                <button type="button" data-rg-night-location-change>Standort ändern</button>
            </div>
            <div class="rpgx-wurst-radius rg-night-radius">
                <small>Radius</small>
                <div>
                    <button type="button" data-rg-night-radius="1">1 km</button>
                    <button type="button" data-rg-night-radius="2.5">2,5 km</button>
                    <button type="button" data-rg-night-radius="5">5 km</button>
                    <button type="button" data-rg-night-radius="10">10 km</button>
                    <button type="button" data-rg-night-radius="25">25 km</button>
                    <button type="button" data-rg-night-radius="50">50 km</button>
                </div>
            </div>
        </header>

        <div class="rg-night-toolbar">
            <div class="rg-night-filters" role="group" aria-label="Nacht-Ergebnisse filtern">
                <button type="button" class="is-active" data-night-filter="all">Alle</button>
                <button type="button" data-night-filter="light">Lichter</button>
                <button type="button" data-night-filter="view">Aussicht</button>
                <button type="button" data-night-filter="industrial">Industriekultur</button>
                <button type="button" data-night-filter="photo">Mit Nachtfoto</button>
            </div>
        </div>

        <div class="rg-night-status" data-rg-night-status>Nachtergebnisse werden geladen …</div>
        <div class="rg-night-grid" data-rg-night-grid></div>

        <div class="rg-night-safety">
            <strong>Wichtig:</strong> Ein Ort kann nachts besonders sehenswert sein, ohne nachts geöffnet oder frei zugänglich zu sein.
            Öffnungszeiten und Zugang bitte vor dem Besuch prüfen. Bei unbekannter Zugänglichkeit kennzeichnet PottFuchs das ausdrücklich.
        </div>
    </section>

    <!-- V179: Standortdialog wird vom globalen RuhrpottGuru LocationModal bereitgestellt. -->

    <div class="rg-night-detail" data-rg-night-detail hidden>
        <div class="rg-night-detail-card" role="dialog" aria-modal="true" aria-labelledby="rg-night-detail-title">
            <button type="button" class="rg-night-close" data-rg-night-detail-close aria-label="Schließen">×</button>
            <div class="rg-night-detail-hero" data-rg-night-detail-hero></div>
            <div class="rg-night-detail-body">
                <div class="rg-night-detail-head">
                    <div><span class="rg-night-kicker">🌙 Bei Nacht</span><h2 id="rg-night-detail-title" data-rg-night-detail-title>Location</h2></div>
                    <div class="rg-night-detail-count" data-rg-night-detail-count></div>
                </div>
                <div class="rg-night-detail-meta" data-rg-night-detail-meta></div>
                <p class="rg-night-detail-reason" data-rg-night-detail-reason></p>
                <div class="rg-night-detail-access" data-rg-night-detail-access></div>
                <div class="rg-night-detail-gallery-wrap">
                    <h3>Nachtfotos</h3>
                    <div class="rg-night-detail-gallery" data-rg-night-detail-gallery><div class="rg-night-empty">Nachtfotos werden geladen …</div></div>
                    <div class="rg-night-detail-credit" data-rg-night-detail-credit></div>
                </div>
                <div class="rg-night-detail-actions">
                    <button type="button" class="rg-night-upload" data-rg-night-detail-upload>📷 Nachtfoto hinzufügen</button>
                    <a class="rg-night-site" data-rg-night-detail-website href="#" target="_blank" rel="noopener noreferrer" hidden>Website</a>
                </div>
            </div>
        </div>
    </div>

    <div class="rg-night-modal" data-rg-night-modal hidden>
        <div class="rg-night-modal-card" role="dialog" aria-modal="true" aria-labelledby="rg-night-upload-title">
            <button type="button" class="rg-night-close" data-rg-night-close aria-label="Schließen">×</button>
            <h2 id="rg-night-upload-title">Nachtfoto hinzufügen</h2>
            <p class="rg-night-modal-place" data-rg-night-modal-place></p>
            <form data-rg-night-upload-form enctype="multipart/form-data">
                <input type="hidden" name="place_id" data-rg-night-place-id>
                <input type="hidden" name="rights_confirmed" value="1">
                <input type="hidden" name="fields_json" value="{}">
                <label>Dein Name
                    <input type="text" name="submitter_name" required maxlength="190">
                </label>
                <label>E-Mail-Adresse
                    <input type="email" name="submitter_email" required maxlength="190">
                </label>
                <label>Nachtfoto
                    <input type="file" name="image_0" required accept="image/*,.heic,.heif">
                </label>
                <label>Urheber / Copyright
                    <input type="text" name="copyright_0" required maxlength="255" placeholder="z. B. Max Mustermann">
                </label>
                <label>Optionale Quelle / Website
                    <input type="url" name="source_url_0" placeholder="https://">
                </label>
                <label class="rg-night-check">
                    <input type="checkbox" required>
                    <span>Ich bin zur Übermittlung des Fotos berechtigt und darf PottFuchs die Nutzung auf der Website erlauben.</span>
                </label>
                <p class="rg-night-form-note">Bitte möglichst keine erkennbaren Personen fotografieren. Der Ort und die Nachtstimmung sollen im Mittelpunkt stehen. Das Bild wird erst nach E-Mail-Bestätigung und redaktioneller Freigabe veröffentlicht.</p>
                <button type="submit" class="rg-night-submit">Foto zur Prüfung senden</button>
                <div class="rg-night-form-status" data-rg-night-form-status></div>
            </form>
        </div>
    </div>
    <?php return ob_get_clean();
}
add_shortcode('ruhrpott_nacht','ruhrpott_guru_night_shortcode');


/**
 * V126 – Büdchen- & Trinkhallen-Radar.
 * The shortcode is deliberately only a mount marker. Header/footer/location
 * remain owned by the existing RuhrpottGuru shell.
 */
function ruhrpott_guru_buedchen_radar_shortcode() {
    return '<div class="rg-buedchen-radar-mount" data-rg-buedchen-radar="1"></div>';
}
add_shortcode('ruhrpott_buedchen_radar', 'ruhrpott_guru_buedchen_radar_shortcode');

/**
 * V150 – Hier geht's um die Wurst.
 * The shortcode stays a mount marker so the global RuhrpottGuru shell keeps
 * ownership of header, footer and the persistent Standort/Radius state.
 */
function ruhrpott_guru_imbissbuden_shortcode() {
    return '<div class="rg-wurst-mount" data-rg-wurst-page="1"></div>';
}
add_shortcode('ruhrpott_imbissbuden', 'ruhrpott_guru_imbissbuden_shortcode');



function ruhrpott_guru_home_live_shortcode($mode) {
    $mode = $mode === 'free' ? 'free' : 'now-open';
    $is_free = $mode === 'free';
    $weekday = (int) wp_date('N', null, wp_timezone());
    $title = $is_free
        ? ($weekday >= 4 ? 'Für lau am Wochenende' : 'Für lau in den nächsten Tagen')
        : 'Jetzt bei dir';
    $subtitle = $is_free
        ? 'Kostenlose Events und Highlights in deiner Nähe.'
        : 'Orte, die jetzt geöffnet sind – und Events, die gerade laufen oder gleich starten.';

    ob_start();
    ?>
    <section
        class="rg-home-module rg-home-live-module <?php echo $is_free ? 'rg-home-live-free' : 'rg-home-live-now'; ?>"
        data-rg-home-live="<?php echo esc_attr($mode); ?>"
        data-rg-home-url="<?php echo esc_url(home_url('/')); ?>"
        aria-label="<?php echo esc_attr($title); ?>"
    >
        <div class="rg-home-section-head">
            <div>
                <span class="rg-home-kicker"><?php echo $is_free ? '🪙 Für lau' : '⚡ Spontan los'; ?></span>
                <h2><?php echo esc_html($title); ?></h2>
                <p><?php echo esc_html($subtitle); ?></p>
            </div>
            <button type="button" class="rg-home-live-location rg-home-location-trigger" data-rg-home-location data-rg-open-location aria-label="Standort ändern"></button>
        </div>
        <div class="rg-home-live-body" data-rg-home-live-body>
            <div class="rg-home-live-loading"><span class="rg-home-spinner" aria-hidden="true"></span>Live-Daten werden geladen …</div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

add_shortcode('ruhrpott_jetzt_offen', function() {
    return ruhrpott_guru_home_live_shortcode('now-open');
});

add_shortcode('ruhrpott_fuer_lau', function() {
    return ruhrpott_guru_home_live_shortcode('free');
});

/**
             * Custom Navigation Walkers for 1:1 Guru Button Layout
             */
            class Ruhrpott_Nav_Walker extends Walker_Nav_Menu {
                function start_el(&$output, $item, $depth = 0, $args = null, $id = 0) {
                    $title = $item->title;
                    $url = $item->url;
                    $active = in_array('current-menu-item', $item->classes) || in_array('current-page-ancestor', $item->classes);

                    // Automatic Lucide Icon Detection based on Title
                    $icon = 'compass';
                    $lower = mb_strtolower($title);
                    if (strpos($lower, 'veranstaltung') !== false || strpos($lower, 'event') !== false) {
                        $icon = 'calendar';
                    } elseif (strpos($lower, 'gastro') !== false || strpos($lower, 'essen') !== false) {
                        $icon = 'utensils';
                    } elseif (strpos($lower, 'aktivit') !== false || strpos($lower, 'sport') !== false) {
                        $icon = 'activity';
                    } elseif (strpos($lower, 'highlight') !== false || strpos($lower, 'zeche') !== false) {
                        $icon = 'landmark';
                    } elseif (strpos($lower, 'kultur') !== false) {
                        $icon = 'ticket';
                    } elseif (strpos($lower, 'guide') !== false || strpos($lower, 'blog') !== false) {
                        $icon = 'map-pin';
                    } elseif (strpos($lower, 'karte') !== false || strpos($lower, 'map') !== false) {
                        $icon = 'map-pin';
                    } elseif (strpos($lower, 'quiz') !== false) {
                        $icon = 'trophy';
                    }

                    $classes = "flex items-center gap-2 px-4 py-2 rounded-xl text-sm lg:text-base font-bold transition-all border ";
                    if ($active) {
                        $classes .= "bg-amber-500 text-slate-950 border-amber-400 shadow-md font-extrabold ";
                    } else {
                        $classes .= "bg-slate-800/90 hover:bg-slate-700 text-slate-200 border-slate-700 ";
                    }

                    $icon_color = $active ? "text-slate-950" : "text-amber-500";
                    if ($icon === 'activity' && !$active) $icon_color = "text-emerald-400";
                    if ($icon === 'map-pin' && !$active) $icon_color = "text-teal-400";
                    if ($icon === 'trophy' && !$active) $icon_color = "text-amber-400";

                    $output .= '<a href="' . esc_url($url) . '" class="' . esc_attr($classes) . '">';
                    $output .= '<i data-lucide="' . $icon . '" class="w-4 h-4 ' . $icon_color . '"></i>';
                    $output .= '<span>' . esc_html($title) . '</span>';
                    $output .= '</a>';
                }
            }

            class Ruhrpott_Burger_Walker extends Walker_Nav_Menu {
                function start_el(&$output, $item, $depth = 0, $args = null, $id = 0) {
                    $title = $item->title;
                    $url = $item->url;

                    $icon = 'chevron-right';
                    $icon_box = 'bg-amber-500/20 text-amber-500';
                    $lower = mb_strtolower($title);

                    if (strpos($lower, 'guide') !== false || strpos($lower, 'blog') !== false) {
                        $icon = 'book-open';
                        $icon_box = 'bg-teal-500/20 text-teal-400';
                    } elseif (strpos($lower, 'datenschutz') !== false) {
                        $icon = 'shield-check';
                        $icon_box = 'bg-emerald-500/20 text-emerald-400';
                        // Always route and label the privacy menu item to the canonical WordPress page.
                        $url = home_url('/datenschutzerklaerung/');
                        $title = 'Datenschutzerklärung';
                    } elseif (strpos($lower, 'impressum') !== false) {
                        $icon = 'file-text';
                        $icon_box = 'bg-indigo-500/20 text-indigo-400';
                        // Always route the Impressum menu item to the canonical WordPress page.
                        $url = home_url('/impressum/');
                    } elseif (strpos($lower, 'cookie') !== false) {
                        $icon = 'cookie';
                        $icon_box = 'bg-amber-500/20 text-amber-500';
                    }

                    $output .= '<a href="' . esc_url($url) . '" class="w-full flex items-center justify-between p-3 rounded-2xl border transition-all text-left font-bold cursor-pointer bg-slate-800/80 hover:bg-slate-800 text-slate-100 border-slate-700 mb-2">';
                    $output .= '<div class="flex items-center gap-3">';
                    $output .= '<div class="p-2 rounded-xl ' . $icon_box . '"><i data-lucide="' . $icon . '" class="w-5 h-5"></i></div>';
                    $output .= '<div><div class="font-extrabold text-sm">' . esc_html($title) . '</div></div>';
                    $output .= '</div>';
                    $output .= '</a>';
                }
            }



/**
 * Pexels symbolic-food media configuration.
 *
 * The API key is server-side only. Preferred sources, in order:
 *  1. RPG_PEXELS_API_KEY constant in wp-config.php
 *  2. RPG_PEXELS_API_KEY environment variable
 *  3. WordPress option configured under Settings -> RuhrpottGuru Bilder
 *
 * The key is never localized into frontend JavaScript.
 */
function ruhrpott_guru_get_pexels_api_key() {
    if (defined('RPG_PEXELS_API_KEY') && is_string(RPG_PEXELS_API_KEY) && trim(RPG_PEXELS_API_KEY) !== '') {
        return trim(RPG_PEXELS_API_KEY);
    }
    $env = getenv('RPG_PEXELS_API_KEY');
    if (is_string($env) && trim($env) !== '') return trim($env);
    $opt = get_option('ruhrpott_guru_pexels_api_key', '');
    return is_string($opt) ? trim($opt) : '';
}

function ruhrpott_guru_register_media_settings() {
    register_setting('ruhrpott_guru_media', 'ruhrpott_guru_pexels_api_key', array(
        'type' => 'string',
        'sanitize_callback' => function($value) {
            return trim(sanitize_text_field((string) $value));
        },
        'default' => '',
    ));
    add_settings_section(
        'ruhrpott_guru_media_section',
        'Pexels Symbolfotos',
        function() {
            echo '<p>Pexels wird ausschließlich als klar gekennzeichnetes Symbolfoto verwendet, wenn alle echten/ortsgebundenen Bildquellen erfolglos bleiben. Der API-Key bleibt auf dem Server.</p>';
        },
        'ruhrpott_guru_media'
    );
    add_settings_field(
        'ruhrpott_guru_pexels_api_key',
        'Pexels API-Key',
        function() {
            $locked = (defined('RPG_PEXELS_API_KEY') && trim((string) RPG_PEXELS_API_KEY) !== '') || trim((string) getenv('RPG_PEXELS_API_KEY')) !== '';
            $value = get_option('ruhrpott_guru_pexels_api_key', '');
            if ($locked) {
                echo '<strong>Aktiv über Server-Konfiguration.</strong><p class="description">Der Schlüssel wird nicht im WordPress-Backend angezeigt.</p>';
                return;
            }
            echo '<input type="password" autocomplete="new-password" class="regular-text" name="ruhrpott_guru_pexels_api_key" value="' . esc_attr($value) . '" />';
            echo '<p class="description">Kostenlosen Pexels API-Key eintragen. Er wird nicht an das öffentliche Frontend ausgeliefert.</p>';
        },
        'ruhrpott_guru_media',
        'ruhrpott_guru_media_section'
    );
}
add_action('admin_init', 'ruhrpott_guru_register_media_settings');

function ruhrpott_guru_add_media_settings_page() {
    add_options_page(
        'PottFuchs Bilder',
        'PottFuchs Bilder',
        'manage_options',
        'ruhrpott-guru-media',
        function() {
            if (!current_user_can('manage_options')) return;
            echo '<div class="wrap"><h1>PottFuchs Bilder</h1>';
            echo '<form method="post" action="options.php">';
            settings_fields('ruhrpott_guru_media');
            do_settings_sections('ruhrpott_guru_media');
            submit_button('API-Key speichern');
            echo '</form></div>';
        }
    );
}
add_action('admin_menu', 'ruhrpott_guru_add_media_settings_page');


/**
 * Official Website Enrichment for gastronomy.
 *
 * This layer never scrapes Google or third-party review portals. It resolves an official web
 * presence from identity-bound OSM, Wikidata and destination.one/eT4 Gastro data. Deterministic
 * guessed domains are only a final fallback and are accepted only when the page itself matches
 * venue name + city + street/house number. Official Facebook/Instagram profiles are used only
 * when no verified own domain is available.
 *
 * Once verified, RuhrpottGuru follows a bounded set of links from that same official site to
 * discover menu/PDF, reservation and opening-hours information. Results are cached server-side.
 */
function ruhrpott_guru_register_food_info_enrichment() {
    register_rest_route('ruhrpottguru/v1', '/food-info', array(
        'methods' => 'POST',
        'callback' => 'ruhrpott_guru_food_info_enrichment',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'ruhrpott_guru_register_food_info_enrichment');

function ruhrpott_guru_food_info_url($url) {
    $url = ruhrpott_guru_food_media_clean_url($url);
    if (!$url || !wp_http_validate_url($url)) return '';
    return $url;
}

function ruhrpott_guru_food_info_host($url) {
    $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
    return preg_replace('/^www\./i', '', $host);
}

function ruhrpott_guru_food_info_root_url($url) {
    $p = wp_parse_url($url);
    if (!$p || empty($p['host'])) return '';
    $scheme = !empty($p['scheme']) ? strtolower($p['scheme']) : 'https';
    return $scheme . '://' . $p['host'] . '/';
}

function ruhrpott_guru_food_info_slug($text) {
    $text = ruhrpott_guru_food_media_normalize_text($text);
    $text = preg_replace('/\b(gmbh|ug|kg|ohg|restaurant|cafe|café|imbiss|bistro)\b/i', ' ', $text);
    $text = trim(preg_replace('/\s+/', ' ', $text));
    return trim(preg_replace('/[^a-z0-9]+/', '-', $text), '-');
}

/**
 * At most two guessed domains are tried. They are NOT trusted by name: every candidate still has
 * to pass the strict exact venue/address identity check before any data is accepted.
 */
function ruhrpott_guru_food_info_domain_candidates($title, $city) {
    $title_slug = ruhrpott_guru_food_info_slug($title);
    $city_slug = ruhrpott_guru_food_info_slug($city);
    if ($title_slug === '' || $city_slug === '') return array();
    $out = array();
    $out[] = 'https://' . $title_slug . '.de/';

    $parts = array_values(array_filter(explode('-', $title_slug)));
    if (count($parts) >= 2) {
        $last = end($parts);
        if ($last && $last !== $city_slug) $out[] = 'https://' . $last . '-' . $city_slug . '.de/';
    } else if ($title_slug !== $city_slug) {
        $out[] = 'https://' . $title_slug . '-' . $city_slug . '.de/';
    }
    return array_values(array_unique(array_slice($out, 0, 2)));
}

function ruhrpott_guru_food_info_fetch_html($url, $timeout = 6) {
    $url = ruhrpott_guru_food_info_url($url);
    if (!$url) return null;
    $resp = wp_safe_remote_get($url, array(
        'timeout' => max(2, min(8, (int)$timeout)),
        'redirection' => 3,
        'limit_response_size' => 650000,
        'user-agent' => 'RuhrpottGuru/1.0 (+https://www.ruhrpottguru.de)',
        'headers' => array('Accept' => 'text/html,application/xhtml+xml'),
    ));
    if (is_wp_error($resp)) return null;
    $code = (int) wp_remote_retrieve_response_code($resp);
    if ($code < 200 || $code >= 400) return null;
    $type = strtolower((string) wp_remote_retrieve_header($resp, 'content-type'));
    if ($type !== '' && strpos($type, 'html') === false && strpos($type, 'xhtml') === false) return null;
    $body = wp_remote_retrieve_body($resp);
    return is_string($body) && strlen($body) >= 80 ? $body : null;
}

function ruhrpott_guru_food_info_identity_matches($html, $title, $city, $address) {
    // Use the strict venue/address identity matcher already used by the regional media layer.
    return ruhrpott_guru_food_media_regional_identity_matches($html, $title, $city, $address);
}

function ruhrpott_guru_food_info_verified_website($website, $title, $city, $address, $allow_guessed = true) {
    $candidates = array();
    $provided = ruhrpott_guru_food_info_url($website);
    if ($provided) $candidates[] = $provided;
    if (!$provided && $allow_guessed) $candidates = array_merge($candidates, ruhrpott_guru_food_info_domain_candidates($title, $city));

    foreach (array_values(array_unique($candidates)) as $candidate) {
        $html = ruhrpott_guru_food_info_fetch_html($candidate, $provided ? 6 : 3);
        if (!$html) continue;
        // A website explicitly attached to the exact OSM POI is already an identity signal;
        // require name + local context. Guessed domains must pass the stricter street/house check.
        $matches = $provided
            ? ruhrpott_guru_food_media_page_matches($html, $title, $city, $address)
            : ruhrpott_guru_food_info_identity_matches($html, $title, $city, $address);
        if (!$matches) continue;
        $root = ruhrpott_guru_food_info_root_url($candidate);
        return array('url' => $root ?: $candidate, 'pageUrl' => $candidate, 'html' => $html, 'discovered' => !$provided, 'source' => $provided ? 'osm-website' : 'verified-domain');
    }
    return null;
}

function ruhrpott_guru_food_info_anchor_links($html, $base_url) {
    $links = array();
    if (!is_string($html) || $html === '') return $links;
    if (preg_match_all('#<a\b[^>]*href\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a>#is', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $href = ruhrpott_guru_food_media_abs_url($base_url, html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (!$href || !wp_http_validate_url($href)) continue;
            $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(wp_strip_all_tags($m[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $links[] = array('url'=>$href, 'text'=>$text, 'context'=>ruhrpott_guru_food_media_normalize_text($text . ' ' . $href));
        }
    }
    return $links;
}

function ruhrpott_guru_food_info_best_link($links, $type, $official_host) {
    $best = null; $best_score = -999;
    foreach ($links as $link) {
        if (empty($link['url'])) continue;
        $url = $link['url'];
        $ctx = isset($link['context']) ? $link['context'] : ruhrpott_guru_food_media_normalize_text(($link['text'] ?? '') . ' ' . $url);
        $host = ruhrpott_guru_food_info_host($url);
        $same_host = ($host !== '' && $official_host !== '' && $host === $official_host);
        $score = 0;
        if ($type === 'menu') {
            if (preg_match('/\b(speisekarte|speisen|menu|menue|food menu)\b/i', $ctx)) $score += 100;
            if (preg_match('/\b(getraenk|getränk|drinks|wein|wine)\b/i', $ctx) && !preg_match('/speisekarte|speisen|food/i', $ctx)) $score -= 45;
            if (preg_match('/\.pdf(?:$|[?#])/i', $url)) $score += 25;
            if (preg_match('#/(speisekarte|menu|menue)(?:/|$)#i', (string)wp_parse_url($url, PHP_URL_PATH))) $score += 30;
        } else if ($type === 'reservation') {
            if (preg_match('/\b(reservierung|reservieren|reservation|reserve|tisch reservieren|booking|book a table)\b/i', $ctx)) $score += 110;
            if (preg_match('#/(reservierung|reservation|booking|book)(?:/|$)#i', (string)wp_parse_url($url, PHP_URL_PATH))) $score += 35;
        }
        if ($same_host) $score += 15; // prefer the venue's own site when equally clear
        if ($score > $best_score && $score >= 80) { $best_score = $score; $best = $url; }
    }
    return $best;
}


/**
 * Food Website Resolver V2
 *
 * Coverage order (all conservative / identity-bound):
 *  1) OSM website/contact:website/url
 *  2) OSM-linked Wikidata P856 (official website)
 *  3) destination.one/eT4 Gastro records (public open-data first, licensed Ruhr pool second)
 *  4) deterministic guessed .de domains with strict name+city+address verification
 *  5) official social profile from OSM/eT4 as a last-resort web presence
 *
 * Search/review portals are never returned as the venue website.
 */
function ruhrpott_guru_food_info_kind($url) {
    $host = ruhrpott_guru_food_info_host($url);
    if ($host === '') return 'website';
    if (preg_match('/(^|\\.)facebook\\.com$/i', $host) || preg_match('/(^|\\.)fb\\.com$/i', $host)) return 'facebook';
    if (preg_match('/(^|\\.)instagram\\.com$/i', $host)) return 'instagram';
    return 'website';
}

function ruhrpott_guru_food_info_label($kind, $detail = false) {
    if ($kind === 'facebook') return 'Facebook';
    if ($kind === 'instagram') return 'Instagram';
    return $detail ? 'Offizielle Webseite' : 'Zur Webseite';
}

function ruhrpott_guru_food_info_directory_host($host) {
    $host = strtolower(preg_replace('/^www\\./i', '', (string)$host));
    if ($host === '') return true;
    return preg_match('/(?:^|\\.)(?:et4\\.de|destination\\.one|outdooractive\\.com|tripadvisor\\.|restaurantguru\\.|yelp\\.|lieferando\\.|ubereats\\.|google\\.|golocal\\.|11880\\.|meinestadt\\.|oeffnungszeitenbuch\\.|werkenntdenbesten\\.)/i', $host) === 1;
}

function ruhrpott_guru_food_info_social_url($value, $platform) {
    if (!is_string($value)) return '';
    $v = trim($value);
    if ($v === '') return '';
    $direct = ruhrpott_guru_food_info_url($v);
    if ($direct && ruhrpott_guru_food_info_kind($direct) === $platform) return $direct;
    $v = ltrim($v, '@/ ');
    if ($v === '' || preg_match('/\\s/', $v)) return '';
    if ($platform === 'facebook') return esc_url_raw('https://www.facebook.com/' . rawurlencode($v));
    if ($platform === 'instagram') return esc_url_raw('https://www.instagram.com/' . rawurlencode($v) . '/');
    return '';
}

function ruhrpott_guru_food_info_wikidata_website($qid) {
    $qid = strtoupper(trim((string)$qid));
    if (!preg_match('/^Q[1-9][0-9]*$/', $qid)) return '';
    $cache_key = 'rg_food_wd_p856_v1_' . strtolower($qid);
    $cached = get_transient($cache_key);
    if (is_string($cached)) return $cached;
    $url = 'https://www.wikidata.org/wiki/Special:EntityData/' . rawurlencode($qid) . '.json';
    $resp = wp_safe_remote_get($url, array('timeout'=>5,'redirection'=>2,'headers'=>array('Accept'=>'application/json'),'user-agent'=>'RuhrpottGuru/1.0; ' . home_url('/')));
    if (is_wp_error($resp) || (int)wp_remote_retrieve_response_code($resp) < 200 || (int)wp_remote_retrieve_response_code($resp) >= 300) {
        set_transient($cache_key, '', 6 * HOUR_IN_SECONDS); return '';
    }
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    $claims = $data['entities'][$qid]['claims']['P856'] ?? array();
    foreach ((array)$claims as $claim) {
        $value = $claim['mainsnak']['datavalue']['value'] ?? '';
        $safe = ruhrpott_guru_food_info_url($value);
        if ($safe && ruhrpott_guru_food_info_kind($safe) === 'website' && !ruhrpott_guru_food_info_directory_host(ruhrpott_guru_food_info_host($safe))) {
            set_transient($cache_key, $safe, 30 * DAY_IN_SECONDS); return $safe;
        }
    }
    set_transient($cache_key, '', 7 * DAY_IN_SECONDS);
    return '';
}

function ruhrpott_guru_food_info_et4_fetch($title, $city, $experience, $use_license) {
    $license_key = $use_license ? ruhrpott_guru_get_et4_license_key() : '';
    if ($use_license && $license_key === '') return array();
    $title = trim((string)$title); $city = trim((string)$city);
    if ($title === '') return array();
    $phrase = str_replace('"', '', $title);
    $queries = array();
    if ($city !== '') $queries[] = '"' . $phrase . '" AND city:"' . str_replace('"', '', $city) . '"';
    $queries[] = '"' . $phrase . '"';
    $queries = array_values(array_unique($queries));

    foreach ($queries as $q) {
        $args = array('experience'=>$experience,'type'=>'Gastro','template'=>'ET2014A.json','mkt'=>'de-DE','limit'=>25,'offset'=>0,'unique'=>'true','q'=>$q);
        if ($license_key !== '') $args['licensekey'] = $license_key;
        $url = add_query_arg($args, 'https://meta.et4.de/rest.ashx/search/');
        $resp = wp_remote_get($url, array('timeout'=>7,'redirection'=>3,'headers'=>array('Accept'=>'application/json'),'user-agent'=>'RuhrpottGuru/1.0; ' . home_url('/')));
        if (is_wp_error($resp)) continue;
        $status = (int)wp_remote_retrieve_response_code($resp);
        if ($status < 200 || $status >= 300) continue;
        $payload = json_decode(wp_remote_retrieve_body($resp), true);
        if (!is_array($payload)) continue;
        $items = function_exists('ruhrpott_guru_et4_extract_items') ? ruhrpott_guru_et4_extract_items($payload) : (isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : array());
        if (!empty($items)) return $items;
    }
    return array();
}

function ruhrpott_guru_food_info_et4_title($item) {
    if (!is_array($item)) return '';
    foreach (array('title','name') as $key) if (!empty($item[$key]) && is_string($item[$key])) return trim($item[$key]);
    foreach ((array)($item['texts'] ?? array()) as $text) {
        if (!is_array($text)) continue;
        $rel = strtolower((string)($text['rel'] ?? ''));
        $type = strtolower((string)($text['type'] ?? ''));
        if (($rel === 'title' || $rel === 'name' || strpos($type,'title') !== false) && !empty($text['value'])) return trim(wp_strip_all_tags((string)$text['value']));
    }
    return '';
}

function ruhrpott_guru_food_info_et4_addresses($item) {
    if (!is_array($item)) return array();
    $addresses = $item['addresses'] ?? array();
    if (is_array($addresses) && isset($addresses['address'])) $addresses = $addresses['address'];
    if (!is_array($addresses)) return array();
    // Associative one-address shape -> list.
    $is_list = array_keys($addresses) === range(0, count($addresses)-1);
    return $is_list ? $addresses : array($addresses);
}

function ruhrpott_guru_food_info_et4_identity_score($item, $title, $city, $address, $lat, $lng) {
    $want_title = ruhrpott_guru_food_media_normalize_text($title);
    $got_title = ruhrpott_guru_food_media_normalize_text(ruhrpott_guru_food_info_et4_title($item));
    if ($want_title === '' || $got_title === '') return -999;
    $score = 0;
    if ($want_title === $got_title) $score += 85;
    elseif (strpos($got_title, $want_title) !== false || strpos($want_title, $got_title) !== false) $score += 65;
    else {
        $tokens = ruhrpott_guru_food_media_name_tokens($title);
        $hits = 0;
        foreach ($tokens as $token) if (strpos($got_title, $token) !== false) $hits++;
        if ($hits === 0) return -999;
        $score += min(60, 25 + $hits * 15);
    }

    $city_n = ruhrpott_guru_food_media_normalize_text($city);
    $addr_n = ruhrpott_guru_food_media_normalize_text($address);
    foreach (ruhrpott_guru_food_info_et4_addresses($item) as $a) {
        if (!is_array($a)) continue;
        $a_city = ruhrpott_guru_food_media_normalize_text($a['city'] ?? ($a['addressLocality'] ?? ($a['locality'] ?? '')));
        $a_street = ruhrpott_guru_food_media_normalize_text(($a['street'] ?? ($a['streetAddress'] ?? '')) . ' ' . ($a['houseNumber'] ?? ($a['housenumber'] ?? '')));
        if ($city_n !== '' && $a_city !== '' && $a_city === $city_n) $score += 35;
        if ($addr_n !== '' && $a_street !== '') {
            $parts = array_values(array_filter(explode(' ', $a_street), function($v){ return strlen($v) >= 3; }));
            $hits = 0; foreach ($parts as $part) if (strpos($addr_n, $part) !== false) $hits++;
            if ($hits >= 2) $score += 45; elseif ($hits === 1) $score += 20;
        }
    }

    $geo = $item['geo']['main'] ?? ($item['geo'] ?? null);
    if (is_array($geo) && is_numeric($lat) && is_numeric($lng)) {
        $ilat = isset($geo['latitude']) ? (float)$geo['latitude'] : (isset($geo['lat']) ? (float)$geo['lat'] : NAN);
        $ilng = isset($geo['longitude']) ? (float)$geo['longitude'] : (isset($geo['lng']) ? (float)$geo['lng'] : NAN);
        if (is_finite($ilat) && is_finite($ilng) && function_exists('ruhrpott_guru_event_distance_km')) {
            $dist = ruhrpott_guru_event_distance_km((float)$lat, (float)$lng, $ilat, $ilng);
            if ($dist <= 0.15) $score += 70;
            elseif ($dist <= 0.35) $score += 55;
            elseif ($dist <= 1.0) $score += 30;
            elseif ($dist > 3.0) $score -= 80;
        }
    }
    return $score;
}

function ruhrpott_guru_food_info_collect_urls($node, $path = '', &$out = array()) {
    if (is_array($node)) {
        foreach ($node as $key => $value) ruhrpott_guru_food_info_collect_urls($value, $path . '/' . strtolower((string)$key), $out);
        return $out;
    }
    if (!is_string($node)) return $out;
    $value = html_entity_decode(trim($node), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($value === '') return $out;
    if (preg_match_all("#https?://[^\\s<>\"']+#iu", $value, $m)) {
        foreach ($m[0] as $raw) {
            $url = ruhrpott_guru_food_info_url(rtrim($raw, '.,;)')); if (!$url) continue;
            $out[] = array('url'=>$url,'path'=>$path);
        }
    }
    return $out;
}

function ruhrpott_guru_food_info_et4_resolve($title, $city, $address, $lat, $lng) {
    $pools = array(array('open-data', false), array('ruhr-tourismus-open-data', true));
    $social = null;
    foreach ($pools as $pool) {
        $items = ruhrpott_guru_food_info_et4_fetch($title, $city, $pool[0], $pool[1]);
        $ranked = array();
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $score = ruhrpott_guru_food_info_et4_identity_score($item, $title, $city, $address, $lat, $lng);
            if ($score >= 90) $ranked[] = array('score'=>$score,'item'=>$item);
        }
        usort($ranked, function($a,$b){ return $b['score'] <=> $a['score']; });
        foreach (array_slice($ranked, 0, 3) as $candidate) {
            $urls = array(); ruhrpott_guru_food_info_collect_urls($candidate['item'], '', $urls);
            foreach ($urls as $entry) {
                $url = $entry['url']; $host = ruhrpott_guru_food_info_host($url); $kind = ruhrpott_guru_food_info_kind($url);
                if ($kind === 'facebook' || $kind === 'instagram') {
                    if (!$social) $social = array('url'=>$url,'kind'=>$kind,'source'=>'et4-' . $pool[0], 'confidence'=>0.94);
                    continue;
                }
                if (ruhrpott_guru_food_info_directory_host($host)) continue;
                // Prefer URL-like structured fields, but still page-verify before accepting.
                if (!preg_match('/(?:website|homepage|url|web|link|contact)/i', $entry['path'])) continue;
                $html = ruhrpott_guru_food_info_fetch_html($url, 5);
                if ($html && ruhrpott_guru_food_media_page_matches($html, $title, $city, $address)) {
                    $root = ruhrpott_guru_food_info_root_url($url);
                    return array('website'=>array('url'=>$root ?: $url,'pageUrl'=>$url,'html'=>$html,'discovered'=>true,'source'=>'et4-' . $pool[0],'kind'=>'website','confidence'=>0.97),'social'=>$social);
                }
                // A dedicated ET2014A web/homepage field on a strongly identity-matched
                // Gastro record is still useful when the venue blocks server-side fetches.
                if ($candidate['score'] >= 160 && preg_match('#/(?:web|website|homepage)$#i', $entry['path'])) {
                    $root = ruhrpott_guru_food_info_root_url($url);
                    return array('website'=>array('url'=>$root ?: $url,'pageUrl'=>$url,'html'=>'','discovered'=>true,'source'=>'et4-' . $pool[0], 'kind'=>'website','confidence'=>0.94),'social'=>$social);
                }
            }
        }
        // Public open-data is the broadest pool. Only hit licensed Ruhr if it did not yield a website.
    }
    return array('website'=>null,'social'=>$social);
}

function ruhrpott_guru_food_info_priority_pages($links, $official_host, $base_url) {
    $scored = array();
    foreach ($links as $link) {
        $url = $link['url'] ?? '';
        if (!$url || ruhrpott_guru_food_info_host($url) !== $official_host) continue;
        $ctx = $link['context'] ?? '';
        $score = 0;
        if (preg_match('/\b(speisekarte|menu|menue)\b/i', $ctx)) $score += 90;
        if (preg_match('/\b(reservierung|reservation|booking|reservieren)\b/i', $ctx)) $score += 90;
        if (preg_match('/\b(kontakt|contact|restaurant|wirtshaus|oeffnungszeiten|öffnungszeiten|opening)\b/i', $ctx)) $score += 70;
        if ($score > 0) $scored[$url] = max($score, $scored[$url] ?? 0);
    }
    arsort($scored, SORT_NUMERIC);
    $urls = array($base_url);
    foreach (array_keys($scored) as $url) {
        if (!in_array($url, $urls, true)) $urls[] = $url;
        if (count($urls) >= 5) break;
    }
    return $urls;
}

function ruhrpott_guru_food_info_day_code($value) {
    $v = strtolower(remove_accents(trim((string)$value)));
    $v = preg_replace('#^https?://schema\.org/#', '', $v);
    $map = array(
        'monday'=>'Mo','montag'=>'Mo','mo'=>'Mo',
        'tuesday'=>'Di','dienstag'=>'Di','tu'=>'Di','di'=>'Di',
        'wednesday'=>'Mi','mittwoch'=>'Mi','we'=>'Mi','mi'=>'Mi',
        'thursday'=>'Do','donnerstag'=>'Do','th'=>'Do','do'=>'Do',
        'friday'=>'Fr','freitag'=>'Fr','fr'=>'Fr',
        'saturday'=>'Sa','samstag'=>'Sa','sat'=>'Sa','sa'=>'Sa',
        'sunday'=>'So','sonntag'=>'So','sun'=>'So','so'=>'So',
    );
    return $map[$v] ?? '';
}

function ruhrpott_guru_food_info_time($value) {
    if (!is_string($value) && !is_numeric($value)) return '';
    $v = trim((string)$value);
    if (preg_match('/^(\d{1,2})[:.]?(\d{2})$/', $v, $m)) return str_pad($m[1],2,'0',STR_PAD_LEFT) . ':' . $m[2];
    return '';
}

function ruhrpott_guru_food_info_jsonld_hours($html) {
    $intervals = array();
    if (!preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', (string)$html, $scripts)) return '';
    $walk = function($node) use (&$walk, &$intervals) {
        if (!is_array($node)) return;
        if (isset($node['openingHoursSpecification'])) {
            $specs = $node['openingHoursSpecification'];
            if (isset($specs['opens'])) $specs = array($specs);
            if (is_array($specs)) foreach ($specs as $spec) {
                if (!is_array($spec) || empty($spec['opens']) || empty($spec['closes'])) continue;
                $days = $spec['dayOfWeek'] ?? array();
                if (!is_array($days)) $days = array($days);
                $open = ruhrpott_guru_food_info_time($spec['opens']);
                $close = ruhrpott_guru_food_info_time($spec['closes']);
                if (!$open || !$close) continue;
                foreach ($days as $day) {
                    $code = ruhrpott_guru_food_info_day_code($day);
                    if ($code) $intervals[$code] = $open . '-' . $close;
                }
            }
        }
        foreach ($node as $child) if (is_array($child)) $walk($child);
    };
    foreach ($scripts[1] as $raw) {
        $raw = html_entity_decode(trim($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $data = json_decode($raw, true);
        if (is_array($data)) $walk($data);
    }
    if (!$intervals) return '';
    $order = array('Mo','Di','Mi','Do','Fr','Sa','So');
    $parts = array();
    foreach ($order as $day) if (!empty($intervals[$day])) $parts[] = $day . ' ' . $intervals[$day];
    return implode('; ', $parts);
}

function ruhrpott_guru_food_info_text_lines($html) {
    $html = preg_replace('#<(?:br\s*/?|/p|/div|/li|/tr|/h[1-6])\s*>#i', "\n", (string)$html);
    $txt = html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $txt = str_replace(array("\r\n", "\r", '–', '—'), array("\n", "\n", '-', '-'), $txt);
    $raw = preg_split('/\n+/', $txt);
    $lines = array();
    foreach ($raw as $line) {
        $line = trim(preg_replace('/\s+/', ' ', $line));
        if ($line !== '') $lines[] = $line;
    }
    return $lines;
}

function ruhrpott_guru_food_info_text_hours($html) {
    $lines = ruhrpott_guru_food_info_text_lines($html);
    if (!$lines) return '';
    $day_rx = '(Montag|Dienstag|Mittwoch|Donnerstag|Freitag|Samstag|Sonntag)';
    $found = array();
    for ($i=0; $i<count($lines); $i++) {
        $line = $lines[$i];
        $line = preg_replace('/Sonn(?:-|\s)*(?:und|&)\s*Feiertag/iu', 'Sonntag', $line);
        if (!preg_match('/' . $day_rx . '/iu', $line)) continue;
        // Headings often put the time/Ruhetag on the following line.
        $joined = $line;
        if (!preg_match('/\d{1,2}[:.]\d{2}|Ruhetag|geschlossen/iu', $joined) && isset($lines[$i+1])) $joined .= ' ' . $lines[$i+1];
        if (preg_match('/Ruhetag|geschlossen/iu', $joined)) continue;
        if (!preg_match('/(\d{1,2}[:.]\d{2})\s*(?:bis|-)\s*(\d{1,2}[:.]\d{2})/iu', $joined, $tm)) continue;
        $open = ruhrpott_guru_food_info_time($tm[1]);
        $close = ruhrpott_guru_food_info_time($tm[2]);
        if (!$open || !$close) continue;
        preg_match_all('/' . $day_rx . '/iu', $joined, $dm);
        $days = $dm[1] ?? array();
        if (!$days) continue;
        $codes = array_values(array_filter(array_map('ruhrpott_guru_food_info_day_code', $days)));
        // "Dienstag bis Donnerstag" => full range; "Freitag und Samstag" => explicit days.
        if (count($codes) >= 2 && preg_match('/' . $day_rx . '\s*(?:bis|-)\s*' . $day_rx . '/iu', $joined, $range)) {
            $start = ruhrpott_guru_food_info_day_code($range[1]);
            $end = ruhrpott_guru_food_info_day_code($range[2]);
            $order = array('Mo','Di','Mi','Do','Fr','Sa','So');
            $a = array_search($start, $order, true); $b = array_search($end, $order, true);
            if ($a !== false && $b !== false && $a <= $b) $codes = array_slice($order, $a, $b-$a+1);
        }
        foreach ($codes as $code) $found[$code] = $open . '-' . $close;
    }
    if (!$found) return '';
    $order = array('Mo','Di','Mi','Do','Fr','Sa','So');
    $parts = array();
    foreach ($order as $day) if (!empty($found[$day])) $parts[] = $day . ' ' . $found[$day];
    return implode('; ', $parts);
}

/**
 * V267: Extract bounded Food facets only from an identity-verified official site.
 *
 * Product/meal facets (Burger, Steak, Sushi, ...) are accepted when they are
 * prominent in title/headings/meta OR recur on the bounded official pages.
 * Diet identity facets (Vegan/Vegetarisch) are intentionally stricter: a single
 * menu option must never classify the whole venue as vegan/vegetarian.
 */
function ruhrpott_guru_food_info_official_tags($pages) {
    if (!is_array($pages) || !$pages) return array();
    $strong_parts = array();
    $all_parts = array();
    foreach ($pages as $html) {
        if (!is_string($html) || trim($html) === '') continue;
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match_all('/<(?:title|h1|h2|h3)[^>]*>(.*?)<\/(?:title|h1|h2|h3)>/isu', $decoded, $m)) {
            foreach ($m[1] as $part) $strong_parts[] = strip_tags($part);
        }
        if (preg_match_all('/<meta[^>]+(?:name|property)=["\'](?:description|og:description|twitter:description)["\'][^>]+content=["\']([^"\']+)["\']/isu', $decoded, $m)) {
            foreach ($m[1] as $part) $strong_parts[] = $part;
        }
        if (preg_match_all('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:name|property)=["\'](?:description|og:description|twitter:description)["\']/isu', $decoded, $m)) {
            foreach ($m[1] as $part) $strong_parts[] = $part;
        }
        $without_noise = preg_replace('/<(?:script|style|noscript|svg)[^>]*>.*?<\/(?:script|style|noscript|svg)>/isu', ' ', $decoded);
        $all_parts[] = preg_replace('/<[^>]+>/u', ' ', (string)$without_noise);
    }
    $normalize = static function($value) {
        $value = (string)$value;
        $value = str_replace(array("\xc2\xa0", '&nbsp;'), ' ', $value);
        return preg_replace('/\s+/u', ' ', $value);
    };
    $strong = $normalize(implode(' | ', $strong_parts));
    $all = $normalize(implode(' | ', $all_parts));
    $out = array();
    $add = static function($label) use (&$out) {
        if (!in_array($label, $out, true) && count($out) < 6) $out[] = $label;
    };
    $prominent = static function($pattern, $min_all = 3) use ($strong, $all) {
        if (preg_match($pattern, $strong)) return true;
        return preg_match_all($pattern, $all, $tmp) >= $min_all;
    };

    $facets = array(
        'Burger' => '/\b(?:burger|hamburger|cheeseburger|smash[ -]?burger)\b/iu',
        'Steak' => '/\b(?:steak|steakhouse|rumpsteak|ribeye|dry[ -]?aged)\b/iu',
        'Sushi' => '/\bsushi\b/iu',
        'Pizza' => '/\b(?:pizza|pizzeria)\b/iu',
        'Pasta' => '/\b(?:pasta|nudelgerichte?)\b/iu',
        'Döner' => '/\b(?:döner|doener|kebab)\b/iu',
        'Tacos' => '/\btacos?\b/iu',
        'Bowls' => '/\bbowls?\b/iu',
        'BBQ' => '/\b(?:bbq|barbecue|smoker)\b/iu',
        'Frühstück' => '/\b(?:frühstück|fruehstueck|breakfast|brunch)\b/iu',
        'Eis' => '/\b(?:eiscaf[eé]|gelato|eiscreme)\b/iu'
    );
    foreach ($facets as $label => $pattern) if ($prominent($pattern, 3)) $add($label);

    // Cuisine identity: require an explicit restaurant/cuisine phrase or prominence.
    $cuisines = array(
        'Italienisch' => '/\b(?:italienisches?\s+(?:restaurant|küche)|cucina\s+italiana)\b/iu',
        'Türkisch' => '/\b(?:türkisches?\s+(?:restaurant|küche)|tuerkisches?\s+(?:restaurant|kueche))\b/iu',
        'Chinesisch' => '/\bchinesisches?\s+(?:restaurant|küche)\b/iu',
        'Indisch' => '/\bindisches?\s+(?:restaurant|küche)\b/iu',
        'Griechisch' => '/\bgriechisches?\s+(?:restaurant|küche)\b/iu',
        'Mexikanisch' => '/\bmexikanisches?\s+(?:restaurant|küche)\b/iu',
        'Japanisch' => '/\bjapanisches?\s+(?:restaurant|küche)\b/iu',
        'Vietnamesisch' => '/\bvietnamesisches?\s+(?:restaurant|küche)\b/iu'
    );
    foreach ($cuisines as $label => $pattern) if (preg_match($pattern, $strong) || preg_match($pattern, $all)) $add($label);

    // Diet identity must describe the venue/concept, not merely one dish on a menu.
    if (preg_match('/\b(?:100\s*%\s*vegan|rein\s+vegan|veganes?\s+(?:restaurant|cafe|café|konzept|küche)|plant[ -]?based\s+(?:restaurant|cafe|café|kitchen|concept))\b/iu', $strong . ' ' . $all)) $add('Vegan');
    if (preg_match('/\b(?:rein\s+vegetarisch|vegetarisches?\s+(?:restaurant|cafe|café|konzept|küche)|vegetarian\s+(?:restaurant|cafe|kitchen|concept))\b/iu', $strong . ' ' . $all)) $add('Vegetarisch');

    return $out;
}

function ruhrpott_guru_food_info_enrichment(WP_REST_Request $request) {
    $p = $request->get_json_params();
    if (!is_array($p)) $p = array();
    $title = sanitize_text_field($p['title'] ?? '');
    $city = sanitize_text_field($p['city'] ?? '');
    $address = sanitize_text_field($p['address'] ?? '');
    $website = esc_url_raw($p['website'] ?? '');
    $wikidata = sanitize_text_field($p['wikidata'] ?? '');
    $lat = isset($p['lat']) && is_numeric($p['lat']) ? (float)$p['lat'] : NAN;
    $lng = isset($p['lng']) && is_numeric($p['lng']) ? (float)$p['lng'] : NAN;
    if ($title === '' || $city === '') return new WP_REST_Response(array('status'=>'invalid'), 400);

    $social_osm = null;
    $fb = ruhrpott_guru_food_info_social_url($p['facebook'] ?? '', 'facebook');
    $ig = ruhrpott_guru_food_info_social_url($p['instagram'] ?? '', 'instagram');
    $website_kind = $website ? ruhrpott_guru_food_info_kind($website) : 'website';
    if ($website && ($website_kind === 'facebook' || $website_kind === 'instagram')) {
        $social_osm = array('url'=>$website,'kind'=>$website_kind,'source'=>'osm-social','confidence'=>0.98);
        $website = '';
    } elseif ($fb) $social_osm = array('url'=>$fb,'kind'=>'facebook','source'=>'osm-social','confidence'=>0.98);
    elseif ($ig) $social_osm = array('url'=>$ig,'kind'=>'instagram','source'=>'osm-social','confidence'=>0.98);

    $cache_key = 'rg_food_info_v3_' . md5(strtolower($title . '|' . $city . '|' . $address . '|' . $website . '|' . $wikidata . '|' . sprintf('%.4f|%.4f', is_finite($lat)?$lat:0, is_finite($lng)?$lng:0)));
    $cached = get_transient($cache_key);
    if (is_array($cached)) return new WP_REST_Response($cached, 200);

    $verified = null;
    $fallback_social = $social_osm;

    // 1) Exact OSM website/contact website. Prefer a page-verified match, but keep
    // the explicit OSM website relation even when the site blocks server-side fetching.
    if ($website && !ruhrpott_guru_food_info_directory_host(ruhrpott_guru_food_info_host($website))) {
        $verified = ruhrpott_guru_food_info_verified_website($website, $title, $city, $address, false);
        if (!$verified) {
            $verified = array('url'=>ruhrpott_guru_food_info_root_url($website) ?: $website,'pageUrl'=>$website,'html'=>'','discovered'=>false,'source'=>'osm-website','kind'=>'website','confidence'=>0.97);
        }
    }

    // 2) OSM-linked Wikidata P856 is an explicit official website relation.
    if (!$verified && $wikidata !== '') {
        $wd_url = ruhrpott_guru_food_info_wikidata_website($wikidata);
        if ($wd_url) {
            $html = ruhrpott_guru_food_info_fetch_html($wd_url, 5);
            // P856 explicitly means official website. Because the Wikidata QID itself
            // comes from the exact OSM POI, retain it even if the page blocks fetches.
            $verified = array('url'=>ruhrpott_guru_food_info_root_url($wd_url) ?: $wd_url,'pageUrl'=>$wd_url,'html'=>$html ?: '','discovered'=>true,'source'=>'wikidata-p856','kind'=>'website','confidence'=>0.99);
        }
    }

    // 3) destination.one/eT4 Gastro: public regional pool first, licensed Ruhr pool second.
    if (!$verified) {
        $et4 = ruhrpott_guru_food_info_et4_resolve($title, $city, $address, $lat, $lng);
        if (!empty($et4['website'])) $verified = $et4['website'];
        if (!$fallback_social && !empty($et4['social'])) $fallback_social = $et4['social'];
    }

    // 4) Last website attempt: only deterministic domains and only after strict address identity verification.
    if (!$verified) $verified = ruhrpott_guru_food_info_verified_website('', $title, $city, $address, true);

    // 5) No own site found: preserve an official social web presence rather than returning a wrong directory.
    if (!$verified && $fallback_social) {
        $kind = $fallback_social['kind'];
        $out = array(
            'status'=>'success','website'=>$fallback_social['url'],'websiteKind'=>$kind,'websiteLabel'=>ruhrpott_guru_food_info_label($kind, false),
            'menuUrl'=>null,'reservationUrl'=>null,'openingHours'=>null,'foodTags'=>array(),'source'=>$fallback_social['source'],'sourceUrl'=>$fallback_social['url'],
            'identityConfidence'=>$fallback_social['confidence'],'discoveredWebsite'=>true,
            'reason'=>'No identity-verified own domain was available; an official social profile linked to the exact venue record is used as web presence.'
        );
        set_transient($cache_key, $out, 3 * DAY_IN_SECONDS);
        return new WP_REST_Response($out, 200);
    }

    if (!$verified) {
        $out = array('status'=>'not-found','website'=>null,'websiteKind'=>null,'websiteLabel'=>null,'menuUrl'=>null,'reservationUrl'=>null,'openingHours'=>null,'foodTags'=>array(),'reason'=>'No identity-verified official website or official social profile available');
        set_transient($cache_key, $out, 8 * HOUR_IN_SECONDS);
        return new WP_REST_Response($out, 200);
    }

    $official = $verified['url'];
    $host = ruhrpott_guru_food_info_host($official);
    $pages = array();
    $pages[$verified['pageUrl']] = $verified['html'];
    $base_links = ruhrpott_guru_food_info_anchor_links($verified['html'], $verified['pageUrl']);
    $priority = ruhrpott_guru_food_info_priority_pages($base_links, $host, $verified['pageUrl']);
    foreach ($priority as $page_url) {
        if (isset($pages[$page_url])) continue;
        $html = ruhrpott_guru_food_info_fetch_html($page_url, 5);
        if ($html) $pages[$page_url] = $html;
    }

    $all_links = $base_links;
    foreach ($pages as $page_url => $html) $all_links = array_merge($all_links, ruhrpott_guru_food_info_anchor_links($html, $page_url));
    $menu = ruhrpott_guru_food_info_best_link($all_links, 'menu', $host);
    $reservation = ruhrpott_guru_food_info_best_link($all_links, 'reservation', $host);

    if ($menu && !preg_match('/\\.pdf(?:$|[?#])/i', $menu) && ruhrpott_guru_food_info_host($menu) === $host) {
        $menu_html = $pages[$menu] ?? ruhrpott_guru_food_info_fetch_html($menu, 5);
        if ($menu_html) {
            $pdf = ruhrpott_guru_food_info_best_link(ruhrpott_guru_food_info_anchor_links($menu_html, $menu), 'menu', $host);
            if ($pdf && preg_match('/\\.pdf(?:$|[?#])/i', $pdf)) $menu = $pdf;
        }
    }

    $hours = '';
    foreach ($pages as $html) { $hours = ruhrpott_guru_food_info_jsonld_hours($html); if ($hours) break; }
    if (!$hours) foreach ($pages as $html) { $hours = ruhrpott_guru_food_info_text_hours($html); if ($hours) break; }
    $food_tags = ruhrpott_guru_food_info_official_tags($pages);

    $kind = 'website';
    $out = array(
        'status'=>'success','website'=>$official,'websiteKind'=>$kind,'websiteLabel'=>ruhrpott_guru_food_info_label($kind, false),
        'menuUrl'=>$menu ?: null,'reservationUrl'=>$reservation ?: null,'openingHours'=>$hours ?: null,'foodTags'=>$food_tags,
        'source'=>$verified['source'] ?? 'official-website','sourceUrl'=>$verified['pageUrl'],
        'identityConfidence'=>$verified['confidence'] ?? 0.99,'discoveredWebsite'=>(bool)($verified['discovered'] ?? false),
        'reason'=>'Official web presence resolved through OSM/Wikidata/eT4/strict-domain verification; bounded same-site crawl extracted factual actions/opening hours.'
    );
    set_transient($cache_key, $out, 7 * DAY_IN_SECONDS);
    return new WP_REST_Response($out, 200);
}


/**
 * Generic official website resolver for Events, Highlights and Culture.
 *
 * This deliberately stays separate from the Food action resolver: no menu,
 * reservation or price logic is applied here. Resolution is identity-bound:
 * exact OSM web relation -> OSM-linked Wikidata P856 -> matching eT4 Event/POI
 * record -> (POIs only) strictly verified deterministic domain -> official
 * social profile. Search/review/directory hosts are never returned as the
 * object's official website.
 */
function ruhrpott_guru_register_place_info_enrichment() {
    register_rest_route('ruhrpottguru/v1', '/place-info', array(
        'methods' => 'POST',
        'callback' => 'ruhrpott_guru_place_info_enrichment',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'ruhrpott_guru_register_place_info_enrichment');

function ruhrpott_guru_place_info_et4_fetch($title, $city, $experience, $use_license, $type) {
    $license_key = $use_license ? ruhrpott_guru_get_et4_license_key() : '';
    if ($use_license && $license_key === '') return array();
    $title = trim((string)$title);
    $city = trim((string)$city);
    $type = $type === 'Event' ? 'Event' : 'POI';
    if ($title === '') return array();

    $phrase = str_replace('"', '', $title);
    $queries = array();
    if ($city !== '') $queries[] = '"' . $phrase . '" AND city:"' . str_replace('"', '', $city) . '"';
    $queries[] = '"' . $phrase . '"';
    $queries = array_values(array_unique($queries));

    foreach ($queries as $q) {
        $args = array(
            'experience'=>$experience,
            'type'=>$type,
            'template'=>'ET2014A.json',
            'mkt'=>'de-DE',
            'limit'=>25,
            'offset'=>0,
            'unique'=>'true',
            'q'=>$q,
        );
        if ($license_key !== '') $args['licensekey'] = $license_key;
        $url = add_query_arg($args, 'https://meta.et4.de/rest.ashx/search/');
        $resp = wp_remote_get($url, array(
            'timeout'=>7,
            'redirection'=>3,
            'headers'=>array('Accept'=>'application/json'),
            'user-agent'=>'RuhrpottGuru/1.0; ' . home_url('/'),
        ));
        if (is_wp_error($resp)) continue;
        $status = (int)wp_remote_retrieve_response_code($resp);
        if ($status < 200 || $status >= 300) continue;
        $payload = json_decode(wp_remote_retrieve_body($resp), true);
        if (!is_array($payload)) continue;
        $items = function_exists('ruhrpott_guru_et4_extract_items')
            ? ruhrpott_guru_et4_extract_items($payload)
            : (isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : array());
        if (!empty($items)) return $items;
    }
    return array();
}

function ruhrpott_guru_place_info_et4_resolve($title, $city, $address, $lat, $lng, $type) {
    $pools = array(array('open-data', false), array('ruhr-tourismus-open-data', true));
    $social = null;
    foreach ($pools as $pool) {
        $items = ruhrpott_guru_place_info_et4_fetch($title, $city, $pool[0], $pool[1], $type);
        $ranked = array();
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $score = ruhrpott_guru_food_info_et4_identity_score($item, $title, $city, $address, $lat, $lng);
            if ($score >= 90) $ranked[] = array('score'=>$score,'item'=>$item);
        }
        usort($ranked, function($a,$b){ return $b['score'] <=> $a['score']; });

        foreach (array_slice($ranked, 0, 3) as $candidate) {
            $urls = array();
            ruhrpott_guru_food_info_collect_urls($candidate['item'], '', $urls);
            foreach ($urls as $entry) {
                $url = $entry['url'];
                $host = ruhrpott_guru_food_info_host($url);
                $kind = ruhrpott_guru_food_info_kind($url);
                if ($kind === 'facebook' || $kind === 'instagram') {
                    if (!$social) $social = array('url'=>$url,'kind'=>$kind,'source'=>'et4-' . $pool[0], 'confidence'=>0.94);
                    continue;
                }
                if (ruhrpott_guru_food_info_directory_host($host)) continue;
                if (!preg_match('/(?:website|homepage|url|web|sameas|link|contact)/i', $entry['path'])) continue;
                if (preg_match('/(?:media|image|photo|picture|thumbnail|download|file)/i', $entry['path'])) continue;
                if (preg_match('/\.(?:jpe?g|png|gif|webp|svg|pdf)(?:$|[?#])/i', $url)) continue;

                $html = ruhrpott_guru_food_info_fetch_html($url, 5);
                if ($html && ruhrpott_guru_food_media_page_matches($html, $title, $city, $address)) {
                    $root = ruhrpott_guru_food_info_root_url($url);
                    return array(
                        'website'=>array('url'=>$root ?: $url,'pageUrl'=>$url,'html'=>$html,'discovered'=>true,'source'=>'et4-' . $pool[0],'kind'=>'website','confidence'=>0.97),
                        'social'=>$social,
                    );
                }

                // A strongly identity-matched ET2014A record may explicitly carry
                // a web/homepage/url field while the target site blocks server-side fetches.
                if ($candidate['score'] >= 160 && preg_match('#/(?:web|website|homepage|url|sameas)$#i', $entry['path'])) {
                    $root = ruhrpott_guru_food_info_root_url($url);
                    return array(
                        'website'=>array('url'=>$root ?: $url,'pageUrl'=>$url,'html'=>'','discovered'=>true,'source'=>'et4-' . $pool[0],'kind'=>'website','confidence'=>0.94),
                        'social'=>$social,
                    );
                }
            }
        }
    }
    return array('website'=>null,'social'=>$social);
}

function ruhrpott_guru_place_info_label($kind, $category) {
    if ($kind === 'facebook') return 'Facebook';
    if ($kind === 'instagram') return 'Instagram';
    return $category === 'Veranstaltungen' ? 'Event-Webseite' : 'Offizielle Webseite';
}

function ruhrpott_guru_place_info_enrichment(WP_REST_Request $request) {
    $p = $request->get_json_params();
    if (!is_array($p)) $p = array();

    $title = sanitize_text_field($p['title'] ?? '');
    $category = sanitize_text_field($p['category'] ?? '');
    $city = sanitize_text_field($p['city'] ?? '');
    $address = sanitize_text_field($p['address'] ?? '');
    $website = esc_url_raw($p['website'] ?? '');
    $wikidata = sanitize_text_field($p['wikidata'] ?? '');
    $lat = isset($p['lat']) && is_numeric($p['lat']) ? (float)$p['lat'] : NAN;
    $lng = isset($p['lng']) && is_numeric($p['lng']) ? (float)$p['lng'] : NAN;
    if ($title === '' || $city === '') return new WP_REST_Response(array('status'=>'invalid'), 400);

    $type = $category === 'Veranstaltungen' ? 'Event' : 'POI';
    $social = null;
    $website_kind = $website ? ruhrpott_guru_food_info_kind($website) : 'website';
    if ($website && ($website_kind === 'facebook' || $website_kind === 'instagram')) {
        $social = array('url'=>$website,'kind'=>$website_kind,'source'=>'record-social','confidence'=>0.98);
        $website = '';
    }
    $fb = ruhrpott_guru_food_info_social_url($p['facebook'] ?? '', 'facebook');
    $ig = ruhrpott_guru_food_info_social_url($p['instagram'] ?? '', 'instagram');
    if (!$social && $fb) $social = array('url'=>$fb,'kind'=>'facebook','source'=>'osm-social','confidence'=>0.98);
    if (!$social && $ig) $social = array('url'=>$ig,'kind'=>'instagram','source'=>'osm-social','confidence'=>0.98);

    $cache_key = 'rg_place_info_v1_' . md5(strtolower($category . '|' . $title . '|' . $city . '|' . $address . '|' . $website . '|' . $wikidata . '|' . sprintf('%.4f|%.4f', is_finite($lat)?$lat:0, is_finite($lng)?$lng:0)));
    $cached = get_transient($cache_key);
    if (is_array($cached)) return new WP_REST_Response($cached, 200);

    $verified = null;

    // 1) Exact website attached to the source object.
    if ($website && !ruhrpott_guru_food_info_directory_host(ruhrpott_guru_food_info_host($website))) {
        $verified = ruhrpott_guru_food_info_verified_website($website, $title, $city, $address, false);
        if (!$verified) {
            // The exact OSM/eT4 web relation itself is an identity signal; retain it
            // if the remote site simply blocks WordPress server-side verification.
            $verified = array(
                'url'=>ruhrpott_guru_food_info_root_url($website) ?: $website,
                'pageUrl'=>$website,
                'html'=>'',
                'discovered'=>false,
                'source'=>'source-record-website',
                'kind'=>'website',
                'confidence'=>0.97,
            );
        }
    }

    // 2) OSM-linked Wikidata official website (P856).
    if (!$verified && $wikidata !== '') {
        $wd_url = ruhrpott_guru_food_info_wikidata_website($wikidata);
        if ($wd_url) {
            $verified = array(
                'url'=>ruhrpott_guru_food_info_root_url($wd_url) ?: $wd_url,
                'pageUrl'=>$wd_url,
                'html'=>'',
                'discovered'=>true,
                'source'=>'wikidata-p856',
                'kind'=>'website',
                'confidence'=>0.99,
            );
        }
    }

    // 3) Matching destination.one/eT4 Event or POI record.
    if (!$verified) {
        $et4 = ruhrpott_guru_place_info_et4_resolve($title, $city, $address, $lat, $lng, $type);
        if (!empty($et4['website'])) $verified = $et4['website'];
        if (!$social && !empty($et4['social'])) $social = $et4['social'];
    }

    // 4) For stable POIs only, allow the existing strict domain verifier. Event
    // titles are temporary and must never be turned into guessed domains.
    if (!$verified && $type === 'POI') {
        $verified = ruhrpott_guru_food_info_verified_website('', $title, $city, $address, true);
    }

    // 5) Last-resort official social presence attached to the exact record.
    if (!$verified && $social) {
        $kind = $social['kind'];
        $out = array(
            'status'=>'success',
            'website'=>$social['url'],
            'websiteKind'=>$kind,
            'websiteLabel'=>ruhrpott_guru_place_info_label($kind, $category),
            'source'=>$social['source'],
            'sourceUrl'=>$social['url'],
            'identityConfidence'=>$social['confidence'],
            'reason'=>'No identity-verified own domain was available; official social presence linked to the exact record is used.',
        );
        set_transient($cache_key, $out, 3 * DAY_IN_SECONDS);
        return new WP_REST_Response($out, 200);
    }

    if (!$verified) {
        $out = array(
            'status'=>'not-found','website'=>null,'websiteKind'=>null,'websiteLabel'=>null,
            'reason'=>'No identity-verified official website or social presence available',
        );
        set_transient($cache_key, $out, 8 * HOUR_IN_SECONDS);
        return new WP_REST_Response($out, 200);
    }

    $kind = $verified['kind'] ?? 'website';
    // Event records often carry a deep link to the exact exhibition/concert page.
    // Preserve that useful page instead of collapsing it to the domain homepage.
    $display_url = ($category === 'Veranstaltungen' && !empty($verified['pageUrl'])) ? $verified['pageUrl'] : $verified['url'];
    $out = array(
        'status'=>'success',
        'website'=>$display_url,
        'websiteKind'=>$kind,
        'websiteLabel'=>ruhrpott_guru_place_info_label($kind, $category),
        'source'=>$verified['source'] ?? 'official-website',
        'sourceUrl'=>$verified['pageUrl'] ?? $verified['url'],
        'identityConfidence'=>$verified['confidence'] ?? 0.97,
        'reason'=>'Official web presence resolved through source record, Wikidata P856, matching eT4 data or strict POI-domain verification.',
    );
    set_transient($cache_key, $out, 7 * DAY_IN_SECONDS);
    return new WP_REST_Response($out, 200);
}

/**
 * Food Media Resolver V1
 *
 * Same-origin, conservative media resolver for gastronomy. It prefers identity-linked
 * media over fuzzy name search:
 *   1. OSM image / wikimedia_commons tags
 *   2. OSM-linked Wikidata P18
 *   3. the venue's own website (OpenGraph / venue-specific image)
 *   4. regional tourism/open-data pages with explicit open image licenses and strict identity checks
 *   5. Panoramax POI-facing open street imagery (the API guarantees the target coordinate is visible)
 *   6. open KartaView street-level imagery near the exact OSM coordinate, direction checked
 *
 * It never performs generic name-only Wikimedia image search for gastronomy and it never invents
 * a restaurant photo. If every real/open source fails, the frontend shows a neutral no-photo state.
 */
function ruhrpott_guru_register_food_media_resolver() {
    register_rest_route('ruhrpottguru/v1', '/food-media', array(
        'methods' => 'POST',
        'callback' => 'ruhrpott_guru_food_media_resolver',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'ruhrpott_guru_register_food_media_resolver');

function ruhrpott_guru_food_media_clean_url($url) {
    if (!is_string($url)) return '';
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($url === '') return '';
    if (strpos($url, '//') === 0) $url = 'https:' . $url;
    if (!preg_match('#^https?://#i', $url)) return '';
    return esc_url_raw($url);
}

function ruhrpott_guru_food_media_abs_url($base, $candidate) {
    $candidate = trim(html_entity_decode((string) $candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($candidate === '') return '';
    if (preg_match('#^https?://#i', $candidate)) return ruhrpott_guru_food_media_clean_url($candidate);
    if (strpos($candidate, '//') === 0) return ruhrpott_guru_food_media_clean_url('https:' . $candidate);
    $bp = wp_parse_url($base);
    if (!$bp || empty($bp['host'])) return '';
    $scheme = !empty($bp['scheme']) ? $bp['scheme'] : 'https';
    if (strpos($candidate, '/') === 0) return ruhrpott_guru_food_media_clean_url($scheme . '://' . $bp['host'] . $candidate);
    $path = isset($bp['path']) ? $bp['path'] : '/';
    $dir = preg_replace('#/[^/]*$#', '/', $path);
    return ruhrpott_guru_food_media_clean_url($scheme . '://' . $bp['host'] . $dir . $candidate);
}

function ruhrpott_guru_food_media_commons_file_url($value) {
    if (!is_string($value)) return '';
    $value = trim($value);
    if ($value === '') return '';
    if (preg_match('/^File\s*:\s*(.+)$/i', $value, $m)) {
        $file = trim($m[1]);
        if ($file !== '') return 'https://commons.wikimedia.org/wiki/Special:FilePath/' . rawurlencode($file) . '?width=1200';
    }
    if (preg_match('#commons\.wikimedia\.org/wiki/File:(.+)$#i', $value, $m)) {
        $file = rawurldecode(preg_replace('/[?#].*$/', '', $m[1]));
        $file = str_replace('_', ' ', $file);
        if ($file !== '') return 'https://commons.wikimedia.org/wiki/Special:FilePath/' . rawurlencode($file) . '?width=1200';
    }
    if (stripos($value, 'Special:FilePath/') !== false) return ruhrpott_guru_food_media_clean_url($value);
    return '';
}

function ruhrpott_guru_food_media_bad_asset($url) {
    $s = strtolower((string) $url);
    return preg_match('/(?:^|[\/_\-.])(logo|icon|favicon|sprite|avatar|placeholder|default|dummy|blank|no[-_ ]?image)(?:[\/_\-.]|$)/i', $s) === 1;
}

function ruhrpott_guru_food_media_normalize_text($text) {
    $text = wp_strip_all_tags((string) $text);
    $text = remove_accents(strtolower($text));
    $text = preg_replace('/[^a-z0-9 ]+/', ' ', $text);
    return trim(preg_replace('/\s+/', ' ', $text));
}

function ruhrpott_guru_food_media_name_tokens($title) {
    $stop = array('restaurant','cafe','café','eiscafe','eiscafé','imbiss','bar','bistro','grill','pizza','pizzeria','wesel','gmbh','und','der','die','das','am','im','zum','zur');
    $norm = ruhrpott_guru_food_media_normalize_text($title);
    $parts = array_filter(explode(' ', $norm));
    $out = array();
    foreach ($parts as $p) {
        if (strlen($p) >= 4 && !in_array($p, $stop, true)) $out[] = $p;
    }
    return array_values(array_unique($out));
}

function ruhrpott_guru_food_media_page_matches($html, $title, $city, $address) {
    $hay = ruhrpott_guru_food_media_normalize_text(substr((string) $html, 0, 350000));
    if ($hay === '') return false;
    $city_n = ruhrpott_guru_food_media_normalize_text($city);
    $address_n = ruhrpott_guru_food_media_normalize_text($address);
    $tokens = ruhrpott_guru_food_media_name_tokens($title);
    $name_match = empty($tokens);
    foreach ($tokens as $t) {
        if (strpos($hay, $t) !== false) { $name_match = true; break; }
    }
    $local_match = ($city_n !== '' && strpos($hay, $city_n) !== false);
    if (!$local_match && $address_n !== '') {
        $addr_parts = array_values(array_filter(explode(' ', $address_n), function($v){ return strlen($v) >= 4; }));
        foreach ($addr_parts as $part) {
            if (strpos($hay, $part) !== false) { $local_match = true; break; }
        }
    }
    return $name_match && $local_match;
}

function ruhrpott_guru_food_media_extract_meta($html, $property) {
    $property_q = preg_quote($property, '/');
    $patterns = array(
        '/<meta[^>]+(?:property|name)=["\']' . $property_q . '["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i',
        '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:property|name)=["\']' . $property_q . '["\'][^>]*>/i',
    );
    foreach ($patterns as $p) if (preg_match($p, $html, $m)) return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return '';
}

function ruhrpott_guru_food_media_extract_attr($tag, $attrs) {
    foreach ($attrs as $attr) {
        $q = preg_quote($attr, '/');
        if (preg_match('/\\b' . $q . '=["\\\']([^"\\\']+)["\\\']/i', $tag, $m)) {
            $v = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($v !== '') return $v;
        }
    }
    return '';
}

function ruhrpott_guru_food_media_srcset_first($srcset) {
    $parts = array_map('trim', explode(',', (string) $srcset));
    foreach (array_reverse($parts) as $part) {
        if ($part === '') continue;
        $bits = preg_split('/\\s+/', $part);
        if (!empty($bits[0])) return $bits[0];
    }
    return '';
}

function ruhrpott_guru_food_media_candidate_score($url, $context, $title, $city, $kind) {
    if (!$url || ruhrpott_guru_food_media_bad_asset($url)) return -1000;
    $ctx = ruhrpott_guru_food_media_normalize_text($context . ' ' . $url);
    $score = 0;
    $tokens = ruhrpott_guru_food_media_name_tokens($title);
    foreach ($tokens as $t) if (strpos($ctx, $t) !== false) $score += 24;
    $city_n = ruhrpott_guru_food_media_normalize_text($city);
    if ($city_n !== '' && strpos($ctx, $city_n) !== false) $score += 12;
    if ($kind === 'jsonld') $score += 18;
    elseif ($kind === 'meta') $score += 14;
    elseif ($kind === 'img') $score += 10;
    elseif ($kind === 'source') $score += 6;

    // V83 generic rule: prefer the physical venue (facade/storefront/rooms) over food/menu imagery.
    // No business name is hard-coded; the rule applies to every Food POI.
    if (preg_match('/storefront|shopfront|fassade|facade|frontansicht|aussenansicht|außenansicht|exterior|eingang|entrance|building|gebaeude|gebäude|location|standort|venue/i', $ctx)) $score += 42;
    elseif (preg_match('/interior|innenraum|gastraum|ambiente|restaurant[-_ ]?raum|terrasse|patio/i', $ctx)) $score += 24;
    elseif (preg_match('/gallery|galerie|impression|hero|header/i', $ctx)) $score += 8;

    if (preg_match('/menu|speisekarte|dish|gericht|meal|food|essen|pizza|doener|doner|döner|kebab|sushi|burger|fries|pommes|plate|teller|drink|cocktail|produkt|product/i', $ctx)) $score -= 38;
    if (preg_match('/person|people|portrait|selfie|staff|team|employee|owner|chef|waiter|guest|customer|crowd|family|friends|mann|frau|menschen|gruppe|gaeste|gäste|mitarbeiter|koch|kellner|besucher/i', $ctx)) $score -= 120;
    if (preg_match('/banner|slider|thumb|thumbnail|logo|icon/i', $ctx)) $score -= 8;
    return $score;
}

function ruhrpott_guru_food_media_collect_page_candidates($html, $page_url, $title, $city) {
    $candidates = array();
    $add = function($raw, $context, $kind) use (&$candidates, $page_url, $title, $city) {
        if (!$raw) return;
        $raw = trim((string) $raw);
        if (strpos($raw, ',') !== false && preg_match('/\\s[0-9]+[wx](?:\\s*,|$)/i', $raw)) {
            $raw = ruhrpott_guru_food_media_srcset_first($raw);
        }
        $url = ruhrpott_guru_food_media_abs_url($page_url, $raw);
        if (!$url || !wp_http_validate_url($url) || ruhrpott_guru_food_media_bad_asset($url)) return;
        $score = ruhrpott_guru_food_media_candidate_score($url, $context, $title, $city, $kind);
        if ($score <= -100) return;
        $candidates[$url] = max(isset($candidates[$url]) ? $candidates[$url] : -999, $score);
    };

    if (preg_match_all('/<img\\b[^>]*>/i', $html, $imgs)) {
        foreach (array_slice($imgs[0], 0, 180) as $tag) {
            $src = ruhrpott_guru_food_media_extract_attr($tag, array('src','data-src','data-lazy-src','data-original','data-flickity-lazyload','data-lazy'));
            if (!$src) {
                $srcset = ruhrpott_guru_food_media_extract_attr($tag, array('srcset','data-srcset'));
                if ($srcset) $src = ruhrpott_guru_food_media_srcset_first($srcset);
            }
            if (!$src) continue;
            if (preg_match('/\\b(?:width|height)=["\\\']?([0-9]{1,4})/i', $tag, $dm) && (int)$dm[1] < 160) continue;
            $add($src, $tag, 'img');
        }
    }

    if (preg_match_all('/<source\\b[^>]*>/i', $html, $sources)) {
        foreach (array_slice($sources[0], 0, 80) as $tag) {
            $srcset = ruhrpott_guru_food_media_extract_attr($tag, array('srcset','data-srcset'));
            if ($srcset) $add(ruhrpott_guru_food_media_srcset_first($srcset), $tag, 'source');
        }
    }

    foreach (array('og:image:secure_url','og:image','twitter:image','twitter:image:src') as $prop) {
        $v = ruhrpott_guru_food_media_extract_meta($html, $prop);
        if ($v) $add($v, $prop, 'meta');
    }
    if (preg_match('/<meta[^>]+itemprop=["\\\']image["\\\'][^>]+content=["\\\']([^"\\\']+)["\\\']/i', $html, $m) ||
        preg_match('/<meta[^>]+content=["\\\']([^"\\\']+)["\\\'][^>]+itemprop=["\\\']image["\\\']/i', $html, $m)) {
        $add($m[1], 'itemprop image', 'meta');
    }
    if (preg_match('/<link[^>]+rel=["\\\']image_src["\\\'][^>]+href=["\\\']([^"\\\']+)["\\\']/i', $html, $m) ||
        preg_match('/<link[^>]+href=["\\\']([^"\\\']+)["\\\'][^>]+rel=["\\\']image_src["\\\']/i', $html, $m)) {
        $add($m[1], 'image_src', 'meta');
    }

    // JSON-LD often carries a cleaner venue photo than the rendered lazy-loaded markup.
    if (preg_match_all('/<script[^>]+type=["\\\']application\\/ld\\+json["\\\'][^>]*>(.*?)<\\/script>/is', $html, $blocks)) {
        foreach (array_slice($blocks[1], 0, 20) as $block) {
            $decoded = html_entity_decode($block, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match_all('/["\\\'](?:image|photo)["\\\']\\s*:\\s*(?:["\\\']([^"\\\']+)["\\\']|\\{[^}]*?["\\\']url["\\\']\\s*:\\s*["\\\']([^"\\\']+)["\\\'])/i', $decoded, $jm, PREG_SET_ORDER)) {
                foreach ($jm as $hit) $add(!empty($hit[1]) ? $hit[1] : (!empty($hit[2]) ? $hit[2] : ''), $decoded, 'jsonld');
            }
        }
    }

    arsort($candidates, SORT_NUMERIC);
    return $candidates;
}

function ruhrpott_guru_food_media_same_host_links($html, $base, $limit = 4) {
    $host = strtolower((string) wp_parse_url($base, PHP_URL_HOST));
    if (!$host || !preg_match_all('/<a\\b[^>]+href=["\\\']([^"\\\']+)["\\\'][^>]*>(.*?)<\\/a>/is', $html, $links, PREG_SET_ORDER)) return array();
    $ranked = array();
    foreach (array_slice($links, 0, 240) as $m) {
        $href = ruhrpott_guru_food_media_abs_url($base, $m[1]);
        if (!$href || strtolower((string) wp_parse_url($href, PHP_URL_HOST)) !== $host) continue;
        $ctx = ruhrpott_guru_food_media_normalize_text($m[1] . ' ' . wp_strip_all_tags($m[2]));
        $score = 0;
        if (preg_match('/galerie|gallery|fotos|bilder|location|standort|aussen|außen|fassade|facade|eingang|ueber|uber|über|kontakt|impression|ambiente|raeume|räume/i', $ctx)) $score += 10;
        if ($score > 0) $ranked[$href] = max(isset($ranked[$href]) ? $ranked[$href] : 0, $score);
    }
    arsort($ranked, SORT_NUMERIC);
    return array_slice(array_keys($ranked), 0, max(0, (int)$limit));
}

function ruhrpott_guru_food_media_fetch_html($url) {
    $resp = wp_safe_remote_get($url, array(
        'timeout' => 7,
        'redirection' => 3,
        'user-agent' => 'RuhrpottGuru/1.0 (+https://ruhrpottguru.de)',
        'limit_response_size' => 500000,
        'headers' => array('Accept' => 'text/html,application/xhtml+xml'),
    ));
    if (is_wp_error($resp)) return null;
    $code = (int) wp_remote_retrieve_response_code($resp);
    if ($code < 200 || $code >= 400) return null;
    $body = (string) wp_remote_retrieve_body($resp);
    return $body !== '' ? $body : null;
}

function ruhrpott_guru_food_media_official_website($website, $title, $city, $address) {
    $website = ruhrpott_guru_food_media_clean_url($website);
    if (!$website || !wp_http_validate_url($website)) return null;
    $host = strtolower((string) wp_parse_url($website, PHP_URL_HOST));
    if ($host === '' || preg_match('/(?:facebook|instagram|tiktok|tripadvisor|yelp|lieferando|ubereats|google)\\./i', $host)) return null;

    $html = ruhrpott_guru_food_media_fetch_html($website);
    if (!$html || !ruhrpott_guru_food_media_page_matches($html, $title, $city, $address)) return null;

    $all = ruhrpott_guru_food_media_collect_page_candidates($html, $website, $title, $city);

    // A small, same-domain crawl recovers photos hidden on gallery/location pages while
    // preserving identity: every crawled page must still match venue + local context.
    foreach (ruhrpott_guru_food_media_same_host_links($html, $website, 3) as $linked) {
        $sub = ruhrpott_guru_food_media_fetch_html($linked);
        if (!$sub || !ruhrpott_guru_food_media_page_matches($sub, $title, $city, $address)) continue;
        foreach (ruhrpott_guru_food_media_collect_page_candidates($sub, $linked, $title, $city) as $url => $score) {
            $all[$url] = max(isset($all[$url]) ? $all[$url] : -999, $score + 4);
        }
    }

    if (!$all) return null;
    arsort($all, SORT_NUMERIC);
    foreach ($all as $img => $score) {
        if ($score < 22) continue;
        return array(
            'imageUrl' => $img,
            'source' => 'official-source',
            'sourceUrl' => $website,
            'website' => $website,
            'matchMethod' => 'official-website-v2',
            'originStatus' => 'probable',
            'identityConfidence' => 0.94,
            'sourceReliabilityScore' => 0.93,
            'credit' => 'Foto: offizielle Website des Betriebs',
            'license' => null,
            'reason' => 'OSM-linked official website matched venue/local context; image recovered from page media metadata',
        );
    }
    return null;
}

function ruhrpott_guru_food_media_commons_category($value) {
    if (!is_string($value)) return null;
    $value = trim($value);
    $category = '';
    if (preg_match('/^Category\\s*:\\s*(.+)$/i', $value, $m)) $category = trim($m[1]);
    elseif (preg_match('#commons\\.wikimedia\\.org/wiki/Category:(.+)$#i', $value, $m)) $category = str_replace('_', ' ', rawurldecode(preg_replace('/[?#].*$/', '', $m[1])));
    if ($category === '') return null;
    $api = 'https://commons.wikimedia.org/w/api.php?action=query&generator=categorymembers&gcmtitle=' . rawurlencode('Category:' . $category) . '&gcmnamespace=6&gcmlimit=12&prop=imageinfo&iiprop=url|user|extmetadata&iiurlwidth=1200&format=json';
    $resp = wp_safe_remote_get($api, array('timeout'=>7,'redirection'=>2,'limit_response_size'=>500000));
    if (is_wp_error($resp) || (int) wp_remote_retrieve_response_code($resp) !== 200) return null;
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    $pages = isset($data['query']['pages']) && is_array($data['query']['pages']) ? $data['query']['pages'] : array();
    foreach ($pages as $page) {
        $title = isset($page['title']) ? (string)$page['title'] : '';
        $info = isset($page['imageinfo'][0]) ? $page['imageinfo'][0] : null;
        if (!$info) continue;
        $img = !empty($info['thumburl']) ? $info['thumburl'] : (!empty($info['url']) ? $info['url'] : '');
        if (!$img || ruhrpott_guru_food_media_bad_asset($img) || ruhrpott_guru_food_media_bad_asset($title)) continue;
        $meta = !empty($info['extmetadata']) ? $info['extmetadata'] : array();
        $artist = !empty($meta['Artist']['value']) ? wp_strip_all_tags($meta['Artist']['value']) : (!empty($info['user']) ? $info['user'] : null);
        $license = !empty($meta['LicenseShortName']['value']) ? $meta['LicenseShortName']['value'] : (!empty($meta['UsageTerms']['value']) ? $meta['UsageTerms']['value'] : null);
        return array(
            'imageUrl'=>$img,
            'source'=>'wikimedia-commons',
            'sourceUrl'=>'https://commons.wikimedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $title)),
            'matchMethod'=>'osm-wikimedia-category',
            'originStatus'=>'verified',
            'identityConfidence'=>0.99,
            'sourceReliabilityScore'=>1.0,
            'credit'=>'Foto via Wikimedia Commons (direkt in OSM verknüpfte Kategorie)',
            'artist'=>$artist,
            'license'=>$license,
            'reason'=>'Wikimedia Commons category is explicitly linked to this OSM venue object',
        );
    }
    return null;
}

function ruhrpott_guru_food_media_wikipedia_pageimage($value) {
    if (!is_string($value)) return null;
    $value = trim($value);
    if ($value === '') return null;
    $lang = 'de'; $title = '';
    if (preg_match('#^https?://([a-z-]+)\\.wikipedia\\.org/wiki/(.+)$#i', $value, $m)) {
        $lang = strtolower($m[1]); $title = rawurldecode(preg_replace('/[?#].*$/', '', $m[2]));
    } elseif (preg_match('/^([a-z-]{2,12}):(.+)$/i', $value, $m)) {
        $lang = strtolower($m[1]); $title = trim($m[2]);
    } else $title = $value;
    if ($title === '' || !preg_match('/^[a-z-]{2,12}$/', $lang)) return null;
    $api = 'https://' . $lang . '.wikipedia.org/w/api.php?action=query&prop=pageimages&piprop=thumbnail|original&pithumbsize=1200&titles=' . rawurlencode(str_replace('_',' ',$title)) . '&format=json';
    $resp = wp_safe_remote_get($api, array('timeout'=>6,'redirection'=>2,'limit_response_size'=>220000));
    if (is_wp_error($resp) || (int) wp_remote_retrieve_response_code($resp) !== 200) return null;
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    $pages = isset($data['query']['pages']) && is_array($data['query']['pages']) ? $data['query']['pages'] : array();
    foreach ($pages as $page) {
        $img = !empty($page['thumbnail']['source']) ? $page['thumbnail']['source'] : (!empty($page['original']['source']) ? $page['original']['source'] : '');
        if (!$img || ruhrpott_guru_food_media_bad_asset($img)) continue;
        return array(
            'imageUrl'=>$img,
            'source'=>'wikimedia-commons',
            'sourceUrl'=>'https://' . $lang . '.wikipedia.org/wiki/' . rawurlencode(str_replace(' ','_',$title)),
            'matchMethod'=>'osm-wikipedia-pageimage',
            'originStatus'=>'verified',
            'identityConfidence'=>0.99,
            'sourceReliabilityScore'=>0.98,
            'credit'=>'Foto via Wikipedia/Wikimedia (direkt in OSM verknüpft)',
            'license'=>null,
            'reason'=>'Wikipedia page is explicitly linked to this OSM venue object',
        );
    }
    return null;
}

function ruhrpott_guru_food_media_geo_tokens($title) {
    $generic = array('china','asia','asian','express','pizza','pizzeria','grill','sushi','wok','kebab','doener','doner','döner','curry','restaurant','cafe','café','bar','bistro','imbiss','haus','stube');
    return array_values(array_filter(ruhrpott_guru_food_media_name_tokens($title), function($t) use ($generic){ return !in_array($t, $generic, true); }));
}

function ruhrpott_guru_food_media_commons_geo($title, $city, $lat, $lng, $radius_m = 120) {
    $lat = is_numeric($lat) ? (float)$lat : null; $lng = is_numeric($lng) ? (float)$lng : null;
    if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) return null;
    $tokens = ruhrpott_guru_food_media_geo_tokens($title);
    if (!$tokens) return null;
    $radius_m = max(50, min(750, (int)$radius_m));
    $api = 'https://commons.wikimedia.org/w/api.php?action=query&generator=geosearch&ggsprimary=all&ggsnamespace=6&ggsradius=' . $radius_m . '&ggscoord=' . rawurlencode($lat . '|' . $lng) . '&ggslimit=30&prop=imageinfo&iiprop=url|user|extmetadata&iiurlwidth=1400&format=json';
    $resp = wp_safe_remote_get($api, array('timeout'=>7,'redirection'=>2,'limit_response_size'=>650000));
    if (is_wp_error($resp) || (int) wp_remote_retrieve_response_code($resp) !== 200) return null;
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    $pages = isset($data['query']['pages']) && is_array($data['query']['pages']) ? $data['query']['pages'] : array();
    $city_n = ruhrpott_guru_food_media_normalize_text($city);
    foreach ($pages as $page) {
        $info = isset($page['imageinfo'][0]) ? $page['imageinfo'][0] : null;
        if (!$info) continue;
        $meta = !empty($info['extmetadata']) ? $info['extmetadata'] : array();
        $context = (isset($page['title']) ? $page['title'] : '') . ' ' . (!empty($meta['ImageDescription']['value']) ? wp_strip_all_tags($meta['ImageDescription']['value']) : '') . ' ' . (!empty($meta['Categories']['value']) ? wp_strip_all_tags($meta['Categories']['value']) : '');
        $norm = ruhrpott_guru_food_media_normalize_text($context);
        $hits = 0; foreach ($tokens as $t) if (strpos($norm, $t) !== false) $hits++;
        $required = count($tokens) >= 2 ? 2 : 1;
        if ($hits < $required) continue;
        // Nearby coordinates are necessary but not sufficient; name-token agreement is mandatory above.
        $img = !empty($info['thumburl']) ? $info['thumburl'] : (!empty($info['url']) ? $info['url'] : '');
        if (!$img || ruhrpott_guru_food_media_bad_asset($img)) continue;
        $artist = !empty($meta['Artist']['value']) ? wp_strip_all_tags($meta['Artist']['value']) : (!empty($info['user']) ? $info['user'] : null);
        $license = !empty($meta['LicenseShortName']['value']) ? $meta['LicenseShortName']['value'] : (!empty($meta['UsageTerms']['value']) ? $meta['UsageTerms']['value'] : null);
        return array(
            'imageUrl'=>$img,
            'source'=>'wikimedia-commons',
            'sourceUrl'=>'https://commons.wikimedia.org/wiki/' . rawurlencode(str_replace(' ', '_', isset($page['title']) ? $page['title'] : '')),
            'matchMethod'=>'commons-geo-name',
            'originStatus'=>'probable',
            'identityConfidence'=>0.92,
            'sourceReliabilityScore'=>0.96,
            'credit'=>'Foto via Wikimedia Commons (Geokoordinate + Namensabgleich)',
            'artist'=>$artist,
            'license'=>$license,
            'reason'=>'Commons image is within ' . $radius_m . ' m and matches the venue-name identity tokens',
        );
    }
    return null;
}

function ruhrpott_guru_food_media_wikidata_p18($qid) {
    $qid = strtoupper(trim((string) $qid));
    if (!preg_match('/^Q[1-9][0-9]*$/', $qid)) return null;
    $url = 'https://www.wikidata.org/wiki/Special:EntityData/' . rawurlencode($qid) . '.json';
    $resp = wp_safe_remote_get($url, array('timeout' => 6, 'redirection' => 2, 'limit_response_size' => 250000));
    if (is_wp_error($resp) || (int) wp_remote_retrieve_response_code($resp) !== 200) return null;
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    $claims = isset($data['entities'][$qid]['claims']['P18']) ? $data['entities'][$qid]['claims']['P18'] : array();
    if (!$claims) return null;
    foreach ($claims as $claim) {
        $file = isset($claim['mainsnak']['datavalue']['value']) ? trim((string) $claim['mainsnak']['datavalue']['value']) : '';
        if ($file === '') continue;
        $img = 'https://commons.wikimedia.org/wiki/Special:FilePath/' . rawurlencode($file) . '?width=1200';
        return array(
            'imageUrl' => $img,
            'source' => 'wikimedia-commons',
            'sourceUrl' => 'https://commons.wikimedia.org/wiki/File:' . rawurlencode(str_replace(' ', '_', $file)),
            'matchMethod' => 'wikidata-p18',
            'originStatus' => 'verified',
            'identityConfidence' => 0.99,
            'sourceReliabilityScore' => 1.0,
            'credit' => 'Foto via Wikimedia Commons (OSM-linked Wikidata P18)',
            'license' => null,
            'reason' => 'Direct OSM Wikidata identity link with P18 image',
        );
    }
    return null;
}


/**
 * Distance in metres between two WGS84 positions.
 */
function ruhrpott_guru_food_media_haversine_m($lat1, $lng1, $lat2, $lng2) {
    $r = 6371000.0;
    $p1 = deg2rad((float) $lat1);
    $p2 = deg2rad((float) $lat2);
    $dp = deg2rad((float) $lat2 - (float) $lat1);
    $dl = deg2rad((float) $lng2 - (float) $lng1);
    $a = sin($dp / 2) * sin($dp / 2) + cos($p1) * cos($p2) * sin($dl / 2) * sin($dl / 2);
    return $r * 2 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));
}

/**
 * Initial bearing (0..360) from the camera position to the POI.
 */
function ruhrpott_guru_food_media_bearing_deg($lat1, $lng1, $lat2, $lng2) {
    $p1 = deg2rad((float) $lat1);
    $p2 = deg2rad((float) $lat2);
    $dl = deg2rad((float) $lng2 - (float) $lng1);
    $y = sin($dl) * cos($p2);
    $x = cos($p1) * sin($p2) - sin($p1) * cos($p2) * cos($dl);
    $b = rad2deg(atan2($y, $x));
    return fmod($b + 360.0, 360.0);
}

function ruhrpott_guru_food_media_angle_diff($a, $b) {
    $d = abs(fmod(((float) $a - (float) $b + 540.0), 360.0) - 180.0);
    return $d;
}

/**
 * Public KartaView nearby-photo fetch. Cache by ~110 m cell so a result grid does not
 * spend one public API request per restaurant. KartaView's public API is used without a key.
 */

/**
 * Strict address check used for regional tourism/open-data pages. Name + city alone is not
 * enough for common restaurant names; when OSM gives street/house number, require them too.
 */
function ruhrpott_guru_food_media_regional_identity_matches($html, $title, $city, $address) {
    if (!ruhrpott_guru_food_media_page_matches($html, $title, $city, $address)) return false;
    $hay = ruhrpott_guru_food_media_normalize_text(substr((string)$html, 0, 500000));
    $city_n = ruhrpott_guru_food_media_normalize_text($city);
    if ($city_n !== '' && strpos($hay, $city_n) === false) return false;

    $addr = ruhrpott_guru_food_media_normalize_text($address);
    if ($addr === '' || $addr === $city_n) return true;
    // Remove the city and generic street words, then require the useful address evidence.
    if ($city_n !== '') $addr = trim(str_replace($city_n, ' ', $addr));
    $parts = array_values(array_filter(explode(' ', preg_replace('/\s+/', ' ', $addr))));
    $house = '';
    $street_tokens = array();
    foreach ($parts as $part) {
        if ($house === '' && preg_match('/^[0-9]+[a-z]?$/', $part)) { $house = $part; continue; }
        if (strlen($part) >= 4 && !in_array($part, array('strasse','straße','str','platz','allee','weg'), true)) $street_tokens[] = $part;
    }
    $street_ok = empty($street_tokens);
    foreach ($street_tokens as $t) if (strpos($hay, $t) !== false) { $street_ok = true; break; }
    $house_ok = ($house === '' || strpos($hay, $house) !== false);
    return $street_ok && $house_ok;
}

function ruhrpott_guru_food_media_open_license_from_page($html) {
    $txt = html_entity_decode(wp_strip_all_tags((string)$html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (!preg_match('/(?:Lizenz|License)\s*:?\s*(cc[_\s-]*(?:by(?:[_\s-]*sa)?|zero)|cc0|public\s+domain|gemeinfrei|lo\s*v?\.?\s*2(?:\.0)?|etalab[-_\s]*2(?:\.0)?)/iu', $txt, $m)) return '';
    $v = strtolower(trim(preg_replace('/\s+/', ' ', $m[1])));
    if (strpos($v, 'by') !== false && strpos($v, 'sa') !== false) return 'CC BY-SA';
    if (strpos($v, 'by') !== false) return 'CC BY';
    if (strpos($v, 'cc0') !== false || strpos($v, 'zero') !== false || strpos($v, 'public') !== false || strpos($v, 'gemeinfrei') !== false) return 'CC0 / Public Domain';
    if (strpos($v, 'lo') === 0 || strpos($v, 'etalab') !== false) return 'Licence Ouverte 2.0';
    return '';
}

function ruhrpott_guru_food_media_regional_credit($html) {
    $txt = html_entity_decode(wp_strip_all_tags((string)$html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (preg_match('/Quelle\s*\/\s*Organisation\s*:?\s*([^\r\n]{2,140})/iu', $txt, $m)) {
        $v = trim(preg_replace('/\s+/', ' ', $m[1]));
        if ($v !== '') return $v;
    }
    return 'Stadt. Land. Niederrhein / regionaler Open-Data-Datensatz';
}


/**
 * The regional portal contains official tourism/municipal source records. When an explicit
 * open license is missing we still allow an exact venue page as an official-source image,
 * but only if the page names a trusted public/tourism/marketing organisation. This mirrors
 * the existing policy for an OSM-linked official business website: identity is verified,
 * while no reuse license is invented.
 */
function ruhrpott_guru_food_media_regional_trusted_credit($credit) {
    $n = ruhrpott_guru_food_media_normalize_text($credit);
    if ($n === '') return false;
    return preg_match('/(?:weselmarketing|niederrhein tourism|tourismus|stadtmarketing|stadt |kreis |gemeinde |wirtschaftsfoerder|wirtschaftsforder|marketing gmbh)/i', $n) === 1;
}

function ruhrpott_guru_food_media_regional_city_supported($city) {
    $n = ruhrpott_guru_food_media_normalize_text($city);
    if ($n === '') return false;
    $supported = array(
        'wesel','dinslaken','voerde','hamminkeln','xanten','moers','kamp lintfort','rheinberg',
        'neukirchen vluyn','schermbeck','hunxe','hünxe','sonsbeck','alpen','krefeld',
        'monchengladbach','mönchengladbach','viersen','kleve','goch','kevelaer','geldern','emmerich',
        'rees','kalkar','straelen','kempen','nettetal','grefrath','tonisvorst','tönisvorst','willich'
    );
    foreach ($supported as $v) if ($n === ruhrpott_guru_food_media_normalize_text($v)) return true;
    return false;
}

function ruhrpott_guru_food_media_regional_search_terms($title, $city) {
    $terms = array(trim($title . ' ' . $city));
    $tokens = ruhrpott_guru_food_media_name_tokens($title);
    if ($tokens) {
        // A distinctive token + city recovers names such as "Eiscafe Venezia Martinelli"
        // when the tourism dataset is stored simply as "Eiscafé Venezia".
        $ranked = $tokens;
        usort($ranked, function($a,$b){ return strlen($b) <=> strlen($a); });
        foreach (array_slice($ranked, 0, 2) as $token) $terms[] = trim($token . ' ' . $city);
    }
    return array_values(array_unique(array_filter($terms)));
}

/**
 * Find a matching gastronomy dataset page from the regional tourism portal. We only accept the
 * image when (a) title/city/address identify the exact venue and (b) the page explicitly declares
 * an open image license. This recovers eT4/WeselMarketing imagery without fuzzy image matching.
 */
function ruhrpott_guru_food_media_regional_open_data($title, $city, $address) {
    $city_n = ruhrpott_guru_food_media_normalize_text($city);
    if ($city_n === '' || !ruhrpott_guru_food_media_regional_city_supported($city)) return null;
    $cache_key = 'rg_food_regional_v2_' . md5(strtolower($title . '|' . $city . '|' . $address));
    $cached = get_transient($cache_key);
    if (is_array($cached) && array_key_exists('hit', $cached)) return $cached['hit'] ? $cached['value'] : null;

    $urls = array();
    // Query the portal with a full identity term first, then at most two distinctive-name variants.
    // This is bounded and cached; every returned page still has to pass the strict address check.
    foreach (ruhrpott_guru_food_media_regional_search_terms($title, $city) as $term) {
        $search_url = add_query_arg(array('search'=>$term,'per_page'=>8,'subtype'=>'any'), 'https://stadt-land-niederrhein.de/wp-json/wp/v2/search');
        $resp = wp_safe_remote_get($search_url, array(
            'timeout'=>5, 'redirection'=>2, 'limit_response_size'=>180000,
            'user-agent'=>'RuhrpottGuru/1.0 (+https://ruhrpottguru.de)',
            'headers'=>array('Accept'=>'application/json'),
        ));
        if (!is_wp_error($resp) && (int)wp_remote_retrieve_response_code($resp) === 200) {
            $rows = json_decode(wp_remote_retrieve_body($resp), true);
            if (is_array($rows)) foreach (array_slice($rows, 0, 8) as $row) {
                $url = !empty($row['url']) ? ruhrpott_guru_food_media_clean_url($row['url']) : '';
                $host = strtolower((string)wp_parse_url($url, PHP_URL_HOST));
                $path = strtolower((string)wp_parse_url($url, PHP_URL_PATH));
                if ($url && $host === 'stadt-land-niederrhein.de' && preg_match('#/(kulinarik|culinary|culinair)/#', $path)) $urls[] = $url;
            }
        }
        if (count(array_unique($urls)) >= 5) break;
    }

    // Fallback if WP REST search is restricted.
    if (!$urls) {
        foreach (ruhrpott_guru_food_media_regional_search_terms($title, $city) as $term) {
            $fallback_url = add_query_arg('s', $term, 'https://stadt-land-niederrhein.de/');
            $search_html = ruhrpott_guru_food_media_fetch_html($fallback_url);
            if ($search_html && preg_match_all('#href=["\'](https?://stadt-land-niederrhein\.de/(?:kulinarik|en/culinary|nl/culinair)/[^"\'#?]+/?)["\']#i', $search_html, $mm)) {
                $urls = array_merge($urls, $mm[1]);
            }
            if (count(array_unique($urls)) >= 5) break;
        }
    }

    foreach (array_slice(array_values(array_unique($urls)), 0, 5) as $url) {
        $html = ruhrpott_guru_food_media_fetch_html($url);
        if (!$html || !ruhrpott_guru_food_media_regional_identity_matches($html, $title, $city, $address)) continue;

        $license = ruhrpott_guru_food_media_open_license_from_page($html);
        $credit = ruhrpott_guru_food_media_regional_credit($html);
        // No anonymous third-party tourism images: without an explicit open license, require a
        // recognisable public/tourism/marketing source on this exact venue page.
        if ($license === '' && !ruhrpott_guru_food_media_regional_trusted_credit($credit)) continue;

        $candidates = ruhrpott_guru_food_media_collect_page_candidates($html, $url, $title, $city);
        if (!$candidates) continue;
        arsort($candidates, SORT_NUMERIC);
        foreach ($candidates as $img => $score) {
            if ($score < 28 || ruhrpott_guru_food_media_bad_asset($img)) continue;
            $is_open = $license !== '';
            $out = array(
                'imageUrl'=>$img,
                'source'=>$is_open ? 'regional-open-data' : 'regional-tourism',
                'sourceUrl'=>$url,
                'matchMethod'=>$is_open ? 'regional-tourism-open-data' : 'regional-tourism-exact',
                'originStatus'=>'verified',
                'identityConfidence'=>0.99,
                'sourceReliabilityScore'=>0.98,
                'credit'=>'Foto: ' . $credit,
                'artist'=>$credit,
                'license'=>$is_open ? $license : null,
                'reason'=>$is_open
                    ? 'Exact regional tourism dataset page matches venue name, city and OSM street/house number; page declares an open image license'
                    : 'Exact regional tourism dataset page matches venue name, city and OSM street/house number; trusted official/tourism source credit present (no reuse license inferred)',
            );
            set_transient($cache_key, array('hit'=>true,'value'=>$out), 7 * DAY_IN_SECONDS);
            return $out;
        }
    }
    set_transient($cache_key, array('hit'=>false,'value'=>null), 6 * HOUR_IN_SECONDS);
    return null;
}

function ruhrpott_guru_food_media_panoramax_asset_url($feature) {
    if (!is_array($feature)) return '';
    $props = isset($feature['properties']) && is_array($feature['properties']) ? $feature['properties'] : array();
    $assets = isset($feature['assets']) && is_array($feature['assets']) ? $feature['assets'] : array();
    // Prefer standard-definition derivatives: enough for cards and much lighter than originals.
    foreach (array('sd','medium','thumbnail','thumb','hd') as $key) {
        if (!empty($assets[$key]['href'])) {
            $u = ruhrpott_guru_food_media_clean_url($assets[$key]['href']);
            if ($u && wp_http_validate_url($u)) return $u;
        }
    }
    foreach (array('geovisio:image','geovisio:thumbnail') as $key) {
        if (!empty($props[$key]) && is_string($props[$key])) {
            $u = ruhrpott_guru_food_media_clean_url($props[$key]);
            if ($u && wp_http_validate_url($u)) return $u;
        }
    }
    // Last-resort: any image asset advertised by STAC.
    foreach ($assets as $asset) {
        if (!is_array($asset) || empty($asset['href'])) continue;
        $type = isset($asset['type']) ? strtolower((string)$asset['type']) : '';
        if ($type && strpos($type, 'image/') !== 0) continue;
        $u = ruhrpott_guru_food_media_clean_url($asset['href']);
        if ($u && wp_http_validate_url($u)) return $u;
    }
    return '';
}

/**
 * Panoramax has a dedicated POI search: returned pictures are 360° or camera-facing the supplied
 * place_position. That is stronger than arbitrary nearby imagery and is designed for illustrating POIs.
 */
function ruhrpott_guru_food_media_panoramax($title, $city, $lat, $lng) {
    if (!is_numeric($lat) || !is_numeric($lng)) return null;
    $lat = (float)$lat; $lng = (float)$lng;
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) return null;
    $cache_key = 'rg_panoramax_poi_v2_' . md5(number_format($lat, 5, '.', '') . '|' . number_format($lng, 5, '.', ''));
    $cached = get_transient($cache_key);
    if (is_array($cached) && array_key_exists('hit', $cached)) return $cached['hit'] ? $cached['value'] : null;

    $api = add_query_arg(array(
        'place_position' => number_format($lng, 6, '.', '') . ',' . number_format($lat, 6, '.', ''),
        'place_distance' => '2-60',
        'place_fov_tolerance' => 34,
        'limit' => 3,
    ), 'https://api.panoramax.xyz/api/search');
    $resp = wp_safe_remote_get($api, array(
        'timeout'=>6, 'redirection'=>2, 'limit_response_size'=>600000,
        'user-agent'=>'RuhrpottGuru/1.0 (+https://ruhrpottguru.de)',
        'headers'=>array('Accept'=>'application/geo+json,application/json'),
    ));
    if (is_wp_error($resp) || (int)wp_remote_retrieve_response_code($resp) !== 200) {
        set_transient($cache_key, array('hit'=>false,'value'=>null), 30 * MINUTE_IN_SECONDS);
        return null;
    }
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    $features = isset($data['features']) && is_array($data['features']) ? $data['features'] : array();
    foreach (array_slice($features, 0, 3) as $feature) {
        $img = ruhrpott_guru_food_media_panoramax_asset_url($feature);
        if (!$img || ruhrpott_guru_food_media_bad_asset($img)) continue;
        $props = isset($feature['properties']) && is_array($feature['properties']) ? $feature['properties'] : array();
        $id = !empty($feature['id']) ? sanitize_text_field((string)$feature['id']) : '';
        $producer = '';
        if (!empty($props['geovisio:producer'])) {
            if (is_string($props['geovisio:producer'])) $producer = sanitize_text_field($props['geovisio:producer']);
            elseif (is_array($props['geovisio:producer'])) {
                foreach (array('name','label','id') as $k) if (!empty($props['geovisio:producer'][$k])) { $producer = sanitize_text_field((string)$props['geovisio:producer'][$k]); break; }
            }
        }
        $license = !empty($props['license']) && is_string($props['license']) ? sanitize_text_field($props['license']) : 'CC BY-SA 4.0 / Licence Ouverte 2.0';
        $source_url = $id ? 'https://api.panoramax.xyz/api/search?ids=' . rawurlencode($id) : 'https://api.panoramax.xyz/';
        $out = array(
            'imageUrl'=>$img,
            'source'=>'panoramax',
            'sourceUrl'=>$source_url,
            'matchMethod'=>'panoramax-poi-visible',
            'originStatus'=>'probable',
            'identityConfidence'=>0.82,
            'sourceReliabilityScore'=>0.96,
            'credit'=>'Offene Straßenaufnahme: Panoramax' . ($producer ? ' · ' . $producer : ''),
            'artist'=>$producer ?: 'Panoramax contributor',
            'license'=>$license,
            'reason'=>'Panoramax POI search returned an open street-level picture whose field of view contains the exact OSM coordinate; contextual location image, not claimed as an exact venue-owned photo',
        );
        set_transient($cache_key, array('hit'=>true,'value'=>$out), 3 * DAY_IN_SECONDS);
        return $out;
    }
    set_transient($cache_key, array('hit'=>false,'value'=>null), 6 * HOUR_IN_SECONDS);
    return null;
}

function ruhrpott_guru_food_media_kartaview_nearby($lat, $lng) {
    if (!is_numeric($lat) || !is_numeric($lng)) return array();
    $lat = (float) $lat; $lng = (float) $lng;
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) return array();

    $cell = number_format($lat, 3, '.', '') . '|' . number_format($lng, 3, '.', '');
    $cache_key = 'rg_kv_nearby_v2_' . md5($cell);
    $cached = get_transient($cache_key);
    if (is_array($cached)) return $cached;

    $api = add_query_arg(array(
        'lat' => $lat,
        'lng' => $lng,
        'zoomLevel' => 18,
        'radius' => 220,
        'join' => 'sequence',
        'orderBy' => 'id',
        'orderDirection' => 'desc',
        'itemsPerPage' => 50,
    ), 'https://api.openstreetcam.org/2.0/photo/');

    $resp = wp_safe_remote_get($api, array(
        'timeout' => 6,
        'redirection' => 2,
        'user-agent' => 'RuhrpottGuru/1.0 (+https://ruhrpottguru.de)',
        'limit_response_size' => 700000,
        'headers' => array('Accept' => 'application/json'),
    ));
    if (is_wp_error($resp) || (int) wp_remote_retrieve_response_code($resp) !== 200) {
        set_transient($cache_key, array(), 20 * MINUTE_IN_SECONDS);
        return array();
    }

    $data = json_decode(wp_remote_retrieve_body($resp), true);
    $photos = isset($data['result']['data']) && is_array($data['result']['data']) ? $data['result']['data'] : array();
    if ($photos && isset($photos['id'])) $photos = array($photos);
    if (!$photos && isset($data['result']) && is_array($data['result']) && isset($data['result'][0])) $photos = $data['result'];

    // Keep only small scalar metadata needed for matching; never cache the whole API response.
    $clean = array();
    foreach (array_slice($photos, 0, 80) as $photo) {
        if (!is_array($photo)) continue;
        $img = '';
        foreach (array('fileurl', 'fileurlTh', 'fileurlLTh', 'fileUrl', 'fileUrlTh') as $k) {
            if (!empty($photo[$k]) && is_string($photo[$k])) { $img = ruhrpott_guru_food_media_clean_url($photo[$k]); if ($img) break; }
        }
        if (!$img || !wp_http_validate_url($img)) continue;
        $plat = isset($photo['lat']) && is_numeric($photo['lat']) ? (float) $photo['lat'] : null;
        $plng = isset($photo['lng']) && is_numeric($photo['lng']) ? (float) $photo['lng'] : null;
        if ($plat === null || $plng === null) continue;
        $clean[] = array(
            'id' => isset($photo['id']) ? sanitize_text_field((string) $photo['id']) : '',
            'url' => $img,
            'lat' => $plat,
            'lng' => $plng,
            'heading' => isset($photo['heading']) && is_numeric($photo['heading']) ? (float) $photo['heading'] : null,
            'fieldOfView' => isset($photo['fieldOfView']) && is_numeric($photo['fieldOfView']) ? (float) $photo['fieldOfView'] : null,
            'projection' => isset($photo['projection']) ? sanitize_text_field((string) $photo['projection']) : '',
            'shotDate' => isset($photo['shotDate']) ? sanitize_text_field((string) $photo['shotDate']) : '',
            'status' => isset($photo['status']) ? strtolower(sanitize_text_field((string) $photo['status'])) : '',
            'visibility' => isset($photo['visibility']) ? strtolower(sanitize_text_field((string) $photo['visibility'])) : '',
        );
    }
    set_transient($cache_key, $clean, $clean ? 60 * MINUTE_IN_SECONDS : 20 * MINUTE_IN_SECONDS);
    return $clean;
}

/**
 * Select a nearby street-level image only when geometry supports it. This is deliberately
 * NOT called a venue photo: it is a real, open-licensed street image near the POI.
 */
function ruhrpott_guru_food_media_kartaview($title, $city, $lat, $lng) {
    if (!is_numeric($lat) || !is_numeric($lng)) return null;
    $lat = (float) $lat; $lng = (float) $lng;
    $photos = ruhrpott_guru_food_media_kartaview_nearby($lat, $lng);
    if (!$photos) return null;

    $best = null; $best_score = -INF;
    foreach ($photos as $photo) {
        if (!is_array($photo) || empty($photo['url'])) continue;
        if (!empty($photo['status']) && !in_array($photo['status'], array('active','published','processed'), true)) continue;
        if (!empty($photo['visibility']) && !in_array($photo['visibility'], array('public','visible'), true)) continue;

        $dist = ruhrpott_guru_food_media_haversine_m($photo['lat'], $photo['lng'], $lat, $lng);
        if ($dist > 95.0) continue;

        $fov = isset($photo['fieldOfView']) && is_numeric($photo['fieldOfView']) ? (float) $photo['fieldOfView'] : 0.0;
        $heading = isset($photo['heading']) && is_numeric($photo['heading']) ? (float) $photo['heading'] : null;
        $angle = null;
        if ($heading !== null && $fov < 180.0) {
            $toward = ruhrpott_guru_food_media_bearing_deg($photo['lat'], $photo['lng'], $lat, $lng);
            $angle = ruhrpott_guru_food_media_angle_diff($heading, $toward);
            if ($angle > 55.0) continue;
        } elseif ($heading === null && $dist > 18.0) {
            // Without heading we only trust imagery extremely close to the POI.
            continue;
        }

        $score = 120.0 - ($dist * 1.15) - (($angle === null ? 0.0 : $angle) * 0.35);
        if ($fov >= 180.0) $score += 4.0;
        if ($score > $best_score) { $best_score = $score; $best = array($photo, $dist, $angle); }
    }
    if (!$best) return null;

    $photo = $best[0]; $dist = $best[1];
    $source_url = 'https://kartaview.org/map/@' . rawurlencode(number_format($lat, 6, '.', '')) . ',' . rawurlencode(number_format($lng, 6, '.', '')) . ',18z';
    return array(
        'imageUrl' => $photo['url'],
        'source' => 'kartaview',
        'sourceUrl' => $source_url,
        'matchMethod' => 'kartaview-nearby-facing-poi',
        'originStatus' => 'probable',
        'identityConfidence' => 0.72,
        'sourceReliabilityScore' => 0.92,
        'credit' => 'Straßenaufnahme nahe dem Ort · © Grab and KartaView Contributors',
        'artist' => '© Grab and KartaView Contributors',
        'license' => 'CC BY-SA 4.0',
        'reason' => 'Open street-level image near the exact OSM coordinate; camera geometry points toward the POI. It is contextual imagery, not claimed as an exact venue photo.',
        'distanceMeters' => (int) round($dist),
        'shotDate' => !empty($photo['shotDate']) ? $photo['shotDate'] : null,
    );
}


/**
 * Map venue metadata to a bounded set of Pexels searches. The restaurant title is
 * used only for cuisine/type classification and deterministic photo selection; it
 * is never used to claim that a stock photo depicts the venue itself.
 */
function ruhrpott_guru_food_media_pexels_query($title, $subcategory, $cuisine, $tags) {
    $haystack = ruhrpott_guru_food_media_normalize_text(
        $title . ' ' . $subcategory . ' ' . $cuisine . ' ' . implode(' ', is_array($tags) ? $tags : array())
    );
    $rules = array(
        array('/acai|bowl|smoothie|healthy/', 'acai bowl fruit healthy food'),
        array('/eis|gelato|ice cream/', 'gelato ice cream dessert'),
        array('/kaffee|coffee|cafe|roest|rosterei|roastery/', 'coffee cafe pastry'),
        array('/back|bakery|brot|konditor|pastry/', 'bakery pastry bread'),
        array('/sushi|japan/', 'sushi japanese food'),
        array('/china|chinese|asiatisch|asian/', 'chinese food restaurant'),
        array('/doner|doener|kebab|turk/', 'kebab turkish food'),
        array('/pizza|pizzeria|italien|italian/', 'pizza italian food'),
        array('/burger|pommes|fried food|fast food/', 'burger fries fast food'),
        array('/indisch|indian/', 'indian food'),
        array('/griech|greek/', 'greek food mediterranean'),
        array('/thai/', 'thai food'),
        array('/vietnam/', 'vietnamese food'),
        array('/mexik|mexican|taco/', 'mexican food tacos'),
        array('/steak|grill|bbq/', 'grilled food steak'),
        array('/bar|pub|cocktail|bier|beer/', 'bar drinks restaurant'),
        array('/fruhstuck|fruehstueck|breakfast|brunch/', 'breakfast brunch cafe'),
    );
    foreach ($rules as $rule) {
        if (preg_match($rule[0], $haystack)) return $rule[1];
    }
    return 'restaurant food table';
}

function ruhrpott_guru_food_media_pexels_bad_alt($alt) {
    $alt = ruhrpott_guru_food_media_normalize_text($alt);
    // V82 strict media rule: an unlabelled stock photo is not safe enough for
    // a person-free UI. Pexels normally supplies descriptive alt text.
    if ($alt === '') return true;
    return preg_match('/\b(man|men|male|woman|women|female|person|persons|people|human|humans|couple|couples|girl|girls|boy|boys|child|children|kid|kids|adult|adults|family|families|friend|friends|group|crowd|customer|customers|guest|guests|staff|worker|workers|employee|employees|owner|owners|chef|chefs|waiter|waiters|waitress|waitresses|bartender|bartenders|tourist|tourists|visitor|visitors|cyclist|cyclists|hiker|hikers|runner|runners|performer|performers|artist|artists|musician|musicians|singer|singers|audience|spectator|spectators|face|faces|portrait|portraits|selfie|selfies|wedding|model|models|mann|maenner|frau|frauen|mensch|menschen|paar|paare|maedchen|junge|kinder|familie|freunde|gruppe|gaeste|mitarbeiter|koch|koechin|kellner|kellnerin|touristen|besucher|radfahrer|wanderer|laeufer|publikum|gesicht|gesichter)\b/i', $alt) === 1;
}

function ruhrpott_guru_food_media_pexels($title, $city, $address, $subcategory, $cuisine, $tags) {
    $api_key = ruhrpott_guru_get_pexels_api_key();
    if ($api_key === '') return null;

    $query = ruhrpott_guru_food_media_pexels_query($title, $subcategory, $cuisine, $tags);
    // One cached pool per semantic food type: never one Pexels API call per POI.
    $pool_key = 'rg_pexels_pool_v3_person_free_' . md5($query);
    $photos = get_transient($pool_key);

    if (!is_array($photos)) {
        $url = add_query_arg(array(
            'query' => $query,
            'orientation' => 'landscape',
            'size' => 'large',
            'per_page' => 80,
            'page' => 1,
        ), 'https://api.pexels.com/v1/search');
        $response = wp_remote_get($url, array(
            'timeout' => 8,
            'redirection' => 2,
            'headers' => array(
                'Authorization' => $api_key,
                'Accept' => 'application/json',
                'User-Agent' => 'RuhrpottGuru/1.0; https://www.ruhrpottguru.de/',
            ),
        ));
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            // Short negative cache prevents repeated calls during provider/rate-limit problems.
            set_transient($pool_key, array(), 15 * MINUTE_IN_SECONDS);
            return null;
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $raw = (is_array($data) && isset($data['photos']) && is_array($data['photos'])) ? $data['photos'] : array();
        $photos = array();
        foreach ($raw as $photo) {
            if (!is_array($photo) || empty($photo['src']) || !is_array($photo['src'])) continue;
            if (ruhrpott_guru_food_media_pexels_bad_alt(isset($photo['alt']) ? $photo['alt'] : '')) continue;
            $image = '';
            foreach (array('landscape','large2x','large','medium') as $size) {
                if (!empty($photo['src'][$size])) { $image = ruhrpott_guru_food_media_clean_url($photo['src'][$size]); break; }
            }
            if ($image === '') continue;
            $photo_page = !empty($photo['url']) ? ruhrpott_guru_food_media_clean_url($photo['url']) : 'https://www.pexels.com/';
            if ($photo_page) {
                $photo_page = add_query_arg(array('utm_source'=>'ruhrpottguru','utm_medium'=>'referral'), $photo_page);
            }
            $photos[] = array(
                'imageUrl' => $image,
                'sourceUrl' => $photo_page ?: 'https://www.pexels.com/',
                'artist' => !empty($photo['photographer']) ? sanitize_text_field($photo['photographer']) : 'Pexels Contributor',
                'photoId' => isset($photo['id']) ? (string) $photo['id'] : '',
                'alt' => isset($photo['alt']) ? sanitize_text_field((string) $photo['alt']) : '',
            );
        }
        // Pexels explicitly recommends caching API responses; 24h also keeps quota usage bounded.
        set_transient($pool_key, $photos, DAY_IN_SECONDS);
    }

    if (!$photos) return null;
    $seed = strtolower($title . '|' . $city . '|' . $address . '|' . $query);
    $index = (int) sprintf('%u', crc32($seed)) % count($photos);
    $photo = $photos[$index];
    return array(
        'imageUrl' => $photo['imageUrl'],
        'source' => 'pexels',
        'sourceUrl' => $photo['sourceUrl'],
        'mediaType' => 'stock-symbolic',
        'matchMethod' => 'pexels-symbolic-' . sanitize_key(str_replace(' ', '-', $query)),
        'originStatus' => 'symbolic',
        'identityConfidence' => 0.0,
        'sourceReliabilityScore' => 1.0,
        'credit' => 'Symbolfoto · Pexels · Foto: ' . $photo['artist'],
        'artist' => $photo['artist'],
        'license' => 'Pexels License',
        'reason' => 'Clearly labelled Pexels stock photo selected by food/cuisine type. It is not claimed to depict this venue.',
        'symbolic' => true,
        'query' => $query,
        'photoId' => $photo['photoId'],
        'alt' => isset($photo['alt']) ? $photo['alt'] : '',
    );
}

function ruhrpott_guru_food_media_resolver(WP_REST_Request $request) {
    $p = $request->get_json_params();
    if (!is_array($p)) $p = array();
    $title = sanitize_text_field(isset($p['title']) ? $p['title'] : '');
    $city = sanitize_text_field(isset($p['city']) ? $p['city'] : '');
    $address = sanitize_text_field(isset($p['address']) ? $p['address'] : '');
    $website = isset($p['website']) ? esc_url_raw($p['website']) : '';
    $qid = sanitize_text_field(isset($p['wikidata']) ? $p['wikidata'] : '');
    $osm_image = isset($p['osmImage']) ? trim((string) $p['osmImage']) : '';
    $commons = isset($p['wikimediaCommons']) ? trim((string) $p['wikimediaCommons']) : '';
    $wikipedia = isset($p['wikipedia']) ? trim((string) $p['wikipedia']) : '';
    $lat = isset($p['lat']) && is_numeric($p['lat']) ? (float) $p['lat'] : null;
    $lng = isset($p['lng']) && is_numeric($p['lng']) ? (float) $p['lng'] : null;
    $subcategory = sanitize_text_field(isset($p['subcategory']) ? $p['subcategory'] : '');
    $cuisine = sanitize_text_field(isset($p['cuisine']) ? $p['cuisine'] : '');
    $tags = (isset($p['tags']) && is_array($p['tags'])) ? array_slice(array_map('sanitize_text_field', $p['tags']), 0, 20) : array();
    $allow_symbolic = !isset($p['allowSymbolic']) || (bool) $p['allowSymbolic'];
    $category = sanitize_key(isset($p['category']) ? $p['category'] : 'food');
    $original_place_category = in_array($category, array('highlights','culture','activities'), true);

    if ($title === '') return new WP_REST_Response(array('status' => 'invalid', 'imageUrl' => null), 400);
    $cache_key = 'rg_food_media_v11_original_places_' . md5(strtolower($category . '|' . $title . '|' . $city . '|' . $address . '|' . $website . '|' . $qid . '|' . $osm_image . '|' . $commons . '|' . $wikipedia . '|' . $lat . '|' . $lng . '|' . $subcategory . '|' . $cuisine . '|' . implode(',', $tags) . '|' . ($allow_symbolic ? '1' : '0')));
    $cached = get_transient($cache_key);
    if (is_array($cached)) return new WP_REST_Response($cached, 200);

    // Direct image linked on the OSM object: strongest non-Wikidata signal.
    $direct = ruhrpott_guru_food_media_commons_file_url($osm_image);
    if (!$direct) $direct = ruhrpott_guru_food_media_clean_url($osm_image);
    if ($direct && !ruhrpott_guru_food_media_bad_asset($direct)) {
        $out = array('status'=>'success','imageUrl'=>$direct,'source'=>'official-source','sourceUrl'=>$website ?: $direct,'matchMethod'=>'osm-image','originStatus'=>'verified','identityConfidence'=>0.99,'sourceReliabilityScore'=>0.95,'credit'=>'Foto laut OSM-Objekt','license'=>null,'reason'=>'Image is explicitly linked to this OSM venue object');
        set_transient($cache_key, $out, 7 * DAY_IN_SECONDS);
        return new WP_REST_Response($out, 200);
    }

    $commons_img = ruhrpott_guru_food_media_commons_file_url($commons);
    if ($commons_img) {
        $out = array('status'=>'success','imageUrl'=>$commons_img,'source'=>'wikimedia-commons','sourceUrl'=>$commons,'matchMethod'=>'osm-wikimedia-commons','originStatus'=>'verified','identityConfidence'=>0.99,'sourceReliabilityScore'=>1.0,'credit'=>'Foto via Wikimedia Commons (direkt in OSM verknüpft)','license'=>null,'reason'=>'Wikimedia Commons media is explicitly linked to this OSM venue object');
        set_transient($cache_key, $out, 7 * DAY_IN_SECONDS);
        return new WP_REST_Response($out, 200);
    }

    $commons_cat = ruhrpott_guru_food_media_commons_category($commons);
    if ($commons_cat) {
        $commons_cat['status'] = 'success';
        set_transient($cache_key, $commons_cat, 7 * DAY_IN_SECONDS);
        return new WP_REST_Response($commons_cat, 200);
    }

    $wd = ruhrpott_guru_food_media_wikidata_p18($qid);
    if ($wd) {
        $wd['status'] = 'success';
        set_transient($cache_key, $wd, 7 * DAY_IN_SECONDS);
        return new WP_REST_Response($wd, 200);
    }

    $wpimg = ruhrpott_guru_food_media_wikipedia_pageimage($wikipedia);
    if ($wpimg) {
        $wpimg['status'] = 'success';
        set_transient($cache_key, $wpimg, 7 * DAY_IN_SECONDS);
        return new WP_REST_Response($wpimg, 200);
    }

    // V112: for Highlights/Kultur/Aktiv prefer original identity-bound sources.
    // If an official website has been resolved (OSM / Wikidata P856 / eT4 POI),
    // inspect that site before looser geospatial discovery.
    if ($original_place_category) {
        $official = ruhrpott_guru_food_media_official_website($website, $title, $city, $address);
        if ($official) {
            $official['status'] = 'success';
            set_transient($cache_key, $official, 3 * DAY_IN_SECONDS);
            return new WP_REST_Response($official, 200);
        }

        $regional = ruhrpott_guru_food_media_regional_open_data($title, $city, $address);
        if ($regional) {
            $regional['status'] = 'success';
            set_transient($cache_key, $regional, 7 * DAY_IN_SECONDS);
            return new WP_REST_Response($regional, 200);
        }

        // Larger POIs (parks, pools, industrial sites, museums) often have Commons
        // photos geotagged away from the OSM centre point. We may search farther,
        // but still require the same strict venue-name token agreement.
        $commons_geo = ruhrpott_guru_food_media_commons_geo($title, $city, $lat, $lng, 500);
        if ($commons_geo) {
            $commons_geo['status'] = 'success';
            set_transient($cache_key, $commons_geo, 7 * DAY_IN_SECONDS);
            return new WP_REST_Response($commons_geo, 200);
        }
    } else {
        // Food keeps the conservative 120 m identity-safe geospatial lookup.
        $commons_geo = ruhrpott_guru_food_media_commons_geo($title, $city, $lat, $lng, 120);
        if ($commons_geo) {
            $commons_geo['status'] = 'success';
            set_transient($cache_key, $commons_geo, 7 * DAY_IN_SECONDS);
            return new WP_REST_Response($commons_geo, 200);
        }

        $regional = ruhrpott_guru_food_media_regional_open_data($title, $city, $address);
        if ($regional) {
            $regional['status'] = 'success';
            set_transient($cache_key, $regional, 7 * DAY_IN_SECONDS);
            return new WP_REST_Response($regional, 200);
        }

        $official = ruhrpott_guru_food_media_official_website($website, $title, $city, $address);
        if ($official) {
            $official['status'] = 'success';
            set_transient($cache_key, $official, 3 * DAY_IN_SECONDS);
            return new WP_REST_Response($official, 200);
        }
    }

    // Panoramax' dedicated POI-visible search is designed to find an image whose field of view
    // contains the supplied coordinate. It is open imagery and remains labelled as contextual.
    if (!$original_place_category) {
        $px = ruhrpott_guru_food_media_panoramax($title, $city, $lat, $lng);
        if ($px) {
            $px['status'] = 'success';
            set_transient($cache_key, $px, 3 * DAY_IN_SECONDS);
            return new WP_REST_Response($px, 200);
        }

        // Secondary open, geospatially constrained street-level context.
        $kv = ruhrpott_guru_food_media_kartaview($title, $city, $lat, $lng);
        if ($kv) {
            $kv['status'] = 'success';
            set_transient($cache_key, $kv, 3 * DAY_IN_SECONDS);
            return new WP_REST_Response($kv, 200);
        }
    }

    // Final launch fallback for ACTIVE venues only: a clearly labelled category/cuisine stock photo.
    // It is never presented as a photo of the venue and it never replaces a verified real image.
    if ($allow_symbolic) {
        $pexels = ruhrpott_guru_food_media_pexels($title, $city, $address, $subcategory, $cuisine, $tags);
        if ($pexels) {
            $pexels['status'] = 'success';
            set_transient($cache_key, $pexels, 7 * DAY_IN_SECONDS);
            return new WP_REST_Response($pexels, 200);
        }
    }

    // Food safety gate: do not use generic Commons/name matching and do not invent an image.
    // The frontend renders a neutral no-photo state when all real/open/symbolic sources are exhausted.
    $out = array('status'=>'not-found','imageUrl'=>null,'reason'=>'No reliable real/contextual image and no configured Pexels symbolic fallback available; neutral no-photo state required');
    set_transient($cache_key, $out, 6 * HOUR_IN_SECONDS);
    return new WP_REST_Response($out, 200);
}


/**
 * V114 – Trusted Event Sources
 *
 * Legal rule: only machine-readable sources with an explicit re-use basis are
 * allowed to enter the automatic event pipeline. Publicly visible calendars
 * without a clear re-use grant are never scraped.
 */
function ruhrpott_guru_event_source_registry_v114() {
    return array(
        'open_data_finder' => array(
            'id' => 'open_data_finder',
            'name' => 'Open Data Finder RTG – Veranstaltungen',
            'operator' => 'Ruhr Tourismus GmbH',
            'status' => 'green',
            'enabled' => true,
            'kind' => 'api',
            'license' => 'CC BY-SA',
            'attribution' => 'Quelle: Ruhr Tourismus GmbH / Open Data Finder – CC BY-SA',
            'terms_url' => 'https://opendata.ruhr/dataset/open-data-finder-rtg-veranstaltungen',
            'coverage' => 'Metropole Ruhr',
            'trust' => array('identity'=>0.94,'date'=>0.90,'venue'=>0.92,'geo'=>0.94,'description'=>0.90,'media'=>0.88),
        ),
        'destination_one_open_data' => array(
            'id' => 'destination_one_open_data',
            'name' => 'destination.one Open-Data-Pool',
            'operator' => 'destination.one / teilnehmende Open-Data-Partner',
            'status' => 'green',
            'enabled' => true,
            'kind' => 'api',
            'license' => 'Open-Data-Pool; Rechte/Attribution gemäß Quelldatensatz',
            'attribution' => 'Quelle: destination.one Open Data / jeweiliger Datenpartner',
            'coverage' => 'öffentlicher überregionaler Fallback',
            'trust' => array('identity'=>0.90,'date'=>0.90,'venue'=>0.88,'geo'=>0.90,'description'=>0.84,'media'=>0.80),
        ),
        'essen_emg_export' => array(
            'id' => 'essen_emg_export',
            'name' => 'Veranstaltungsexport Essen',
            'operator' => 'EMG - Essen Marketing GmbH',
            'status' => 'green',
            'enabled' => true,
            'kind' => 'official-export',
            'license' => 'Official re-use grant / attribution required',
            'attribution' => 'Mit freundlicher Unterstützung der EMG - Essen Marketing GmbH.',
            'terms_url' => 'https://www.visitessen.de/exporte/export_veranstaltungen.de.jsp',
            'coverage' => 'Essen',
            'trust' => array('identity'=>0.98,'date'=>0.98,'venue'=>0.98,'geo'=>0.95,'description'=>0.00,'media'=>0.00),
            // V114 deliberately uses only factual fields. Descriptions/images
            // are not copied from this supplementary feed unless their rights
            // are separately machine-readable.
            'factual_only' => true,
        ),
        'dortmund_rss' => array(
            'id' => 'dortmund_rss',
            'name' => 'Stadt Dortmund Veranstaltungs-RSS',
            'operator' => 'Stadt Dortmund',
            'status' => 'red',
            'enabled' => false,
            'kind' => 'rss',
            'license' => 'Commercial integration expressly not permitted',
            'coverage' => 'Dortmund',
            'reason' => 'Nicht für RuhrpottGuru importieren; städtische RSS-Feeds dürfen laut veröffentlichter Auskunft nicht in kommerzielle Dienste eingebunden werden.',
        ),
        'hagen_rss' => array(
            'id' => 'hagen_rss',
            'name' => 'Stadt Hagen Veranstaltungs-RSS',
            'operator' => 'Stadt Hagen',
            'status' => 'yellow',
            'enabled' => false,
            'kind' => 'rss',
            'license' => 'Subscription offered; no explicit republication grant verified',
            'coverage' => 'Hagen',
            'reason' => 'RSS-Abonnement ist bestätigt, automatische Weiterveröffentlichung jedoch nicht ausdrücklich freigegeben.',
        ),
        'datahub_nrw_partners' => array(
            'id' => 'datahub_nrw_partners',
            'name' => 'DataHub NRW / DestinationHub.Ruhr Partnerdaten',
            'operator' => 'Tourismus NRW / Ruhr Tourismus und kommunale Partner',
            'status' => 'green-redundant',
            'enabled' => false,
            'kind' => 'open-data-ecosystem',
            'license' => 'CC0 / CC BY / CC BY-SA per record',
            'coverage' => 'mehrere Kommunen der Metropole Ruhr',
            'reason' => 'Bereits über den Open Data Finder / destination.one-Pool im bestehenden RuhrpottGuru-Transport abgedeckt; kein separater Doppelimport.',
        ),
    );
}

function ruhrpott_guru_event_municipalities_v114() {
    return array(
        'Bochum','Bottrop','Dortmund','Duisburg','Essen','Gelsenkirchen','Hagen','Hamm','Herne','Mülheim an der Ruhr','Oberhausen',
        'Breckerfeld','Ennepetal','Gevelsberg','Hattingen','Herdecke','Schwelm','Sprockhövel','Wetter (Ruhr)','Witten',
        'Castrop-Rauxel','Datteln','Dorsten','Gladbeck','Haltern am See','Herten','Marl','Oer-Erkenschwick','Recklinghausen','Waltrop',
        'Bergkamen','Bönen','Fröndenberg/Ruhr','Holzwickede','Kamen','Lünen','Schwerte','Selm','Unna','Werne',
        'Alpen','Dinslaken','Hamminkeln','Hünxe','Kamp-Lintfort','Moers','Neukirchen-Vluyn','Rheinberg','Schermbeck','Sonsbeck','Voerde (Niederrhein)','Wesel','Xanten'
    );
}

function ruhrpott_guru_event_source_coverage_v114() {
    $rows = array();
    foreach (ruhrpott_guru_event_municipalities_v114() as $city) {
        $rows[$city] = array(
            'baseSource' => 'open_data_finder',
            'baseStatus' => 'green',
            'supplementalSource' => null,
            'supplementalStatus' => 'none-whitelisted',
        );
    }
    $rows['Essen']['supplementalSource'] = 'essen_emg_export';
    $rows['Essen']['supplementalStatus'] = 'green';
    $rows['Dortmund']['supplementalSource'] = 'dortmund_rss';
    $rows['Dortmund']['supplementalStatus'] = 'red';
    $rows['Hagen']['supplementalSource'] = 'hagen_rss';
    $rows['Hagen']['supplementalStatus'] = 'yellow';
    $rows['Lünen']['supplementalSource'] = 'datahub_nrw_partners';
    $rows['Lünen']['supplementalStatus'] = 'green-redundant';
    return $rows;
}

function ruhrpott_guru_register_event_sources_rest_v114() {
    register_rest_route('ruhrpottguru/v1', '/event-sources', array(
        'methods' => 'GET',
        'callback' => function () {
            return new WP_REST_Response(array(
                'ok' => true,
                'version' => 'v114',
                'policy' => 'No calendar scraping. Only explicitly reusable machine-readable sources may be enabled.',
                'sources' => ruhrpott_guru_event_source_registry_v114(),
                'coverage' => ruhrpott_guru_event_source_coverage_v114(),
            ), 200);
        },
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'ruhrpott_guru_register_event_sources_rest_v114');

function ruhrpott_guru_event_tag_source_v114($item, $source_id) {
    if (!is_array($item)) return $item;
    $registry = ruhrpott_guru_event_source_registry_v114();
    if (empty($registry[$source_id])) return $item;
    $s = $registry[$source_id];
    $item['provider_source'] = $source_id;
    $item['source_name'] = $s['name'];
    $item['source_operator'] = $s['operator'];
    $item['source_license'] = $s['license'];
    $item['source_attribution'] = $s['attribution'] ?? '';
    $item['source_terms_url'] = $s['terms_url'] ?? '';
    return $item;
}

function ruhrpott_guru_event_tag_list_v114($items, $source_id) {
    $out = array();
    foreach ((array) $items as $item) {
        if (is_array($item)) $out[] = ruhrpott_guru_event_tag_source_v114($item, $source_id);
    }
    return $out;
}

/**
 * The Essen adapter intentionally discovers the official export form instead
 * of hard-coding an undocumented private endpoint. The page itself explicitly
 * offers JSON/XML export for re-use. We submit only to visitessen.de.
 */
function ruhrpott_guru_essen_export_request_v114() {
    $landing = 'https://www.visitessen.de/exporte/export_veranstaltungen.de.jsp';
    $res = wp_remote_get($landing, array(
        'timeout' => 15,
        'redirection' => 3,
        'headers' => array('Accept'=>'text/html,application/xhtml+xml'),
        'user-agent' => 'RuhrpottGuru/1.0; ' . home_url('/'),
    ));
    if (is_wp_error($res)) return new WP_Error('essen-export-landing', $res->get_error_message());
    if ((int) wp_remote_retrieve_response_code($res) < 200 || (int) wp_remote_retrieve_response_code($res) >= 300) {
        return new WP_Error('essen-export-landing-http', 'HTTP ' . wp_remote_retrieve_response_code($res));
    }
    $html = wp_remote_retrieve_body($res);
    if (!$html) return new WP_Error('essen-export-empty', 'Leere Exportseite');

    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $chosen = null;
    foreach ($dom->getElementsByTagName('form') as $form) {
        $has_json = false;
        foreach ($form->getElementsByTagName('option') as $option) {
            if (strtoupper(trim($option->textContent)) === 'JSON') { $has_json = true; break; }
        }
        if ($has_json) { $chosen = $form; break; }
    }
    if (!$chosen) return new WP_Error('essen-export-form', 'JSON-Exportformular nicht gefunden');

    $action = trim($chosen->getAttribute('action'));
    if ($action === '') $action = $landing;
    elseif (!preg_match('~^https?://~i', $action)) {
        $base = 'https://www.visitessen.de';
        $action = rtrim($base, '/') . '/' . ltrim($action, '/');
    }
    $host = strtolower((string) wp_parse_url($action, PHP_URL_HOST));
    if (!in_array($host, array('www.visitessen.de','visitessen.de'), true)) {
        return new WP_Error('essen-export-host', 'Nicht freigegebener Exporthost');
    }

    $fields = array();
    foreach ($chosen->getElementsByTagName('input') as $input) {
        $name = trim($input->getAttribute('name'));
        if ($name === '') continue;
        $type = strtolower($input->getAttribute('type'));
        if (in_array($type, array('submit','button','image','file'), true)) continue;
        if (in_array($type, array('checkbox','radio'), true) && !$input->hasAttribute('checked')) continue;
        $fields[$name] = $input->getAttribute('value');
    }
    foreach ($chosen->getElementsByTagName('select') as $select) {
        $name = trim($select->getAttribute('name'));
        if ($name === '') continue;
        $value = '';
        $json_value = null;
        foreach ($select->getElementsByTagName('option') as $option) {
            if ($option->hasAttribute('selected')) $value = $option->getAttribute('value');
            if (strtoupper(trim($option->textContent)) === 'JSON') $json_value = $option->getAttribute('value');
        }
        $fields[$name] = $json_value !== null ? $json_value : $value;
    }

    $method = strtoupper(trim($chosen->getAttribute('method')));
    $args = array(
        'timeout' => 20,
        'redirection' => 3,
        'headers' => array('Accept'=>'application/json,text/plain,*/*'),
        'user-agent' => 'RuhrpottGuru/1.0; ' . home_url('/'),
    );
    if ($method === 'POST') {
        $args['body'] = $fields;
        $export = wp_remote_post($action, $args);
    } else {
        $export = wp_remote_get(add_query_arg($fields, $action), $args);
    }
    if (is_wp_error($export)) return $export;
    $code = (int) wp_remote_retrieve_response_code($export);
    if ($code < 200 || $code >= 300) return new WP_Error('essen-export-http', 'HTTP ' . $code);
    return array(
        'body' => wp_remote_retrieve_body($export),
        'content_type' => strtolower((string) wp_remote_retrieve_header($export, 'content-type')),
        'url' => $action,
    );
}

function ruhrpott_guru_json_event_lists_v114($node, &$lists, $depth = 0) {
    if ($depth > 7 || !is_array($node)) return;
    $is_list = function_exists('array_is_list') ? array_is_list($node) : ruhrpott_guru_array_is_list($node);
    if ($is_list && count($node)) {
        $eventish = 0;
        foreach (array_slice($node, 0, 10) as $row) {
            if (is_array($row) && (
                isset($row['name']) || isset($row['title']) || isset($row['startDate']) ||
                isset($row['start']) || isset($row['date'])
            )) $eventish++;
        }
        if ($eventish > 0) $lists[] = $node;
    }
    foreach ($node as $value) {
        if (is_array($value)) ruhrpott_guru_json_event_lists_v114($value, $lists, $depth + 1);
    }
}

function ruhrpott_guru_essen_first_v114($row, $keys, $default = '') {
    foreach ($keys as $key) {
        if (isset($row[$key]) && !is_array($row[$key]) && trim((string)$row[$key]) !== '') return trim((string)$row[$key]);
    }
    return $default;
}

function ruhrpott_guru_essen_event_normalize_v114($row, $index = 0) {
    if (!is_array($row)) return null;
    $title = ruhrpott_guru_essen_first_v114($row, array('name','title','bezeichnung','veranstaltung','eventName'));
    if ($title === '') return null;

    $start = ruhrpott_guru_essen_first_v114($row, array('startDate','start','begin','beginn','date','datum','start_time'));
    $end   = ruhrpott_guru_essen_first_v114($row, array('endDate','end','ende','end_time'));
    if ($start === '') return null;

    $location = isset($row['location']) && is_array($row['location']) ? $row['location'] : array();
    $address = isset($location['address']) && is_array($location['address']) ? $location['address'] : (isset($row['address']) && is_array($row['address']) ? $row['address'] : array());
    $geo = isset($location['geo']) && is_array($location['geo']) ? $location['geo'] : (isset($row['geo']) && is_array($row['geo']) ? $row['geo'] : array());

    $lat = null; $lng = null;
    foreach (array($geo, $row, $location) as $g) {
        if (!is_array($g)) continue;
        $la = $g['latitude'] ?? ($g['lat'] ?? null);
        $lo = $g['longitude'] ?? ($g['lng'] ?? ($g['lon'] ?? null));
        if (is_numeric($la) && is_numeric($lo)) { $lat=(float)$la; $lng=(float)$lo; break; }
    }
    // No guessed coordinates: if the official export does not provide a real
    // point, the item cannot safely enter a radius-based RuhrpottGuru result.
    if ($lat === null || $lng === null || $lat < 50.9 || $lat > 51.7 || $lng < 6.6 || $lng > 7.4) return null;

    $city = ruhrpott_guru_essen_first_v114($address, array('addressLocality','city','ort'), 'Essen');
    if (stripos($city, 'essen') === false) $city = 'Essen';
    $venue = ruhrpott_guru_essen_first_v114($location, array('name','title','venueName','locationName'));
    $street = ruhrpott_guru_essen_first_v114($address, array('streetAddress','street','strasse'));
    $zip = ruhrpott_guru_essen_first_v114($address, array('postalCode','zip','plz'));
    $url = ruhrpott_guru_essen_first_v114($row, array('url','detailUrl','website','link'));
    $id = ruhrpott_guru_essen_first_v114($row, array('id','identifier','global_id'));
    if ($id === '') $id = 'essen-emg-' . md5($title . '|' . $start . '|' . $venue . '|' . $lat . '|' . $lng);

    $item = array(
        'global_id' => $id,
        'title' => wp_strip_all_tags($title),
        'venueName' => wp_strip_all_tags($venue),
        'description' => '', // factual-only legal policy
        'start' => $start,
        'end' => $end,
        'geo' => array('main'=>array('latitude'=>$lat,'longitude'=>$lng)),
        'address' => array('rel'=>'venue','street'=>$street,'zip'=>$zip,'city'=>'Essen'),
        'url' => esc_url_raw($url),
        'source_record_index' => (int)$index,
    );
    return ruhrpott_guru_event_tag_source_v114($item, 'essen_emg_export');
}

function ruhrpott_guru_refresh_essen_events_v114() {
    $attempted = time();
    $result = ruhrpott_guru_essen_export_request_v114();
    if (is_wp_error($result)) {
        update_option('rpg_events_essen_v114_status', array(
            'attemptedAt'=>$attempted,'ok'=>false,'error'=>$result->get_error_message()
        ), false);
        return;
    }

    $payload = json_decode($result['body'], true);
    if (!is_array($payload)) {
        // Some export forms return HTML with a generated JSON download link.
        $body = (string)$result['body'];
        if (preg_match('~href=["\']([^"\']+\.json(?:\?[^"\']*)?)["\']~i', $body, $m)) {
            $url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            if (!preg_match('~^https?://~i', $url)) $url = 'https://www.visitessen.de/' . ltrim($url, '/');
            $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
            if (in_array($host, array('www.visitessen.de','visitessen.de'), true)) {
                $download = wp_remote_get($url, array('timeout'=>20,'redirection'=>3,'headers'=>array('Accept'=>'application/json'),'user-agent'=>'RuhrpottGuru/1.0; '.home_url('/')));
                if (!is_wp_error($download)) $payload = json_decode(wp_remote_retrieve_body($download), true);
            }
        }
    }
    if (!is_array($payload)) {
        update_option('rpg_events_essen_v114_status', array('attemptedAt'=>$attempted,'ok'=>false,'error'=>'Export lieferte kein parsebares JSON'), false);
        return;
    }

    $lists = array();
    ruhrpott_guru_json_event_lists_v114($payload, $lists);
    usort($lists, function($a,$b){ return count($b)-count($a); });
    $rows = $lists[0] ?? array();
    $items = array();
    foreach ($rows as $index=>$row) {
        $item = ruhrpott_guru_essen_event_normalize_v114($row, $index);
        if ($item) $items[] = $item;
    }

    update_option('rpg_events_essen_v114_snapshot', array(
        'updatedAt'=>time(),
        'items'=>$items,
        'source'=>'essen_emg_export',
    ), false);
    update_option('rpg_events_essen_v114_status', array(
        'attemptedAt'=>$attempted,'ok'=>true,'count'=>count($items),'error'=>null
    ), false);
}

add_action('rpg_refresh_essen_events_v114', 'ruhrpott_guru_refresh_essen_events_v114');

function ruhrpott_guru_schedule_event_sources_v114() {
    if (!wp_next_scheduled('rpg_refresh_essen_events_v114')) {
        wp_schedule_event(time() + 60, 'hourly', 'rpg_refresh_essen_events_v114');
    }
}
add_action('init', 'ruhrpott_guru_schedule_event_sources_v114');

function ruhrpott_guru_essen_snapshot_v114() {
    $snap = get_option('rpg_events_essen_v114_snapshot', array());
    $updated = isset($snap['updatedAt']) ? (int)$snap['updatedAt'] : 0;
    if (!$updated || time() - $updated > 2 * HOUR_IN_SECONDS) {
        if (!wp_next_scheduled('rpg_refresh_essen_events_v114')) {
            wp_schedule_single_event(time() + 5, 'rpg_refresh_essen_events_v114');
        }
    }
    return (is_array($snap) && !empty($snap['items']) && is_array($snap['items'])) ? $snap['items'] : array();
}

function ruhrpott_guru_event_norm_text_v114($value) {
    $value = remove_accents(wp_strip_all_tags((string)$value));
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $value = preg_replace('/\b(?:gmbh|e\.?\s*v\.?|essen|dortmund|bochum|duisburg|ruhr)\b/u', ' ', $value);
    $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);
    return trim(preg_replace('/\s+/u', ' ', $value));
}

function ruhrpott_guru_event_start_day_v114($item) {
    $v = isset($item['start']) ? (string)$item['start'] : '';
    if ($v !== '') {
        $ts = strtotime($v);
        if ($ts) return gmdate('Y-m-d', $ts);
    }
    $ints = $item['timeIntervals'] ?? array();
    if (is_array($ints)) {
        foreach ($ints as $x) {
            if (!is_array($x)) continue;
            $v = $x['start'] ?? ($x['startDate'] ?? '');
            $ts = $v ? strtotime((string)$v) : false;
            if ($ts) return gmdate('Y-m-d', $ts);
        }
    }
    return '';
}

function ruhrpott_guru_event_similarity_v114($a, $b) {
    $ta = ruhrpott_guru_event_norm_text_v114($a['title'] ?? ($a['name'] ?? ''));
    $tb = ruhrpott_guru_event_norm_text_v114($b['title'] ?? ($b['name'] ?? ''));
    if ($ta === '' || $tb === '') return 0.0;
    similar_text($ta, $tb, $title_pct);
    $title_score = $title_pct / 100.0;
    if ($title_score < 0.68) return 0.0;

    $da = ruhrpott_guru_event_start_day_v114($a);
    $db = ruhrpott_guru_event_start_day_v114($b);
    if ($da !== '' && $db !== '' && $da !== $db) return 0.0;

    $va = ruhrpott_guru_event_norm_text_v114(ruhrpott_guru_event_venue_name_from_item($a, $a['address'] ?? null));
    $vb = ruhrpott_guru_event_norm_text_v114(ruhrpott_guru_event_venue_name_from_item($b, $b['address'] ?? null));
    $venue_score = 0.5;
    if ($va !== '' && $vb !== '') {
        similar_text($va, $vb, $venue_pct);
        $venue_score = $venue_pct / 100.0;
    }

    $geo_score = 0.5;
    $ga = ruhrpott_guru_event_geo_pair($a);
    $gb = ruhrpott_guru_event_geo_pair($b);
    if ($ga && $gb) {
        $d = ruhrpott_guru_event_distance_km($ga[0],$ga[1],$gb[0],$gb[1]);
        if ($d > 2.0) return 0.0;
        $geo_score = max(0.0, 1.0 - ($d / 2.0));
    }
    return 0.62*$title_score + 0.20*$venue_score + 0.18*$geo_score;
}

function ruhrpott_guru_event_merge_source_meta_v114($keep, $duplicate) {
    if (!is_array($keep) || !is_array($duplicate)) return $keep;
    $sources = array();
    foreach (array($keep,$duplicate) as $x) {
        $id = $x['provider_source'] ?? '';
        if ($id !== '') $sources[$id] = array(
            'id'=>$id,
            'name'=>$x['source_name'] ?? '',
            'license'=>$x['source_license'] ?? '',
            'attribution'=>$x['source_attribution'] ?? '',
            'termsUrl'=>$x['source_terms_url'] ?? '',
        );
    }
    if ($sources) $keep['rpg_sources'] = array_values($sources);
    return $keep;
}


/**
 * Authoritative ET4 event transport.
 *
 * Browser -> WordPress -> ET4. The browser sends only the selected search
 * centre. The ET4 credential stays server-side. We combine a geographic
 * catalogue query with a city query (important for records whose ET4 geo
 * index is incomplete) and an unrolled city occurrence query for today and
 * the future. The frontend remains responsible for the final Haversine radius
 * filter and urgency/date ordering.
 */
function ruhrpott_guru_register_event_rest() {
    register_rest_route('ruhrpottguru/v1', '/events', array(
        'methods' => 'POST',
        'callback' => 'ruhrpott_guru_event_rest',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'ruhrpott_guru_register_event_rest');

function ruhrpott_guru_array_is_list($value) {
    if (!is_array($value)) return false;
    $expected = 0;
    foreach ($value as $key => $_value) {
        if ($key !== $expected) return false;
        $expected++;
    }
    return true;
}

function ruhrpott_guru_et4_extract_items($payload) {
    if (!is_array($payload)) return array();

    // Some licensed ET4 profiles return a direct root array.
    if (ruhrpott_guru_array_is_list($payload)) return $payload;

    if (!isset($payload['items'])) return array();
    $items = $payload['items'];

    if (is_array($items) && ruhrpott_guru_array_is_list($items)) return $items;
    if (is_array($items) && isset($items['item'])) {
        if (is_array($items['item']) && ruhrpott_guru_array_is_list($items['item'])) return $items['item'];
        if (is_array($items['item'])) return array($items['item']);
    }
    return array();
}

function ruhrpott_guru_et4_overall_count($payload) {
    if (!is_array($payload) || ruhrpott_guru_array_is_list($payload)) return null;
    foreach (array('overallcount', 'overallCount', 'total') as $key) {
        if (isset($payload[$key]) && is_numeric($payload[$key])) {
            return max(0, (int) $payload[$key]);
        }
    }
    return null;
}

function ruhrpott_guru_et4_fetch_pages($extra_args, $max_pages = 20, $experience = 'ruhr-tourismus-open-data', $use_license = true) {
    $license_key = $use_license ? ruhrpott_guru_get_et4_license_key() : '';
    if ($use_license && $license_key === '') {
        return array('ok' => false, 'items' => array(), 'pages' => array(), 'error' => 'missing-license', 'experience' => $experience);
    }

    $limit = 100;
    $offset = isset($extra_args['_start_offset']) ? max(0, (int)$extra_args['_start_offset']) : 0;
    if (array_key_exists('_start_offset', $extra_args)) unset($extra_args['_start_offset']);
    $items = array();
    $pages = array();
    $base = array(
        'experience' => $experience,
        'type' => 'Event',
        'template' => 'ET2014A.json',
        'mkt' => 'de-DE',
        'limit' => $limit,
    );
    if ($license_key !== '') $base['licensekey'] = $license_key;

    for ($page = 1; $page <= $max_pages; $page++) {
        $args = array_merge($base, $extra_args, array('offset' => $offset));
        $url = add_query_arg($args, 'https://meta.et4.de/rest.ashx/search/');
        $started = microtime(true);
        $response = wp_remote_get($url, array(
            'timeout' => 12,
            'redirection' => 3,
            'headers' => array('Accept' => 'application/json'),
            'user-agent' => 'RuhrpottGuru/1.0; ' . home_url('/'),
        ));

        if (is_wp_error($response)) {
            return array(
                'ok' => count($items) > 0,
                'items' => $items,
                'pages' => $pages,
                'partial' => count($items) > 0,
                'error' => $response->get_error_message(),
            );
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            return array(
                'ok' => count($items) > 0,
                'items' => $items,
                'pages' => $pages,
                'partial' => count($items) > 0,
                'error' => 'HTTP ' . $status,
            );
        }

        $body = wp_remote_retrieve_body($response);
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return array(
                'ok' => count($items) > 0,
                'items' => $items,
                'pages' => $pages,
                'partial' => count($items) > 0,
                'error' => 'invalid-json',
            );
        }

        // ET2014A wrapper exposes status. A direct root array does not.
        if (!ruhrpott_guru_array_is_list($payload) && isset($payload['status']) && strtoupper((string) $payload['status']) !== 'OK') {
            return array(
                'ok' => count($items) > 0,
                'items' => $items,
                'pages' => $pages,
                'partial' => count($items) > 0,
                'error' => isset($payload['message']) ? (string) $payload['message'] : 'et4-status-' . $payload['status'],
            );
        }

        $page_items = ruhrpott_guru_et4_extract_items($payload);
        $overall = ruhrpott_guru_et4_overall_count($payload);
        $count = count($page_items);
        if ($count === 0) break;

        foreach ($page_items as $item) {
            if (is_array($item)) $items[] = $item;
        }

        $pages[] = array(
            'page' => $page,
            'offset' => $offset,
            'count' => $count,
            'overallcount' => $overall,
            'durationMs' => (int) round((microtime(true) - $started) * 1000),
        );

        // Wrapper response: use overallcount. Root array: a short page is the
        // only authoritative end marker.
        if ($overall !== null && $overall > 0) {
            if ($offset + $count >= $overall || count($items) >= $overall) break;
        } elseif ($count < $limit) {
            break;
        }

        $offset += $limit;
    }

    return array('ok' => true, 'items' => $items, 'pages' => $pages, 'partial' => false, 'error' => null, 'experience' => $experience);
}

function ruhrpott_guru_event_city_query($city) {
    $city = trim((string) $city);
    if ($city === '') return '';
    // Remove UI-only suffixes, not real place-name parentheses.
    $city = preg_replace('/\s+\(Zentrum\)$/u', '', $city);
    $city = preg_replace('/^Dein Standort\s*\(GPS\)$/u', '', $city);
    if ($city === '') return '';
    return 'city:"' . str_replace('"', '', $city) . '"';
}

/**
 * Haversine distance used server-side only for source gating. The frontend
 * still performs the authoritative per-card distance calculation.
 */
function ruhrpott_guru_event_distance_km($lat1, $lng1, $lat2, $lng2) {
    $earth = 6371.0;
    $dlat = deg2rad($lat2 - $lat1);
    $dlng = deg2rad($lng2 - $lng1);
    $a = sin($dlat / 2) * sin($dlat / 2)
       + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
       * sin($dlng / 2) * sin($dlng / 2);
    return $earth * 2 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));
}

function ruhrpott_guru_event_address_score($address) {
    if (!is_array($address)) return -10000;
    $rel = strtolower(trim((string) ($address['rel'] ?? ($address['type'] ?? ''))));
    $score = 0;
    if (preg_match('/event|venue|location|place|veranstaltungsort|main/', $rel)) $score += 150;
    if ($rel === 'default') $score += 15;
    if (preg_match('/organizer|veranstalter|contact|author|source|business|billing|invoice/', $rel)) $score -= 220;
    $street = trim((string) ($address['street'] ?? ($address['streetAddress'] ?? ($address['strasse'] ?? ''))));
    $house = trim((string) ($address['houseNumber'] ?? ($address['house_number'] ?? ($address['housenumber'] ?? ''))));
    $city = trim((string) ($address['city'] ?? ($address['addressLocality'] ?? ($address['locality'] ?? ($address['ort'] ?? '')))));
    $zip = trim((string) ($address['zip'] ?? ($address['postalCode'] ?? ($address['postcode'] ?? ($address['plz'] ?? '')))));
    if ($city !== '') $score += 20;
    if ($street !== '') $score += 75;
    if ($house !== '') $score += 20;
    if ($zip !== '') $score += 35;
    // City-only records are useful as a last resort, but must never beat a
    // complete street address hidden deeper in ET4/destination.one.
    if ($street === '' && $zip === '' && $city !== '') $score -= 30;
    return $score;
}

function ruhrpott_guru_event_normalize_address_v161($a) {
    if (!is_array($a)) return null;
    $street = trim((string) ($a['street'] ?? ($a['streetAddress'] ?? ($a['strasse'] ?? ($a['addressLine1'] ?? '')))));
    $house = trim((string) ($a['houseNumber'] ?? ($a['house_number'] ?? ($a['housenumber'] ?? ($a['houseNo'] ?? '')))));
    if ($house === '' && $street !== '' && preg_match('/^(.*?)[,\s]+(\d+[a-zA-Z]?(?:[-\/]\d+[a-zA-Z]?)?)$/u', $street, $m)) {
        $street = trim($m[1]); $house = trim($m[2]);
    }
    $zip = trim((string) ($a['zip'] ?? ($a['postalCode'] ?? ($a['postcode'] ?? ($a['plz'] ?? '')))));
    $city = trim((string) ($a['city'] ?? ($a['addressLocality'] ?? ($a['locality'] ?? ($a['ort'] ?? '')))));
    $name = trim((string) ($a['name'] ?? ($a['title'] ?? ($a['venueName'] ?? ($a['locationName'] ?? ($a['placeName'] ?? ''))))));
    $rel = trim((string) ($a['rel'] ?? ($a['type'] ?? '')));
    if ($street === '' && $zip === '' && $city === '' && $name === '') return null;
    return array('rel'=>$rel,'name'=>$name,'street'=>$street,'houseNumber'=>$house,'zip'=>$zip,'city'=>$city);
}

function ruhrpott_guru_event_address_candidates_v161($node, &$out, $depth = 0, $parent_key = '') {
    if ($depth > 7 || !is_array($node)) return;
    $looks = false;
    foreach (array('street','streetAddress','strasse','addressLine1','postalCode','postcode','zip','plz','addressLocality','city','ort','houseNumber','house_number','housenumber') as $k) {
        if (isset($node[$k]) && !is_array($node[$k]) && trim((string)$node[$k]) !== '') { $looks = true; break; }
    }
    if ($looks) {
        $candidate = ruhrpott_guru_event_normalize_address_v161($node);
        if ($candidate) {
            if ($candidate['rel'] === '' && $parent_key !== '') $candidate['rel'] = $parent_key;
            $out[] = $candidate;
        }
    }
    foreach ($node as $key=>$value) {
        if (!is_array($value)) continue;
        $k = strtolower((string)$key);
        // Do not descend deeply into obvious organizer/contact branches unless
        // no alternative exists; their postal addresses caused the old bug.
        if ($depth > 1 && preg_match('/organizer|veranstalter|contact|author|billing|invoice/', $k)) continue;
        ruhrpott_guru_event_address_candidates_v161($value, $out, $depth + 1, $k);
    }
}

function ruhrpott_guru_event_venue_name_from_item($item, $address = null) {
    if (!is_array($item)) return '';
    $values = array(
        $item['venueName'] ?? null, $item['venueTitle'] ?? null, $item['venue'] ?? null,
        $item['locationName'] ?? null, $item['placeName'] ?? null,
    );
    $location = $item['location'] ?? null;
    if (is_array($location)) {
        foreach (array('name','title','venueName','locationName','label') as $key) $values[] = $location[$key] ?? null;
    }
    if (is_array($address)) {
        foreach (array('name','title','venueName','locationName','label','placeName') as $key) $values[] = $address[$key] ?? null;
    }
    $city = strtolower(trim((string) (($address['city'] ?? null) ?: ($item['city'] ?? ''))));
    foreach ($values as $value) {
        if (is_array($value)) {
            foreach (array('name','title','label') as $key) if (!empty($value[$key])) { $value = $value[$key]; break; }
        }
        if (!is_string($value)) continue;
        $value = trim(wp_strip_all_tags($value));
        if ($value === '' || strlen($value) > 180) continue;
        if ($city !== '' && strtolower($value) === $city) continue;
        if (preg_match('/^(veranstalter|organizer|kontakt|contact)\b/i', $value)) continue;
        return $value;
    }
    return '';
}

function ruhrpott_guru_event_db_address_v161($item, $address) {
    global $wpdb;
    if (!is_array($item)) return $address;
    $geo = ruhrpott_guru_event_geo_pair($item);
    if (!$geo) return $address;
    $table = $wpdb->prefix . 'rg_places';
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if ($exists !== $table) return $address;
    $venue = ruhrpott_guru_event_venue_name_from_item($item, $address);
    $lat=(float)$geo[0]; $lng=(float)$geo[1];
    $dlat=0.0040; $dlng=0.0065; // ~450 m bounding box in the Ruhr area.
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id,name,lat,lng,street,house_number,postcode,city FROM $table WHERE active=1 AND lat BETWEEN %f AND %f AND lng BETWEEN %f AND %f AND (street<>'' OR postcode<>'') LIMIT 40",
        $lat-$dlat,$lat+$dlat,$lng-$dlng,$lng+$dlng
    ), ARRAY_A);
    if (!$rows) return $address;
    $best=null; $bestScore=-999;
    $venueNorm = ruhrpott_guru_event_norm_text_v114($venue);
    foreach ($rows as $r) {
        $dist=ruhrpott_guru_event_distance_km($lat,$lng,(float)$r['lat'],(float)$r['lng']);
        if ($dist > 0.45) continue;
        $score = max(0, 80 - $dist*180);
        if ($venueNorm !== '') {
            $rn=ruhrpott_guru_event_norm_text_v114($r['name']);
            similar_text($venueNorm,$rn,$pct);
            $score += ($pct/100)*120;
            // With an explicit venue identity, do not accept a different nearby POI.
            if ($pct < 45 && $dist > 0.08) continue;
        } elseif ($dist > 0.08) continue;
        if ($score>$bestScore) { $best=$r; $bestScore=$score; }
    }
    if (!$best) return $address;
    $out = is_array($address) ? $address : array();
    if (empty($out['street'])) $out['street']=$best['street'];
    if (empty($out['houseNumber'])) $out['houseNumber']=$best['house_number'];
    if (empty($out['zip'])) $out['zip']=$best['postcode'];
    if (empty($out['city'])) $out['city']=$best['city'];
    if (empty($out['name']) && $venue==='') $out['name']=$best['name'];
    $out['enrichedFrom']='ruhrpottguru-places-db';
    $out['enrichedPlaceId']=(int)$best['id'];
    return $out;
}

/**
 * Globally choose the most complete event venue address. ET4/destination.one
 * can expose addresses at several nesting levels. Afterwards our own place DB
 * fills street/postcode gaps only when the event coordinate matches the venue.
 */
function ruhrpott_guru_event_prefer_venue_address($item) {
    if (!is_array($item)) return $item;
    $candidates=array();
    ruhrpott_guru_event_address_candidates_v161($item,$candidates,0,'');
    $best = null; $best_score=-10000;
    foreach ($candidates as $candidate) {
        $score=ruhrpott_guru_event_address_score($candidate);
        if ($score>$best_score) { $best=$candidate; $best_score=$score; }
    }
    if (!$best && is_array($item['address'] ?? null)) $best=ruhrpott_guru_event_normalize_address_v161($item['address']);
    $best=ruhrpott_guru_event_db_address_v161($item,$best);
    if (is_array($best)) $item['address']=$best;
    $venue_name=ruhrpott_guru_event_venue_name_from_item($item,$best);
    if ($venue_name!== '') $item['venueName']=$venue_name;
    return $item;
}

function ruhrpott_guru_event_next_open_occurrence($range_start, $range_end, $weekdays, $open_time, $close_time, $now = null) {
    $tz = new DateTimeZone('Europe/Berlin');
    $now = $now instanceof DateTimeImmutable ? $now->setTimezone($tz) : new DateTimeImmutable('now', $tz);
    $start_day = new DateTimeImmutable($range_start . ' 00:00:00', $tz);
    $end_day = new DateTimeImmutable($range_end . ' 23:59:59', $tz);
    $cursor = $now > $start_day ? new DateTimeImmutable($now->format('Y-m-d') . ' 00:00:00', $tz) : $start_day;
    $allowed = array_map('intval', (array) $weekdays); // PHP N: 1=Mon ... 7=Sun

    for ($i = 0; $i < 370 && $cursor <= $end_day; $i++, $cursor = $cursor->modify('+1 day')) {
        if (!in_array((int) $cursor->format('N'), $allowed, true)) continue;
        $start = new DateTimeImmutable($cursor->format('Y-m-d') . ' ' . $open_time . ':00', $tz);
        $end = new DateTimeImmutable($cursor->format('Y-m-d') . ' ' . $close_time . ':00', $tz);
        if ($end < $now) continue;
        return array('start' => $start->format(DateTimeInterface::ATOM), 'end' => $end->format(DateTimeInterface::ATOM));
    }
    return null;
}

/**
 * Verified Wesel coverage fallback. These records contain factual event data
 * only (title, time, venue, coordinates and source URL). They are deliberately
 * limited by known validity ranges and therefore disappear automatically when
 * expired. This closes the current Open-Data coverage gap without inventing
 * coordinates or using a synthetic city-centre point.
 */
function ruhrpott_guru_verified_wesel_events($lat, $lng, $max_radius_km, $city, $now = null) {
    $wesel_lat = 51.6572;
    $wesel_lng = 6.6186;
    $city_clean = strtolower(ruhrpott_guru_event_city_query($city));
    $is_wesel_city = strpos($city_clean, 'wesel') !== false;
    $centre_distance = ruhrpott_guru_event_distance_km((float) $lat, (float) $lng, $wesel_lat, $wesel_lng);
    if (!$is_wesel_city && $centre_distance > (float) $max_radius_km + 12.0) return array();

    $items = array();
    $lvr_occurrence = ruhrpott_guru_event_next_open_occurrence('2026-07-10', '2026-09-27', array(2,3,4,5,6,7), '11:00', '17:00', $now);
    if ($lvr_occurrence) {
        $items[] = array(
            'global_id' => 'rg_verified_wesel_lvr_baumgaertel_2026',
            'title' => 'Thomas Baumgärtel NiederrheinTour 2026. Freiheit für Kunst',
            'venueName' => 'LVR-Niederrheinmuseum Wesel',
            'description' => 'Sonderausstellung im LVR-Niederrheinmuseum Wesel. Details und aktuelle Hinweise beim Museum.',
            'category' => 'Ausstellung',
            'start' => $lvr_occurrence['start'],
            'end' => $lvr_occurrence['end'],
            'geo' => array('main' => array('latitude' => 51.65105, 'longitude' => 6.61439)),
            'address' => array('rel' => 'venue', 'street' => 'An der Zitadelle', 'houseNumber' => '14-20', 'zip' => '46483', 'city' => 'Wesel'),
            'url' => 'https://niederrheinmuseum-wesel.lvr.de/ausstellungen/thomas-baumgaertel-niederrheintour-2026-freiheit-fuer-kunst/',
            'provider_source' => 'verified-official-wesel',
        );
    }

    $history_occurrence = ruhrpott_guru_event_next_open_occurrence('2026-08-12', '2026-12-31', array(2,3,4,5,6,7), '11:00', '17:00', $now);
    if ($history_occurrence) {
        $items[] = array(
            'global_id' => 'rg_verified_wesel_lvr_geschichte_niederrhein_2026',
            'title' => 'Die Geschichte des Niederrheins',
            'venueName' => 'LVR-Niederrheinmuseum Wesel',
            'description' => 'Ausstellung im LVR-Niederrheinmuseum Wesel. Details und Termine über Tourismus NRW.',
            'category' => 'Ausstellung',
            'start' => $history_occurrence['start'],
            'end' => $history_occurrence['end'],
            'geo' => array('main' => array('latitude' => 51.65105, 'longitude' => 6.61439)),
            'address' => array('rel' => 'venue', 'street' => 'An der Zitadelle', 'houseNumber' => '14-20', 'zip' => '46483', 'city' => 'Wesel'),
            'url' => 'https://www.nrw-tourismus.de/events/die-geschichte-des-niederrheins',
            'provider_source' => 'verified-official-wesel',
        );
    }

    $bislich_occurrence = ruhrpott_guru_event_next_open_occurrence('2026-03-29', '2026-09-27', array(6,7), '14:00', '17:00', $now);
    if ($bislich_occurrence) {
        $items[] = array(
            'global_id' => 'rg_verified_wesel_bislich_spiel_2026',
            'title' => 'SPIEL! Ein Wort – viele Jahrhunderte',
            'venueName' => 'Deichdorfmuseum Bislich',
            'description' => 'Sonderausstellung im Deichdorfmuseum Bislich. Details und Öffnungszeiten beim Museum.',
            'category' => 'Ausstellung',
            'start' => $bislich_occurrence['start'],
            'end' => $bislich_occurrence['end'],
            'geo' => array('main' => array('latitude' => 51.678205, 'longitude' => 6.490309)),
            'address' => array('rel' => 'venue', 'street' => 'Dorfstraße', 'houseNumber' => '24', 'zip' => '46487', 'city' => 'Wesel'),
            'url' => 'https://www.deichdorfmuseum.de/',
            'provider_source' => 'verified-official-wesel',
        );
    }

    // Return only records that can actually enter the requested maximum radius.
    return array_values(array_filter($items, function($item) use ($lat, $lng, $max_radius_km) {
        $geo = $item['geo']['main'];
        return ruhrpott_guru_event_distance_km((float) $lat, (float) $lng, (float) $geo['latitude'], (float) $geo['longitude']) <= (float) $max_radius_km;
    }));
}

function ruhrpott_guru_event_geo_pair($item) {
    if (!is_array($item)) return null;
    $geo = $item['geo']['main'] ?? ($item['geo'] ?? ($item['location']['geo'] ?? null));
    if (!is_array($geo)) return null;
    $lat = isset($geo['latitude']) ? (float) $geo['latitude'] : (isset($geo['lat']) ? (float) $geo['lat'] : NAN);
    $lng = isset($geo['longitude']) ? (float) $geo['longitude'] : (isset($geo['lng']) ? (float) $geo['lng'] : NAN);
    if (!is_finite($lat) || !is_finite($lng)) return null;
    return array($lat, $lng);
}

function ruhrpott_guru_event_count_within($items, $lat, $lng, $radius_km) {
    $count = 0;
    foreach ((array) $items as $item) {
        $pair = ruhrpott_guru_event_geo_pair($item);
        if (!$pair) continue;
        if (ruhrpott_guru_event_distance_km((float) $lat, (float) $lng, $pair[0], $pair[1]) <= (float) $radius_km) $count++;
    }
    return $count;
}

/**
 * V232 – exact ET4 media hydration for live occurrence rows.
 *
 * ET4 can return the same event twice in different representations:
 * - unique=false + unrollIntervals=true: concrete occurrence, often sparse
 * - unique=true: rich event object, including event-owned media_objects
 *
 * The association is ONLY Item.global_id within the SAME licensed
 * ruhr-tourismus-open-data response set. No title, venue, city, other feed or
 * external resolver participates. The occurrence keeps its concrete start/end;
 * only the media branch from the exact same ET4 event object is copied.
 */
function ruhrpott_guru_et4_media_branch_v232($item) {
    if (!is_array($item)) return array();
    foreach (array('media_objects','mediaObjects','media_object','mediaObject') as $key) {
        if (array_key_exists($key, $item) && !empty($item[$key])) return array($key => $item[$key]);
    }
    return array();
}

function ruhrpott_guru_et4_hydrate_occurrence_media_v232($occurrences, $rich_items) {
    $media_by_global_id = array();
    foreach ((array)$rich_items as $rich) {
        if (!is_array($rich)) continue;
        $gid = trim((string)($rich['global_id'] ?? ($rich['globalId'] ?? '')));
        if ($gid === '') continue;
        $branch = ruhrpott_guru_et4_media_branch_v232($rich);
        if ($branch && !isset($media_by_global_id[$gid])) $media_by_global_id[$gid] = $branch;
    }
    $out = array();
    foreach ((array)$occurrences as $occ) {
        if (!is_array($occ)) { $out[] = $occ; continue; }
        $gid = trim((string)($occ['global_id'] ?? ($occ['globalId'] ?? '')));
        if ($gid !== '' && isset($media_by_global_id[$gid]) && !ruhrpott_guru_et4_media_branch_v232($occ)) {
            foreach ($media_by_global_id[$gid] as $key => $value) $occ[$key] = $value;
            $occ['_rpg_et4_media_hydrated_v232'] = true;
        }
        $out[] = $occ;
    }
    return $out;
}

function ruhrpott_guru_event_raw_identity($item, $index) {
    if (isset($item['global_id']) && $item['global_id'] !== '') return 'g:' . (string) $item['global_id'];
    if (isset($item['id']) && $item['id'] !== '') return 'i:' . (string) $item['id'];
    $title = isset($item['title']) ? $item['title'] : (isset($item['name']) ? $item['name'] : '');
    return 'f:' . md5(wp_json_encode(array($title, $item['geo'] ?? null, $item['timeIntervals'] ?? null, $index)));
}

function ruhrpott_guru_event_metropole_city_keys_v173() {
    static $keys=null;if($keys!==null)return $keys;
    $cities=array('Bochum','Bottrop','Dortmund','Duisburg','Essen','Gelsenkirchen','Hagen','Hamm','Herne','Mülheim an der Ruhr','Oberhausen','Breckerfeld','Ennepetal','Gevelsberg','Hattingen','Herdecke','Schwelm','Sprockhövel','Wetter (Ruhr)','Witten','Castrop-Rauxel','Datteln','Dorsten','Gladbeck','Haltern am See','Herten','Marl','Oer-Erkenschwick','Recklinghausen','Waltrop','Bergkamen','Bönen','Fröndenberg/Ruhr','Holzwickede','Kamen','Lünen','Schwerte','Selm','Unna','Werne','Alpen','Dinslaken','Hamminkeln','Hünxe','Kamp-Lintfort','Moers','Neukirchen-Vluyn','Rheinberg','Schermbeck','Sonsbeck','Voerde (Niederrhein)','Wesel','Xanten');
    $norm=function($v){$v=remove_accents(mb_strtolower(trim((string)$v),'UTF-8'));$v=preg_replace('/[^a-z0-9]+/','-',$v);return trim((string)$v,'-');};$keys=array();foreach($cities as $c)$keys[$norm($c)]=1;
    foreach(array('voerde'=>'Voerde (Niederrhein)','mulheim'=>'Mülheim an der Ruhr','frondenberg'=>'Fröndenberg/Ruhr','wetter'=>'Wetter (Ruhr)') as $alias=>$target)$keys[$norm($alias)]=1;
    return $keys;
}

function ruhrpott_guru_event_city_in_metropole_v173($item) {
    if(!is_array($item))return false;$a=is_array($item['address']??null)?$item['address']:array();$city=trim((string)($a['city']??($a['addressLocality']??($a['locality']??($a['ort']??($item['city']??''))))));if($city==='')return false;$key=remove_accents(mb_strtolower($city,'UTF-8'));$key=preg_replace('/[^a-z0-9]+/','-',$key);$key=trim((string)$key,'-');return isset(ruhrpott_guru_event_metropole_city_keys_v173()[$key]);
}

function ruhrpott_guru_event_rest(WP_REST_Request $request) {
    $params = $request->get_json_params();
    if (!is_array($params)) $params = array();

    $lat = isset($params['lat']) ? (float) $params['lat'] : NAN;
    $lng = isset($params['lng']) ? (float) $params['lng'] : NAN;
    $city = isset($params['city']) ? sanitize_text_field((string) $params['city']) : '';
    $max_radius_km = isset($params['maxRadiusKm']) ? (float) $params['maxRadiusKm'] : 50.0;
    $max_radius_km = min(50.0, max(1.0, $max_radius_km));

    if (!is_finite($lat) || !is_finite($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        return new WP_REST_Response(array('ok' => false, 'message' => 'Ungültiger Suchstandort'), 400);
    }

    // V163: once the persistent Event DB has completed at least one fresh
    // 53-city nightly wave, it is the visitor-facing source of truth. The
    // existing Open-Data-Finder transport remains the automatic bootstrap/
    // outage fallback, so installing the build cannot make the site empty.
    if (function_exists('ruhrpottguru_events_db_frontend_query')) {
        try {
            $db_payload = ruhrpottguru_events_db_frontend_query($lat, $lng, $max_radius_km, $city);
            if (is_array($db_payload) && !empty($db_payload['ready'])) {
                $db_payload['cache'] = 'DB';
                if (!isset($db_payload['meta']) || !is_array($db_payload['meta'])) $db_payload['meta'] = array();
                $db_payload['meta']['version'] = 'v163-event-db-first';
                $db_payload['meta']['searchCenter'] = array('lat'=>$lat,'lng'=>$lng,'city'=>$city);
                return new WP_REST_Response($db_payload, 200);
            }
        } catch (Throwable $e) {
            // Fail open to the proven licensed live transport below.
        }
    }

    $city_query = ruhrpott_guru_event_city_query($city);
    $cache_key = 'rg_events_v173_trusted_sources_' . md5(sprintf('%.4f|%.4f|%.1f|%s', $lat, $lng, $max_radius_km, strtolower($city)));
    $cached = get_transient($cache_key);
    if (is_array($cached) && isset($cached['items'])) {
        $cached['cache'] = 'HIT';
        return new WP_REST_Response($cached, 200);
    }

    $empty = array('ok' => false, 'items' => array(), 'pages' => array(), 'partial' => false, 'error' => 'not-run');

    // Keep the licensed Ruhr pool as the primary source to preserve all
    // existing coverage and only invoke the broad public pool when the active
    // centre has too little genuinely local data.
    $ruhr_geo = ruhrpott_guru_et4_fetch_pages(array(
        'unique' => 'true',
        'latitude' => $lat,
        'longitude' => $lng,
        'distance' => (int) round($max_radius_km * 1000),
        'sort' => 'distance asc',
    ), 20, 'ruhr-tourismus-open-data', true);
    $ruhr_city_catalog = $empty;
    $ruhr_city_occurrences = $empty;

    if ($city_query !== '') {
        $tz = new DateTimeZone('Europe/Berlin');
        $today = new DateTimeImmutable('today', $tz);
        $until = $today->modify('+365 days');
        $ruhr_city_catalog = ruhrpott_guru_et4_fetch_pages(array(
            'unique' => 'true', 'q' => $city_query, 'sort' => 'title asc'
        ), 10, 'ruhr-tourismus-open-data', true);
        $ruhr_city_occurrences = ruhrpott_guru_et4_fetch_pages(array(
            'unique' => 'false', 'q' => $city_query,
            'startdate' => $today->format('d.m.Y'), 'enddate' => $until->format('d.m.Y'),
            'unrollIntervals' => 'true', 'sort' => 'start asc'
        ), 10, 'ruhr-tourismus-open-data', true);
    }

    // V232: hydrate sparse occurrence rows with media from the exact same ET4
    // global_id in the rich licensed catalog/geo result BEFORE any listing
    // dedupe happens. This keeps occurrence timing while preserving ET4 artwork.
    $ruhr_city_occurrences['items'] = ruhrpott_guru_et4_hydrate_occurrence_media_v232(
        $ruhr_city_occurrences['items'],
        array_merge($ruhr_city_catalog['items'], $ruhr_geo['items'])
    );

    $ruhr_local_probe = array_merge($ruhr_city_occurrences['items'], $ruhr_city_catalog['items'], $ruhr_geo['items']);
    $local_5km_count = ruhrpott_guru_event_count_within($ruhr_local_probe, $lat, $lng, 5.0);

    $public_geo = $empty;
    $public_city_occurrences = $empty;
    if ($local_5km_count < 3) {
        // Public destination.one pool: documented public experience, no
        // licence key. This fills destination gaps beyond the Ruhr-only pool.
        $public_geo = ruhrpott_guru_et4_fetch_pages(array(
            'unique' => 'true',
            'latitude' => $lat,
            'longitude' => $lng,
            'distance' => (int) round($max_radius_km * 1000),
            'sort' => 'distance asc',
        ), 20, 'open-data', false);
        if ($city_query !== '') {
            $tz = new DateTimeZone('Europe/Berlin');
            $today = new DateTimeImmutable('today', $tz);
            $until = $today->modify('+365 days');
            $public_city_occurrences = ruhrpott_guru_et4_fetch_pages(array(
                'unique' => 'false', 'q' => $city_query,
                'startdate' => $today->format('d.m.Y'), 'enddate' => $until->format('d.m.Y'),
                'unrollIntervals' => 'true', 'sort' => 'start asc'
            ), 10, 'open-data', false);
        }
    }

    // V114: no hand-maintained event scraping/safety-net records. Additional
    // sources must pass the Trusted Event Sources legal gate.
    $essen_items = ruhrpott_guru_essen_snapshot_v114();

    $combined = array_merge(
        ruhrpott_guru_event_tag_list_v114($ruhr_city_occurrences['items'], 'open_data_finder'),
        ruhrpott_guru_event_tag_list_v114($ruhr_city_catalog['items'], 'open_data_finder'),
        ruhrpott_guru_event_tag_list_v114($ruhr_geo['items'], 'open_data_finder'),
        ruhrpott_guru_event_tag_list_v114($public_city_occurrences['items'], 'destination_one_open_data'),
        ruhrpott_guru_event_tag_list_v114($public_geo['items'], 'destination_one_open_data'),
        $essen_items
    );

    $seen = array();
    $semantic_seen = array();
    $final = array();
    foreach ($combined as $index => $item) {
        if (!is_array($item)) continue;
        $item = ruhrpott_guru_event_prefer_venue_address($item);
        // V173: broad public destination.one is supplemental only. Never return
        // a public-pool event whose actual venue city lies outside the canonical
        // 53 municipalities of the Metropole Ruhr.
        if (($item['provider_source'] ?? '') === 'destination_one_open_data' && !ruhrpott_guru_event_city_in_metropole_v173($item)) continue;
        $base = ruhrpott_guru_event_raw_identity($item, $index);
        $start_value = isset($item['start']) ? (string) $item['start'] : '';
        $end_value = isset($item['end']) ? (string) $item['end'] : '';
        $time_sig = md5(wp_json_encode($item['timeIntervals'] ?? array()));
        $key = $base . '|' . $start_value . '|' . $end_value . '|' . $time_sig;
        if (isset($seen[$key])) continue;

        // V114 fuzzy identity: title + occurrence day + venue + geospatial
        // proximity. This catches e.g. "Grugahalle" vs "Grugahalle Essen"
        // without collapsing distinct performances.
        $duplicate_index = null;
        foreach ($final as $fi=>$existing) {
            if (ruhrpott_guru_event_similarity_v114($existing, $item) >= 0.86) {
                $duplicate_index = $fi;
                break;
            }
        }
        if ($duplicate_index !== null) {
            $final[$duplicate_index] = ruhrpott_guru_event_merge_source_meta_v114($final[$duplicate_index], $item);
            $seen[$key] = true;
            continue;
        }

        $seen[$key] = true;
        $final[] = $item;
    }

    $any_success = $ruhr_geo['ok'] || $ruhr_city_catalog['ok'] || $ruhr_city_occurrences['ok']
        || $public_geo['ok'] || $public_city_occurrences['ok'] || count($essen_items) > 0;
    if (!$any_success) {
        return new WP_REST_Response(array(
            'ok' => false,
            'message' => 'Event-Daten momentan nicht erreichbar',
            'meta' => array(
                'ruhrGeoError' => $ruhr_geo['error'],
                'ruhrCityError' => $ruhr_city_catalog['error'],
                'publicGeoError' => $public_geo['error'],
            ),
        ), 502);
    }

    $source_meta = function($result) {
        return array(
            'count' => count($result['items']),
            'pages' => $result['pages'],
            'partial' => !empty($result['partial']),
            'error' => $result['error'] ?? null,
        );
    };

    $payload = array(
        'ok' => true,
        'items' => $final,
        'cache' => 'MISS',
        'meta' => array(
            'version' => 'v173-region-safe-event-sources',
            'searchCenter' => array('lat' => $lat, 'lng' => $lng, 'city' => $city),
            'maxRadiusKm' => $max_radius_km,
            'local5kmBeforeBroadFallback' => $local_5km_count,
            'sources' => array(
                'ruhrGeo' => $source_meta($ruhr_geo),
                'ruhrCity' => $source_meta($ruhr_city_catalog),
                'ruhrOccurrences' => $source_meta($ruhr_city_occurrences),
                'publicOpenDataGeo' => $source_meta($public_geo),
                'publicOpenDataOccurrences' => $source_meta($public_city_occurrences),
                'essenEmgExport' => array(
                    'count' => count($essen_items),
                    'status' => get_option('rpg_events_essen_v114_status', array()),
                    'license' => 'Official re-use grant; attribution required',
                ),
            ),
            'combinedCount' => count($final),
        ),
    );

    set_transient($cache_key, $payload, 10 * MINUTE_IN_SECONDS);
    return new WP_REST_Response($payload, 200);
}


/**
 * Event Original Media Resolver V85
 *
 * Resolves the original image from an event's own/official detail page on demand.
 * This is intentionally event-specific: unlike POI imagery, original event artwork
 * is allowed even when it contains people. Identity still comes from the exact
 * event page/title; generic image search is never used.
 */
function ruhrpott_guru_register_event_media_resolver_v85() {
    register_rest_route('ruhrpottguru/v1', '/event-media', array(
        'methods' => 'POST',
        'callback' => 'ruhrpott_guru_event_media_resolver_v85',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'ruhrpott_guru_register_event_media_resolver_v85');

function ruhrpott_guru_event_media_norm_v85($text) {
    $text = remove_accents(strtolower(wp_strip_all_tags((string) $text)));
    $text = preg_replace('/[^a-z0-9 ]+/', ' ', $text);
    return trim(preg_replace('/\s+/', ' ', $text));
}

function ruhrpott_guru_event_media_tokens_v85($title) {
    $stop = array('der','die','das','den','dem','des','ein','eine','einer','eines','und','oder','fur','für','von','mit','im','in','am','an','zu','zum','zur','2026','2025','event','veranstaltung');
    $out = array();
    foreach (explode(' ', ruhrpott_guru_event_media_norm_v85($title)) as $token) {
        if (strlen($token) >= 4 && !in_array($token, $stop, true)) $out[] = $token;
    }
    return array_values(array_unique($out));
}

function ruhrpott_guru_event_media_bad_url_v85($url) {
    $u = strtolower((string) $url);
    return preg_match('/(?:^|[\/_\-.])(logo|icon|favicon|sprite|avatar|placeholder|default|dummy|blank|no[-_ ]?image)(?:[\/_\-.]|$)/i', $u) === 1;
}

function ruhrpott_guru_event_media_abs_v85($base, $raw) {
    if (function_exists('ruhrpott_guru_food_media_abs_url')) return ruhrpott_guru_food_media_abs_url($base, $raw);
    $raw = trim(html_entity_decode((string) $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($raw === '') return '';
    if (preg_match('#^https?://#i', $raw)) return esc_url_raw($raw);
    $bp = wp_parse_url($base);
    if (!$bp || empty($bp['host'])) return '';
    $scheme = !empty($bp['scheme']) ? $bp['scheme'] : 'https';
    if (strpos($raw, '//') === 0) return esc_url_raw($scheme . ':' . $raw);
    if (strpos($raw, '/') === 0) return esc_url_raw($scheme . '://' . $bp['host'] . $raw);
    $path = isset($bp['path']) ? $bp['path'] : '/';
    $dir = preg_replace('#/[^/]*$#', '/', $path);
    return esc_url_raw($scheme . '://' . $bp['host'] . $dir . $raw);
}

function ruhrpott_guru_event_media_meta_v85($html, $name) {
    if (function_exists('ruhrpott_guru_food_media_extract_meta')) return ruhrpott_guru_food_media_extract_meta($html, $name);
    $q = preg_quote($name, '/');
    $patterns = array(
        '/<meta[^>]+(?:property|name)=["\']' . $q . '["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i',
        '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:property|name)=["\']' . $q . '["\'][^>]*>/i',
    );
    foreach ($patterns as $pattern) if (preg_match($pattern, $html, $m)) return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return '';
}

function ruhrpott_guru_event_media_identity_v85($html, $title, $city = '', $start = '') {
    // V162: event identity must be proven by event-specific page metadata/headings,
    // never merely because title words occur somewhere in an archive, tourism page
    // or another event on the same page.
    $title_norm = ruhrpott_guru_event_media_norm_v85($title);
    $tokens = ruhrpott_guru_event_media_tokens_v85($title);
    // A city appended to an event title is context, not event identity. Remove
    // city tokens here and verify the city separately as an independent anchor.
    $city_tokens = ruhrpott_guru_event_media_tokens_v85($city);
    if ($city_tokens) $tokens = array_values(array_filter($tokens, function($t) use ($city_tokens) { return !in_array($t, $city_tokens, true); }));
    if ($title_norm === '' || !$tokens) return false;

    $signals = array();
    foreach (array('og:title','twitter:title') as $meta) {
        $v = ruhrpott_guru_event_media_meta_v85($html, $meta);
        if ($v) $signals[] = $v;
    }
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) $signals[] = wp_strip_all_tags($m[1]);
    if (preg_match_all('/<h1\b[^>]*>(.*?)<\/h1>/is', $html, $m)) {
        foreach (array_slice($m[1], 0, 4) as $v) $signals[] = wp_strip_all_tags($v);
    }
    if (preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $json_blocks)) {
        foreach ($json_blocks[1] as $block) {
            $data = json_decode(html_entity_decode(trim($block), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
            if (!is_array($data)) continue;
            $stack = array($data);
            while ($stack) {
                $node = array_pop($stack);
                if (!is_array($node)) continue;
                $type = strtolower((string)($node['@type'] ?? ''));
                if (($type === 'event' || in_array('Event', (array)($node['@type'] ?? array()), true)) && !empty($node['name'])) $signals[] = (string)$node['name'];
                foreach ($node as $value) if (is_array($value)) $stack[] = $value;
            }
        }
    }

    $best_ratio = 0.0;
    $best_hits = 0;
    foreach ($signals as $signal) {
        $norm = ruhrpott_guru_event_media_norm_v85($signal);
        if ($norm === '') continue;
        if ($norm === $title_norm || strpos($norm, $title_norm) !== false || strpos($title_norm, $norm) !== false) return true;
        $hits = 0;
        foreach ($tokens as $token) if (preg_match('/(?:^| )'.preg_quote($token,'/').'(?: |$)/', $norm)) $hits++;
        $ratio = $hits / max(1, count($tokens));
        if ($ratio > $best_ratio) { $best_ratio = $ratio; $best_hits = $hits; }
    }

    // Short titles must match completely; longer titles need a strong majority
    // of their distinctive tokens in an actual title/H1/Event-name signal.
    $need_ratio = count($tokens) <= 2 ? 1.0 : 0.70;
    $need_hits = count($tokens) <= 2 ? count($tokens) : max(2, (int)ceil(count($tokens) * $need_ratio));
    if ($best_hits < $need_hits || $best_ratio < $need_ratio) return false;

    // For non-exact matches, require one independent contextual anchor when supplied.
    $context = ruhrpott_guru_event_media_norm_v85(implode(' ', $signals) . ' ' . substr(wp_strip_all_tags($html), 0, 120000));
    $anchors = 0;
    $city_norm = ruhrpott_guru_event_media_norm_v85($city);
    if ($city_norm !== '' && strpos($context, $city_norm) !== false) $anchors++;
    if ($start) {
        $ts = strtotime((string)$start);
        if ($ts) {
            $date_bits = array(date('d.m.Y',$ts), date('j.n.Y',$ts), date('Y-m-d',$ts));
            foreach ($date_bits as $d) if (strpos((string)$html, $d) !== false) { $anchors++; break; }
        }
    }
    return $anchors >= 1 || ($city_norm === '' && !$start);
}

function ruhrpott_guru_event_media_collect_v85($html, $page_url, $title) {
    $candidates = array();
    $add = function($raw, $kind, $context = '') use (&$candidates, $page_url, $title) {
        $url = ruhrpott_guru_event_media_abs_v85($page_url, $raw);
        if (!$url || !wp_http_validate_url($url) || ruhrpott_guru_event_media_bad_url_v85($url)) return;
        $score = 0;
        if ($kind === 'og:image') $score = 120;
        elseif ($kind === 'twitter:image') $score = 112;
        elseif ($kind === 'jsonld') $score = 108;
        elseif ($kind === 'image_src') $score = 96;
        else $score = 55;
        $ctx = ruhrpott_guru_event_media_norm_v85($context . ' ' . $url);
        foreach (ruhrpott_guru_event_media_tokens_v85($title) as $token) if (strpos($ctx, $token) !== false) $score += 14;
        if (preg_match('/hero|header|stage|event|veranstaltung|ausstellung|exhibition|kunst|artwork|poster|motiv|cover/i', $ctx)) $score += 18;
        if (preg_match('/thumb|thumbnail|small|logo|icon|avatar|sprite/i', $ctx)) $score -= 30;
        if (!isset($candidates[$url]) || $score > $candidates[$url]['score']) $candidates[$url] = array('url'=>$url,'score'=>$score,'kind'=>$kind);
    };

    foreach (array('og:image','og:image:secure_url','twitter:image','twitter:image:src') as $meta) {
        $value = ruhrpott_guru_event_media_meta_v85($html, $meta);
        if ($value) $add($value, strpos($meta, 'twitter') === 0 ? 'twitter:image' : 'og:image', $meta);
    }
    if (preg_match_all('/<link[^>]+rel=["\'][^"\']*image_src[^"\']*["\'][^>]+href=["\']([^"\']+)["\'][^>]*>/i', $html, $m)) {
        foreach ($m[1] as $value) $add($value, 'image_src', 'image_src');
    }
    if (preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $json_blocks)) {
        foreach ($json_blocks[1] as $block) {
            $data = json_decode(html_entity_decode(trim($block), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
            if (!is_array($data)) continue;
            $stack = array($data);
            while ($stack) {
                $node = array_pop($stack);
                if (!is_array($node)) continue;
                if (isset($node['image'])) {
                    $images = is_array($node['image']) ? $node['image'] : array($node['image']);
                    foreach ($images as $img) {
                        if (is_string($img)) $add($img, 'jsonld', 'jsonld image');
                        elseif (is_array($img)) {
                            foreach (array('url','contentUrl','thumbnailUrl') as $key) if (!empty($img[$key]) && is_string($img[$key])) $add($img[$key], 'jsonld', 'jsonld image');
                        }
                    }
                }
                foreach ($node as $value) if (is_array($value)) $stack[] = $value;
            }
        }
    }
    if (preg_match_all('/<img\b[^>]*>/i', $html, $tags)) {
        foreach (array_slice($tags[0], 0, 120) as $tag) {
            $src = '';
            foreach (array('data-src','data-lazy-src','src') as $attr) {
                if (preg_match('/\b' . preg_quote($attr, '/') . '=["\']([^"\']+)["\']/i', $tag, $m)) { $src = $m[1]; break; }
            }
            if (!$src) continue;
            $context = $tag;
            $ctx_norm = ruhrpott_guru_event_media_norm_v85($context);
            $ctx_hits = 0;
            foreach (ruhrpott_guru_event_media_tokens_v85($title) as $tok) if (strpos($ctx_norm, $tok) !== false) $ctx_hits++;
            if ($ctx_hits >= min(2, max(1, count(ruhrpott_guru_event_media_tokens_v85($title))))) $add($src, 'img', $context);
        }
    }
    uasort($candidates, function($a, $b){ return $b['score'] <=> $a['score']; });
    return array_values($candidates);
}


function ruhrpott_guru_event_media_page_candidates_v161($raw, $explicit = '') {
    $out=array();
    $add=function($v) use (&$out) {
        if (is_array($v)) {
            foreach (array('url','href','@id') as $k) if (!empty($v[$k]) && is_string($v[$k])) { $v=$v[$k]; break; }
        }
        if (!is_string($v)) return;
        $v=esc_url_raw(trim($v));
        if ($v==='' || !wp_http_validate_url($v)) return;
        if (preg_match('/\.(?:jpe?g|png|gif|webp|avif|svg)(?:[?#].*)?$/i',$v)) return;
        $host=strtolower((string)wp_parse_url($v,PHP_URL_HOST));
        if ($host==='') return;
        // Source-platform/directory pages are not treated as an official event
        // website fallback. Native ET4/destination.one media is handled earlier.
        $bare=preg_replace('/^www\./','',$host);
        if(preg_match('/(?:^|\.)destination\.one$|(?:^|\.)et4\.de$/',$bare))return;
        $out[$v]=true;
    };
    $add($explicit);
    $walk=function($node,$depth=0,$parent='') use (&$walk,$add) {
        if ($depth>6 || !is_array($node)) return;
        foreach ($node as $k=>$v) {
            $key=strtolower((string)$k);
            if (is_array($v)) { $walk($v,$depth+1,$key); continue; }
            if (!is_string($v)) continue;
            if (in_array($key,array('url','website','web','detailurl','detail_url','homepage','sameas','sourceurl','source_url','link','eventurl','event_url'),true)) $add($v);
        }
    };
    if (is_array($raw)) $walk($raw);
    return array_slice(array_keys($out),0,10);
}

function ruhrpott_guru_event_media_open_license_v233($license) {
    $license = strtoupper(trim(wp_strip_all_tags((string)$license)));
    if ($license === '') return false;
    if (preg_match('/(?:NC|NON[- ]?COMMERCIAL|ND|NO[- ]?DERIV)/i', $license)) return false;
    return preg_match('/(?:CC0|PUBLIC DOMAIN|PDM|CC\s*BY(?:\s*-?\s*SA)?(?:\s*[0-9.]*)?)/i', $license) === 1;
}

function ruhrpott_guru_event_media_commons_metadata_v233($source_url) {
    $source_url = esc_url_raw((string)$source_url);
    if (!$source_url || stripos($source_url, 'commons.wikimedia.org') === false) return null;
    $file = '';
    if (preg_match('~/wiki/(?:File:|Special:FilePath/)([^?#]+)~i', $source_url, $m)) $file = rawurldecode(str_replace('_',' ', $m[1]));
    if ($file === '') return null;
    $api = add_query_arg(array(
        'action'=>'query','titles'=>'File:'.$file,'prop'=>'imageinfo','iiprop'=>'url|user|extmetadata','iiurlwidth'=>1400,'format'=>'json'
    ), 'https://commons.wikimedia.org/w/api.php');
    $resp = wp_safe_remote_get($api, array('timeout'=>7,'redirection'=>2,'limit_response_size'=>450000,'headers'=>array('Accept'=>'application/json')));
    if (is_wp_error($resp) || (int)wp_remote_retrieve_response_code($resp)!==200) return null;
    $data=json_decode((string)wp_remote_retrieve_body($resp),true);
    $pages=is_array($data['query']['pages']??null)?$data['query']['pages']:array();
    foreach($pages as $page){
        $info=$page['imageinfo'][0]??null;if(!is_array($info))continue;
        $meta=is_array($info['extmetadata']??null)?$info['extmetadata']:array();
        $license=(string)($meta['LicenseShortName']['value']??($meta['UsageTerms']['value']??''));
        if(!ruhrpott_guru_event_media_open_license_v233($license))continue;
        $artist=wp_strip_all_tags((string)($meta['Artist']['value']??($info['user']??'')));
        $img=esc_url_raw((string)($info['thumburl']??($info['url']??'')));
        if(!$img||!wp_http_validate_url($img))continue;
        return array('imageUrl'=>$img,'license'=>$license,'artist'=>$artist,'sourceUrl'=>$source_url);
    }
    return null;
}

/**
 * Venue fallback is deliberately narrower than the normal place-image resolver:
 * only Wikimedia/Commons identity-linked media with an explicit reusable licence
 * may be inherited by an Event, and it is always labelled as venue context.
 */
function ruhrpott_guru_event_media_from_place_db_v233($title,$venue,$city,$coords) {
    global $wpdb;
    if (!is_array($coords) || !isset($coords['lat'],$coords['lng']) || !is_numeric($coords['lat']) || !is_numeric($coords['lng'])) return null;
    $places=$wpdb->prefix.'rg_places'; $media=$wpdb->prefix.'rg_place_media';
    if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$places))!==$places || $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$media))!==$media) return null;
    $lat=(float)$coords['lat'];$lng=(float)$coords['lng'];$vn=ruhrpott_guru_event_media_norm_v85($venue);
    $rows=$wpdb->get_results($wpdb->prepare(
        "SELECT p.id,p.name,p.lat,p.lng,p.city,m.url,m.credit,m.artist,m.license,m.source,m.source_url,m.identity_confidence,m.visual_quality,m.is_primary
         FROM $places p JOIN $media m ON m.place_id=p.id
         WHERE p.active=1 AND m.media_type='image' AND m.moderation_status='active'
           AND p.lat BETWEEN %f AND %f AND p.lng BETWEEN %f AND %f
         ORDER BY m.is_primary DESC,m.identity_confidence DESC,m.visual_quality DESC,m.id DESC LIMIT 80",
        $lat-0.0035,$lat+0.0035,$lng-0.0055,$lng+0.0055
    ),ARRAY_A);
    $best=null;$bestScore=-999;
    foreach((array)$rows as $r){
        $sourceHay=strtolower((string)($r['source']??'').' '.(string)($r['source_url']??''));
        if(strpos($sourceHay,'wikimedia')===false && strpos($sourceHay,'commons')===false && strpos($sourceHay,'wikidata')===false)continue;
        $license=trim((string)($r['license']??''));$img=esc_url_raw((string)($r['url']??''));$artist=(string)($r['artist']??'');$sourceUrl=esc_url_raw((string)($r['source_url']??''));
        if(!ruhrpott_guru_event_media_open_license_v233($license) && $sourceUrl){
            $meta=ruhrpott_guru_event_media_commons_metadata_v233($sourceUrl);
            if($meta){$license=$meta['license'];$artist=$meta['artist'];$img=$meta['imageUrl'];$sourceUrl=$meta['sourceUrl'];}
        }
        if(!ruhrpott_guru_event_media_open_license_v233($license) || !$img || !wp_http_validate_url($img))continue;
        $dist=ruhrpott_guru_event_distance_km($lat,$lng,(float)$r['lat'],(float)$r['lng']);if($dist>0.35)continue;
        $score=max(0,80-$dist*200)+(int)$r['is_primary']*25+(float)$r['identity_confidence']*30+(float)$r['visual_quality']*15;
        if($vn!==''){similar_text($vn,ruhrpott_guru_event_media_norm_v85($r['name']),$pct);$score+=($pct/100)*140;if($pct<55&&$dist>0.06)continue;}elseif($dist>0.06)continue;
        if($score>$bestScore){$bestScore=$score;$best=array_merge($r,array('_image'=>$img,'_license'=>$license,'_artist'=>$artist,'_sourceUrl'=>$sourceUrl));}
    }
    if(!$best)return null;
    $credit=trim((string)($best['credit']??''));if($credit==='')$credit='Orts-Kontextbild · Wikimedia Commons'.($best['_artist']!==''?' · Foto: '.$best['_artist']:'');
    return array(
        'ok'=>true,'imageUrl'=>$best['_image'],'source'=>'venue-commons-context','sourceUrl'=>$best['_sourceUrl']?:$best['_image'],
        'credit'=>$credit,'artist'=>$best['_artist'],'license'=>$best['_license'],'originStatus'=>'venue-context','identityConfidence'=>0.82,
        'matchMethod'=>'venue-coordinate-open-commons','mediaLabel'=>'Event-Ort','contextual'=>true
    );
}


/**
 * V235 – Event-Ort bridge into the established place identity/media stack.
 *
 * The previous v233 context fallback only inspected already-persisted rg_place_media
 * rows that already carried an explicit open licence. That was too narrow: many
 * correctly identified RuhrpottGuru places have OSM/Wikidata/Wikipedia identity
 * metadata but no pre-materialized licensed place_media row. V235 first resolves
 * the exact venue place, then reuses those identity-bound signals to obtain an
 * explicitly reusable Commons image. It never uses an official-site image here.
 */
function ruhrpott_guru_event_media_commons_file_from_url_v235($url) {
    $url=html_entity_decode(trim((string)$url),ENT_QUOTES|ENT_HTML5,'UTF-8');
    if($url==='')return '';
    if(preg_match('~/wiki/(?:File:|Special:FilePath/)([^?#]+)~i',$url,$m))return rawurldecode(str_replace('_',' ',$m[1]));
    if(preg_match('~/thumb/[0-9a-f]/[0-9a-f]{2}/([^/]+)/~i',$url,$m))return rawurldecode($m[1]);
    if(preg_match('~upload\.wikimedia\.org/.*/[0-9a-f]/[0-9a-f]{2}/([^/?#]+)(?:[?#].*)?$~i',$url,$m))return rawurldecode($m[1]);
    return '';
}

function ruhrpott_guru_event_media_commons_metadata_v235($source_url,$image_url='') {
    $file=ruhrpott_guru_event_media_commons_file_from_url_v235($source_url);
    if($file==='')$file=ruhrpott_guru_event_media_commons_file_from_url_v235($image_url);
    if($file==='')return null;
    $api=add_query_arg(array('action'=>'query','titles'=>'File:'.$file,'prop'=>'imageinfo','iiprop'=>'url|user|size|extmetadata','iiurlwidth'=>1400,'format'=>'json'),'https://commons.wikimedia.org/w/api.php');
    $resp=wp_safe_remote_get($api,array('timeout'=>7,'redirection'=>2,'limit_response_size'=>500000,'headers'=>array('Accept'=>'application/json','User-Agent'=>'RuhrpottGuru/1.0; https://www.ruhrpottguru.de/')));
    if(is_wp_error($resp)||(int)wp_remote_retrieve_response_code($resp)!==200)return null;
    $data=json_decode((string)wp_remote_retrieve_body($resp),true);$pages=is_array($data['query']['pages']??null)?$data['query']['pages']:array();
    foreach($pages as $page){
        $info=$page['imageinfo'][0]??null;if(!is_array($info))continue;$meta=is_array($info['extmetadata']??null)?$info['extmetadata']:array();
        $license=(string)($meta['LicenseShortName']['value']??($meta['UsageTerms']['value']??''));if(!ruhrpott_guru_event_media_open_license_v233($license))continue;
        $artist=trim(wp_strip_all_tags((string)($meta['Artist']['value']??($info['user']??''))));
        $img=esc_url_raw((string)($info['thumburl']??($info['url']??'')));if(!$img||!wp_http_validate_url($img))continue;
        $title=(string)($page['title']??('File:'.$file));$file_page='https://commons.wikimedia.org/wiki/'.rawurlencode(str_replace(' ','_',$title));
        $desc=trim(wp_strip_all_tags((string)($meta['ImageDescription']['value']??'')));
        $cats=trim(wp_strip_all_tags((string)($meta['Categories']['value']??'')));
        $obj=trim(wp_strip_all_tags((string)($meta['ObjectName']['value']??'')));
        return array(
            'imageUrl'=>$img,'license'=>$license,'artist'=>$artist,'sourceUrl'=>$file_page,
            'title'=>$title,'description'=>$desc,'categories'=>$cats,'objectName'=>$obj,
            'width'=>(int)($info['thumbwidth']??($info['width']??0)),
            'height'=>(int)($info['thumbheight']??($info['height']??0))
        );
    }
    return null;
}

function ruhrpott_guru_event_media_commons_quality_v287($meta,$candidate=array()) {
    if(!is_array($meta))return array('ok'=>false,'reason'=>'metadata-missing');
    $w=(int)($meta['width']??0);$h=(int)($meta['height']??0);
    if($w>0&&$h>0){
        $ratio=$w/max(1,$h);
        if($w<480||$h<270)return array('ok'=>false,'reason'=>'too-small');
        if($ratio<0.62||$ratio>2.75)return array('ok'=>false,'reason'=>'extreme-aspect');
    }
    $joined=implode(' ',array(
        (string)($meta['title']??''),(string)($meta['objectName']??''),(string)($meta['description']??''),(string)($meta['categories']??''),
        (string)($candidate['sourceUrl']??''),(string)($candidate['imageUrl']??'')
    ));
    $hay=function_exists('mb_strtolower')?mb_strtolower($joined,'UTF-8'):strtolower($joined);
    $hay=urldecode(str_replace(array('_','-'), ' ', $hay));
    $bad=array(
        'logo'=>'/(?:^|[^a-zäöüß])(logo|logos|logotype|wordmark)(?:[^a-zäöüß]|$)/u',
        'poster'=>'/(?:^|[^a-zäöüß])(poster|posters|plakat|plakate|flyer|leaflet|brochure|prospekt|banner)(?:[^a-zäöüß]|$)/u',
        'sign'=>'/(?:^|[^a-zäöüß])(signage|signs?|schild|schilder|wegweiser|hinweistafel|infotafel|notice board|information board)(?:[^a-zäöüß]|$)/u',
        'map-diagram'=>'/(?:^|[^a-zäöüß])(diagramm?|diagram|chart|graph|lageplan|site plan|map|maps)(?:[^a-zäöüß]|$)/u',
        'scan-document'=>'/(?:^|[^a-zäöüß])(scan|scanned|document scan|documents?|screenshot|screen shot|facsimile|faksimile)(?:[^a-zäöüß]|$)/u',
        'typography'=>'/(?:^|[^a-zäöüß])(typography|lettering|inscription|inscriptions|schriftzug|texttafel|text only)(?:[^a-zäöüß]|$)/u',
        'emblem'=>'/(?:^|[^a-zäöüß])(wappen|coat of arms|flag|flags|emblem)(?:[^a-zäöüß]|$)/u',
    );
    foreach($bad as $reason=>$rx)if(preg_match($rx,$hay))return array('ok'=>false,'reason'=>'metadata-'.$reason);
    return array('ok'=>true,'reason'=>'commons-metadata-pass');
}

function ruhrpott_guru_event_media_rights_basis_from_license_v235($license) {
    $l=strtoupper(trim((string)$license));
    if(strpos($l,'CC0')!==false)return 'cc0';
    if(strpos($l,'PUBLIC DOMAIN')!==false||strpos($l,'PDM')!==false)return 'public_domain';
    if(preg_match('/CC\s*BY\s*-?\s*SA/i',$l))return 'cc_by_sa';
    if(preg_match('/CC\s*BY/i',$l))return 'cc_by';
    return '';
}

function ruhrpott_guru_event_media_venue_aliases_v235($row) {
    $aliases=array((string)($row['name']??''));$tags=json_decode((string)($row['raw_tags']??''),true);if(!is_array($tags))$tags=array();
    foreach(array('name','official_name','alt_name','short_name','loc_name','old_name') as $k){if(!empty($tags[$k])&&is_string($tags[$k]))foreach(preg_split('/\s*;\s*/',(string)$tags[$k]) as $v)if(trim($v)!=='')$aliases[]=trim($v);}
    return array_values(array_unique(array_filter($aliases)));
}

function ruhrpott_guru_event_media_match_place_v235($venue,$city,$coords) {
    global $wpdb;if(!is_array($coords)||!isset($coords['lat'],$coords['lng'])||!is_numeric($coords['lat'])||!is_numeric($coords['lng']))return null;
    $venue=trim((string)$venue);if($venue==='')return null; // no anonymous nearest-place borrowing
    $lat=(float)$coords['lat'];$lng=(float)$coords['lng'];$places=$wpdb->prefix.'rg_places';if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$places))!==$places)return null;
    $rows=$wpdb->get_results($wpdb->prepare(
        "SELECT id,name,lat,lng,city,street,house_number,postcode,website,wikidata,wikipedia,primary_category,primary_subcategory,quality_score,raw_tags
         FROM $places WHERE active=1 AND lat BETWEEN %f AND %f AND lng BETWEEN %f AND %f ORDER BY quality_score DESC,id ASC LIMIT 80",
        $lat-0.0060,$lat+0.0060,$lng-0.0090,$lng+0.0090
    ),ARRAY_A);
    $vn=ruhrpott_guru_event_media_norm_v85($venue);$vTokens=ruhrpott_guru_event_media_tokens_v85($venue);$cityn=ruhrpott_guru_event_media_norm_v85($city);$best=null;$bestScore=-9999;
    foreach((array)$rows as $r){
        $dist=ruhrpott_guru_event_distance_km($lat,$lng,(float)$r['lat'],(float)$r['lng'])*1000.0;if($dist>550)continue;
        if($cityn!==''&&!empty($r['city'])&&ruhrpott_guru_event_media_norm_v85($r['city'])!==$cityn&&$dist>80)continue;
        $bestPct=0.0;$bestHits=0;
        foreach(ruhrpott_guru_event_media_venue_aliases_v235($r) as $alias){$an=ruhrpott_guru_event_media_norm_v85($alias);if($an==='')continue;similar_text($vn,$an,$pct);$hits=0;foreach($vTokens as $t)if($t!==''&&strpos($an,$t)!==false)$hits++;if($pct>$bestPct)$bestPct=$pct;if($hits>$bestHits)$bestHits=$hits;}
        $required=count($vTokens)<=1?1:max(2,(int)ceil(count($vTokens)*0.60));
        $strong=($bestPct>=78.0&&$dist<=550)||($bestPct>=65.0&&$dist<=80)||($bestHits>=$required&&$dist<=180);
        if(!$strong)continue;
        $identityBonus=$bestPct*1.8+$bestHits*18;$distanceScore=max(0,100-$dist/4);$metaBonus=(!empty($r['wikidata'])||!empty($r['wikipedia']))?22:0;
        $tags=json_decode((string)($r['raw_tags']??''),true);if(is_array($tags)&&(!empty($tags['wikimedia_commons'])||!empty($tags['wikimedia:commons'])||!empty($tags['wikidata'])||!empty($tags['wikipedia'])))$metaBonus+=20;
        $score=$identityBonus+$distanceScore+$metaBonus+(float)($r['quality_score']??0)*15;
        if($score>$bestScore){$r['_distance_m']=$dist;$r['_venue_match_pct']=$bestPct;$r['_venue_token_hits']=$bestHits;$best=$r;$bestScore=$score;}
    }
    return $best;
}

function ruhrpott_guru_event_media_validate_open_commons_v235($candidate) {
    if(!is_array($candidate)||empty($candidate['imageUrl']))return null;$source=(string)($candidate['sourceUrl']??'');$image=(string)$candidate['imageUrl'];
    $meta=ruhrpott_guru_event_media_commons_metadata_v235($source,$image);if(!$meta)return null;
    $quality=ruhrpott_guru_event_media_commons_quality_v287($meta,$candidate);if(empty($quality['ok']))return null;
    return array_merge($candidate,array(
        'imageUrl'=>$meta['imageUrl'],'sourceUrl'=>$meta['sourceUrl'],'license'=>$meta['license'],'artist'=>$meta['artist'],
        'qualityReason'=>(string)($quality['reason']??'commons-metadata-pass')
    ));
}

function ruhrpott_guru_event_media_context_candidate_for_place_v235($place) {
    global $wpdb;if(!is_array($place)||empty($place['id']))return null;$pid=(int)$place['id'];$cache='rg_event_venue_context_v287_'.$pid;$cached=get_transient($cache);if(is_array($cached)&&array_key_exists('hit',$cached))return !empty($cached['hit'])?$cached['data']:null;
    $media=$wpdb->prefix.'rg_place_media';$candidates=array();
    if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$media))===$media){
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $media WHERE place_id=%d AND media_type='image' AND moderation_status='active' ORDER BY is_primary DESC,identity_confidence DESC,visual_quality DESC,id DESC LIMIT 30",$pid),ARRAY_A);
        foreach((array)$rows as $m){$hay=strtolower((string)($m['source']??'').' '.(string)($m['source_url']??'').' '.(string)($m['url']??''));if(strpos($hay,'commons')===false&&strpos($hay,'wikimedia')===false&&strpos($hay,'wikidata')===false)continue;$candidates[]=array('imageUrl'=>(string)$m['url'],'sourceUrl'=>(string)($m['source_url']??''),'license'=>(string)($m['license']??''),'artist'=>(string)($m['artist']??''),'credit'=>(string)($m['credit']??''),'matchMethod'=>'stored-place-media','identityConfidence'=>(float)($m['identity_confidence']??0.9));}
    }
    $tags=json_decode((string)($place['raw_tags']??''),true);if(!is_array($tags))$tags=array();$commons=(string)($tags['wikimedia_commons']??($tags['wikimedia:commons']??''));
    if($commons!==''){
        $file=ruhrpott_guru_food_media_commons_file_url($commons);if($file)$candidates[]=array('imageUrl'=>$file,'sourceUrl'=>$commons,'matchMethod'=>'osm-wikimedia-commons','identityConfidence'=>0.99);
        $cat=ruhrpott_guru_food_media_commons_category($commons);if($cat)$candidates[]=$cat;
    }
    $qid=(string)($place['wikidata']??($tags['wikidata']??''));if($qid!==''){$wd=ruhrpott_guru_food_media_wikidata_p18($qid);if($wd)$candidates[]=$wd;}
    $wiki=(string)($place['wikipedia']??($tags['wikipedia']??''));if($wiki!==''){$wpimg=ruhrpott_guru_food_media_wikipedia_pageimage($wiki);if($wpimg)$candidates[]=$wpimg;}
    $geo=ruhrpott_guru_food_media_commons_geo((string)$place['name'],(string)$place['city'],(float)$place['lat'],(float)$place['lng'],500);if($geo)$candidates[]=$geo;
    foreach($candidates as $cand){$valid=ruhrpott_guru_event_media_validate_open_commons_v235($cand);if(!$valid)continue;$artist=trim((string)($valid['artist']??''));$valid['credit']='Event-Ort · Wikimedia Commons'.($artist!==''?' · Foto: '.$artist:'');$valid['source']='venue-commons-context';$valid['originStatus']='venue-context';$valid['identityConfidence']=max(0.82,(float)($valid['identityConfidence']??0.82));$valid['mediaLabel']='Event-Ort';$valid['contextual']=true;$valid['rightsStatus']='approved';$valid['rightsBasis']=ruhrpott_guru_event_media_rights_basis_from_license_v235($valid['license']??'');$valid['rightsHolder']=$artist;$valid['rightsProofUrl']=$valid['sourceUrl']??'';$valid['rightsNote']='Automatisch freigegeben als Event-Ort-Kontextbild auf Basis einer explizit wiederverwendbaren Wikimedia-Commons-Lizenz.';$valid['placeId']=$pid;$valid['placeName']=(string)$place['name'];$valid['matchMethod']='place-identity-'.sanitize_key((string)($valid['matchMethod']??'commons'));set_transient($cache,array('hit'=>1,'data'=>$valid),7*DAY_IN_SECONDS);return $valid;}
    set_transient($cache,array('hit'=>0,'data'=>null),12*HOUR_IN_SECONDS);return null;
}

function ruhrpott_guru_event_media_from_place_db_v235($title,$venue,$city,$coords) {
    $place=ruhrpott_guru_event_media_match_place_v235($venue,$city,$coords);if(!$place)return null;$candidate=ruhrpott_guru_event_media_context_candidate_for_place_v235($place);if(!$candidate)return null;
    $candidate['matchedVenue']=$venue;$candidate['venueMatchPct']=(float)($place['_venue_match_pct']??0);$candidate['venueDistanceM']=(float)($place['_distance_m']??0);return $candidate;
}

function ruhrpott_guru_event_media_theme_v298($title,$eventType,$raw=array()) {
    $title_n=ruhrpott_guru_food_media_normalize_text((string)$title);
    $type_n=ruhrpott_guru_food_media_normalize_text((string)$eventType);
    $structured='';
    if(is_array($raw)){
        $structured=ruhrpott_guru_food_media_normalize_text(wp_json_encode(array(
            $raw['categories']??array(),$raw['keywords']??array(),$raw['features']??array(),
            $raw['type']??array(),$raw['types']??array(),$raw['classification']??array(),$raw['classifications']??array()
        ),JSON_UNESCAPED_UNICODE));
    }
    $all=trim($title_n.' '.$type_n.' '.$structured);

    // V298: strong event-title signals win over broad audience/location metadata.
    // This keeps e.g. "Parkour: Kindertraining" in Sport instead of a generic
    // Familienbild and "Halloween" in Party/Nightlife instead of museum/interior.
    if(preg_match('/\b(halloween|party|clubnacht|nightlife|disco|dj|tanzparty|dance\s*party)\b/i',$title_n))return 'party';
    if(preg_match('/\b(parkour|sport|marathon|lauf(?:veranstaltung)?|turnier|rennen|fussball|fußball|handball|basketball|radsport|cycling)\b/i',$title_n))return 'sports';
    if(preg_match('/\b(workshop|kurs|seminar|mitmach|atelierkurs)\b/i',$title_n))return 'workshop';
    if(preg_match('/\b(fuehrung|führung|rundgang|stadtrundgang|werksfuehrung|werksführung|tour)\b/i',$title_n))return 'tour';
    if(preg_match('/\b(vortrag|lesung|lecture|talk|diskussion|podium)\b/i',$title_n))return 'talk';
    if(preg_match('/\b(kino|filmvorfuehrung|filmvorführung|screening)\b/i',$title_n))return 'film';
    if(preg_match('/\b(raetsel|rätsel|escape|quiz|schnitzeljagd|detektiv)\b/i',$title_n))return 'puzzle';

    // The normalized frontend event type is the primary semantic contract.
    if(preg_match('/party|nightlife|nachtleben|club|disco|tanz/i',$type_n))return 'party';
    if(preg_match('/konzert|musik|music/i',$type_n))return 'concert';
    if(preg_match('/comedy|kabarett|show|variet|zauber|circus|zirkus/i',$type_n))return 'comedy';
    if(preg_match('/theater|buehne|bühne|oper|musical|schauspiel|performance/i',$type_n))return 'theater';
    if(preg_match('/ausstellung|kunst|exhibition|galerie|museum/i',$type_n))return 'exhibition';
    if(preg_match('/markt|stadtfest|kirmes|messe|volksfest/i',$type_n))return 'market';
    if(preg_match('/sport/i',$type_n))return 'sports';
    if(preg_match('/familie|kinder|family/i',$type_n))return 'family';
    if(preg_match('/tour|wissen|workshop/i',$type_n)){
        if(preg_match('/fuehrung|führung|rundgang|tour|industriekultur/i',$all))return 'tour';
        if(preg_match('/vortrag|lesung|lecture|talk|diskussion|podium/i',$all))return 'talk';
        if(preg_match('/workshop|kurs|seminar|mitmach|atelier/i',$all))return 'workshop';
        return 'knowledge';
    }

    // Structured ODF/ET4 categories are a fallback, never venue text.
    if(preg_match('/\b(halloween|party|clubnacht|nightlife|disco|tanzparty)\b/i',$structured))return 'party';
    if(preg_match('/\b(konzert|musikveranstaltung|live\s*music|chor|orchester|jazz|rock|pop)\b/i',$structured))return 'concert';
    if(preg_match('/\b(comedy|kabarett|variet|zauber|circus|zirkus)\b/i',$structured))return 'comedy';
    if(preg_match('/\b(theater|buehne|bühne|schauspiel|oper|musical|performance)\b/i',$structured))return 'theater';
    if(preg_match('/\b(ausstellung|exhibition|vernissage|galerie|kunstschau)\b/i',$structured))return 'exhibition';
    if(preg_match('/\b(flohmarkt|troedelmarkt|trödelmarkt|markt|stadtfest|kirmes|volksfest|messe)\b/i',$structured))return 'market';
    if(preg_match('/\b(parkour|sport|marathon|laufveranstaltung|turnier|rennen|fussball|fußball|handball|basketball|radsport)\b/i',$structured))return 'sports';
    if(preg_match('/\b(kinder|familien|familie|kids|ferienprogramm)\b/i',$structured))return 'family';
    if(preg_match('/\b(fuehrung|führung|rundgang|tour|industriekultur)\b/i',$structured))return 'tour';
    if(preg_match('/\b(workshop|kurs|seminar|mitmach|atelierkurs)\b/i',$structured))return 'workshop';
    if(preg_match('/\b(vortrag|lesung|lecture|talk|diskussion|podium)\b/i',$structured))return 'talk';
    if(preg_match('/\b(kino|filmvorfuehrung|filmvorführung|screening|film)\b/i',$structured))return 'film';
    if(preg_match('/\b(raetsel|rätsel|escape|quiz|schnitzeljagd|detektiv)\b/i',$structured))return 'puzzle';
    if(preg_match('/\b(natur|garten|park|pflanzen|wald|forest)\b/i',$structured))return 'nature';

    // Last deterministic title fallback. Unknown stays unknown; there is no
    // generic "event venue interior" stock image anymore.
    if(preg_match('/konzert|musik|band|orchester|jazz|rock|pop|ska/i',$title_n))return 'concert';
    if(preg_match('/comedy|kabarett|show|variet|zauber|circus|zirkus/i',$title_n))return 'comedy';
    if(preg_match('/theater|buehne|bühne|schauspiel|oper|musical/i',$title_n))return 'theater';
    if(preg_match('/ausstellung|vernissage|galerie|kunst/i',$title_n))return 'exhibition';
    if(preg_match('/markt|kirmes|stadtfest|volksfest|messe/i',$title_n))return 'market';
    if(preg_match('/familie|kinder|kids/i',$title_n))return 'family';
    if(preg_match('/natur|garten|park|wald/i',$title_n))return 'nature';
    return '';
}

function ruhrpott_guru_event_media_pexels_query_v233($title,$eventType,$raw=array()) {
    $theme=ruhrpott_guru_event_media_theme_v298($title,$eventType,$raw);
    $queries=array(
        'party'=>'nightclub empty dance floor neon lights music club interior',
        'concert'=>'concert stage instruments lights empty music venue',
        'comedy'=>'comedy club stage microphone spotlight empty',
        'theater'=>'theater stage curtain auditorium empty',
        'exhibition'=>'museum exhibition gallery artwork interior',
        'market'=>'fairground carnival rides market stalls festival',
        'sports'=>'sports venue field court equipment empty',
        'family'=>'playground games colorful activity area empty',
        'tour'=>'industrial heritage architecture mine factory building',
        'workshop'=>'workshop table tools craft materials studio',
        'talk'=>'lecture hall podium microphone auditorium empty',
        'film'=>'cinema interior screen seats empty',
        'puzzle'=>'escape room puzzle locks interior',
        'nature'=>'park garden forest trail landscape',
        'knowledge'=>'learning workshop lecture room materials'
    );
    return $theme!==''&&isset($queries[$theme])?$queries[$theme]:'';
}

function ruhrpott_guru_event_media_pexels_alt_matches_v298($alt,$theme) {
    $alt=ruhrpott_guru_food_media_normalize_text((string)$alt);
    if($alt===''||$theme==='')return false;
    $rules=array(
        'party'=>'/\b(nightclub|club|disco|dance floor|dj|neon|bar|music venue)\b/i',
        'concert'=>'/\b(concert|stage|instrument|guitar|drum|piano|microphone|music venue)\b/i',
        'comedy'=>'/\b(comedy|microphone|stage|spotlight|curtain|theater|theatre)\b/i',
        'theater'=>'/\b(theater|theatre|stage|curtain|auditorium|opera|seats)\b/i',
        'exhibition'=>'/\b(museum|gallery|exhibition|artwork|sculpture|art exhibit)\b/i',
        'market'=>'/\b(market|fairground|carnival|ride|stall|festival|fair)\b/i',
        'sports'=>'/\b(sport|stadium|field|court|track|goal|ball|gym)\b/i',
        'family'=>'/\b(playground|play area|toys|toy|games|game room|colorful activity)\b/i',
        'tour'=>'/\b(industrial|heritage|factory|mine|architecture|historic building)\b/i',
        'workshop'=>'/\b(workshop|tools|materials|craft|studio|workbench)\b/i',
        'talk'=>'/\b(lecture|podium|microphone|conference|auditorium|presentation)\b/i',
        'film'=>'/\b(cinema|movie|screen|theater|theatre|seats)\b/i',
        'puzzle'=>'/\b(escape room|puzzle|lock|riddle|mystery room)\b/i',
        'nature'=>'/\b(park|garden|forest|trail|plants|nature)\b/i',
        'knowledge'=>'/\b(lecture|workshop|classroom|learning|materials|seminar)\b/i'
    );
    return isset($rules[$theme])&&preg_match($rules[$theme],$alt)===1;
}

function ruhrpott_guru_event_media_pexels_v298($title,$city,$eventType,$raw=array()) {
    return ruhrpott_guru_event_media_pexels_v233($title,$city,$eventType,$raw);
}

function ruhrpott_guru_event_media_pexels_v233($title,$city,$eventType,$raw=array()) {
    $api_key=ruhrpott_guru_get_pexels_api_key();if($api_key==='')return null;
    $theme=ruhrpott_guru_event_media_theme_v298($title,$eventType,$raw);
    $query=ruhrpott_guru_event_media_pexels_query_v233($title,$eventType,$raw);
    if($theme===''||$query==='')return null;
    $pool_key='rg_pexels_event_pool_v298_semantic_person_free_'.md5($theme.'|'.$query);$photos=get_transient($pool_key);
    if(!is_array($photos)){
        $url=add_query_arg(array('query'=>$query,'orientation'=>'landscape','size'=>'large','per_page'=>80,'page'=>1),'https://api.pexels.com/v1/search');
        $resp=wp_safe_remote_get($url,array('timeout'=>8,'redirection'=>2,'limit_response_size'=>900000,'headers'=>array('Authorization'=>$api_key,'Accept'=>'application/json','User-Agent'=>'RuhrpottGuru/1.0; https://www.ruhrpottguru.de/')));
        if(is_wp_error($resp)||(int)wp_remote_retrieve_response_code($resp)!==200){set_transient($pool_key,array(),15*MINUTE_IN_SECONDS);return null;}
        $data=json_decode((string)wp_remote_retrieve_body($resp),true);$rawPhotos=is_array($data['photos']??null)?$data['photos']:array();$photos=array();
        foreach($rawPhotos as $photo){
            if(!is_array($photo)||!is_array($photo['src']??null))continue;
            $alt=sanitize_text_field((string)($photo['alt']??''));
            if(ruhrpott_guru_food_media_pexels_bad_alt($alt))continue;
            if(!ruhrpott_guru_event_media_pexels_alt_matches_v298($alt,$theme))continue;
            $image='';foreach(array('landscape','large2x','large','medium') as $size){if(!empty($photo['src'][$size])){$image=esc_url_raw((string)$photo['src'][$size]);break;}}
            if(!$image||!wp_http_validate_url($image))continue;
            $page=esc_url_raw((string)($photo['url']??'https://www.pexels.com/'));if($page)$page=add_query_arg(array('utm_source'=>'ruhrpottguru','utm_medium'=>'referral'),$page);
            $photos[]=array('imageUrl'=>$image,'sourceUrl'=>$page,'artist'=>sanitize_text_field((string)($photo['photographer']??'Pexels Contributor')),'photoId'=>(string)($photo['id']??''),'alt'=>$alt);
        }
        set_transient($pool_key,$photos,DAY_IN_SECONDS);
    }
    if(!$photos)return null;$seed=strtolower($title.'|'.$city.'|'.$theme.'|'.$query);$idx=(int)sprintf('%u',crc32($seed))%count($photos);$photo=$photos[$idx];
    return array(
        'ok'=>true,'imageUrl'=>$photo['imageUrl'],'source'=>'pexels-symbolic-event','sourceUrl'=>$photo['sourceUrl'],
        'credit'=>'Symbolfoto · Pexels · Foto: '.$photo['artist'],'artist'=>$photo['artist'],'license'=>'Pexels License',
        'originStatus'=>'symbolic','identityConfidence'=>0.0,'matchMethod'=>'pexels-event-v298-'.$theme,
        'mediaLabel'=>'Symbolfoto','symbolic'=>true,'query'=>$query,'semanticTheme'=>$theme,'semanticVersion'=>'v298',
        'photoId'=>$photo['photoId'],'alt'=>$photo['alt']
    );
}

function ruhrpott_guru_event_media_host_v233($url){$h=strtolower((string)wp_parse_url((string)$url,PHP_URL_HOST));return preg_replace('/^www\./','',$h);}

/** Strict one-hop discovery: same host, event-title-bearing link text/path, max 3 pages. */
function ruhrpott_guru_event_media_same_domain_links_v233($html,$page_url,$title,$limit=3){
    $host=ruhrpott_guru_event_media_host_v233($page_url);$tokens=ruhrpott_guru_event_media_tokens_v85($title);if($host===''||!$tokens)return array();$ranked=array();
    if(preg_match_all('/<a\b[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is',(string)$html,$m,PREG_SET_ORDER)){
        foreach(array_slice($m,0,600) as $a){$url=ruhrpott_guru_event_media_abs_v85($page_url,$a[1]);if(!$url||ruhrpott_guru_event_media_host_v233($url)!==$host)continue;$path=strtolower((string)wp_parse_url($url,PHP_URL_PATH));if(preg_match('#/(?:impressum|datenschutz|privacy|kontakt|contact|jobs?|presse|newsletter|login|search|suche)(?:/|$)#i',$path))continue;$ctx=ruhrpott_guru_event_media_norm_v85(wp_strip_all_tags($a[2]).' '.$path);$hits=0;foreach($tokens as $t)if(strpos($ctx,$t)!==false)$hits++;$ratio=$hits/max(1,count($tokens));$need=count($tokens)<=2?1.0:0.70;if($ratio<$need||$hits<(count($tokens)<=2?count($tokens):max(2,(int)ceil(count($tokens)*$need))))continue;$ranked[$url]=max($ranked[$url]??0,(int)round($ratio*100)+$hits*10);}
    }
    arsort($ranked,SORT_NUMERIC);return array_slice(array_keys($ranked),0,max(1,min(3,(int)$limit)));
}

function ruhrpott_guru_event_media_fetch_html_v233($url){
    $resp=wp_safe_remote_get($url,array('timeout'=>7,'redirection'=>4,'limit_response_size'=>700000,'user-agent'=>'RuhrpottGuru/1.0 (+https://www.ruhrpottguru.de/)','headers'=>array('Accept'=>'text/html,application/xhtml+xml')));
    if(is_wp_error($resp))return '';$code=(int)wp_remote_retrieve_response_code($resp);if($code<200||$code>=400)return '';$type=strtolower((string)wp_remote_retrieve_header($resp,'content-type'));if($type!==''&&strpos($type,'text/html')===false&&strpos($type,'application/xhtml+xml')===false)return '';return (string)wp_remote_retrieve_body($resp);
}

function ruhrpott_guru_event_media_official_candidate_v233($pages,$title,$city,$start){
    foreach((array)$pages as $page){
        $html=ruhrpott_guru_event_media_fetch_html_v233($page);if($html==='')continue;
        $toCheck=array(array($page,$html));
        if(!ruhrpott_guru_event_media_identity_v85($html,$title,$city,$start)){
            foreach(ruhrpott_guru_event_media_same_domain_links_v233($html,$page,$title,3) as $linked){$sub=ruhrpott_guru_event_media_fetch_html_v233($linked);if($sub!=='')$toCheck[]=array($linked,$sub);}
        }
        foreach($toCheck as $pair){list($candidatePage,$candidateHtml)=$pair;if(!ruhrpott_guru_event_media_identity_v85($candidateHtml,$title,$city,$start))continue;$candidates=ruhrpott_guru_event_media_collect_v85($candidateHtml,$candidatePage,$title);if(!$candidates)continue;foreach($candidates as $best){$img=esc_url_raw($best['url']);if(!$img||!wp_http_validate_url($img))continue;return array('imageUrl'=>$img,'source'=>'official-candidate','sourceUrl'=>$candidatePage,'credit'=>'Bildkandidat · offizielle Veranstaltungsseite','license'=>'','artist'=>'','originStatus'=>'candidate-rights-unverified','identityConfidence'=>0.99,'matchMethod'=>'official-event-page-'.sanitize_key($best['kind']),'requiresEditorialApproval'=>true,'mediaLabel'=>'Offizieller Bildkandidat');}}
    }
    return null;
}

function ruhrpott_guru_event_media_resolver_v85(WP_REST_Request $request) {
    $p=$request->get_json_params();if(!is_array($p))$p=array();
    $title=sanitize_text_field((string)($p['title']??''));$explicit=esc_url_raw((string)($p['website']??($p['pageUrl']??($p['sourceUrl']??''))));$raw=is_array($p['raw']??null)?$p['raw']:array();$venue=sanitize_text_field((string)($p['venueName']??''));$city=sanitize_text_field((string)($p['city']??''));$coords=is_array($p['coords']??null)?$p['coords']:array();$start=sanitize_text_field((string)($p['start']??''));$eventType=sanitize_text_field((string)($p['eventType']??''));
    if($title==='')return new WP_REST_Response(array('ok'=>false,'message'=>'Eventtitel fehlt'),200);
    $pages=ruhrpott_guru_event_media_page_candidates_v161($raw,$explicit);$cache_key='rg_event_media_v287_'.md5($title.'|'.implode('|',$pages).'|'.$venue.'|'.$city.'|'.$start.'|'.wp_json_encode($coords));$cached=get_transient($cache_key);if(is_array($cached))return new WP_REST_Response($cached,200);

    $official=ruhrpott_guru_event_media_official_candidate_v233($pages,$title,$city,$start);
    $fallback=ruhrpott_guru_event_media_from_place_db_v235($title,$venue,$city,$coords);
    if(!$fallback)$fallback=ruhrpott_guru_event_media_pexels_v233($title,$city,$eventType,$raw);

    if($official){$out=array('ok'=>true,'candidateOnly'=>true,'candidate'=>$official,'message'=>'Offizielles Eventbild gefunden; Veröffentlichung erfordert redaktionelle Rechtefreigabe.');if($fallback)$out['fallback']=$fallback;set_transient($cache_key,$out,DAY_IN_SECONDS);return new WP_REST_Response($out,200);}
    if($fallback){$out=$fallback;$out['ok']=true;set_transient($cache_key,$out,DAY_IN_SECONDS);return new WP_REST_Response($out,200);}
    $out=array('ok'=>false,'message'=>'Kein freigegebenes Eventbild, kein offen lizenziertes Orts-Kontextbild und kein Pexels-Symbolbild verfügbar');set_transient($cache_key,$out,30*MINUTE_IN_SECONDS);return new WP_REST_Response($out,200);
}

/**
 * V242 – Batch resolver for repeated Event-Ort images in list cards.
 *
 * Only symbolic Pexels media from the already established, person-filtered
 * event pool is returned. No event, original image, DB row or rights state is
 * changed. The client sends only cards whose same Event-Ort image already
 * repeats inside the current list.
 */
function rpg_v241_register_event_media_diversity() {
    register_rest_route('ruhrpottguru/v1', '/event-media-diversity', array(
        'methods' => 'POST',
        'callback' => 'rpg_v241_event_media_diversity',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'rpg_v241_register_event_media_diversity');

function rpg_v241_event_media_diversity(WP_REST_Request $request) {
    $p = $request->get_json_params();
    $purpose = is_array($p) ? sanitize_key((string)($p['purpose'] ?? '')) : '';
    $quality_fallback = $purpose === 'quality-fallback';
    $semantic_fallback = $purpose === 'semantic-fallback';
    if (!$quality_fallback && !$semantic_fallback && !rpg_v241_event_media_diversity_enabled()) {
        return new WP_REST_Response(array('ok'=>true,'enabled'=>false,'items'=>array()), 200);
    }

    // V298: symbolic event images are allowed only when they can be tied to a
    // deterministic event theme. No generic stock image is returned. Existing
    // visitor caps remain bounded to protect shared hosting and the Pexels API.
    $cap = $quality_fallback ? 12 : ($semantic_fallback ? 36 : 24);
    $items = is_array($p) && is_array($p['items'] ?? null) ? array_slice($p['items'], 0, $cap) : array();
    if (!$items) return new WP_REST_Response(array('ok'=>true,'enabled'=>true,'items'=>array()), 200);

    $out = array();
    $queries = array();
    $used_photo_ids = array();
    $max_unique_queries = $quality_fallback ? 3 : 4;

    foreach ($items as $row) {
        if (!is_array($row)) continue;
        $id = sanitize_text_field((string)($row['id'] ?? ''));
        $title = sanitize_text_field((string)($row['title'] ?? ''));
        $city = sanitize_text_field((string)($row['city'] ?? ''));
        $event_type = sanitize_text_field((string)($row['eventType'] ?? ''));
        if ($id === '' || $title === '') continue;

        $theme = ruhrpott_guru_event_media_theme_v298($title, $event_type, array());
        $query = ruhrpott_guru_event_media_pexels_query_v233($title, $event_type, array());
        if ($theme === '' || $query === '') continue;
        if (!isset($queries[$query])) {
            if (count($queries) >= $max_unique_queries) continue;
            $queries[$query] = true;
        }

        // Deterministic per-event selection. If a rare CRC collision picks a
        // photo already used in this batch, retry with a display-only salt.
        $media = null;
        for ($variant = 0; $variant < 4; $variant++) {
            $seed_title = $title . ($variant ? ' diversity-' . $variant . '-' . $id : ' ' . $id);
            $candidate = ruhrpott_guru_event_media_pexels_v298($seed_title, $city, $event_type, array());
            if (!$candidate || empty($candidate['imageUrl'])) continue;
            $photo_id = (string)($candidate['photoId'] ?? '');
            if ($photo_id !== '' && isset($used_photo_ids[$photo_id]) && $variant < 3) continue;
            $media = $candidate;
            if ($photo_id !== '') $used_photo_ids[$photo_id] = true;
            break;
        }
        if (!$media) continue;

        $out[] = array(
            'id' => $id,
            'imageUrl' => esc_url_raw((string)$media['imageUrl']),
            'source' => 'pexels-symbolic-event',
            'sourceUrl' => esc_url_raw((string)($media['sourceUrl'] ?? '')),
            'credit' => sanitize_text_field((string)($media['credit'] ?? 'Symbolfoto · Pexels')),
            'artist' => sanitize_text_field((string)($media['artist'] ?? '')),
            'license' => sanitize_text_field((string)($media['license'] ?? 'Pexels License')),
            'mediaLabel' => 'Symbolfoto',
            'semanticTheme' => sanitize_key((string)($media['semanticTheme'] ?? $theme)),
            'semanticVersion' => 'v298',
            'displayOnly' => true,
        );
    }

    return new WP_REST_Response(array('ok'=>true,'enabled'=>true,'items'=>$out), 200);
}

/** V242 – one-click rollback switch in RP Redaktion; option name stays v241 for zero-migration rollback compatibility. */
function rpg_v241_event_media_diversity_menu() {
    add_submenu_page(
        'rp-redaktion',
        'RP Redaktion · Event-Bilder',
        'Event-Bilder',
        'manage_options',
        'rp-event-bilder',
        'rpg_v241_render_event_media_diversity_admin'
    );
}
add_action('admin_menu', 'rpg_v241_event_media_diversity_menu', 31);

function rpg_v241_render_event_media_diversity_admin() {
    if (!current_user_can('manage_options')) wp_die('Keine Berechtigung.');
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rpg_v241_media_save'])) {
        check_admin_referer('rpg_v241_media_settings');
        update_option('rpg_event_media_diversity_v241', isset($_POST['rpg_event_media_diversity_v241']) ? '1' : '0', false);
        update_option('rpg_event_type_badges_v243', isset($_POST['rpg_event_type_badges_v243']) ? '1' : '0', false);
        echo '<div class="notice notice-success is-dismissible"><p>Event-Kartendarstellung gespeichert.</p></div>';
    }
    $enabled = rpg_v241_event_media_diversity_enabled();
    $type_badges_enabled = rpg_v243_event_type_badges_enabled();
    echo '<div class="wrap"><h1>Event-Bilder</h1>';
    echo '<p>Die Einstellungen betreffen ausschließlich die Darstellung von Eventkarten. Eventdaten, Reihenfolge, Sichtbarkeit, echte ET4-/ODF-Bilder und die gespeicherte Medienzuordnung bleiben unverändert.</p>';
    echo '<form method="post">';
    wp_nonce_field('rpg_v241_media_settings');
    echo '<label style="display:flex;gap:10px;align-items:flex-start;max-width:850px;padding:16px;background:#fff;border:1px solid #dcdcde;border-radius:8px">';
    echo '<input type="checkbox" name="rpg_event_media_diversity_v241" value="1" '.checked($enabled,true,false).' style="margin-top:2px">';
    echo '<span><strong>Wiederholte Event-Ort-Bilder reduzieren</strong><br><span style="color:#50575e">Innerhalb eines Fensters von 10 Eventkarten soll dasselbe Event-Ort-Bild nur einmal sichtbar sein. Bei Wiederholungen wird für die Kartenansicht zuerst ein bereits geladenes, semantisch passendes Symbolfoto derselben Eventart verwendet; nur falls dort nichts passt, darf der bestehende personengefilterte Pexels-Pool nachliefern. Originale ET4-/ODF-Bilder werden niemals ersetzt. Gibt es keine geeignete Alternative, bleibt das Event-Ort-Bild bestehen. Alles ist reine Darstellung und mit diesem Haken sofort auf das Verhalten von v240 zurückstellbar.</span></span>';
    echo '</label>';
    echo '<label style="display:flex;gap:10px;align-items:flex-start;max-width:850px;padding:16px;margin-top:12px;background:#fff;border:1px solid #dcdcde;border-radius:8px">';
    echo '<input type="checkbox" name="rpg_event_type_badges_v243" value="1" '.checked($type_badges_enabled,true,false).' style="margin-top:2px">';
    echo '<span><strong>Eventart auf Eventkarten anzeigen</strong><br><span style="color:#50575e">Zeigt oben links ein kleines, einheitliches Scan-Badge wie Führung, Ausstellung, Konzert, Rätsel oder Workshop. Die Zuordnung wird ausschließlich für die Darstellung aus vorhandenen ET4-Kategorien, Keywords und eindeutigen Titelbegriffen abgeleitet. Bei unklaren Daten erscheint kein Badge. Bilder, Eventdaten, Reihenfolge und Sichtbarkeit werden nicht verändert.</span></span>';
    echo '</label>';
    echo '<p><strong>Rollback:</strong> Jeder Haken wirkt unabhängig. "Eventart auf Eventkarten anzeigen" deaktivieren stellt die Karten sofort ohne Typ-Badge dar. "Wiederholte Event-Ort-Bilder reduzieren" deaktivieren stellt zusätzlich die Bildlogik auf v240 zurück. Keine Datenmigration und kein Neuimport.</p>';
    submit_button('Einstellung speichern','primary','rpg_v241_media_save');
    echo '</form></div>';
}

/**
 * V118 – Privacy-first Analytics & Commercial Foundation
 * No Google Ads / ad networks. No analytics cookies. No persistent visitor IDs.
 * Product analytics are aggregate counters. Commercial features are dormant
 * until an explicit future campaign is configured.
 */
function rpg_v118_analytics_schema() {
    global $wpdb;
    $charset=$wpdb->get_charset_collate();
    $daily=$wpdb->prefix.'rpg_analytics_daily';
    $partners=$wpdb->prefix.'rpg_partners';
    $campaigns=$wpdb->prefix.'rpg_campaigns';
    $placements=$wpdb->prefix.'rpg_placements';
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE $daily (
      id bigint unsigned NOT NULL AUTO_INCREMENT,
      day date NOT NULL,
      metric_class varchar(20) NOT NULL,
      event_type varchar(40) NOT NULL,
      category varchar(24) NOT NULL DEFAULT '',
      area varchar(80) NOT NULL DEFAULT '',
      radius_bucket varchar(16) NOT NULL DEFAULT '',
      placement varchar(60) NOT NULL DEFAULT '',
      content_id varchar(120) NOT NULL DEFAULT '',
      raw_count bigint unsigned NOT NULL DEFAULT 0,
      accepted_count bigint unsigned NOT NULL DEFAULT 0,
      filtered_count bigint unsigned NOT NULL DEFAULT 0,
      PRIMARY KEY (id),
      UNIQUE KEY metric (day,metric_class,event_type,category,area,radius_bucket,placement,content_id)
    ) $charset;");
    dbDelta("CREATE TABLE $partners (
      id bigint unsigned NOT NULL AUTO_INCREMENT,
      name varchar(190) NOT NULL,
      partner_type varchar(40) NOT NULL DEFAULT 'local',
      status varchar(20) NOT NULL DEFAULT 'inactive',
      website varchar(500) NOT NULL DEFAULT '',
      created_at datetime NOT NULL,
      updated_at datetime NOT NULL,
      PRIMARY KEY (id),
      KEY status (status)
    ) $charset;");
    dbDelta("CREATE TABLE $campaigns (
      id bigint unsigned NOT NULL AUTO_INCREMENT,
      partner_id bigint unsigned NOT NULL,
      campaign_type varchar(30) NOT NULL DEFAULT 'featured',
      status varchar(20) NOT NULL DEFAULT 'inactive',
      content_type varchar(30) NOT NULL DEFAULT '',
      content_id varchar(120) NOT NULL DEFAULT '',
      category varchar(24) NOT NULL DEFAULT '',
      area varchar(80) NOT NULL DEFAULT '',
      radius_km decimal(6,2) DEFAULT NULL,
      starts_at datetime DEFAULT NULL,
      ends_at datetime DEFAULT NULL,
      affiliate_url varchar(1000) NOT NULL DEFAULT '',
      created_at datetime NOT NULL,
      updated_at datetime NOT NULL,
      PRIMARY KEY (id),
      KEY eligibility (status,category,area,starts_at,ends_at),
      KEY partner_id (partner_id)
    ) $charset;");
    dbDelta("CREATE TABLE $placements (
      id bigint unsigned NOT NULL AUTO_INCREMENT,
      placement_key varchar(60) NOT NULL,
      label varchar(120) NOT NULL,
      context_type varchar(30) NOT NULL DEFAULT '',
      status varchar(20) NOT NULL DEFAULT 'inactive',
      created_at datetime NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY placement_key (placement_key)
    ) $charset;");
    update_option('rpg_v118_schema','1',false);
}
function rpg_v118_maybe_schema(){
    if(get_option('rpg_v118_schema')!=='1') rpg_v118_analytics_schema();
}
add_action('init','rpg_v118_maybe_schema',2);

function rpg_v118_allowed_events(){
    return array(
      'page_view'=>'traffic','visit_start'=>'traffic','category_view'=>'product','card_open'=>'product','filter_change'=>'product','radius_change'=>'product',
      'burger_open'=>'product','burger_menu_click'=>'product',
      'route_click'=>'outbound','website_click'=>'outbound','reservation_click'=>'outbound','ticket_click'=>'outbound',
      'sponsored_impression'=>'commercial','sponsored_click'=>'commercial','affiliate_click'=>'commercial','partner_route_click'=>'commercial'
    );
}
function rpg_v118_clean_area($v){
    $v=sanitize_text_field((string)$v);
    return mb_substr($v,0,80);
}
function rpg_v118_radius_bucket($v){
    if($v===null || $v==='' || !is_numeric($v)) return '';
    $n=(float)$v;
    if($n<=0) return '';
    foreach(array(1,2.5,5,10,25,50) as $b) if($n <= $b) return (string)$b.'km';
    return '50km+';
}
function rpg_v118_increment($class,$event,$category,$area,$radius,$placement,$content,$accepted=true){
    global $wpdb;
    $table=$wpdb->prefix.'rpg_analytics_daily';
    $day=current_time('Y-m-d');
    $vals=array($day,$class,$event,$category,$area,$radius,$placement,$content,1,$accepted?1:0,$accepted?0:1);
    $sql="INSERT INTO $table
      (day,metric_class,event_type,category,area,radius_bucket,placement,content_id,raw_count,accepted_count,filtered_count)
      VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%d,%d,%d)
      ON DUPLICATE KEY UPDATE raw_count=raw_count+1, accepted_count=accepted_count+VALUES(accepted_count), filtered_count=filtered_count+VALUES(filtered_count)";
    $wpdb->query($wpdb->prepare($sql,$vals));
}
function rpg_v118_analytics_rest(){
    register_rest_route('ruhrpottguru/v1','/analytics',array(
      'methods'=>'POST',
      'permission_callback'=>'__return_true',
      'callback'=>function(WP_REST_Request $req){
        $p=(array)$req->get_json_params();
        $allowed=rpg_v118_allowed_events();
        $event=sanitize_key($p['event_type']??'');
        if(!isset($allowed[$event])) return new WP_REST_Response(array('ok'=>false),400);
        $class=$allowed[$event];
        // Commercial metrics require the signed flow and are not accepted by
        // the anonymous product endpoint.
        if($class==='commercial') return new WP_REST_Response(array('ok'=>false,'reason'=>'signed-commercial-required'),403);
        $cats=array('events','food','highlights','culture','activities','kultur','aktiv','buedchen-radar','route','');
        $category=sanitize_key($p['category']??'');
        if(!in_array($category,$cats,true)) $category='';
        $area=rpg_v118_clean_area($p['area']??'');
        $radius=rpg_v118_radius_bucket($p['radius']??0);
        $placement=substr(sanitize_key($p['placement']??''),0,60);
        $content=substr(sanitize_text_field((string)($p['content_id']??'')),0,120);
        $marketing=rpg_v231_marketing_payload($p);
        if($event==='page_view'){
            rpg_v230_increment_traffic(
                sanitize_text_field((string)($p['page_path']??'/')),
                sanitize_text_field((string)($p['page_title']??'')),
                sanitize_key((string)($p['page_type']??'page')),
                sanitize_key((string)($p['device']??'unknown'))
            );
            if($marketing){
                rpg_v231_increment_marketing('page_view',$marketing);
                if(!empty($p['mk_landing'])) rpg_v231_increment_marketing('landing',$marketing);
            }
        } elseif($event==='visit_start') {
            rpg_v237_increment_visit(
                sanitize_text_field((string)($p['page_path']??'/')),
                sanitize_text_field((string)($p['page_title']??'')),
                sanitize_key((string)($p['page_type']??'page')),
                sanitize_key((string)($p['device']??'unknown'))
            );
        } else {
            rpg_v118_increment($class,$event,$category,$area,$radius,$placement,$content,true);
            if($marketing && in_array($event,array('category_view','card_open','route_click','website_click','reservation_click','ticket_click'),true)){
                rpg_v231_increment_marketing($event,$marketing);
            }
        }
        return new WP_REST_Response(array('ok'=>true),202);
      }
    ));
}
add_action('rest_api_init','rpg_v118_analytics_rest');

/* Commercial token foundation. No campaign is delivered by V118. */
function rpg_v118_commercial_secret(){
    return hash('sha256',wp_salt('auth').'|rpg-commercial-v1',true);
}
function rpg_v118_sign_commercial_token($claims){
    $claims['iat']=time(); $claims['exp']=time()+1800;
    $claims['nonce']=wp_generate_password(24,false,false);
    $payload=rtrim(strtr(base64_encode(wp_json_encode($claims)),'+/','-_'),'=');
    $sig=hash_hmac('sha256',$payload,rpg_v118_commercial_secret());
    return $payload.'.'.$sig;
}
function rpg_v118_verify_commercial_token($token){
    $parts=explode('.',(string)$token,2);
    if(count($parts)!==2) return false;
    if(!hash_equals(hash_hmac('sha256',$parts[0],rpg_v118_commercial_secret()),$parts[1])) return false;
    $raw=base64_decode(strtr($parts[0],'-_','+/'));
    $c=json_decode($raw,true);
    if(!is_array($c)||empty($c['exp'])||time()>(int)$c['exp']||empty($c['nonce'])) return false;
    return $c;
}
/* TTL adapter: WordPress transients work on 1blu; a persistent object cache,
   if installed later, is used transparently by WordPress. */
function rpg_v118_sequence_set($nonce,$state,$ttl=1800){
    return set_transient('rpg_seq_'.hash('sha256',$nonce),$state,$ttl);
}
function rpg_v118_sequence_get($nonce){
    return get_transient('rpg_seq_'.hash('sha256',$nonce));
}
function rpg_v118_sequence_delete($nonce){
    delete_transient('rpg_seq_'.hash('sha256',$nonce));
}


function rpg_v118_enqueue_analytics(){
    $f=get_template_directory().'/src/react-app/analytics.js';
    wp_enqueue_script('rpg-analytics',get_template_directory_uri().'/src/react-app/analytics.js',array(),file_exists($f)?filemtime($f):'118',true);
}
add_action('wp_enqueue_scripts','rpg_v118_enqueue_analytics',8);

/* V119 Founding Beta feedback: message + page/category only; no analytics identifier stored. */
function rpg_v119_feedback_schema(){
 global $wpdb; if(get_option('rpg_v119_feedback_schema')==='1')return;
 require_once ABSPATH.'wp-admin/includes/upgrade.php'; $t=$wpdb->prefix.'rpg_beta_feedback'; $cc=$wpdb->get_charset_collate();
 dbDelta("CREATE TABLE $t (id bigint unsigned NOT NULL AUTO_INCREMENT,created_at datetime NOT NULL,page varchar(255) NOT NULL DEFAULT '',category varchar(30) NOT NULL DEFAULT '',message text NOT NULL,PRIMARY KEY(id),KEY created_at(created_at)) $cc;");
 update_option('rpg_v119_feedback_schema','1',false);
}
add_action('init','rpg_v119_feedback_schema',3);
/* V122 feedback abuse protection: first-party token + honeypot + ephemeral rate limits.
   The raw IP is never written to the feedback/analytics tables; only a salted
   one-way key may exist briefly in WordPress transient storage for abuse defence. */
function rpg_v122_feedback_client_key(){
    $ip=isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : 'unknown';
    return hash_hmac('sha256',$ip,wp_salt('auth'));
}
function rpg_v122_feedback_counter($scope,$limit,$ttl){
    $key='rpg_fb_rl_'.sanitize_key($scope);
    $value=(int)get_transient($key);
    if($value >= $limit) return false;
    set_transient($key,$value+1,$ttl);
    return true;
}
function rpg_v122_feedback_token(){
    $claims=array(
        'purpose'=>'feedback',
        'iat'=>time(),
        'exp'=>time()+900,
        'nonce'=>wp_generate_password(20,false,false)
    );
    $payload=rtrim(strtr(base64_encode(wp_json_encode($claims)),'+/','-_'),'=');
    $sig=hash_hmac('sha256',$payload,hash('sha256',wp_salt('nonce').'|rpg-feedback-v122',true));
    return $payload.'.'.$sig;
}
function rpg_v122_feedback_verify_token($token){
    $parts=explode('.',(string)$token,2);
    if(count($parts)!==2) return false;
    $expected=hash_hmac('sha256',$parts[0],hash('sha256',wp_salt('nonce').'|rpg-feedback-v122',true));
    if(!hash_equals($expected,$parts[1])) return false;
    $raw=base64_decode(strtr($parts[0],'-_','+/'));
    $claims=json_decode($raw,true);
    if(!is_array($claims)||($claims['purpose']??'')!=='feedback'||empty($claims['iat'])||empty($claims['exp'])||empty($claims['nonce'])) return false;
    if(time()>(int)$claims['exp'] || time()-(int)$claims['iat']<2) return false;
    return $claims;
}
function rpg_v122_feedback_origin_ok(){
    $home_host=strtolower((string)wp_parse_url(home_url('/'),PHP_URL_HOST));
    foreach(array('HTTP_ORIGIN','HTTP_REFERER') as $h){
        if(empty($_SERVER[$h])) continue;
        $host=strtolower((string)wp_parse_url((string)$_SERVER[$h],PHP_URL_HOST));
        if($host!=='' && $host!==$home_host) return false;
    }
    return true;
}
function rpg_v122_feedback_error($reason,$status){
    return new WP_REST_Response(array('ok'=>false,'reason'=>$reason),$status);
}
add_action('rest_api_init',function(){
 register_rest_route('ruhrpottguru/v1','/feedback-token',array(
   'methods'=>'GET','permission_callback'=>'__return_true',
   'callback'=>function(){
      $client=rpg_v122_feedback_client_key();
      if(!rpg_v122_feedback_counter('token_'.substr($client,0,32),30,10*MINUTE_IN_SECONDS)) return rpg_v122_feedback_error('rate_limited',429);
      return new WP_REST_Response(array('ok'=>true,'token'=>rpg_v122_feedback_token()),200);
   }
 ));
 register_rest_route('ruhrpottguru/v1','/beta-feedback',array(
   'methods'=>'POST','permission_callback'=>'__return_true',
   'callback'=>function(WP_REST_Request $r){
      global $wpdb;
      if(!rpg_v122_feedback_origin_ok()) return rpg_v122_feedback_error('origin',403);
      $len=isset($_SERVER['CONTENT_LENGTH'])?(int)$_SERVER['CONTENT_LENGTH']:0;
      if($len>8192) return rpg_v122_feedback_error('too_large',413);

      $p=(array)$r->get_json_params();
      if(!empty($p['company'])) return rpg_v122_feedback_error('spam',400); // honeypot

      $claims=rpg_v122_feedback_verify_token((string)($p['feedback_token']??''));
      if(!$claims) return rpg_v122_feedback_error('token',403);

      // Each issued token can submit at most once.
      $nonce_key='rpg_fb_nonce_'.hash('sha256',(string)$claims['nonce']);
      if(get_transient($nonce_key)) return rpg_v122_feedback_error('replay',409);

      $client=rpg_v122_feedback_client_key();
      $short=substr($client,0,32);
      if(!rpg_v122_feedback_counter('10m_'.$short,5,10*MINUTE_IN_SECONDS)) return rpg_v122_feedback_error('rate_limited',429);
      if(!rpg_v122_feedback_counter('1h_'.$short,15,HOUR_IN_SECONDS)) return rpg_v122_feedback_error('rate_limited',429);
      if(!rpg_v122_feedback_counter('global_5m',150,5*MINUTE_IN_SECONDS)) return rpg_v122_feedback_error('busy',429);

      $msg=trim(sanitize_textarea_field((string)($p['message']??'')));
      $length=mb_strlen($msg);
      if($length<4||$length>1200) return rpg_v122_feedback_error('invalid_message',400);
      if(substr_count(strtolower($msg),'http://')+substr_count(strtolower($msg),'https://')>3) return rpg_v122_feedback_error('spam',400);

      // Reject rapid exact duplicates from the same ephemeral client key.
      $dup_key='rpg_fb_dup_'.hash('sha256',$short.'|'.mb_strtolower($msg));
      if(get_transient($dup_key)) return rpg_v122_feedback_error('duplicate',409);

      $page=substr(sanitize_text_field((string)($p['page']??'')),0,255);
      $cat=substr(sanitize_key($p['category']??''),0,30);
      $ok=$wpdb->insert(
          $wpdb->prefix.'rpg_beta_feedback',
          array('created_at'=>current_time('mysql'),'page'=>$page,'category'=>$cat,'message'=>$msg,'status'=>'new'),
          array('%s','%s','%s','%s','%s')
      );
      if($ok===false) return rpg_v122_feedback_error('storage',500);

      set_transient($nonce_key,1,15*MINUTE_IN_SECONDS);
      set_transient($dup_key,1,15*MINUTE_IN_SECONDS);
      return new WP_REST_Response(array('ok'=>true),202);
   }
 ));
});


/* V120 – Beta feedback retention: automatic deletion after 12 months. */
function rpg_v120_cleanup_beta_feedback(){
    global $wpdb;
    $table=$wpdb->prefix.'rpg_beta_feedback';
    $cutoff=date('Y-m-d H:i:s', current_time('timestamp') - (365 * DAY_IN_SECONDS));
    $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE created_at < %s",$cutoff));
}
function rpg_v120_schedule_cleanup(){
    if(!wp_next_scheduled('rpg_v120_daily_privacy_cleanup')){
        wp_schedule_event(time()+HOUR_IN_SECONDS,'daily','rpg_v120_daily_privacy_cleanup');
    }
}
add_action('init','rpg_v120_schedule_cleanup',20);
add_action('rpg_v120_daily_privacy_cleanup','rpg_v120_cleanup_beta_feedback');

/* Keep retention visible in WP Admin without adding a public-facing tracker UI. */
add_action('admin_notices',function(){
    if(!current_user_can('manage_options')) return;
    if(get_option('rpg_v120_privacy_notice_seen')==='1') return;
    echo '<div class="notice notice-info is-dismissible"><p><strong>RuhrpottGuru v120:</strong> Beta-Feedback wird automatisch nach 12 Monaten gelöscht. Die First-Party-Analytics bleiben aggregiert und verwenden keine persistente Besucher-ID.</p></div>';
    update_option('rpg_v120_privacy_notice_seen','1',false);
});



/**
 * V121 – Persistent RuhrpottGuru Feedback Inbox
 *
 * The feedback channel survives the beta. The frontend may be renamed later,
 * but the storage/admin workflow remains the central first-party inbox.
 */
function rpg_v121_feedback_schema_upgrade(){
    global $wpdb;
    if(get_option('rpg_v121_feedback_schema')==='1') return;
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    $t=$wpdb->prefix.'rpg_beta_feedback';
    $cc=$wpdb->get_charset_collate();
    dbDelta("CREATE TABLE $t (
      id bigint unsigned NOT NULL AUTO_INCREMENT,
      created_at datetime NOT NULL,
      page varchar(255) NOT NULL DEFAULT '',
      category varchar(30) NOT NULL DEFAULT '',
      message text NOT NULL,
      status varchar(20) NOT NULL DEFAULT 'new',
      resolved_at datetime DEFAULT NULL,
      PRIMARY KEY(id),
      KEY created_at(created_at),
      KEY status(status)
    ) $cc;");
    update_option('rpg_v121_feedback_schema','1',false);
}
add_action('init','rpg_v121_feedback_schema_upgrade',4);

function rpg_v121_feedback_menu(){
    global $wpdb;
    $t=$wpdb->prefix.'rpg_beta_feedback';
    $new_count=0;
    if($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s",$t))===$t){
        $new_count=(int)$wpdb->get_var("SELECT COUNT(*) FROM $t WHERE status='new'");
    }
    $bubble=$new_count>0 ? ' <span class="awaiting-mod count-'.$new_count.'"><span class="pending-count">'.min($new_count,99).'</span></span>' : '';
    add_menu_page(
        'PottFuchs Feedback',
        'RPG Feedback'.$bubble,
        'manage_options',
        'rpg-feedback',
        'rpg_v121_render_feedback_admin',
        'dashicons-format-chat',
        58
    );
}
add_action('admin_menu','rpg_v121_feedback_menu');

function rpg_v121_feedback_admin_action(){
    if(!current_user_can('manage_options')) wp_die('Keine Berechtigung.');
    check_admin_referer('rpg_feedback_action');
    global $wpdb;
    $t=$wpdb->prefix.'rpg_beta_feedback';
    $id=absint($_POST['feedback_id'] ?? 0);
    $action=sanitize_key($_POST['feedback_action'] ?? '');
    if(!$id) return;

    if($action==='delete'){
        $wpdb->delete($t,array('id'=>$id),array('%d'));
    } elseif(in_array($action,array('new','in_progress','done'),true)){
        $data=array('status'=>$action);
        $format=array('%s');
        if($action==='done'){
            $data['resolved_at']=current_time('mysql');
            $format[]='%s';
        } else {
            $data['resolved_at']=null;
            $format[]='%s';
        }
        $wpdb->update($t,$data,array('id'=>$id),$format,array('%d'));
    }
    $redirect=add_query_arg(array(
        'page'=>'rpg-feedback',
        'status'=>sanitize_key($_POST['return_status'] ?? 'all'),
        'updated'=>'1'
    ),admin_url('admin.php'));
    wp_safe_redirect($redirect);
    exit;
}
add_action('admin_post_rpg_feedback_action','rpg_v121_feedback_admin_action');

function rpg_v121_feedback_status_label($status){
    $map=array(
        'new'=>'Neu',
        'in_progress'=>'In Bearbeitung',
        'done'=>'Erledigt'
    );
    return $map[$status] ?? $status;
}

function rpg_v121_render_feedback_admin(){
    if(!current_user_can('manage_options')) return;
    global $wpdb;
    $t=$wpdb->prefix.'rpg_beta_feedback';
    $status=sanitize_key($_GET['status'] ?? 'all');
    if(!in_array($status,array('all','new','in_progress','done'),true)) $status='all';
    $search=sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
    $paged=max(1,absint($_GET['paged'] ?? 1));
    $per_page=30;
    $offset=($paged-1)*$per_page;

    $where=array('1=1');
    $args=array();
    if($status!=='all'){
        $where[]='status=%s';
        $args[]=$status;
    }
    if($search!==''){
        $like='%'.$wpdb->esc_like($search).'%';
        $where[]='(message LIKE %s OR page LIKE %s OR category LIKE %s)';
        array_push($args,$like,$like,$like);
    }
    $where_sql=implode(' AND ',$where);

    $count_sql="SELECT COUNT(*) FROM $t WHERE $where_sql";
    $rows_sql="SELECT * FROM $t WHERE $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
    $count_args=$args;
    $rows_args=array_merge($args,array($per_page,$offset));
    $total=(int)($count_args ? $wpdb->get_var($wpdb->prepare($count_sql,$count_args)) : $wpdb->get_var($count_sql));
    $rows=$wpdb->get_results($wpdb->prepare($rows_sql,$rows_args));

    $counts=array();
    foreach(array('new','in_progress','done') as $st){
        $counts[$st]=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE status=%s",$st));
    }
    $counts['all']=array_sum($counts);

    echo '<div class="wrap"><h1>PottFuchs Feedback</h1>';
    echo '<p>Hier landet das direkte Feedback aus dem Guide. Das System bleibt auch nach der Beta bestehen. Feedback wird gemäß der aktuellen Datenschutzregel nach 12 Monaten automatisch gelöscht.</p>';
    if(!empty($_GET['updated'])) echo '<div class="notice notice-success is-dismissible"><p>Feedback aktualisiert.</p></div>';

    echo '<ul class="subsubsub">';
    $labels=array('all'=>'Alle','new'=>'Neu','in_progress'=>'In Bearbeitung','done'=>'Erledigt');
    $parts=array();
    foreach($labels as $key=>$label){
        $url=add_query_arg(array('page'=>'rpg-feedback','status'=>$key),admin_url('admin.php'));
        $cls=$status===$key?' class="current"':'';
        $parts[]='<li><a'.$cls.' href="'.esc_url($url).'">'.esc_html($label).' <span class="count">('.intval($counts[$key]).')</span></a></li>';
    }
    echo implode(' | ',$parts);
    echo '</ul>';

    echo '<form method="get" style="margin:12px 0 14px;clear:both">';
    echo '<input type="hidden" name="page" value="rpg-feedback"><input type="hidden" name="status" value="'.esc_attr($status).'">';
    echo '<p class="search-box"><label class="screen-reader-text" for="rpg-feedback-search">Feedback durchsuchen:</label>';
    echo '<input type="search" id="rpg-feedback-search" name="s" value="'.esc_attr($search).'"> ';
    submit_button('Feedback durchsuchen','',false,false,array('id'=>'search-submit'));
    echo '</p></form>';

    echo '<style>
      .rpgfb-grid{display:grid;gap:12px;clear:both}
      .rpgfb-card{background:#fff;border:1px solid #dcdcde;border-left:4px solid #72aee6;border-radius:4px;padding:14px 16px}
      .rpgfb-card.is-done{border-left-color:#00a32a}.rpgfb-card.is-in_progress{border-left-color:#dba617}
      .rpgfb-meta{display:flex;flex-wrap:wrap;gap:8px 16px;color:#646970;font-size:12px;margin-bottom:8px}
      .rpgfb-message{font-size:14px;line-height:1.55;white-space:pre-wrap;margin:8px 0 12px}
      .rpgfb-actions{display:flex;gap:7px;flex-wrap:wrap;align-items:center}
      .rpgfb-actions form{margin:0}.rpgfb-delete{color:#b32d2e!important}
      .rpgfb-status{display:inline-block;padding:2px 7px;border-radius:999px;background:#f0f0f1;font-weight:600}
      @media(max-width:782px){.rpgfb-card{padding:12px}.rpgfb-actions .button{min-height:40px}}
    </style>';

    if(!$rows){
        echo '<div class="notice notice-info inline"><p>Für diese Auswahl gibt es aktuell kein Feedback.</p></div>';
    } else {
        echo '<div class="rpgfb-grid">';
        foreach($rows as $row){
            $row_status=in_array($row->status,array('new','in_progress','done'),true)?$row->status:'new';
            echo '<article class="rpgfb-card is-'.esc_attr($row_status).'">';
            echo '<div class="rpgfb-meta">';
            echo '<span><strong>#'.intval($row->id).'</strong></span>';
            echo '<span>'.esc_html(mysql2date('d.m.Y H:i',$row->created_at)).'</span>';
            echo '<span class="rpgfb-status">'.esc_html(rpg_v121_feedback_status_label($row_status)).'</span>';
            if($row->category!=='') echo '<span>Kategorie: <strong>'.esc_html($row->category).'</strong></span>';
            if($row->page!=='') echo '<span>Seite: <code>'.esc_html($row->page).'</code></span>';
            echo '</div>';
            echo '<div class="rpgfb-message">'.esc_html($row->message).'</div>';
            echo '<div class="rpgfb-actions">';

            foreach(array('new'=>'Als neu','in_progress'=>'In Bearbeitung','done'=>'Erledigt') as $act=>$label){
                if($row_status===$act) continue;
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
                wp_nonce_field('rpg_feedback_action');
                echo '<input type="hidden" name="action" value="rpg_feedback_action">';
                echo '<input type="hidden" name="feedback_id" value="'.intval($row->id).'">';
                echo '<input type="hidden" name="feedback_action" value="'.esc_attr($act).'">';
                echo '<input type="hidden" name="return_status" value="'.esc_attr($status).'">';
                echo '<button class="button button-small" type="submit">'.esc_html($label).'</button></form>';
            }
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return confirm(\'Dieses Feedback wirklich löschen?\');">';
            wp_nonce_field('rpg_feedback_action');
            echo '<input type="hidden" name="action" value="rpg_feedback_action">';
            echo '<input type="hidden" name="feedback_id" value="'.intval($row->id).'">';
            echo '<input type="hidden" name="feedback_action" value="delete">';
            echo '<input type="hidden" name="return_status" value="'.esc_attr($status).'">';
            echo '<button class="button button-small rpgfb-delete" type="submit">Löschen</button></form>';

            echo '</div></article>';
        }
        echo '</div>';
    }

    $pages=max(1,(int)ceil($total/$per_page));
    if($pages>1){
        echo '<div class="tablenav"><div class="tablenav-pages" style="margin:16px 0">';
        echo paginate_links(array(
            'base'=>add_query_arg(array('page'=>'rpg-feedback','status'=>$status,'s'=>$search,'paged'=>'%#%'),admin_url('admin.php')),
            'format'=>'',
            'current'=>$paged,
            'total'=>$pages,
            'prev_text'=>'‹',
            'next_text'=>'›'
        ));
        echo '</div></div>';
    }
    echo '</div>';
}



/**
 * V167 – echte globale Suche. /suche/ ist eine virtuelle React-Seite und
 * benötigt keine manuell angelegte WordPress-Seite.
 */
function ruhrpott_guru_global_search_rewrite_v167() {
    add_rewrite_rule('^suche/?$', 'index.php?rg_global_search=1', 'top');
}
add_action('init', 'ruhrpott_guru_global_search_rewrite_v167', 2);
function ruhrpott_guru_global_search_query_var_v167($vars){ $vars[]='rg_global_search'; return $vars; }
add_filter('query_vars','ruhrpott_guru_global_search_query_var_v167');
function ruhrpott_guru_global_search_template_v167($template){
    if(get_query_var('rg_global_search')){
        $custom=get_template_directory().'/search-global.php';
        if(file_exists($custom)) return $custom;
    }
    return $template;
}
add_filter('template_include','ruhrpott_guru_global_search_template_v167',99);
function ruhrpott_guru_global_search_flush_v167(){
    if(get_option('ruhrpott_guru_search_rewrite_v167')==='1') return;
    ruhrpott_guru_global_search_rewrite_v167(); flush_rewrite_rules(false);
    update_option('ruhrpott_guru_search_rewrite_v167','1',false);
}
add_action('init','ruhrpott_guru_global_search_flush_v167',40);

/**
 * V219 – branded sender name for all WordPress mails.
 * Keep the configured sender email address unchanged; only replace the
 * default display name (e.g. "WordPress") with the RuhrpottGuru brand.
 */
function ruhrpott_guru_mail_from_name_v219($name) {
    return 'Ruhrpottguru';
}
add_filter('wp_mail_from_name', 'ruhrpott_guru_mail_from_name_v219', 999);


/*
 * V230 – First-party Analytics Dashboard
 * Aggregate traffic only: no cookie, no visitor/session ID, no raw IP, no GPS.
 */
function rpg_v230_analytics_schema(){
    global $wpdb;
    $t=$wpdb->prefix.'rpg_analytics_traffic_daily';
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    $cc=$wpdb->get_charset_collate();
    dbDelta("CREATE TABLE $t (
      id bigint unsigned NOT NULL AUTO_INCREMENT,
      day date NOT NULL,
      page_path varchar(255) NOT NULL DEFAULT '/',
      path_hash char(40) NOT NULL,
      page_title varchar(190) NOT NULL DEFAULT '',
      page_type varchar(32) NOT NULL DEFAULT 'page',
      device varchar(16) NOT NULL DEFAULT 'unknown',
      view_count bigint unsigned NOT NULL DEFAULT 0,
      visit_count bigint unsigned NOT NULL DEFAULT 0,
      PRIMARY KEY (id),
      UNIQUE KEY traffic (day,path_hash,device),
      KEY day (day),
      KEY device (device)
    ) $cc;");
    update_option('rpg_v230_analytics_schema','1',false);
}
function rpg_v230_maybe_schema(){if(get_option('rpg_v230_analytics_schema')!=='1')rpg_v230_analytics_schema();}
add_action('init','rpg_v230_maybe_schema',3);

/* V237: extend the aggregate traffic table with privacy-preserving visit/session counts. */
function rpg_v237_analytics_schema_upgrade(){
    if(get_option('rpg_v237_analytics_visits_schema')==='1') return;
    // Re-running dbDelta is intentional: rpg_v230_analytics_schema() now also
    // contains visit_count and upgrades existing installations in place.
    rpg_v230_analytics_schema();
    update_option('rpg_v237_analytics_visits_schema','1',false);
}
add_action('init','rpg_v237_analytics_schema_upgrade',4);

function rpg_v230_increment_traffic($path,$title,$type,$device){
    global $wpdb;
    $t=$wpdb->prefix.'rpg_analytics_traffic_daily';
    $path='/'.ltrim((string)wp_parse_url((string)$path,PHP_URL_PATH),'/');
    if($path==='')$path='/';
    $path=mb_substr(sanitize_text_field($path),0,255);
    $title=mb_substr(sanitize_text_field($title),0,190);
    $types=array('home','category','search','post','page','other');
    if(!in_array($type,$types,true))$type='page';
    $devices=array('mobile','tablet','desktop','unknown');
    if(!in_array($device,$devices,true))$device='unknown';
    $day=current_time('Y-m-d');
    $hash=sha1($path.'|'.$type);
    $sql="INSERT INTO $t (day,page_path,path_hash,page_title,page_type,device,view_count) VALUES (%s,%s,%s,%s,%s,%s,1)
          ON DUPLICATE KEY UPDATE view_count=view_count+1,page_title=VALUES(page_title),page_path=VALUES(page_path),page_type=VALUES(page_type)";
    $wpdb->query($wpdb->prepare($sql,$day,$path,$hash,$title,$type,$device));
}

function rpg_v237_increment_visit($path,$title,$type,$device){
    global $wpdb;
    $t=$wpdb->prefix.'rpg_analytics_traffic_daily';
    $path='/'.ltrim((string)wp_parse_url((string)$path,PHP_URL_PATH),'/');
    if($path==='')$path='/';
    $path=mb_substr(sanitize_text_field($path),0,255);
    $title=mb_substr(sanitize_text_field($title),0,190);
    $types=array('home','category','search','post','page','other');
    if(!in_array($type,$types,true))$type='page';
    $devices=array('mobile','tablet','desktop','unknown');
    if(!in_array($device,$devices,true))$device='unknown';
    $day=current_time('Y-m-d');
    $hash=sha1($path.'|'.$type);
    $sql="INSERT INTO $t (day,page_path,path_hash,page_title,page_type,device,view_count,visit_count) VALUES (%s,%s,%s,%s,%s,%s,0,1)
          ON DUPLICATE KEY UPDATE visit_count=visit_count+1,page_title=VALUES(page_title),page_path=VALUES(page_path),page_type=VALUES(page_type)";
    $wpdb->query($wpdb->prepare($sql,$day,$path,$hash,$title,$type,$device));
}

function rpg_v230_analytics_menu(){
    add_submenu_page('rp-redaktion','RP Redaktion · Analytics','Analytics','manage_options','rp-analytics','rpg_v230_render_analytics_admin');
}
add_action('admin_menu','rpg_v230_analytics_menu',30);

function rpg_v230_metric_label($event){
    $labels=array(
      'category_view'=>'Kategorie aufgerufen','card_open'=>'Detail geöffnet','filter_change'=>'Filter geändert','radius_change'=>'Radius geändert',
      'burger_open'=>'Burger-Menü geöffnet','burger_menu_click'=>'Klick im Burger-Menü',
      'route_click'=>'Route','website_click'=>'Website','reservation_click'=>'Reservierung','ticket_click'=>'Tickets'
    );
    return $labels[$event]??$event;
}
function rpg_v230_category_label($cat){
    $labels=array('events'=>'Events','food'=>'Food','highlights'=>'Highlights','culture'=>'Kultur','kultur'=>'Kultur','activities'=>'Aktivitäten','aktiv'=>'Aktivitäten','buedchen-radar'=>'Büdchen Radar','route'=>'Route Industriekultur');
    return $labels[$cat]??($cat?:'Ohne Kategorie');
}
function rpg_v230_render_analytics_admin(){
    if(!current_user_can('manage_options'))wp_die('Keine Berechtigung.');
    global $wpdb;
    $traffic=$wpdb->prefix.'rpg_analytics_traffic_daily';
    $events=$wpdb->prefix.'rpg_analytics_daily';
    $days=isset($_GET['days'])?absint($_GET['days']):1;
    if(!in_array($days,array(1,7,30,90,365),true))$days=1;
    $from=date('Y-m-d',current_time('timestamp')-(($days-1)*DAY_IN_SECONDS));
    $today=current_time('Y-m-d');

    $visits=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(visit_count),0) FROM $traffic WHERE day >= %s",$from));
    $visits_today=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(visit_count),0) FROM $traffic WHERE day=%s",$today));
    $views=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(view_count),0) FROM $traffic WHERE day >= %s",$from));
    $views_today=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(view_count),0) FROM $traffic WHERE day=%s",$today));
    $pages_per_visit=$visits>0?round($views/$visits,2):0;
    $interactions=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(accepted_count),0) FROM $events WHERE day >= %s AND metric_class='product'",$from));
    $outbound=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(accepted_count),0) FROM $events WHERE day >= %s AND metric_class='outbound'",$from));
    $detail=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(accepted_count),0) FROM $events WHERE day >= %s AND event_type='card_open'",$from));
    $burger_opens=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(accepted_count),0) FROM $events WHERE day >= %s AND event_type='burger_open'",$from));

    $daily=(array)$wpdb->get_results($wpdb->prepare("SELECT day,SUM(view_count) views,SUM(visit_count) visits FROM $traffic WHERE day >= %s GROUP BY day ORDER BY day",$from),ARRAY_A);
    $devices=(array)$wpdb->get_results($wpdb->prepare("SELECT device,SUM(visit_count) n FROM $traffic WHERE day >= %s GROUP BY device HAVING n>0 ORDER BY n DESC",$from),ARRAY_A);
    $pages=(array)$wpdb->get_results($wpdb->prepare("SELECT page_path,MAX(page_title) page_title,MAX(page_type) page_type,SUM(view_count) n FROM $traffic WHERE day >= %s GROUP BY page_path ORDER BY n DESC LIMIT 15",$from),ARRAY_A);
    $landings=(array)$wpdb->get_results($wpdb->prepare("SELECT page_path,MAX(page_title) page_title,MAX(page_type) page_type,SUM(visit_count) n FROM $traffic WHERE day >= %s GROUP BY page_path HAVING n>0 ORDER BY n DESC LIMIT 15",$from),ARRAY_A);
    $cats=(array)$wpdb->get_results($wpdb->prepare("SELECT category,SUM(accepted_count) n FROM $events WHERE day >= %s AND event_type IN ('category_view','card_open') GROUP BY category ORDER BY n DESC",$from),ARRAY_A);
    $areas=(array)$wpdb->get_results($wpdb->prepare("SELECT area,SUM(accepted_count) n FROM $events WHERE day >= %s AND area<>'' GROUP BY area ORDER BY n DESC LIMIT 12",$from),ARRAY_A);
    $actions=(array)$wpdb->get_results($wpdb->prepare("SELECT event_type,SUM(accepted_count) n FROM $events WHERE day >= %s GROUP BY event_type ORDER BY n DESC",$from),ARRAY_A);
    $radii=(array)$wpdb->get_results($wpdb->prepare("SELECT radius_bucket,SUM(accepted_count) n FROM $events WHERE day >= %s AND radius_bucket<>'' GROUP BY radius_bucket ORDER BY n DESC",$from),ARRAY_A);
    $burger=(array)$wpdb->get_results($wpdb->prepare("SELECT content_id,SUM(accepted_count) n FROM $events WHERE day >= %s AND event_type='burger_menu_click' AND content_id<>'' GROUP BY content_id ORDER BY n DESC",$from),ARRAY_A);
    $burger_clicks=0;foreach($burger as $r)$burger_clicks+=(int)$r['n'];

    $series=array();$cursor=strtotime($from);$end=strtotime($today);$by=array();foreach($daily as $r)$by[$r['day']]=array('views'=>(int)$r['views'],'visits'=>(int)$r['visits']);
    while($cursor<=$end){$d=date('Y-m-d',$cursor);$series[]=array('day'=>$d,'views'=>$by[$d]['views']??0,'visits'=>$by[$d]['visits']??0);$cursor+=DAY_IN_SECONDS;}

    echo '<div class="wrap rpg-analytics"><h1>PottFuchs Analytics</h1><p><a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=rp-analytics')).'">Nutzung</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=rp-marketing-tracking')).'">Marketing Tracking</a></p>';
    echo '<p>First-Party-Nutzungsdaten ohne Analytics-Cookie, Browser-Speicher, persistente Besucher-ID oder GPS-Speicherung. <strong>Visits</strong> zählen neue Einstiege in PottFuchs. Interne Navigation, Reloads und Zurück/Vorwärts-Navigation erzeugen keinen neuen Visit; Seitenaufrufe werden weiterhin als Page Impressions gezählt. Frühere Pageviews werden nicht künstlich in Visits umgerechnet.</p>';
    echo '<div class="rpg-range">Zeitraum: ';foreach(array(1=>'Heute',7=>'7 Tage',30=>'30 Tage',90=>'90 Tage',365=>'365 Tage') as $d=>$label){$cls=$days===$d?'button button-primary':'button';echo '<a class="'.esc_attr($cls).'" href="'.esc_url(admin_url('admin.php?page=rp-analytics&days='.$d)).'">'.esc_html($label).'</a> ';}echo '</div>';
    echo '<div class="rpg-kpis">';
    $cards=array(
      array('Visits',$visits,$visits_today.' heute'),
      array('Seitenaufrufe',$views,$views_today.' heute'),
      array('Seiten / Visit',$pages_per_visit,'Ø im Zeitraum'),
      array('Detail-Views',$detail,'geöffnete Cards'),
      array('Interaktionen',$interactions,'inkl. Menü/Filter/Kategorien'),
      array('Outbound',$outbound,'Website/Route/Reservierung/Tickets')
    );
    foreach($cards as $c)echo '<div class="rpg-kpi"><span>'.esc_html($c[0]).'</span><strong>'.esc_html(is_float($c[1])?number_format_i18n($c[1],2):number_format_i18n($c[1])).'</strong><small>'.esc_html($c[2]).'</small></div>';
    echo '</div>';

    $render_series=function($title,$field,$class)use($series,$from,$today){$max=1;foreach($series as $r)$max=max($max,(int)$r[$field]);echo '<div class="rpg-panel rpg-wide"><h2>'.esc_html($title).'</h2><div class="rpg-bars">';foreach($series as $r){$n=(int)$r[$field];$h=max(2,round(($n/$max)*160));echo '<div class="rpg-bar-wrap" title="'.esc_attr(date_i18n('d.m.Y',strtotime($r['day'])).': '.number_format_i18n($n)).'"><div class="rpg-bar '.esc_attr($class).'" style="height:'.intval($h).'px"></div></div>';}echo '</div><div class="rpg-axis"><span>'.esc_html(date_i18n('d.m.',strtotime($from))).'</span><span>'.esc_html(date_i18n('d.m.',strtotime($today))).'</span></div></div>';};
    echo '<div class="rpg-chart-grid">';$render_series('Visits · Verlauf','visits','rpg-bar-visits');$render_series('Seitenaufrufe · Verlauf','views','rpg-bar-views');echo '</div>';

    echo '<div class="rpg-grid">';
    $render=function($title,$rows,$labelFn=null){echo '<div class="rpg-panel"><h2>'.esc_html($title).'</h2><div class="rpg-ranked">';$top=0;foreach($rows as $r){$n=(int)$r['n'];$top=max($top,$n);}foreach($rows as $r){$n=(int)$r['n'];$key='';foreach($r as $k=>$v){if($k!=='n'){$key=$v;break;}}$label=$labelFn?call_user_func($labelFn,$key,$r):(string)$key;$pct=$top?round($n/$top*100):0;echo '<div class="rpg-rank"><div><span>'.esc_html($label).'</span><strong>'.number_format_i18n($n).'</strong></div><i><b style="width:'.intval($pct).'%"></b></i></div>';}if(!$rows)echo '<p>Noch keine Daten in diesem Zeitraum.</p>';echo '</div></div>';};
    $render('Geräte · Visits',$devices,function($v){return array('mobile'=>'Mobile','tablet'=>'Tablet','desktop'=>'Desktop','unknown'=>'Unbekannt')[$v]??$v;});
    $render('Kategorien',$cats,function($v){return rpg_v230_category_label($v);});
    $render('Orte / Standortwahl',$areas);
    $render('Radien',$radii);
    echo '</div>';

    echo '<div class="rpg-panel rpg-wide"><h2>Burger-Menü</h2><div class="rpg-burger-summary"><div><span>Menü geöffnet</span><strong>'.number_format_i18n($burger_opens).'</strong></div><div><span>Klicks auf Menüpunkte</span><strong>'.number_format_i18n($burger_clicks).'</strong></div></div><table class="widefat striped"><thead><tr><th>Menüpunkt</th><th style="text-align:right">Klicks</th><th style="text-align:right">Anteil</th></tr></thead><tbody>';
    if($burger){foreach($burger as $r){$n=(int)$r['n'];$pct=$burger_clicks?round(($n/$burger_clicks)*100,1):0;echo '<tr><td><strong>'.esc_html($r['content_id']).'</strong></td><td style="text-align:right"><strong>'.number_format_i18n($n).'</strong></td><td style="text-align:right">'.esc_html(number_format_i18n($pct,1)).' %</td></tr>';}}else echo '<tr><td colspan="3">Noch keine Burger-Menü-Klicks seit v237.</td></tr>';
    echo '</tbody></table></div>';

    echo '<div class="rpg-grid">';
    echo '<div class="rpg-panel"><h2>Top-Einstiege · Visits</h2><table class="widefat striped"><thead><tr><th>Landingpage</th><th style="text-align:right">Visits</th></tr></thead><tbody>';if($landings){foreach($landings as $r){$label=$r['page_title']?:$r['page_path'];echo '<tr><td><strong>'.esc_html($label).'</strong><br><code>'.esc_html($r['page_path']).'</code></td><td style="text-align:right"><strong>'.number_format_i18n((int)$r['n']).'</strong></td></tr>';}}else echo '<tr><td colspan="2">Visits werden erst ab v237 erfasst.</td></tr>';echo '</tbody></table></div>';
    echo '<div class="rpg-panel"><h2>Top-Seiten · Seitenaufrufe</h2><table class="widefat striped"><thead><tr><th>Seite</th><th style="text-align:right">Aufrufe</th></tr></thead><tbody>';if($pages){foreach($pages as $r){$label=$r['page_title']?:$r['page_path'];echo '<tr><td><strong>'.esc_html($label).'</strong><br><code>'.esc_html($r['page_path']).'</code></td><td style="text-align:right"><strong>'.number_format_i18n((int)$r['n']).'</strong></td></tr>';}}else echo '<tr><td colspan="2">Noch keine Pageview-Daten.</td></tr>';echo '</tbody></table></div>';
    echo '</div>';

    echo '<div class="rpg-panel rpg-wide"><h2>Aktionen</h2><table class="widefat striped"><thead><tr><th>Aktion</th><th style="text-align:right">Anzahl</th></tr></thead><tbody>';foreach($actions as $r)echo '<tr><td>'.esc_html(rpg_v230_metric_label($r['event_type'])).'</td><td style="text-align:right"><strong>'.number_format_i18n((int)$r['n']).'</strong></td></tr>';if(!$actions)echo '<tr><td colspan="2">Noch keine Daten.</td></tr>';echo '</tbody></table></div>';

    echo '<style>
    .rpg-analytics{max-width:1320px}.rpg-range{margin:18px 0}.rpg-kpis{display:grid;grid-template-columns:repeat(3,minmax(150px,1fr));gap:14px;margin:18px 0}.rpg-kpi,.rpg-panel{background:#fff;border:1px solid #dcdcde;border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.03)}.rpg-kpi{padding:18px}.rpg-kpi span,.rpg-kpi small{display:block;color:#646970}.rpg-kpi strong{display:block;font-size:30px;line-height:1.15;margin:7px 0;color:#1d2327}.rpg-grid,.rpg-chart-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.rpg-panel{padding:18px;margin:16px 0}.rpg-panel h2{margin-top:0}.rpg-bars{height:170px;display:flex;align-items:flex-end;gap:3px;border-bottom:1px solid #dcdcde}.rpg-bar-wrap{flex:1;min-width:2px;height:100%;display:flex;align-items:flex-end}.rpg-bar{width:100%;border-radius:3px 3px 0 0;min-height:2px}.rpg-bar-visits{background:#1d2327}.rpg-bar-views{background:#f36f21}.rpg-axis{display:flex;justify-content:space-between;color:#646970;font-size:12px;margin-top:6px}.rpg-rank{margin:13px 0}.rpg-rank>div{display:flex;justify-content:space-between;gap:10px;margin-bottom:5px}.rpg-rank i{display:block;height:7px;background:#f0f0f1;border-radius:999px;overflow:hidden}.rpg-rank b{display:block;height:100%;background:#f36f21;border-radius:999px}.rpg-panel code{font-size:11px}.rpg-wide{width:auto}.rpg-burger-summary{display:grid;grid-template-columns:repeat(2,minmax(0,220px));gap:12px;margin:0 0 16px}.rpg-burger-summary>div{padding:12px 14px;border-radius:10px;background:#f6f7f7}.rpg-burger-summary span,.rpg-burger-summary strong{display:block}.rpg-burger-summary span{color:#646970}.rpg-burger-summary strong{font-size:24px;margin-top:3px}@media(max-width:1000px){.rpg-kpis{grid-template-columns:repeat(2,1fr)}.rpg-grid,.rpg-chart-grid{grid-template-columns:1fr}}@media(max-width:600px){.rpg-kpis{grid-template-columns:1fr}.rpg-burger-summary{grid-template-columns:1fr}}
    </style>';
    echo '</div>';
}


/*
 * V231 – Marketing Tracking Dashboard
 * Aggregate acquisition + conversion attribution. No cookies, visitor IDs,
 * raw IPs, full referrer URLs, click IDs or search terms are stored.
 */
function rpg_v231_marketing_schema(){
    global $wpdb;
    $t=$wpdb->prefix.'rpg_marketing_daily';
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    $cc=$wpdb->get_charset_collate();
    dbDelta("CREATE TABLE $t (
      id bigint unsigned NOT NULL AUTO_INCREMENT,
      day date NOT NULL,
      event_type varchar(40) NOT NULL,
      dimension_hash char(40) NOT NULL,
      source varchar(100) NOT NULL DEFAULT '',
      medium varchar(60) NOT NULL DEFAULT '',
      campaign varchar(120) NOT NULL DEFAULT '',
      content varchar(120) NOT NULL DEFAULT '',
      channel varchar(40) NOT NULL DEFAULT '',
      referrer_domain varchar(120) NOT NULL DEFAULT '',
      landing_path varchar(255) NOT NULL DEFAULT '/',
      device varchar(16) NOT NULL DEFAULT 'unknown',
      event_count bigint unsigned NOT NULL DEFAULT 0,
      PRIMARY KEY (id),
      UNIQUE KEY marketing_metric (day,event_type,dimension_hash),
      KEY day (day),
      KEY channel (channel),
      KEY campaign (campaign)
    ) $cc;");
    update_option('rpg_v231_marketing_schema','1',false);
}
function rpg_v231_maybe_schema(){if(get_option('rpg_v231_marketing_schema')!=='1')rpg_v231_marketing_schema();}
add_action('init','rpg_v231_maybe_schema',4);

function rpg_v231_marketing_clean($v,$max){
    $v=wp_strip_all_tags((string)$v);
    $v=preg_replace('/[\x00-\x1F\x7F]/u','',$v);
    return mb_substr(trim($v),0,$max);
}
function rpg_v231_marketing_payload($p){
    $has=false;
    foreach(array('mk_source','mk_medium','mk_campaign','mk_content','mk_channel','mk_referrer','mk_landing_path') as $k){if(isset($p[$k]) && (string)$p[$k]!==''){$has=true;break;}}
    if(!$has)return null;
    $device=sanitize_key((string)($p['mk_device']??'unknown'));
    if(!in_array($device,array('mobile','tablet','desktop','unknown'),true))$device='unknown';
    $path='/'.ltrim((string)wp_parse_url((string)($p['mk_landing_path']??'/'),PHP_URL_PATH),'/');
    if($path==='')$path='/';
    return array(
      'source'=>rpg_v231_marketing_clean($p['mk_source']??'',100),
      'medium'=>rpg_v231_marketing_clean($p['mk_medium']??'',60),
      'campaign'=>rpg_v231_marketing_clean($p['mk_campaign']??'',120),
      'content'=>rpg_v231_marketing_clean($p['mk_content']??'',120),
      'channel'=>rpg_v231_marketing_clean($p['mk_channel']??'',40),
      'referrer_domain'=>preg_replace('/[^a-z0-9.\-]/i','',rpg_v231_marketing_clean($p['mk_referrer']??'',120)),
      'landing_path'=>mb_substr(sanitize_text_field($path),0,255),
      'device'=>$device
    );
}
function rpg_v231_increment_marketing($event,$m){
    if(!$m || !is_array($m))return;
    global $wpdb;
    $t=$wpdb->prefix.'rpg_marketing_daily';
    $allowed=array('landing','page_view','category_view','card_open','route_click','website_click','reservation_click','ticket_click');
    if(!in_array($event,$allowed,true))return;
    $day=current_time('Y-m-d');
    $hash=sha1(implode('|',array($m['source'],$m['medium'],$m['campaign'],$m['content'],$m['channel'],$m['referrer_domain'],$m['landing_path'],$m['device'])));
    $sql="INSERT INTO $t (day,event_type,dimension_hash,source,medium,campaign,content,channel,referrer_domain,landing_path,device,event_count)
          VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,1)
          ON DUPLICATE KEY UPDATE event_count=event_count+1";
    $wpdb->query($wpdb->prepare($sql,$day,$event,$hash,$m['source'],$m['medium'],$m['campaign'],$m['content'],$m['channel'],$m['referrer_domain'],$m['landing_path'],$m['device']));
}
function rpg_v231_marketing_menu(){
    add_submenu_page('rp-redaktion','RP Redaktion · Marketing Tracking','Marketing Tracking','manage_options','rp-marketing-tracking','rpg_v231_render_marketing_admin');
}
add_action('admin_menu','rpg_v231_marketing_menu',31);

function rpg_v231_channel_label($v){return $v?:'Unbekannt';}
function rpg_v231_event_label($v){
    $m=array('card_open'=>'Detail geöffnet','route_click'=>'Route','website_click'=>'Website','reservation_click'=>'Reservierung','ticket_click'=>'Tickets','category_view'=>'Kategorie aufgerufen','page_view'=>'Seitenaufruf','landing'=>'Landing');
    return $m[$v]??$v;
}
function rpg_v231_render_marketing_admin(){
    if(!current_user_can('manage_options'))wp_die('Keine Berechtigung.');
    global $wpdb;
    $t=$wpdb->prefix.'rpg_marketing_daily';
    $days=isset($_GET['days'])?absint($_GET['days']):30;
    if(!in_array($days,array(1,7,30,90,365),true))$days=30;
    $from=date('Y-m-d',current_time('timestamp')-(($days-1)*DAY_IN_SECONDS));
    $today=current_time('Y-m-d');
    $outEvents="'route_click','website_click','reservation_click','ticket_click'";

    $landings=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(event_count),0) FROM $t WHERE day >= %s AND event_type='landing'",$from));
    $pageviews=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(event_count),0) FROM $t WHERE day >= %s AND event_type='page_view'",$from));
    $details=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(event_count),0) FROM $t WHERE day >= %s AND event_type='card_open'",$from));
    $outbound=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(event_count),0) FROM $t WHERE day >= %s AND event_type IN ($outEvents)",$from));
    $campaignLandings=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(event_count),0) FROM $t WHERE day >= %s AND event_type='landing' AND campaign<>''",$from));
    $cvr=$landings?round(($outbound/$landings)*100,1):0;

    $daily=(array)$wpdb->get_results($wpdb->prepare("SELECT day,SUM(event_count) n FROM $t WHERE day >= %s AND event_type='landing' GROUP BY day ORDER BY day",$from),ARRAY_A);
    $channels=(array)$wpdb->get_results($wpdb->prepare("SELECT channel,SUM(event_count) n FROM $t WHERE day >= %s AND event_type='landing' GROUP BY channel ORDER BY n DESC",$from),ARRAY_A);
    $sources=(array)$wpdb->get_results($wpdb->prepare("SELECT CONCAT(source,' / ',medium) label,SUM(event_count) n FROM $t WHERE day >= %s AND event_type='landing' GROUP BY source,medium ORDER BY n DESC LIMIT 15",$from),ARRAY_A);
    $landingPages=(array)$wpdb->get_results($wpdb->prepare("SELECT landing_path label,SUM(event_count) n FROM $t WHERE day >= %s AND event_type='landing' GROUP BY landing_path ORDER BY n DESC LIMIT 15",$from),ARRAY_A);
    $devices=(array)$wpdb->get_results($wpdb->prepare("SELECT device,SUM(event_count) n FROM $t WHERE day >= %s AND event_type='landing' GROUP BY device ORDER BY n DESC",$from),ARRAY_A);
    $conversions=(array)$wpdb->get_results($wpdb->prepare("SELECT event_type,SUM(event_count) n FROM $t WHERE day >= %s AND event_type IN ('card_open','route_click','website_click','reservation_click','ticket_click') GROUP BY event_type ORDER BY n DESC",$from),ARRAY_A);
    $campaigns=(array)$wpdb->get_results($wpdb->prepare("SELECT campaign,source,medium,
      SUM(CASE WHEN event_type='landing' THEN event_count ELSE 0 END) landings,
      SUM(CASE WHEN event_type='page_view' THEN event_count ELSE 0 END) views,
      SUM(CASE WHEN event_type='card_open' THEN event_count ELSE 0 END) details,
      SUM(CASE WHEN event_type IN ($outEvents) THEN event_count ELSE 0 END) conversions
      FROM $t WHERE day >= %s AND campaign<>'' GROUP BY campaign,source,medium ORDER BY landings DESC,conversions DESC LIMIT 20",$from),ARRAY_A);

    $series=array();$by=array();foreach($daily as $r)$by[$r['day']]=(int)$r['n'];$cursor=strtotime($from);$end=strtotime($today);$max=1;
    while($cursor<=$end){$d=date('Y-m-d',$cursor);$n=$by[$d]??0;$series[]=array('day'=>$d,'n'=>$n);$max=max($max,$n);$cursor+=DAY_IN_SECONDS;}

    echo '<div class="wrap rpg-analytics"><h1>PottFuchs Marketing Tracking</h1>';
    echo '<p><a class="button" href="'.esc_url(admin_url('admin.php?page=rp-analytics')).'">Nutzung</a> <a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=rp-marketing-tracking')).'">Marketing Tracking</a></p>';
    echo '<p>Aggregierte Herkunft und Conversion-Zuordnung ohne Analytics-Cookies, Browser-Speicher oder Besucher-ID. Referrer werden nur als Domain gespeichert; Click-IDs und Suchbegriffe werden nicht persistiert.</p>';
    echo '<div class="rpg-range">Zeitraum: ';foreach(array(1=>'Heute',7=>'7 Tage',30=>'30 Tage',90=>'90 Tage',365=>'365 Tage') as $d=>$label){$cls=$days===$d?'button button-primary':'button';echo '<a class="'.esc_attr($cls).'" href="'.esc_url(admin_url('admin.php?page=rp-marketing-tracking&days='.$d)).'">'.esc_html($label).'</a> ';}echo '</div>';
    echo '<div class="rpg-kpis">';
    $cards=array(array('Landings',$landings,'neue Einstiege mit Herkunft'),array('Marketing-Views',$pageviews,'zugeordnete Seitenaufrufe'),array('Kampagnen-Landings',$campaignLandings,'mit utm_campaign'),array('Detail-Views',$details,'aus Marketing-Herkunft'),array('Outbound',$outbound,'Route/Website/Reservierung/Tickets'),array('Conversion Rate',number_format_i18n($cvr,1).' %','Outbound / Landings'));
    foreach($cards as $c)echo '<div class="rpg-kpi"><span>'.esc_html($c[0]).'</span><strong>'.(is_numeric($c[1])?number_format_i18n($c[1]):esc_html($c[1])).'</strong><small>'.esc_html($c[2]).'</small></div>';
    echo '</div>';

    echo '<div class="rpg-panel rpg-wide"><h2>Landings · Verlauf</h2><div class="rpg-bars">';foreach($series as $r){$h=max(2,round(($r['n']/$max)*160));echo '<div class="rpg-bar-wrap" title="'.esc_attr(date_i18n('d.m.Y',strtotime($r['day'])).': '.number_format_i18n($r['n'])).'"><div class="rpg-bar" style="height:'.intval($h).'px"></div></div>';}echo '</div><div class="rpg-axis"><span>'.esc_html(date_i18n('d.m.',strtotime($from))).'</span><span>'.esc_html(date_i18n('d.m.',strtotime($today))).'</span></div></div>';

    $render=function($title,$rows,$labelFn=null){echo '<div class="rpg-panel"><h2>'.esc_html($title).'</h2><div class="rpg-ranked">';$top=0;foreach($rows as $r)$top=max($top,(int)$r['n']);foreach($rows as $r){$n=(int)$r['n'];$key='';foreach($r as $k=>$v){if($k!=='n'){$key=$v;break;}}$label=$labelFn?call_user_func($labelFn,$key,$r):(string)$key;$pct=$top?round($n/$top*100):0;echo '<div class="rpg-rank"><div><span>'.esc_html($label).'</span><strong>'.number_format_i18n($n).'</strong></div><i><b style="width:'.intval($pct).'%"></b></i></div>';}if(!$rows)echo '<p>Noch keine Daten in diesem Zeitraum.</p>';echo '</div></div>';};
    echo '<div class="rpg-grid">';
    $render('Kanäle',$channels,function($v){return rpg_v231_channel_label($v);});
    $render('Quellen / Medium',$sources);
    $render('Landingpages',$landingPages);
    $render('Geräte',$devices,function($v){return array('mobile'=>'Mobile','tablet'=>'Tablet','desktop'=>'Desktop','unknown'=>'Unbekannt')[$v]??$v;});
    $render('Conversions & Intent',$conversions,function($v){return rpg_v231_event_label($v);});
    echo '</div>';

    echo '<div class="rpg-panel rpg-wide"><h2>Kampagnen</h2><table class="widefat striped"><thead><tr><th>Kampagne</th><th>Quelle / Medium</th><th style="text-align:right">Landings</th><th style="text-align:right">Views</th><th style="text-align:right">Details</th><th style="text-align:right">Outbound</th><th style="text-align:right">CVR</th></tr></thead><tbody>';
    if($campaigns){foreach($campaigns as $r){$l=(int)$r['landings'];$cv=(int)$r['conversions'];$rate=$l?round($cv/$l*100,1):0;echo '<tr><td><strong>'.esc_html($r['campaign']).'</strong></td><td>'.esc_html($r['source'].' / '.$r['medium']).'</td><td style="text-align:right">'.number_format_i18n($l).'</td><td style="text-align:right">'.number_format_i18n((int)$r['views']).'</td><td style="text-align:right">'.number_format_i18n((int)$r['details']).'</td><td style="text-align:right">'.number_format_i18n($cv).'</td><td style="text-align:right"><strong>'.number_format_i18n($rate,1).' %</strong></td></tr>';}}else echo '<tr><td colspan="7">Noch keine UTM-Kampagnen erfasst. Beispiel: ?utm_source=instagram&amp;utm_medium=social&amp;utm_campaign=launch</td></tr>';
    echo '</tbody></table></div>';
    echo '<div class="rpg-panel rpg-wide"><h2>Tracking-Konvention</h2><p>Für Instagram & Co. verwende Links mit <code>utm_source</code>, <code>utm_medium</code>, <code>utm_campaign</code> und optional <code>utm_content</code>. <code>utm_term</code> wird aus Datenschutzgründen nicht gespeichert.</p></div>';
    echo '<style>
    .rpg-analytics{max-width:1320px}.rpg-range{margin:18px 0}.rpg-kpis{display:grid;grid-template-columns:repeat(6,minmax(145px,1fr));gap:14px;margin:18px 0}.rpg-kpi,.rpg-panel{background:#fff;border:1px solid #dcdcde;border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.03)}.rpg-kpi{padding:18px}.rpg-kpi span,.rpg-kpi small{display:block;color:#646970}.rpg-kpi strong{display:block;font-size:28px;line-height:1.15;margin:7px 0;color:#1d2327}.rpg-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.rpg-panel{padding:18px;margin:16px 0}.rpg-panel h2{margin-top:0}.rpg-bars{height:170px;display:flex;align-items:flex-end;gap:3px;border-bottom:1px solid #dcdcde}.rpg-bar-wrap{flex:1;min-width:2px;height:100%;display:flex;align-items:flex-end}.rpg-bar{width:100%;background:#f36f21;border-radius:3px 3px 0 0;min-height:2px}.rpg-axis{display:flex;justify-content:space-between;color:#646970;font-size:12px;margin-top:6px}.rpg-rank{margin:13px 0}.rpg-rank>div{display:flex;justify-content:space-between;gap:10px;margin-bottom:5px}.rpg-rank i{display:block;height:7px;background:#f0f0f1;border-radius:999px;overflow:hidden}.rpg-rank b{display:block;height:100%;background:#f36f21;border-radius:999px}.rpg-panel code{font-size:11px}.rpg-wide{width:auto}@media(max-width:1100px){.rpg-kpis{grid-template-columns:repeat(3,1fr)}}@media(max-width:800px){.rpg-kpis{grid-template-columns:repeat(2,1fr)}.rpg-grid{grid-template-columns:1fr}}@media(max-width:600px){.rpg-kpis{grid-template-columns:1fr}}
    </style>';
    echo '</div>';
}
