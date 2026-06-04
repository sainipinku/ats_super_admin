<?php
$file = 'resources/js/Pages/Admin/JobPosts/JobListing.jsx';
$content = file_get_contents($file);

// Find and fix the broken handleEditInputChange
$edit_search = "        if (name === 'openings') {
            const numVal = parseInt(value) || '';
            if (numVal !== '' && numVal < 1) return;
            setEditForm(prev => ({ ...prev, [name]: numVal }));
            return;
        }));
            return;
        }
        setEditForm(prev => ({ ...prev, [name]: value }));
    };";

$edit_replace = "        if (name === 'openings') {
            const numVal = parseInt(value) || '';
            if (numVal !== '' && numVal < 1) return;
            setEditForm(prev => ({ ...prev, [name]: numVal }));
            return;
        }
        setEditForm(prev => ({ ...prev, [name]: value }));
    };";

if (strpos($content, $edit_search) !== false) {
    $content = str_replace($edit_search, $edit_replace, $content);
    echo "Fixed handleEditInputChange\n";
} else {
    echo "handleEditInputChange pattern not found\n";
}

// Find and fix the broken handleCreateInputChange
$create_search = "        if (name === 'openings') {
            const numVal = parseInt(value) || '';
            if (numVal !== '' && numVal < 1) return;
            setCreateJobForm(prev => ({ ...prev, [name]: numVal }));
            return;
        }));
            return;
        }
        setCreateJobForm(prev => ({ ...prev, [name]: value }));
    };";

$create_replace = "        if (name === 'openings') {
            const numVal = parseInt(value) || '';
            if (numVal !== '' && numVal < 1) return;
            setCreateJobForm(prev => ({ ...prev, [name]: numVal }));
            return;
        }
        setCreateJobForm(prev => ({ ...prev, [name]: value }));
    };";

if (strpos($content, $create_search) !== false) {
    $content = str_replace($create_search, $create_replace, $content);
    echo "Fixed handleCreateInputChange\n";
} else {
    echo "handleCreateInputChange pattern not found\n";
}

file_put_contents($file, $content);

// Check for any remaining
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    if (strpos($line, '}));') !== false && (strpos($line, 'setEditForm') !== false || strpos($line, 'setCreateJobForm') !== false)) {
        echo "Still broken at line " . ($i+1) . ": " . trim($line) . "\n";
    }
}
echo "Done!\n";
