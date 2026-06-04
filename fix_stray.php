<?php
$file = 'resources/js/Pages/Admin/JobPosts/JobListing.jsx';
$content = file_get_contents($file);

// Fix the exact broken line - "        }));" standalone
$lines = explode("\n", $content);
$fixed = 0;
foreach ($lines as $i => $line) {
    if (trim($line) === '}));') {
        // Check the previous line and the next line to understand context
        $prev = isset($lines[$i-1]) ? $lines[$i-1] : '';
        $next = isset($lines[$i+1]) ? $lines[$i+1] : '';
        // If previous line ends with `,` and next is `}` (after return), this is broken syntax
        if (strpos($prev, 'setEditForm') !== false || strpos($prev, 'setCreateJobForm') !== false) {
            // Remove this stray line
            $lines[$i] = '';
            $fixed++;
        }
    }
}

$content = implode("\n", $lines);
file_put_contents($file, $content);
echo "Removed $fixed stray lines\n";
