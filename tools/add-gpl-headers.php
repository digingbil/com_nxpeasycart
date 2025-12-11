<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  Tools
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Script to add GPL license headers to PHP files that don't have them.
 *
 * Usage: php tools/add-gpl-headers.php [--dry-run]
 */

$dryRun = in_array('--dry-run', $argv);

$baseDir = dirname(__DIR__);

// GPL header template - this is the standard Joomla-style docblock
$gplHeader = <<<'GPL'
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
GPL;

// Directories to process (relative to base)
$includeDirs = [
    'administrator/components/com_nxpeasycart',
    'components/com_nxpeasycart',
    'modules/mod_nxpeasycart_cart',
    'plugins/task/nxpeasycartcleanup',
];

// Directories to exclude
$excludeDirs = [
    'vendor',
    '.release-stage',
    'node_modules',
];

// Files to exclude
$excludeFiles = [
    '.php-cs-fixer.php',
];

/**
 * Check if a file already has a GPL-compatible license header
 */
function hasGplHeader(string $content): bool
{
    // Check for common GPL indicators in the first 1500 characters
    $header = substr($content, 0, 1500);

    $gplIndicators = [
        'GNU General Public License',
        '@license',
        'GPL',
        'General Public License',
    ];

    foreach ($gplIndicators as $indicator) {
        if (stripos($header, $indicator) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Add GPL header to a PHP file
 */
function addGplHeader(string $filePath, string $gplHeader, bool $dryRun): bool
{
    $content = file_get_contents($filePath);

    if ($content === false) {
        echo "  ERROR: Could not read file\n";
        return false;
    }

    // Check if already has GPL header
    if (hasGplHeader($content)) {
        return false; // Skip - already has header
    }

    // Find the position after <?php and any whitespace
    $phpTagPos = strpos($content, '<?php');
    if ($phpTagPos === false) {
        echo "  SKIP: No <?php tag found\n";
        return false;
    }

    // Find the end of the PHP opening tag line
    $afterPhpTag = $phpTagPos + 5; // Length of "<?php"

    // Skip any whitespace after <?php
    while ($afterPhpTag < strlen($content) && in_array($content[$afterPhpTag], [' ', "\t"])) {
        $afterPhpTag++;
    }

    // If there's a newline, include it
    if ($afterPhpTag < strlen($content) && $content[$afterPhpTag] === "\n") {
        $afterPhpTag++;
    } elseif ($afterPhpTag + 1 < strlen($content) && $content[$afterPhpTag] === "\r" && $content[$afterPhpTag + 1] === "\n") {
        $afterPhpTag += 2;
    }

    // Build the new content
    $beforePhp = substr($content, 0, $phpTagPos);
    $restContent = substr($content, $afterPhpTag);

    // Trim leading empty lines from rest content, but preserve one newline
    $restContent = ltrim($restContent, "\r\n");

    $newContent = $beforePhp . "<?php\n" . $gplHeader . "\n\n" . $restContent;

    if ($dryRun) {
        echo "  WOULD ADD header (dry-run)\n";
        return true;
    }

    $result = file_put_contents($filePath, $newContent);
    if ($result === false) {
        echo "  ERROR: Could not write file\n";
        return false;
    }

    echo "  ADDED header\n";
    return true;
}

/**
 * Recursively find PHP files
 */
function findPhpFiles(string $dir, array $excludeDirs, array $excludeFiles): array
{
    $files = [];

    if (!is_dir($dir)) {
        return $files;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        // Skip directories
        if ($file->isDir()) {
            continue;
        }

        // Only PHP files
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $filePath = $file->getPathname();
        $relativePath = $filePath;

        // Check excluded directories
        $skip = false;
        foreach ($excludeDirs as $excludeDir) {
            if (strpos($filePath, DIRECTORY_SEPARATOR . $excludeDir . DIRECTORY_SEPARATOR) !== false) {
                $skip = true;
                break;
            }
        }
        if ($skip) {
            continue;
        }

        // Check excluded files
        $filename = basename($filePath);
        if (in_array($filename, $excludeFiles)) {
            continue;
        }

        $files[] = $filePath;
    }

    sort($files);
    return $files;
}

// Main execution
echo "==============================================\n";
echo "  NXP Easy Cart - GPL Header Addition Tool\n";
echo "==============================================\n\n";

if ($dryRun) {
    echo "*** DRY RUN MODE - No files will be modified ***\n\n";
}

$totalFiles = 0;
$modifiedFiles = 0;
$skippedFiles = 0;

foreach ($includeDirs as $dir) {
    $fullDir = $baseDir . '/' . $dir;

    if (!is_dir($fullDir)) {
        echo "Directory not found: $dir\n";
        continue;
    }

    echo "Processing: $dir\n";
    echo str_repeat('-', 50) . "\n";

    $files = findPhpFiles($fullDir, $excludeDirs, $excludeFiles);

    foreach ($files as $file) {
        $totalFiles++;
        $relativePath = str_replace($baseDir . '/', '', $file);
        echo "$relativePath\n";

        $modified = addGplHeader($file, $gplHeader, $dryRun);

        if ($modified) {
            $modifiedFiles++;
        } else {
            $skippedFiles++;
        }
    }

    echo "\n";
}

echo "==============================================\n";
echo "  Summary\n";
echo "==============================================\n";
echo "Total files scanned:  $totalFiles\n";
echo "Files modified:       $modifiedFiles\n";
echo "Files skipped:        $skippedFiles (already had headers or excluded)\n";

if ($dryRun && $modifiedFiles > 0) {
    echo "\nRun without --dry-run to apply changes.\n";
}

echo "\nDone!\n";
