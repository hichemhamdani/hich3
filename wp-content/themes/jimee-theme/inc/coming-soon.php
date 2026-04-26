<?php
/**
 * Coming Soon — toggle via admin menu.
 * Accessible a : Administrator + Technique (Web Rocket)
 * Le Gerant ne voit PAS ce menu.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   ADMIN PAGE
   ============================================================ */

add_action( 'admin_menu', 'jimee_cs_menu' );
function jimee_cs_menu() {
    add_menu_page(
        'Coming Soon',
        'Coming Soon',
        'manage_options', // Admin + Technique only
        'jimee-coming-soon',
        'jimee_cs_page',
        'dashicons-lock',
        90
    );
}

add_action( 'admin_init', 'jimee_cs_register' );
function jimee_cs_register() {
    register_setting( 'jimee_coming_soon', 'jimee_cs_enabled' );
    register_setting( 'jimee_coming_soon', 'jimee_cs_password' );
}

function jimee_cs_page() {
    $enabled  = get_option( 'jimee_cs_enabled', '0' );
    $password = get_option( 'jimee_cs_password', 'JimeePreview2026' );
    ?>
    <div class="wrap" style="max-width:600px">
        <h1 style="font-size:28px;font-weight:300;margin-bottom:24px">Coming <strong>Soon</strong></h1>

        <?php settings_errors(); ?>

        <form method="post" action="options.php">
            <?php settings_fields( 'jimee_coming_soon' ); ?>

            <div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:28px;margin-bottom:20px">

                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
                    <div>
                        <strong style="font-size:16px">Mode Coming Soon</strong>
                        <p class="description" style="margin:4px 0 0">Quand active, les visiteurs voient une page de mot de passe.</p>
                    </div>
                    <label class="jimee-toggle">
                        <input type="hidden" name="jimee_cs_enabled" value="0">
                        <input type="checkbox" name="jimee_cs_enabled" value="1" <?php checked( $enabled, '1' ); ?>>
                        <span class="jimee-toggle-track"><span class="jimee-toggle-thumb"></span></span>
                    </label>
                    <style>
                    .jimee-toggle { position:relative; display:inline-block; width:52px; height:28px; cursor:pointer; }
                    .jimee-toggle input[type="checkbox"] { opacity:0; width:0; height:0; position:absolute; }
                    .jimee-toggle-track { position:absolute; inset:0; background:#ccc; border-radius:28px; transition:all .3s; }
                    .jimee-toggle input:checked + .jimee-toggle-track { background:#000; }
                    .jimee-toggle-thumb { position:absolute; top:3px; left:3px; width:22px; height:22px; background:#fff; border-radius:50%; transition:all .3s; box-shadow:0 1px 3px rgba(0,0,0,.2); }
                    .jimee-toggle input:checked + .jimee-toggle-track .jimee-toggle-thumb { left:27px; }
                    </style>
                </div>

                <div style="margin-bottom:12px">
                    <label style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Mot de passe</label>
                    <input type="text" name="jimee_cs_password" value="<?php echo esc_attr( $password ); ?>"
                           class="regular-text" placeholder="JimeePreview2026"
                           style="width:100%;padding:10px 14px;border:1.5px solid #ddd;border-radius:8px">
                    <p class="description" style="margin-top:6px">Les visiteurs doivent entrer ce mot de passe pour acceder au site.</p>
                </div>

                <div style="padding:12px 16px;background:#f8f6f3;border-radius:8px;font-size:13px;color:#555">
                    Les utilisateurs connectes (admin, technique, gerant) voient toujours le site normalement.
                </div>
            </div>

            <?php submit_button( 'Enregistrer' ); ?>
        </form>
    </div>
    <?php
}

/* ============================================================
   FRONT — Block access if enabled
   ============================================================ */

// Disable SG cache when coming soon is active
add_action( 'init', 'jimee_cs_nocache', 1 );
function jimee_cs_nocache() {
    if ( get_option( 'jimee_cs_enabled' ) !== '1' ) return;
    if ( is_user_logged_in() ) return;
    if ( is_admin() || wp_doing_ajax() || defined( 'REST_REQUEST' ) ) return;

    $password = get_option( 'jimee_cs_password', 'JimeePreview2026' );
    if ( isset( $_COOKIE['jimee_preview'] ) && $_COOKIE['jimee_preview'] === md5( $password ) ) return;

    // Tell SiteGround and browsers not to cache
    nocache_headers();
    header( 'X-Cache-Enabled: False' );
    if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
}

add_action( 'template_redirect', 'jimee_cs_redirect' );
function jimee_cs_redirect() {
    if ( get_option( 'jimee_cs_enabled' ) !== '1' ) return;
    if ( is_user_logged_in() ) return;
    if ( is_admin() ) return;

    // Allow AJAX, REST, cron
    if ( wp_doing_ajax() || defined( 'REST_REQUEST' ) || wp_doing_cron() ) return;

    // Allow wp-login
    if ( strpos( $_SERVER['REQUEST_URI'], 'wp-login' ) !== false ) return;

    // Check cookie
    $password = get_option( 'jimee_cs_password', 'JimeePreview2026' );
    if ( isset( $_COOKIE['jimee_preview'] ) && $_COOKIE['jimee_preview'] === md5( $password ) ) return;

    // Handle form submission
    if ( isset( $_POST['preview_pass'] ) && $_POST['preview_pass'] === $password ) {
        nocache_headers();
        setcookie( 'jimee_preview', md5( $password ), time() + 30 * DAY_IN_SECONDS, '/' );
        wp_safe_redirect( home_url( '/' ) );
        exit;
    }

    // Show coming soon page
    nocache_headers();
    $logo_url = get_template_directory_uri() . '/assets/img/logo-jimee-cosmetics-noir.png';
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>Jimee Cosmetics - Bientot disponible</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <style>
            * { margin:0; padding:0; box-sizing:border-box; }
            body { font-family:'Poppins',sans-serif; background:#F8F6F3; min-height:100vh; display:flex; align-items:center; justify-content:center; }
            .cs { text-align:center; padding:40px 24px; max-width:440px; width:100%; }
            .cs-logo { height:40px; margin-bottom:32px; }
            .cs h1 { font-size:28px; font-weight:300; margin-bottom:8px; }
            .cs h1 em { font-style:italic; font-weight:600; }
            .cs p { font-size:14px; color:#777; margin-bottom:32px; line-height:1.6; }
            .cs form { display:flex; gap:8px; max-width:320px; margin:0 auto; }
            .cs-input { flex:1; padding:14px 20px; border:1.5px solid #E8E4DF; border-radius:999px; font-size:14px; font-family:inherit; outline:none; background:#fff; transition:all .3s; }
            .cs-input:focus { border-color:#000; box-shadow:0 0 0 3px rgba(0,0,0,.06); }
            .cs-btn { padding:14px 28px; background:#000; color:#fff; border:none; border-radius:999px; font-size:14px; font-weight:600; font-family:inherit; cursor:pointer; transition:all .3s; }
            .cs-btn:hover { background:#333; transform:translateY(-1px); }
            .cs-footer { margin-top:40px; font-size:11px; color:#bbb; }
        </style>
    </head>
    <body>
        <div class="cs">
            <img src="<?php echo esc_url( $logo_url ); ?>" alt="Jimee Cosmetics" class="cs-logo">
            <h1>Bientot <em>disponible</em></h1>
            <p>Notre boutique est en cours de preparation. Entrez le mot de passe pour acceder a l'apercu.</p>
            <form method="post">
                <input type="password" name="preview_pass" placeholder="Mot de passe" class="cs-input" autocomplete="off" required>
                <button type="submit" class="cs-btn">Acceder</button>
            </form>
            <div class="cs-footer">Jimee Cosmetics - Kouba, Alger</div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
