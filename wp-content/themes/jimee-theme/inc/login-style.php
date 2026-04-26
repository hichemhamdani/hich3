<?php
/**
 * Custom WordPress login page — Jimee Cosmetics branding.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Replace WP logo URL with homepage
add_filter( 'login_headerurl', function() {
    return home_url( '/' );
});

// Replace WP logo title
add_filter( 'login_headertext', function() {
    return 'Jimee Cosmetics';
});

// Enqueue Google Fonts on login page
add_action( 'login_enqueue_scripts', function() {
    wp_enqueue_style( 'jimee-poppins', 'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap', [], null );
});

// Inject custom CSS
add_action( 'login_enqueue_scripts', 'jimee_login_styles' );
function jimee_login_styles() {
    $logo_url = esc_url( get_template_directory_uri() . '/assets/img/logo-jimee-cosmetics-noir.png' );
    ?>
    <style>
    /* ── Background ── */
    body.login {
        background: #F8F6F3 !important;
        font-family: 'Poppins', sans-serif !important;
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        min-height: 100vh;
    }

    /* ── Logo ── */
    #login h1 a {
        background-image: url('<?php echo $logo_url; ?>') !important;
        background-size: contain !important;
        background-position: center !important;
        background-repeat: no-repeat !important;
        width: 200px !important;
        height: 60px !important;
        margin: 0 auto 28px !important;
    }

    /* ── Form card ── */
    #loginform, #registerform, #lostpasswordform {
        background: #fff !important;
        border: none !important;
        border-radius: 24px !important;
        box-shadow: 0 4px 24px rgba(0,0,0,.06) !important;
        padding: 36px 32px !important;
    }

    /* ── Labels ── */
    #loginform label, #registerform label, #lostpasswordform label {
        font-family: 'Poppins', sans-serif !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        text-transform: uppercase !important;
        letter-spacing: .8px !important;
        color: #000 !important;
        margin-bottom: 6px !important;
        display: block !important;
    }

    /* ── Inputs ── */
    #loginform input[type="text"],
    #loginform input[type="password"],
    #registerform input[type="text"],
    #registerform input[type="email"],
    #lostpasswordform input[type="text"] {
        width: 100% !important;
        padding: 14px 18px !important;
        border: 1.5px solid #E8E4DF !important;
        border-radius: 12px !important;
        font-size: 14px !important;
        font-family: 'Poppins', sans-serif !important;
        color: #000 !important;
        background: #F8F6F3 !important;
        transition: all .3s cubic-bezier(.4,0,.2,1) !important;
        outline: none !important;
        box-shadow: none !important;
        margin-top: 6px !important;
    }
    #loginform input[type="text"]:focus,
    #loginform input[type="password"]:focus,
    #registerform input[type="text"]:focus,
    #registerform input[type="email"]:focus,
    #lostpasswordform input[type="text"]:focus {
        border-color: #000 !important;
        background: #fff !important;
        box-shadow: 0 0 0 3px rgba(0,0,0,.06) !important;
    }

    /* ── Password reveal button ── */
    .wp-pwd .button.wp-hide-pw {
        color: #999 !important;
        background: none !important;
        border: none !important;
        box-shadow: none !important;
        height: auto !important;
        min-height: auto !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
        right: 12px !important;
    }
    .wp-pwd .button.wp-hide-pw:hover { color: #000 !important; }
    .wp-pwd .button.wp-hide-pw:focus { box-shadow: none !important; outline: none !important; }

    /* ── Remember me ── */
    .forgetmenot {
        font-family: 'Poppins', sans-serif !important;
        font-size: 13px !important;
        color: #555 !important;
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        margin-bottom: 4px !important;
    }
    .forgetmenot label {
        font-size: 13px !important;
        text-transform: none !important;
        letter-spacing: 0 !important;
        font-weight: 400 !important;
        color: #555 !important;
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
    }
    .forgetmenot input[type="checkbox"] {
        accent-color: #000 !important;
        width: 16px !important;
        height: 16px !important;
        border-radius: 4px !important;
    }

    /* ── Submit button ── */
    #wp-submit {
        display: block !important;
        width: 100% !important;
        padding: 14px 32px !important;
        background: #000 !important;
        color: #fff !important;
        border: none !important;
        border-radius: 999px !important;
        font-family: 'Poppins', sans-serif !important;
        font-size: 14px !important;
        font-weight: 600 !important;
        letter-spacing: .5px !important;
        cursor: pointer !important;
        transition: all .3s cubic-bezier(.4,0,.2,1) !important;
        text-shadow: none !important;
        box-shadow: none !important;
        margin-top: 4px !important;
        float: none !important;
    }
    #wp-submit:hover {
        background: #333 !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 8px 40px rgba(0,0,0,.10) !important;
    }

    /* ── Login actions row — stack vertically ── */
    .login-action-login .submit,
    .login .submit {
        display: flex !important;
        flex-direction: column-reverse !important;
        gap: 16px !important;
        padding-top: 0 !important;
        clear: both !important;
    }
    .login .submit::after { display: none !important; /* kill WP clearfix */ }

    /* ── Links below form ── */
    #login #nav, #login #backtoblog {
        text-align: center !important;
        padding: 0 !important;
        margin: 12px 0 0 !important;
    }
    #login #nav a, #login #backtoblog a {
        font-family: 'Poppins', sans-serif !important;
        font-size: 13px !important;
        color: #999 !important;
        text-decoration: none !important;
        transition: all .3s cubic-bezier(.4,0,.2,1) !important;
    }
    #login #nav a:hover, #login #backtoblog a:hover {
        color: #000 !important;
    }

    /* ── Separator in nav links ── */
    #login #nav { font-size: 13px; color: #ccc; }

    /* ── Messages / Notices ── */
    #login .message, #login .success, #login #login_error {
        border: 1.5px solid #E8E4DF !important;
        border-radius: 16px !important;
        background: #fff !important;
        color: #555 !important;
        font-family: 'Poppins', sans-serif !important;
        font-size: 13px !important;
        padding: 14px 20px !important;
        box-shadow: none !important;
        margin-bottom: 16px !important;
        border-left-width: 1.5px !important;
    }
    #login #login_error {
        border-color: #8B0000 !important;
    }
    #login .message {
        border-color: #D4AF37 !important;
    }
    #login #login_error a, #login .message a {
        color: #000 !important;
        font-weight: 500 !important;
    }

    /* ── Privacy policy link ── */
    .login .privacy-policy-page-link {
        text-align: center !important;
        margin-top: 16px !important;
    }
    .login .privacy-policy-page-link a {
        font-family: 'Poppins', sans-serif !important;
        font-size: 12px !important;
        color: #bbb !important;
    }
    .login .privacy-policy-page-link a:hover { color: #000 !important; }

    /* ── Language switcher — hidden ── */
    .language-switcher { display: none !important; }

    /* ── Hide the default WP logo square ── */
    #login h1 a { outline: none !important; }

    /* ── Responsive ── */
    @media (max-width: 480px) {
        #login {
            width: 100% !important;
            padding: 0 16px !important;
        }
        #loginform, #registerform, #lostpasswordform {
            padding: 28px 20px !important;
        }
    }
    </style>
    <?php
}
