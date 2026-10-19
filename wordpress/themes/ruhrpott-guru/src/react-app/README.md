# React app source

The active UI is intentionally shipped as readable browser JavaScript and uses WordPress core `wp.element` (React + ReactDOM). There is no minified application bundle and no hidden build step.

The files are loaded in this order by `functions.php`:

1. runtime.js
2. api.js
3. location.js
4. components.js
5. views.js
6. main.js

This makes the deployed WordPress ZIP itself a complete handover artifact.
