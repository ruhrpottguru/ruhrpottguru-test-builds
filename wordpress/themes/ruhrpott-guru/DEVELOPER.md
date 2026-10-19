V104 contains V103 completely. Activities Home now combines Activity Intelligence with distance: candidates are selected by a hybrid utility score with diversity caps, then the six visible cards are ordered nearest-first. Places Backend remains 1.2.4.

# RuhrpottGuru – Source-owned React architecture (V103)

V96 continues the V95 source takeover and restores the proven V94 visual structures. V95 replaced the former Google AI Studio compiled React bundle with readable application source.


## V103 – Activity Intelligence / Visitor Utility

V103 contains V102 completely. The Activities home feed now keeps the curated backend order. Places Backend 1.2.4 filters pure harbour/marina and membership-only water-sport objects, retains direct public offers, and deduplicates near-identical venue records. Category pages stay distance sorted.

## V102 – Activities Local Coverage & Lifecycle

V102 contains V101 completely. No React component or user-facing UI element was changed. The functional update lives exclusively in Places Backend 1.2.3: local pools remain discoverable, action/experience spots are prioritized for discovery, category leakage is blocked, and known closed lifecycle states are filtered more strictly.

## V101 – Experience Intelligence Backend Update

V101 contains V100 completely. This release does not modify any React component, user-facing filter, chip, button or UI flow. The Activities Experience Intelligence change is implemented exclusively in the separate Places Backend 1.2.2 (SQL candidate filtering/prioritization plus PHP scoring).

## Runtime

WordPress supplies React/ReactDOM through its core `wp-element` package. No npm install is required to run the production theme.

Active application files:

- `src/react-app/runtime.js` – shared runtime, helpers, city centres
- `src/react-app/api.js` – REST transport + data normalization + media resolution
- `src/react-app/location.js` – GPS/manual location persistence and radius
- `src/react-app/components.js` – header, navigation, cards, detail overlay, location layer
- `src/react-app/views.js` – Home, category, Route Industriekultur and WP-page views
- `src/react-app/main.js` – single React mount/entry point
- `css/rpg-react-source.css` – source-owned UI styles

The historic WordPress script handle `ruhrpott-compiled-js` is retained only for compatibility with the Places Backend cache-reset hook. It now points to `src/react-app/main.js`.

## Data boundaries

The React app does not own or duplicate the catalog. It consumes same-origin WordPress REST APIs:

- Places: `/wp-json/ruhrpottguru/v1/places/home`
- Events: `/wp-json/ruhrpottguru/v1/events`
- Route Industriekultur: `/wp-json/ruhrpottguru/v1/places/industrial-route`
- POI media: `/wp-json/ruhrpottguru/v1/food-media`
- Event original media: `/wp-json/ruhrpottguru/v1/event-media`

Places Backend/MySQL therefore remains replaceable independently from the UI.

## Navigation model

Home and category cards call the same React `DetailOverlay` component. Card clicks never need a category-page navigation to show details. Category pages are still normal WordPress URLs for direct navigation, SEO and the sticky footer.

## WordPress ownership

WordPress still owns pages, Gutenberg shortcode placement, menus, Impressum/Datenschutz and editorial content. `front-page.php` and `page.php` expose their content to the React shell through hidden source nodes and root data attributes.

## Handover

A developer or another AI can work directly from this ZIP. There is no missing AI Studio project or source repository needed to understand or change the active React application.

## V97 – Editorial inserts on Home

The source-owned React Home can consume two server-rendered WordPress editorial sources from `front-page.php`:

- `#ruhrpott-home-halden-source` is inserted immediately before the Highlights live module.
- `#ruhrpott-home-interview-source` is inserted immediately before the Culture live module.

The article data remains in WordPress (`post` + featured image + permalink). React only controls placement, so a developer or another AI can change the Home composition without recovering any AI Studio source.
