#!/usr/bin/env php
<?php
/**
 * Translation Management Script for Woo Bordereau Generator
 * 
 * This script helps manage JavaScript translations for the plugin.
 * WordPress uses MD5 hash of the JS file's relative path (not content) to match translation files.
 * 
 * Usage:
 *   ddev exec "cd /var/www/html/wp-content/plugins/woo-bordereau-generator && php scripts/update-translations.php [command] [options]"
 * 
 * Commands:
 *   list              - List all JS files and their translation file hashes
 *   add <locale>      - Add translations from translations/<locale>.json to the correct file
 *   check <locale>    - Check which strings are missing translations
 *   extract           - Extract translatable strings from source files (requires wp i18n)
 * 
 * Examples:
 *   php scripts/update-translations.php list
 *   php scripts/update-translations.php add ar
 *   php scripts/update-translations.php check ar
 */

define('PLUGIN_ROOT', dirname(__DIR__));
define('LANGUAGES_DIR', PLUGIN_ROOT . '/languages');
define('TRANSLATIONS_DIR', PLUGIN_ROOT . '/translations');
define('TEXT_DOMAIN', 'woo-bordereau-generator');

// JS files that need translations (relative paths from plugin root)
$jsFiles = [
    'admin/js/wc-bordereau-generator-admin.js',
    'admin/js/wc-bordereau-generator-admin-order-edit.js',
    'public/js/wc-bordereau-generator.js',
    'public/js/wc-bordereau-generator-public.js',
    'public/js/wc-bordereau-generator-checkout.js',
    'public/js/wc-bordereau-generator-checkout-public.js',
    'public/js/wc-bordereau-generator-ecotrack.js',
    'public/js/wc-bordereau-generator-updated.js',
];

/**
 * Calculate the MD5 hash WordPress uses for translation file naming
 * WordPress uses MD5 of the relative path, not the file contents
 */
function getTranslationFileHash(string $relativePath): string {
    return md5($relativePath);
}

/**
 * Get the translation file path for a JS file and locale
 */
function getTranslationFilePath(string $jsRelativePath, string $locale): string {
    $hash = getTranslationFileHash($jsRelativePath);
    return LANGUAGES_DIR . '/' . TEXT_DOMAIN . '-' . $locale . '-' . $hash . '.json';
}

/**
 * Load a JSON translation file
 */
function loadTranslationFile(string $path): ?array {
    if (!file_exists($path)) {
        return null;
    }
    $content = file_get_contents($path);
    return json_decode($content, true);
}

/**
 * Save a JSON translation file
 */
function saveTranslationFile(string $path, array $data): bool {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return file_put_contents($path, $json) !== false;
}

/**
 * List all JS files and their translation hashes
 */
function commandList(): void {
    global $jsFiles;
    
    echo "\n=== JS Files and Translation Hashes ===\n\n";
    echo sprintf("%-50s %s\n", "JS File", "Hash (for translation filename)");
    echo str_repeat("-", 85) . "\n";
    
    foreach ($jsFiles as $jsFile) {
        $hash = getTranslationFileHash($jsFile);
        $exists = file_exists(PLUGIN_ROOT . '/' . $jsFile) ? '✓' : '✗';
        echo sprintf("%s %-48s %s\n", $exists, $jsFile, $hash);
    }
    
    echo "\n";
    echo "Translation file format: " . TEXT_DOMAIN . "-{locale}-{hash}.json\n";
    echo "Example: " . TEXT_DOMAIN . "-ar-" . getTranslationFileHash($jsFiles[0]) . ".json\n\n";
}

/**
 * Add translations from a JSON file to the correct translation files
 */
function commandAdd(string $locale): void {
    global $jsFiles;
    
    // Look for translations file
    $translationsFile = TRANSLATIONS_DIR . '/' . $locale . '.json';
    if (!file_exists($translationsFile)) {
        echo "Error: Translations file not found: $translationsFile\n";
        echo "Please create this file with your translations.\n";
        echo "\nExpected format:\n";
        echo '{"English string": "Translated string", ...}' . "\n\n";
        return;
    }
    
    $newTranslations = json_decode(file_get_contents($translationsFile), true);
    if (!$newTranslations) {
        echo "Error: Could not parse translations file: $translationsFile\n";
        return;
    }
    
    echo "\n=== Adding Translations for '$locale' ===\n\n";
    echo "Found " . count($newTranslations) . " translations to add\n\n";
    
    // Main admin JS file (most strings go here)
    $mainJsFile = 'admin/js/wc-bordereau-generator-admin.js';
    $translationPath = getTranslationFilePath($mainJsFile, $locale);
    
    echo "Target file: " . basename($translationPath) . "\n";
    
    $existing = loadTranslationFile($translationPath);
    if (!$existing) {
        echo "Creating new translation file...\n";
        $existing = [
            'translation-revision-date' => date('Y-m-d H:i+0000'),
            'generator' => 'update-translations.php',
            'source' => $mainJsFile,
            'domain' => TEXT_DOMAIN,
            'locale_data' => [
                TEXT_DOMAIN => [
                    '' => [
                        'domain' => TEXT_DOMAIN,
                        'lang' => $locale,
                        'plural-forms' => 'nplurals=6; plural=n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100 >= 3 && n%100<=10 ? 3 : n%100 >= 11 && n%100<=99 ? 4 : 5;'
                    ]
                ]
            ]
        ];
    }
    
    $localeData = &$existing['locale_data'][TEXT_DOMAIN];
    $added = 0;
    $updated = 0;
    
    foreach ($newTranslations as $en => $translation) {
        if (!isset($localeData[$en])) {
            $localeData[$en] = [$translation];
            $added++;
        } elseif ($localeData[$en] === [''] || $localeData[$en] === '' || empty($localeData[$en][0])) {
            $localeData[$en] = [$translation];
            $updated++;
        }
    }
    
    // Update revision date
    $existing['translation-revision-date'] = date('Y-m-d H:i+0000');
    
    if (saveTranslationFile($translationPath, $existing)) {
        echo "\n✓ Added $added new translations\n";
        echo "✓ Updated $updated empty translations\n";
        echo "✓ Total translations: " . (count($localeData) - 1) . "\n\n";
    } else {
        echo "\n✗ Error saving translation file\n\n";
    }
}

/**
 * Check which strings are missing translations
 */
function commandCheck(string $locale): void {
    global $jsFiles;
    
    $translationsFile = TRANSLATIONS_DIR . '/' . $locale . '.json';
    $newTranslations = [];
    if (file_exists($translationsFile)) {
        $newTranslations = json_decode(file_get_contents($translationsFile), true) ?? [];
    }
    
    $mainJsFile = 'admin/js/wc-bordereau-generator-admin.js';
    $translationPath = getTranslationFilePath($mainJsFile, $locale);
    
    echo "\n=== Checking Translations for '$locale' ===\n\n";
    
    $existing = loadTranslationFile($translationPath);
    if (!$existing) {
        echo "Translation file not found: " . basename($translationPath) . "\n";
        return;
    }
    
    $localeData = $existing['locale_data'][TEXT_DOMAIN] ?? [];
    
    // Check strings from translations file
    $missing = [];
    $translated = 0;
    
    foreach ($newTranslations as $en => $translation) {
        if (!isset($localeData[$en]) || $localeData[$en] === [''] || empty($localeData[$en][0])) {
            $missing[] = $en;
        } else {
            $translated++;
        }
    }
    
    echo "Translated: $translated\n";
    echo "Missing: " . count($missing) . "\n\n";
    
    if (count($missing) > 0) {
        echo "Missing translations:\n";
        foreach (array_slice($missing, 0, 20) as $str) {
            echo "  - $str\n";
        }
        if (count($missing) > 20) {
            echo "  ... and " . (count($missing) - 20) . " more\n";
        }
    }
    echo "\n";
}

/**
 * Show usage information
 */
function showUsage(): void {
    echo <<<USAGE

Translation Management Script for Woo Bordereau Generator

Usage:
  php scripts/update-translations.php <command> [options]

Commands:
  list              List all JS files and their translation file hashes
  add <locale>      Add translations from translations/<locale>.json
  check <locale>    Check which strings are missing translations

Examples:
  php scripts/update-translations.php list
  php scripts/update-translations.php add ar
  php scripts/update-translations.php check ar

Setup:
  1. Create translations/<locale>.json with your translations:
     {"English string": "Translated string", ...}
  
  2. Run: php scripts/update-translations.php add <locale>

USAGE;
}

// Main
$command = $argv[1] ?? null;
$arg1 = $argv[2] ?? null;

switch ($command) {
    case 'list':
        commandList();
        break;
    case 'add':
        if (!$arg1) {
            echo "Error: Please specify a locale (e.g., 'ar' or 'fr_FR')\n";
            exit(1);
        }
        commandAdd($arg1);
        break;
    case 'check':
        if (!$arg1) {
            echo "Error: Please specify a locale (e.g., 'ar' or 'fr_FR')\n";
            exit(1);
        }
        commandCheck($arg1);
        break;
    default:
        showUsage();
        break;
}
