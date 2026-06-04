<?php
$content = file_get_contents('resources/js/Pages/Admin/JobPosts/JobListing.jsx');
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    if (trim($line) === '}));') {
        echo "Line " . ($i+1) . ": '" . trim($line) . "'\n";
        echo "  Prev: '" . trim($lines[$i-1]) . "'\n";
        echo "  Next: '" . trim($lines[$i+1]) . "'\n";
        echo "  Prev2: '" . trim($lines[$i-2]) . "'\n";
    }
}
