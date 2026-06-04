<?php
$file = 'resources/js/Pages/Admin/JobPosts/JobListing.jsx';
$content = file_get_contents($file);

// Fix handleEditInputChange broken syntax
$oldBlock = "        if (name === 'openings') {
            const numVal = parseInt(value) || '';
            if (numVal !== '' && numVal < 1) return;
            setEditForm(prev => ({ ...prev, [name]: numVal }));
            return;
        }));
            return;
        }
        setEditForm(prev => ({ ...prev, [name]: value }));
    };";

$newBlock = "        if (name === 'openings') {
            const numVal = parseInt(value) || '';
            if (numVal !== '' && numVal < 1) return;
            setEditForm(prev => ({ ...prev, [name]: numVal }));
            return;
        }
        setEditForm(prev => ({ ...prev, [name]: value }));
    };";

$content = str_replace($oldBlock, $newBlock, $content, $count1);
echo "Fixed handleEditInputChange: $count1 replacements\n";

// Fix handleCreateInputChange broken syntax
$oldBlock2 = "        if (name === 'openings') {
            const numVal = parseInt(value) || '';
            if (numVal !== '' && numVal < 1) return;
            setCreateJobForm(prev => ({ ...prev, [name]: numVal }));
            return;
        }));
            return;
        }
        setCreateJobForm(prev => ({ ...prev, [name]: value }));
    };";

$newBlock2 = "        if (name === 'openings') {
            const numVal = parseInt(value) || '';
            if (numVal !== '' && numVal < 1) return;
            setCreateJobForm(prev => ({ ...prev, [name]: numVal }));
            return;
        }
        setCreateJobForm(prev => ({ ...prev, [name]: value }));
    };";

$content = str_replace($oldBlock2, $newBlock2, $content, $count2);
echo "Fixed handleCreateInputChange: $count2 replacements\n";

file_put_contents($file, $content);
echo "Done!\n";
