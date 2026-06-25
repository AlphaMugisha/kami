<?php
// cashier/qr_builder.php
declare(strict_types=1);
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

// ============================================================================
// BULLETPROOF LIVE URL DETECTOR
// Automatically finds your live domain and routes exactly to the menu
// ============================================================================
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$scriptPath = $_SERVER['SCRIPT_NAME']; 
$baseDir = dirname(dirname($scriptPath)); 

// Clean up slash formatting just in case it's in the root folder
if ($baseDir === '/' || $baseDir === '\\') {
    $baseDir = '';
}

// The exact, unbreakable link to the VIP menu
$liveMenuUrl = $protocol . "://" . $host . $baseDir . "/menu/index.php";
?>
<?php
$staff_area = 'cashier';
$page_title = 'QR Generator';
require '../includes/staff_header.php';
?>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        .split-layout { display: flex; gap: 40px; height: calc(100vh - 160px); }
        .controls-panel { width: 400px; background: var(--kami-surface-2); padding: 32px; border-radius: 16px; border: 1px solid var(--kami-border); display: flex; flex-direction: column; gap: 24px; overflow-y: auto; }
        .preview-panel { flex: 1; background: var(--kami-surface-1); border-radius: 16px; display: flex; justify-content: center; align-items: center; overflow-y: auto; padding: 40px; position: relative; border: 1px solid var(--kami-border); }
        
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: var(--kami-text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.1em; }
        .form-input { width: 100%; padding: 14px 16px; background: rgba(0,0,0,0.2); border: 1px solid var(--kami-border); color: #fff; border-radius: 8px; font-size: 15px; }
        .form-input:focus { border-color: var(--kami-accent); outline: none; }
        
        .success-box { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); padding: 16px; border-radius: 8px; font-size: 13px; color: #34d399; line-height: 1.5; }
        
        /* THE PRINTABLE CARD DESIGN */
        #print-sheet {
            background: #fff;
            width: 100mm;
            height: 150mm;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 10mm;
            position: relative;
            color: #000;
        }
        
        .card-border {
            position: absolute; inset: 5mm; border: 2px solid #D4AF37; outline: 1px solid #000; outline-offset: -6px;
        }

        .card-brand { font-family: 'Cormorant Garamond', serif; font-size: 32px; font-weight: 600; letter-spacing: 0.3em; margin-bottom: 4mm; z-index: 10; text-align: center; }
        .card-subtitle { font-family: sans-serif; font-size: 10px; text-transform: uppercase; letter-spacing: 0.2em; color: #555; margin-bottom: 12mm; z-index: 10; }
        
        #qrcode-canvas { background: #fff; padding: 4mm; border: 1px solid #eee; margin-bottom: 12mm; z-index: 10; }
        
        .card-action { font-family: 'Cormorant Garamond', serif; font-style: italic; font-size: 20px; z-index: 10; margin-bottom: 2mm; }
        .card-table-name { font-family: 'Space Grotesk', sans-serif; font-size: 24px; font-weight: 700; color: #D4AF37; z-index: 10; text-align: center; }

        /* PRINT STYLES - Hides the dark UI, only prints the card */
        @media print {
            body * { visibility: hidden; }
            .app-layout { padding: 0 !important; margin: 0 !important; background: #fff; }
            #print-sheet, #print-sheet * { visibility: visible; }
            #print-sheet {
                position: absolute;
                left: 0;
                top: 0;
                box-shadow: none;
                width: 100mm;
                height: 150mm;
                page-break-after: always;
            }
        }
    </style>

        <h1 class="kami-page-title">QR Menu Generator</h1>
        <p class="kami-page-sub">Generate and print physical table codes for VIP lounges.</p>

        <div class="split-layout">
            <div class="controls-panel">
                <div class="success-box">
                    <strong><i class="ph-bold ph-check-circle"></i> Live Server Detected</strong><br>
                    The system has automatically locked onto your live domain. The QR codes generated below will instantly route clients to the VIP Menu.
                </div>

                <div class="form-group">
                    <label>Target URL (Auto-Detected)</label>
                    <input type="text" id="targetUrl" class="form-input" value="<?= htmlspecialchars($liveMenuUrl) ?>" readonly style="opacity: 0.7; cursor: not-allowed; font-family: monospace; font-size: 12px;">
                </div>

                <div class="form-group">
                    <label>Table / Lounge Name</label>
                    <input type="text" id="tableName" class="form-input" placeholder="e.g. VIP Lounge 1" value="VIP Lounge 1" onkeyup="updateCard()">
                </div>

                <button class="btn btn-primary" style="margin-top: auto; padding: 16px;" onclick="window.print()">
                    <i class="ph-bold ph-printer"></i> Print Table Card
                </button>
            </div>

            <div class="preview-panel">
                <div id="print-sheet">
                    <div class="card-border"></div>
                    <div class="card-brand">OZONE</div>
                    <div class="card-subtitle">Private Reserve Menu</div>
                    
                    <div id="qrcode-canvas"></div>
                    
                    <div class="card-action">Scan to Order</div>
                    <div class="card-table-name" id="displayTableName">VIP Lounge 1</div>
                </div>
            </div>
        </div>

    <script>
        let qrCodeObj = null;

        function updateCard() {
            // Pull the exact PHP generated URL
            const fullUrl = document.getElementById('targetUrl').value;
            const tableName = document.getElementById('tableName').value || 'Any Table';
            
            // Update Text on the Card
            document.getElementById('displayTableName').innerText = tableName;

            // Generate/Update QR Code
            const qrContainer = document.getElementById('qrcode-canvas');
            qrContainer.innerHTML = ''; // Clear old code
            
            qrCodeObj = new QRCode(qrContainer, {
                text: fullUrl,
                width: 180,
                height: 180,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });
        }

        // Run immediately on load so the first card is ready
        window.onload = updateCard;
    </script>
<?php require '../includes/staff_footer.php'; ?>