<?php
// Entry point for shared hosts where the document root can't be pointed at
// public/ (upload the whole project as-is to your web folder). This just
// delegates to the real front controller; the accompanying root .htaccess
// routes every other request (assets, login.php, pretty URLs, etc.) into
// public/ the same way. If your host lets you set the document root
// directly to public/, do that instead - this file/the root .htaccess
// aren't needed in that case.
chdir(__DIR__ . '/public');
require __DIR__ . '/public/index.php';
