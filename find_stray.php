<?php
$content = file_get_contents('resources/js/Pages/Admin/JobPosts/JobListing.jsx');
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    if (strpos($line, '}));') !== false) {
        echo ($i+1) . ": " . trim($line) . "\n";
    }
}
