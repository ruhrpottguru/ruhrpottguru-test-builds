<!-- MOBILE UNFOLDED BURGER MENU OVERLAY -->
<div id="mobile-menu-drawer" class="fixed inset-0 z-50 hidden flex-col animate-in fade-in duration-200">
    <style id="rpg-mobile-menu-panel-v343">
        /* V343: drawer overlays the site header on mobile and desktop so the panel header and close button are always visible. */
        html body #mobile-menu-drawer {
            z-index: 99999 !important;
        }
        @media (max-width: 767px) {
            html body #mobile-menu-drawer > .rpg-mobile-menu-panel {
                position: absolute !important;
                top: 0 !important;
                right: 0 !important;
                left: auto !important;
                width: min(82vw, 430px) !important;
                max-width: calc(100vw - 48px) !important;
                height: 100dvh !important;
                max-height: 100dvh !important;
                margin: 0 !important;
                align-self: auto !important;
                border-left: 1px solid #1e293b !important;
                border-bottom: 0 !important;
            }
            html body #mobile-menu-drawer .rpg-mobile-menu-panel .font-extrabold.text-sm {
                white-space: nowrap !important;
            }
        }
        @media (min-width: 768px) {
            html body #mobile-menu-drawer > .rpg-mobile-menu-panel {
                position: absolute !important;
                top: 0 !important;
                right: 0 !important;
                left: auto !important;
                width: min(640px, 96vw) !important;
                height: 100dvh !important;
                max-height: 100dvh !important;
                margin: 0 !important;
                border-left: 1px solid #1e293b;
                border-bottom: 0;
            }
        }
    </style>
    <!-- Backdrop -->
    <div id="mobile-menu-backdrop" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm"></div>

    <!-- Menu Panel -->
    <div class="rpg-mobile-menu-panel relative w-full border-b shadow-2xl flex flex-col z-10 transition-colors bg-slate-900 text-white border-slate-800 max-h-[90vh] overflow-y-auto">
        <!-- Panel Header -->
        <div class="px-4 py-3 border-b flex items-center justify-between bg-slate-950/80 border-slate-800">
            <div class="flex items-center gap-2">
                <i data-lucide="menu" class="w-4 h-4 text-amber-500"></i>
                <span class="font-bold text-xs uppercase tracking-wider font-display">Guru Hauptmenü</span>
            </div>
            <button
                type="button"
                id="btn-close-menu"
                class="p-1.5 rounded-xl transition-colors cursor-pointer hover:bg-slate-800 text-slate-300"
                aria-label="Menü schließen"
            >
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Menu Items List -->
        <div class="p-4 space-y-2 text-sm">
            <?php
            if (has_nav_menu('burger')) {
                wp_nav_menu(array(
                    'theme_location' => 'burger',
                    'container'      => false,
                    'menu_class'     => 'space-y-2',
                    'fallback_cb'    => false,
                    'walker'         => new Ruhrpott_Burger_Walker(),
                ));
            } else {
            ?>
            <!-- Default Fallback Burger Links -->
            <a
                href="<?php echo esc_url(home_url('/guides/')); ?>"
                class="w-full flex items-center justify-between p-3 rounded-2xl border transition-all text-left font-bold cursor-pointer bg-slate-800/80 hover:bg-slate-800 text-slate-100 border-slate-700"
            >
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-teal-500/20 text-teal-400">
                        <i data-lucide="book-open" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="font-extrabold text-sm">Städte-Blog</div>
                        <div class="text-[11px] font-normal text-slate-400">
                            Kulturtipps & Revier-Highlights
                        </div>
                    </div>
                </div>
            </a>

            <!-- 2. Datenschutz -->
            <a
                href="https://www.ruhrpottguru.de/datenschutzerklaerung/"
                class="w-full flex items-center justify-between p-3 rounded-2xl border transition-all text-left font-bold cursor-pointer bg-slate-800/80 hover:bg-slate-800 text-slate-100 border-slate-700"
            >
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-emerald-500/20 text-emerald-400">
                        <i data-lucide="shield-check" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="font-extrabold text-sm">Datenschutzerklärung</div>
                        <div class="text-[11px] font-normal text-slate-400">
                            Datenschutzerklärung & Rechterahmen
                        </div>
                    </div>
                </div>
            </a>

            <!-- 3. Impressum -->
            <a
                href="https://www.ruhrpottguru.de/impressum/"
                class="w-full flex items-center justify-between p-3 rounded-2xl border transition-all text-left font-bold cursor-pointer bg-slate-800/80 hover:bg-slate-800 text-slate-100 border-slate-700"
            >
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-indigo-500/20 text-indigo-400">
                        <i data-lucide="file-text" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="font-extrabold text-sm">Impressum</div>
                        <div class="text-[11px] font-normal text-slate-400">
                            Anbieterkennzeichnung & Kontakt
                        </div>
                    </div>
                </div>
            </a>

            <!-- 4. Cookie-Einstellungen (LETZTE POSITION) -->
            <button
                type="button"
                id="btn-menu-cookie-settings"
                class="w-full flex items-center justify-between p-3 rounded-2xl border transition-all text-left font-bold cursor-pointer bg-slate-800/80 hover:bg-slate-800 text-slate-100 border-slate-700"
            >
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-amber-500/20 text-amber-500">
                        <i data-lucide="cookie" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="font-extrabold text-sm">Cookie-Einstellungen</div>
                        <div class="text-[11px] font-normal text-slate-400">
                            Einwilligung & Präferenzen verwalten
                        </div>
                    </div>
                </div>
            </button>
            <?php } ?>

            <!-- Bottom Utility Bar: Merkzettel -->
            <div class="pt-3 border-t border-slate-800 flex items-center justify-between gap-2">
                <button
                    type="button"
                    id="btn-drawer-open-favorites"
                    class="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-xl border text-xs font-bold transition-all cursor-pointer bg-slate-800 hover:bg-slate-700 text-slate-200 border-slate-700"
                >
                    <i data-lucide="heart" class="w-4 h-4 text-slate-400"></i>
                    <span>Merkzettel (0)</span>
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$rg_footer_category_menu = function_exists('ruhrpott_guru_get_footer_category_menu_items')
    ? ruhrpott_guru_get_footer_category_menu_items()
    : array();
?>
<script type="application/json" id="ruhrpott-footer-menu-data"><?php
    echo wp_json_encode(
        $rg_footer_category_menu,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
?></script>

<?php if (shortcode_exists('surecookie_reconsent_button')) : ?>
<div id="rg-surecookie-reconsent-bridge" hidden>
    <?php echo do_shortcode('[surecookie_reconsent_button]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
<?php endif; ?>

<div class="rg-osm-attribution" role="contentinfo" aria-label="Datenquellen">
    Ortsdaten: <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">© OpenStreetMap contributors</a>
    · <a href="https://opendatacommons.org/licenses/odbl/1-0/" target="_blank" rel="noopener noreferrer">ODbL 1.0</a>
</div>

<?php wp_footer(); ?>
<script>
    if (window.lucide) {
        lucide.createIcons();
    }
</script>
</body>
</html>
