import { chromium } from 'playwright';

const SITE_URL = 'https://wp-amineware.ddev.site';
const ADMIN_URL = `${SITE_URL}/wp-admin`;
const PLUGIN_URL = `${ADMIN_URL}/admin.php?page=woo-bordereau-generator`;

// Expected Arabic translations
const arabicTranslations = {
    // Homepage tabs
    'Homepage': 'الرئيسية',
    'Shipping Providers': 'شركات التوصيل',
    'Settings': 'الإعدادات',
    'Tutorial': 'الشرح',
    
    // Welcome page
    'Welcome back!': 'مرحباً بعودتك!',
    'Quick Actions': 'إجراءات سريعة',
    'Create Shipping Label': 'إنشاء بوردورو شحن',
    'View Orders': 'عرض الطلبيات',
    'Configure Providers': 'إعداد شركات التوصيل',
    'Import Shipping Rates': 'استيراد أسعار الشحن',
    'Active Providers': 'الشركات النشطة',
    'Getting Started': 'البدء',
    'Setup Checklist': 'قائمة الإعداد',
    'Resources': 'المصادر',
    'Documentation': 'التوثيق',
    'Support': 'الدعم',
    
    // Clear cache
    'Clear Cache': 'مسح الذاكرة المؤقتة',
};

async function testTranslations() {
    console.log('Starting translation test...\n');
    
    const browser = await chromium.launch({ 
        headless: true,
        args: ['--ignore-certificate-errors']
    });
    
    const context = await browser.newContext({
        ignoreHTTPSErrors: true
    });
    
    const page = await context.newPage();
    
    try {
        // Login to WordPress
        console.log('1. Logging into WordPress...');
        await page.goto(`${ADMIN_URL}/`, { waitUntil: 'networkidle' });
        
        // Check if already logged in
        const isLoginPage = await page.locator('#user_login').count() > 0;
        
        if (isLoginPage) {
            await page.fill('#user_login', 'admin');
            await page.fill('#user_pass', 'admin');
            await page.click('#wp-submit');
            await page.waitForNavigation({ waitUntil: 'networkidle' });
            console.log('   ✓ Logged in successfully\n');
        } else {
            console.log('   ✓ Already logged in\n');
        }
        
        // Navigate to plugin page
        console.log('2. Navigating to Bordereau Generator plugin...');
        await page.goto(PLUGIN_URL, { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000); // Wait for React to render
        console.log('   ✓ Plugin page loaded\n');
        
        // Take screenshot of homepage
        await page.screenshot({ path: 'scripts/screenshot-homepage.png', fullPage: true });
        console.log('   ✓ Screenshot saved: scripts/screenshot-homepage.png\n');
        
        // Get page content
        const pageContent = await page.content();
        const pageText = await page.locator('body').innerText();
        
        console.log('3. Checking translations on Homepage tab...');
        let foundTranslations = [];
        let missingTranslations = [];
        let englishStringsFound = [];
        
        for (const [english, arabic] of Object.entries(arabicTranslations)) {
            if (pageText.includes(arabic)) {
                foundTranslations.push({ english, arabic });
            } else if (pageText.includes(english)) {
                englishStringsFound.push({ english, arabic });
            } else {
                missingTranslations.push({ english, arabic });
            }
        }
        
        console.log('\n   === TRANSLATION RESULTS ===\n');
        
        if (foundTranslations.length > 0) {
            console.log('   ✓ ARABIC TRANSLATIONS FOUND:');
            foundTranslations.forEach(t => {
                console.log(`     - "${t.english}" → "${t.arabic}"`);
            });
        }
        
        if (englishStringsFound.length > 0) {
            console.log('\n   ⚠ ENGLISH STRINGS (not translated):');
            englishStringsFound.forEach(t => {
                console.log(`     - "${t.english}" (expected: "${t.arabic}")`);
            });
        }
        
        if (missingTranslations.length > 0) {
            console.log('\n   ✗ MISSING (not found at all):');
            missingTranslations.forEach(t => {
                console.log(`     - "${t.english}" / "${t.arabic}"`);
            });
        }
        
        // Check each tab
        console.log('\n4. Checking other tabs...');
        
        // Click on Shipping Providers tab (tab index 1)
        const tabs = await page.locator('button[role="tab"]').all();
        if (tabs.length >= 2) {
            await tabs[1].click();
            await page.waitForTimeout(1500);
            await page.screenshot({ path: 'scripts/screenshot-providers.png', fullPage: true });
            console.log('   ✓ Providers tab screenshot: scripts/screenshot-providers.png');
        }
        
        // Click on Settings tab (tab index 2)
        if (tabs.length >= 3) {
            await tabs[2].click();
            await page.waitForTimeout(1500);
            await page.screenshot({ path: 'scripts/screenshot-settings.png', fullPage: true });
            console.log('   ✓ Settings tab screenshot: scripts/screenshot-settings.png');
        }
        
        // Click on Tutorial tab (tab index 3)
        if (tabs.length >= 4) {
            await tabs[3].click();
            await page.waitForTimeout(1500);
            await page.screenshot({ path: 'scripts/screenshot-tutorial.png', fullPage: true });
            console.log('   ✓ Tutorial tab screenshot: scripts/screenshot-tutorial.png');
        }
        
        // Summary
        console.log('\n\n========== SUMMARY ==========');
        console.log(`Total strings checked: ${Object.keys(arabicTranslations).length}`);
        console.log(`Arabic translations found: ${foundTranslations.length}`);
        console.log(`English strings (untranslated): ${englishStringsFound.length}`);
        console.log(`Missing entirely: ${missingTranslations.length}`);
        console.log('==============================\n');
        
        if (englishStringsFound.length > 0) {
            console.log('⚠ WARNING: Some strings are showing in English instead of Arabic.');
            console.log('This may indicate translation files need to be regenerated.\n');
        }
        
    } catch (error) {
        console.error('Error during test:', error.message);
        await page.screenshot({ path: 'scripts/screenshot-error.png', fullPage: true });
        console.log('Error screenshot saved: scripts/screenshot-error.png');
    } finally {
        await browser.close();
    }
}

testTranslations().catch(console.error);
