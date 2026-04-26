<?php
/**
 * Coming Soon page
 */
$error = isset($_GET['wrong']) ? 'Mot de passe incorrect' : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Jimee Cosmetics — Bientôt disponible</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Poppins', sans-serif;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #F8F6F3;
    color: #000;
    padding: 24px;
}
.cs-container {
    text-align: center;
    max-width: 480px;
    width: 100%;
}
.cs-logo {
    width: 180px;
    margin-bottom: 40px;
}
h1 {
    font-size: 28px;
    font-weight: 600;
    margin-bottom: 12px;
    letter-spacing: -0.5px;
}
.cs-subtitle {
    font-size: 15px;
    color: #666;
    line-height: 1.6;
    margin-bottom: 40px;
}
.cs-form {
    display: flex;
    flex-direction: column;
    gap: 12px;
    align-items: center;
}
.cs-input {
    width: 100%;
    max-width: 320px;
    padding: 14px 20px;
    border: 1px solid #E0DCD7;
    border-radius: 50px;
    font-family: 'Poppins', sans-serif;
    font-size: 14px;
    background: #fff;
    outline: none;
    transition: border-color .3s;
    text-align: center;
}
.cs-input:focus {
    border-color: #000;
}
.cs-btn {
    width: 100%;
    max-width: 320px;
    padding: 14px 20px;
    background: #000;
    color: #fff;
    border: none;
    border-radius: 50px;
    font-family: 'Poppins', sans-serif;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: opacity .3s;
}
.cs-btn:hover { opacity: .8; }
.cs-error {
    color: #8B0000;
    font-size: 13px;
    margin-top: 4px;
}
.cs-footer {
    margin-top: 48px;
    font-size: 12px;
    color: #999;
}
.cs-divider {
    width: 40px;
    height: 1px;
    background: #D4AF37;
    margin: 32px auto;
}
</style>
</head>
<body>
<div class="cs-container">
    <img src="<?php echo get_template_directory_uri(); ?>/assets/img/logo-jimee-cosmetics-noir.png" alt="Jimee Cosmetics" class="cs-logo">
    
    <h1>Bientôt disponible</h1>
    <p class="cs-subtitle">Notre boutique en ligne est en cours de préparation.<br>Revenez bientôt pour découvrir nos produits.</p>
    
    <div class="cs-divider"></div>
    
    <p class="cs-subtitle" style="margin-bottom: 16px; font-size: 13px; color: #999;">Accès professionnel</p>
    
    <form method="POST" class="cs-form">
        <input type="password" name="preview_pass" placeholder="Mot de passe" class="cs-input" autocomplete="off">
        <button type="submit" class="cs-btn">Accéder au site</button>
        <?php if ($error) : ?>
            <p class="cs-error"><?php echo $error; ?></p>
        <?php endif; ?>
    </form>
    
    <p class="cs-footer">Jimee Cosmetics — Kouba, Alger</p>
</div>
</body>
</html>
<?php exit; ?>
