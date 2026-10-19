<?php
/**
 * Single Post Template - Ruhrpott Guru
 *
 * @package Ruhrpott_Guru
 */

get_header();
?>

<main class="flex-1 max-w-4xl w-full mx-auto px-4 py-8">
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <article class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-2xl p-6 sm:p-8 text-slate-100">
            
            <a href="<?php echo esc_url(home_url('/')); ?>" class="inline-flex items-center gap-2 text-xs font-bold text-amber-500 hover:text-amber-400 mb-6 transition-colors">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Zurück zur Übersicht
            </a>

            <header class="mb-6">
                <h1 class="font-display text-2xl sm:text-4xl font-extrabold text-white mb-3">
                    <?php the_title(); ?>
                </h1>
                <div class="flex items-center gap-4 text-xs text-slate-400">
                    <span class="flex items-center gap-1">
                        <i data-lucide="calendar" class="w-3.5 h-3.5 text-amber-500"></i>
                        <?php echo get_the_date(); ?>
                    </span>
                    <span class="flex items-center gap-1">
                        <i data-lucide="user" class="w-3.5 h-3.5 text-amber-500"></i>
                        <?php the_author(); ?>
                    </span>
                </div>
            </header>

            <?php if (has_post_thumbnail()): ?>
                <div class="mb-8 rounded-2xl overflow-hidden max-h-[450px]">
                    <?php the_post_thumbnail('full', array('class' => 'w-full h-full object-cover')); ?>
                </div>
            <?php endif; ?>

            <div class="prose prose-invert prose-amber max-w-none text-slate-200 text-sm leading-relaxed space-y-4">
                <?php the_content(); ?>
            </div>

        </article>
    <?php endwhile; endif; ?>
</main>

<?php get_footer(); ?>
