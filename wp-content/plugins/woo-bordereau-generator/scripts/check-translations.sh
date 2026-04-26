#!/bin/bash

# Translation Check Script
# This script helps identify untranslated strings in the PO file

LANG_PATH="languages"
LOCALE="ar"
PO_FILE="$LANG_PATH/woo-bordereau-generator-$LOCALE.po"

echo "======================================"
echo "Translation Status Report"
echo "======================================"
echo ""

# Count total strings
TOTAL=$(grep -c '^msgid "' "$PO_FILE")
echo "Total translatable strings: $TOTAL"

# Count translated strings (msgstr not empty)
TRANSLATED=$(grep -A1 '^msgid "' "$PO_FILE" | grep 'msgstr "' | grep -v 'msgstr ""' | wc -l)
echo "Translated strings: $TRANSLATED"

# Count untranslated strings
UNTRANSLATED=$((TOTAL - TRANSLATED))
echo "Untranslated strings: $UNTRANSLATED"

# Calculate percentage
if [ $TOTAL -gt 0 ]; then
    PERCENTAGE=$((TRANSLATED * 100 / TOTAL))
    echo "Translation progress: $PERCENTAGE%"
fi

echo ""
echo "======================================"
echo "Top 20 Untranslated Strings"
echo "======================================"
echo ""

# Find untranslated strings and show context
grep -B1 'msgstr ""' "$PO_FILE" | grep '^msgid "' | head -20

echo ""
echo "======================================"
echo "French Strings Needing Translation"
echo "======================================"
echo ""

# Find French strings (containing common French words)
grep '^msgid "' "$PO_FILE" | grep -E '(Votre|Choisir|Comment|avec|sans|pour|dans|sur)' | grep -A1 . | grep 'msgstr ""' -B1 | grep msgid | head -20

echo ""
echo "Run './scripts/translate-batch.sh' to translate in batches"
