<?php
if (!defined('ABSPATH')) { exit; }
status_header(404);
nocache_headers();
get_header();
?>
<main id="primary" class="site-main rg-wp-page-shell">
  <article class="rg-wp-page-article" style="max-width:760px;margin:0 auto;padding:48px 20px 72px">
    <h1 class="rg-wp-page-title">Diese Seite gibt’s hier nicht</h1>
    <p>Der Link ist möglicherweise veraltet oder die Adresse wurde falsch eingegeben.</p>
    <p><a href="<?php echo esc_url(home_url('/')); ?>">Zur Startseite</a> · <a href="<?php echo esc_url(home_url('/suche/')); ?>">PottFuchs durchsuchen</a></p>
    <nav aria-label="Hauptkategorien"><a href="<?php echo esc_url(home_url('/veranstaltungen/')); ?>">Events</a> · <a href="<?php echo esc_url(home_url('/food/')); ?>">Food</a> · <a href="<?php echo esc_url(home_url('/highlights/')); ?>">Highlights</a> · <a href="<?php echo esc_url(home_url('/kultur/')); ?>">Kultur</a> · <a href="<?php echo esc_url(home_url('/aktiv/')); ?>">Aktiv</a></nav>
  </article>
</main>
<?php get_footer();
