<?php
/**
 * Main Template File - Ruhrpott Guru 1:1 WordPress Theme
 *
 * @package Ruhrpott_Guru
 */

get_header();
?>

<main class="flex-1 max-w-7xl w-full mx-auto px-2.5 sm:px-4 py-2 sm:py-3.5">

    <!-- Hero Welcome Banner -->
    <?php if (is_home() && !is_search()): ?>
    <div class="relative overflow-hidden p-4 sm:p-5 rounded-2xl border border-amber-500/30 bg-gradient-to-r from-slate-900 via-slate-900 to-amber-950/40 text-white shadow-xl mb-4">
        <div class="max-w-2xl relative z-10">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-bold uppercase tracking-wider mb-3">
                <i data-lucide="sparkles" class="w-3.5 h-3.5"></i> Glückauf im Revier
            </span>
            <h1 class="font-display text-2xl sm:text-4xl font-extrabold tracking-tight">
                Dein digitaler Guru für das <span class="text-amber-500">Ruhrgebiet</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-300 mt-2 leading-relaxed">
                Entdecke Zechen, Konzerte, Kulturtipps & die besten Currywurst-Imbisse im Revier. Mit smarter Standortsuche & interaktiven Guides.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Search Header Indicator -->
    <?php if (is_search()): ?>
    <div class="mb-6 p-4 rounded-2xl bg-slate-900 border border-amber-500/30 flex items-center justify-between">
        <div>
            <h2 class="text-base font-bold text-white flex items-center gap-2 font-display">
                <i data-lucide="compass" class="w-5 h-5 text-amber-500"></i>
                Suchergebnisse für: <span class="text-amber-400">"<?php echo get_search_query(); ?>"</span>
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">Suche in allen Kategorien im Umkreis von 15 km um Essen.</p>
        </div>
        <a href="<?php echo esc_url(home_url('/')); ?>" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs text-slate-300 font-bold transition-all">
            Suche zurücksetzen
        </a>
    </div>
    <?php endif; ?>

    <!-- Interactive Ruhrpott Guru React App Container (Live OpenData, Radius, Search, Filters, Map) -->
    <div class="mb-8">
        <?php echo do_shortcode('[ruhrpott_app category="Alle"]'); ?>
    </div>

    <!-- Traditional WP Posts Grid (Optional SEO / Native WP Posts Fallback) -->
    <?php if (have_posts() && !is_front_page()): ?>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 border-b pb-3 mb-6 border-slate-800">
        <div class="flex flex-col gap-1.5">
            <div class="flex items-center gap-3 flex-wrap">
                <h2 class="text-xl font-bold font-display text-white flex items-center gap-2">
                    <i data-lucide="compass" class="w-5 h-5 text-amber-500"></i>
                    <span>
                        <?php 
                        if (is_search()) {
                            echo 'Suchergebnisse für "' . esc_html(get_search_query()) . '"';
                        } else {
                            echo 'Weitere Beiträge & Artikel';
                        }
                        ?>
                    </span>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-900/40 text-amber-300 border border-amber-700/50">
                        <?php echo $wp_query->found_posts; ?> Treffer
                    </span>
                </h2>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Loop Grid of Posts / Angebote -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (have_posts()): ?>
            <?php while (have_posts()): the_post(); ?>
                <article class="bg-slate-900 border border-slate-800 hover:border-amber-500/50 rounded-2xl overflow-hidden shadow-lg hover:shadow-2xl hover:shadow-amber-500/10 transition-all group flex flex-col">
                    
                    <!-- Thumbnail -->
                    <div class="relative h-48 bg-slate-800 overflow-hidden">
                        <?php if (has_post_thumbnail()): ?>
                            <?php the_post_thumbnail('medium_large', array('class' => 'w-full h-full object-cover group-hover:scale-105 transition-transform duration-500')); ?>
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center bg-slate-800 text-slate-600">
                                <i data-lucide="image" class="w-10 h-10"></i>
                            </div>
                        <?php endif; ?>

                        <!-- Category Badge -->
                        <div class="absolute top-3 left-3 px-2.5 py-1 rounded-full bg-slate-950/80 backdrop-blur-md border border-slate-700/80 text-[11px] font-bold text-amber-400">
                            <?php 
                            $terms = get_the_terms(get_the_ID(), 'ruhrpott_kategorie');
                            if ($terms && !is_wp_error($terms)) {
                                echo esc_html($terms[0]->name);
                            } else {
                                echo 'Empfehlung';
                            }
                            ?>
                        </div>

                        <!-- Favorite Heart Badge -->
                        <button type="button" class="btn-toggle-fav absolute top-3 right-3 p-2 rounded-full bg-slate-950/80 backdrop-blur-md border border-slate-700/80 text-slate-400 hover:text-red-500 transition-colors" data-id="<?php the_ID(); ?>">
                            <i data-lucide="heart" class="w-4 h-4"></i>
                        </button>
                    </div>

                    <!-- Article Body -->
                    <div class="p-5 flex-1 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center gap-1.5 text-xs text-slate-400 mb-2">
                                <i data-lucide="map-pin" class="w-3.5 h-3.5 text-amber-500"></i>
                                <span>
                                    <?php 
                                    $staedte = get_the_terms(get_the_ID(), 'ruhrpott_stadt');
                                    if ($staedte && !is_wp_error($staedte)) {
                                        echo esc_html($staedte[0]->name);
                                    } else {
                                        echo 'Ruhrgebiet';
                                    }
                                    ?>
                                </span>
                            </div>

                            <h3 class="font-display text-lg font-bold text-white group-hover:text-amber-400 transition-colors mb-2">
                                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                            </h3>

                            <p class="text-xs text-slate-300 line-clamp-3 leading-relaxed mb-4">
                                <?php echo wp_trim_words(get_the_excerpt(), 22, '...'); ?>
                            </p>
                        </div>

                        <!-- Read More Action -->
                        <div class="pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs">
                            <a href="<?php the_permalink(); ?>" class="text-amber-500 font-bold group-hover:translate-x-1 transition-transform flex items-center gap-1">
                                Details & Infos <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                            </a>
                        </div>
                    </div>

                </article>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-span-full py-16 text-center bg-slate-900 border border-slate-800 rounded-3xl p-8">
                <i data-lucide="search-x" class="w-12 h-12 text-amber-500/60 mx-auto mb-3"></i>
                <h3 class="font-display text-lg font-bold text-white">Keine Einträge gefunden</h3>
                <p class="text-xs text-slate-400 max-w-md mx-auto mt-1">
                    Leider gibt es für deine Suche derzeit keine passenden Orte oder Angebote. Versuche es mit einem anderen Begriff oder wähle eine Stadt.
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <div class="mt-8 flex justify-center">
        <?php
        the_posts_pagination(array(
            'mid_size'  => 2,
            'prev_text' => __('&laquo; Zurück', 'ruhrpott-guru'),
            'next_text' => __('Weiter &raquo;', 'ruhrpott-guru'),
            'class'     => 'flex gap-2 text-xs font-bold',
        ));
        ?>
    </div>

</main>

<?php get_footer(); ?>
