<?php
// Shared list of Umrah page stylesheets served by css/bundle.php.
// Paths are relative to the css/ root. Single source of truth — the page
// uses this list to compute a cache-busting version from filemtimes.
return [
    'general/modal-styles.css',
    'umrah/umrah-enhanced.css',
    'document-upload.css',
    'passport-photo-extractor.css',
];
