<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>OZONE | Your Portfolio</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <style>
        :root {
            --bg-base: #000000;
            --text-main: #FFFFFF;
            --text-muted: #8E8E93;
            --accent-gold: #D4AF37;
            --font-display: 'Cormorant Garamond', serif;
            --font-ui: 'Inter', -apple-system, sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            background-color: var(--bg-base); 
            color: var(--text-main); 
            font-family: var(--font-ui); 
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 24px;
            background-image: radial-gradient(circle at 50% 0%, rgba(212, 175, 55, 0.1) 0%, transparent 60%);
        }

        .icon-wrapper {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 32px;
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(10px);
        }

        .icon-wrapper i {
            font-size: 32px;
            color: var(--accent-gold);
        }

        h1 {
            font-family: var(--font-display);
            font-size: 42px;
            font-weight: 400;
            margin-bottom: 16px;
        }

        p {
            color: var(--text-muted);
            font-size: 15px;
            line-height: 1.6;
            max-width: 400px;
            margin-bottom: 40px;
        }

        .btn-return {
            background: #fff;
            color: #000;
            padding: 18px 40px;
            border-radius: 100px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.05em;
            transition: transform 0.2s, background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-return:hover {
            transform: scale(1.02);
            background: var(--accent-gold);
        }
    </style>
</head>
<body>

    <div class="icon-wrapper">
        <i class="ph-light ph-wine"></i>
    </div>

    <h1>Active Session</h1>
    
    <p>Your tasting portfolio and checkout interface are seamlessly integrated directly within the live menu to ensure uninterrupted service.</p>

    <a href="menu/index.php" class="btn-return">
        Return to Menu <i class="ph-bold ph-arrow-right"></i>
    </a>

    <script src="assets/js/public-fx.js" defer></script>
</body>
</html>