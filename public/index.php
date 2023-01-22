<?php
// Check if vendor folder exist
if (file_exists(__DIR__ . '/../vendor')) {
    (require __DIR__ . '/../src/start.php')->run();
} else {
    readfile(__DIR__ . '/../resources/views/install.html');
}
