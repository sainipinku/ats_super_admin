/**
 * Move + rename the 5 colliding construction models to root App\Models.
 * Construction\ActivityLog -> App\Models\ConstructionActivityLog
 * Construction\Document    -> App\Models\ConstructionDocument
 * Construction\Equipment   -> App\Models\ConstructionEquipment
 * Construction\Role        -> App\Models\ConstructionRole
 * Construction\Vehicle     -> App\Models\ConstructionVehicle
 * Then delete app/Models/Construction/ and update ALL references.
 */
const fs = require('fs');
const path = require('path');

const BASE = __dirname;
const MODELS_DIR = path.join(BASE, 'app', 'Models');
const CONSTRUCTION_DIR = path.join(MODELS_DIR, 'Construction');

// old short name -> new full class name
const RENAMES = {
    ActivityLog: 'ConstructionActivityLog',
    Document:    'ConstructionDocument',
    Equipment:   'ConstructionEquipment',
    Role:        'ConstructionRole',
    Vehicle:     'ConstructionVehicle',
};

// ---------------------------------------------------------------
// Step 1: Move + rename the 5 model files (namespace + class name)
// ---------------------------------------------------------------
for (const [oldName, newName] of Object.entries(RENAMES)) {
    const src = path.join(CONSTRUCTION_DIR, oldName + '.php');
    const dst = path.join(MODELS_DIR, newName + '.php');
    if (!fs.existsSync(src)) { console.log('SKIP: source missing ' + src); continue; }
    if (fs.existsSync(dst)) { console.log('SKIP: destination exists ' + dst); continue; }
    fs.copyFileSync(src, dst);
    fs.unlinkSync(src);
    let c = fs.readFileSync(dst, 'utf8');
    c = c.replace('namespace App\\Models\\Construction;', 'namespace App\\Models;');
    c = c.replace('class ' + oldName + ' extends', 'class ' + newName + ' extends');
    fs.writeFileSync(dst, c);
    console.log(`Moved+Renamed: ${oldName}.php -> ${newName}.php`);
}

// ---------------------------------------------------------------
// Step 2: Replace FQCN references across the project
//   App\Models\Construction\X -> App\Models\ConstructionX
// ---------------------------------------------------------------
const scanDirs = ['app', 'routes', 'tests', 'database', 'config', 'bootstrap']
    .map(d => path.join(BASE, d))
    .filter(d => fs.existsSync(d));
const phpFiles = [];
function walk(dir) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        if (entry.isDirectory()) {
            if (!['vendor', 'node_modules', 'storage'].includes(entry.name)) walk(path.join(dir, entry.name));
        } else if (entry.name.endsWith('.php')) {
            phpFiles.push(path.join(dir, entry.name));
        }
    }
}
scanDirs.forEach(walk);

let replacedCount = 0;
const fqcnFiles = [];
for (const fp of phpFiles) {
    const orig = fs.readFileSync(fp, 'utf8');
    let content = orig;
    for (const [oldName, newName] of Object.entries(RENAMES)) {
        content = content.split(`App\\Models\\Construction\\${oldName}`).join(`App\\Models\\${newName}`);
    }
    if (content !== orig) {
        const cnt = (orig.match(/App\\Models\\Construction\\(ActivityLog|Document|Equipment|Role|Vehicle)/g) || []).length;
        replacedCount += cnt;
        fs.writeFileSync(fp, content);
        fqcnFiles.push(path.relative(BASE, fp));
        console.log(`FQCN updated (${cnt}): ${path.relative(BASE, fp)}`);
    }
}

// ---------------------------------------------------------------
// Step 3: Rename short-name class usages in files that import
//         an App\Models\ConstructionX class.
//
//   Pattern A:  X::            (static call / ::class)
//   Pattern B:  new X          (constructor)
//   Pattern C:  X $var         (type hint, after space/param)
//   Pattern D:  ): X           (return type)
//
//   IMPORTANT: if a file imports BOTH the root App\Models\X AND
//   App\Models\ConstructionX, unqualified X refers to the root class,
//   so we SKIP renaming short usages for that name in that file.
// ---------------------------------------------------------------
let shortReplacedFiles = 0;
for (const fp of phpFiles) {
    let content = fs.readFileSync(fp, 'utf8');

    for (const [oldName, newName] of Object.entries(RENAMES)) {
        const constructionImport = `use App\\Models\\${newName};`;
        if (!content.includes(constructionImport)) continue;

        // If the file also imports the ROOT class, unqualified oldName
        // resolves to the root class - do not rename short usages.
        const rootImport = `use App\\Models\\${oldName};`;
        if (content.includes(rootImport)) {
            console.log(`  !! SKIP short-rename ${oldName} in ${path.relative(BASE, fp)} (imports both root and construction)`);
            continue;
        }

        const before = content;
        const patterns = [
            // A: oldName::  (not preceded by word/backslash)
            new RegExp('(?<![A-Za-z0-9_\\\\])' + oldName + '::', 'g'),
            // B: new oldName (word boundary after)
            new RegExp('(?<![A-Za-z0-9_\\\\])new ' + oldName + '\\b', 'g'),
            // C: oldName $var (type hint)
            new RegExp('(?<![A-Za-z0-9_\\\\])' + oldName + '\\s+\\$', 'g'),
            // D: ): oldName (return type)
            new RegExp('\\):\\s*' + oldName + '\\b', 'g'),
        ];
        for (const re of patterns) {
            content = content.replace(re, m => m.replace(oldName, newName));
        }
        if (content !== before) {
            console.log(`  Short-usages ${oldName}->${newName} in ${path.relative(BASE, fp)}`);
        }
    }

    if (content !== fs.readFileSync(fp, 'utf8')) {
        fs.writeFileSync(fp, content);
        shortReplacedFiles++;
    }
}

// ---------------------------------------------------------------
// Step 4: Delete empty Construction folder
// ---------------------------------------------------------------
if (fs.existsSync(CONSTRUCTION_DIR)) {
    const remaining = fs.readdirSync(CONSTRUCTION_DIR);
    if (remaining.length === 0) {
        fs.rmdirSync(CONSTRUCTION_DIR);
        console.log('Construction folder DELETED.');
    } else {
        console.log('Construction folder NOT deleted, still contains: ' + remaining.join(', '));
    }
} else {
    console.log('Construction folder already gone.');
}

// ---------------------------------------------------------------
// Step 5: Verify zero App\Models\Construction\ references remain
// ---------------------------------------------------------------
let remainingRefs = 0;
for (const fp of phpFiles) {
    const c = fs.readFileSync(fp, 'utf8');
    const matches = c.match(/App\\Models\\Construction\\\w+/g) || [];
    if (matches.length) {
        remainingRefs += matches.length;
        console.log('  REMAINING REF: ' + path.relative(BASE, fp) + ': ' + matches.join(', '));
    }
}

console.log('\nTotal FQCN references replaced: ' + replacedCount);
console.log('Files with short-usage updates: ' + shortReplacedFiles);
console.log('Remaining App\\Models\\Construction\\ refs: ' + remainingRefs);