<?php
// ============================================================
//  varaus.php – Karelia Ulkorakennus Oy
//  Vastaanottaa ajanvarauslomakkeen, validoi, tallentaa
//  tietokantaan ja lähettää vahvistussähköpostin Brevon kautta.
// ============================================================

// ── Tietokantayhteystiedot ──────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'karelia-db');
define('DB_USER',    'karelia-user');
define('DB_PASS',    'iq3A5oHZX8w9izVw7jH2');
define('DB_CHARSET', 'utf8mb4');

// ── Brevo-asetukset ─────────────────────────────────────────
define('BREVO_API_KEY', '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');
define('SENDER_EMAIL',  'kskrausova@gmail.com');
define('SENDER_NAME',   'Karelia Ulkorakennus Oy');
define('NOTIFY_EMAIL',  'kskrausova@gmail.com'); // saat ilmoituksen uudesta varauksesta

// ── Apufunktiot ─────────────────────────────────────────────

function clean(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function redirect(string $status): never {
    header('Location: ajanvaraus.html?status=' . $status);
    exit;
}

/**
 * Lähettää sähköpostin Brevo Transactional Email API:n kautta.
 */
function lahetaSahkoposti(
    string $vastaanottaja_email,
    string $vastaanottaja_nimi,
    string $aihe,
    string $html_sisalto
): bool {
    $data = [
        'sender' => [
            'email' => SENDER_EMAIL,
            'name'  => SENDER_NAME,
        ],
        'to' => [
            ['email' => $vastaanottaja_email, 'name' => $vastaanottaja_nimi],
        ],
        'subject'     => $aihe,
        'htmlContent' => $html_sisalto,
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'api-key: ' . BREVO_API_KEY,
        ],
        CURLOPT_TIMEOUT => 10,
    ]);

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_exec($ch);
    curl_close($ch);

    return $http_code === 201;
}

/**
 * Vahvistussähköposti asiakkaalle.
 */
function rakennaSahkopostiAsiakkaalle(
    string $etunimi,
    string $sukunimi,
    string $pvm,
    string $aika
): string {
    $nimi   = htmlspecialchars($etunimi . ' ' . $sukunimi);
    $pvm_fi = htmlspecialchars(date('d.m.Y', strtotime($pvm)));
    $aika_h = htmlspecialchars($aika);

    return <<<HTML
<!DOCTYPE html>
<html lang="fi">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#faf7f4;font-family:system-ui,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#faf7f4;padding:40px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

        <!-- Header -->
        <tr>
          <td style="background:#2c1f14;border-radius:10px 10px 0 0;padding:28px 36px;">
            <p style="margin:0;font-size:20px;font-weight:700;color:#ffffff;letter-spacing:.02em;">
              Karelia <span style="color:#c97d4e;">Ulkorakennus</span> Oy
            </p>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="background:#ffffff;padding:36px;border-left:1px solid #e0d5c8;border-right:1px solid #e0d5c8;">
            <h1 style="margin:0 0 8px;font-size:22px;color:#2c1f14;">Suunnitteluaika vahvistettu ✓</h1>
            <p style="margin:0 0 24px;color:#5a4030;font-size:15px;">
              Hei {$nimi}, suunnitteluaikasi on vahvistettu! Nähdään sovittuna aikana.
            </p>

            <!-- Details box -->
            <table width="100%" cellpadding="0" cellspacing="0"
                   style="background:#faf7f4;border:1px solid #e0d5c8;border-radius:8px;margin-bottom:24px;">
              <tr>
                <td style="padding:20px 24px;">
                  <p style="margin:0 0 12px;font-size:13px;font-weight:700;color:#8a7060;
                            text-transform:uppercase;letter-spacing:.08em;">Varauksen tiedot</p>
                  <table cellpadding="0" cellspacing="0" style="width:100%;font-size:14px;color:#2c1f14;">
                    <tr>
                      <td style="padding:5px 0;color:#8a7060;width:140px;">Päivämäärä</td>
                      <td style="padding:5px 0;font-weight:600;">{$pvm_fi}</td>
                    </tr>
                    <tr>
                      <td style="padding:5px 0;color:#8a7060;">Aika</td>
                      <td style="padding:5px 0;font-weight:600;">{$aika_h}</td>
                    </tr>
                    <tr>
                      <td style="padding:5px 0;color:#8a7060;">Kesto</td>
                      <td style="padding:5px 0;">~30 minuuttia</td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>

            <p style="margin:0 0 8px;color:#5a4030;font-size:14px;">
              Suunnitteluaika kestää noin <strong>30 minuuttia</strong> ja se on täysin
              <strong>ilmainen ja sitoutumaton</strong>. Jos sinulle tulee kysyttävää tai
              tarvitset muuttaa aikaa, ota rohkeasti yhteyttä:
            </p>
            <p style="margin:16px 0 0;color:#5a4030;font-size:14px;">
              <a href="mailto:info@kareliarakennus.fi" style="color:#c97d4e;">info@kareliarakennus.fi</a>
              · <a href="tel:+358501234567" style="color:#c97d4e;">050 123 4567</a>
            </p>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#f5ede3;border:1px solid #e0d5c8;border-top:none;
                      border-radius:0 0 10px 10px;padding:20px 36px;text-align:center;">
            <p style="margin:0;font-size:12px;color:#8a7060;">
              Karelia Ulkorakennus Oy · Joensuu, Pohjois-Karjala<br>
              Tämä on automaattinen viesti – älä vastaa tähän sähköpostiin.
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}

/**
 * Ilmoitussähköposti adminille (sinulle) uudesta varauksesta.
 */
function rakennaSahkopostiAdminille(
    string $etunimi,
    string $sukunimi,
    string $puhelin,
    string $email,
    string $pvm,
    string $aika
): string {
    $nimi      = htmlspecialchars($etunimi . ' ' . $sukunimi);
    $pvm_fi    = htmlspecialchars(date('d.m.Y', strtotime($pvm)));
    $aika_h    = htmlspecialchars($aika);
    $puhelin_h = htmlspecialchars($puhelin);
    $email_h   = htmlspecialchars($email);

    return <<<HTML
<!DOCTYPE html>
<html lang="fi">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#faf7f4;font-family:system-ui,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#faf7f4;padding:40px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
        <tr>
          <td style="background:#2c1f14;border-radius:10px 10px 0 0;padding:24px 36px;">
            <p style="margin:0;font-size:16px;font-weight:700;color:#c97d4e;">
              🔔 Uusi varaus – Karelia Ulkorakennus
            </p>
          </td>
        </tr>
        <tr>
          <td style="background:#ffffff;padding:30px 36px;
                      border-left:1px solid #e0d5c8;border-right:1px solid #e0d5c8;">
            <table cellpadding="0" cellspacing="0" style="width:100%;font-size:14px;color:#2c1f14;">
              <tr><td style="padding:6px 0;color:#8a7060;width:140px;">Nimi</td>       <td style="padding:6px 0;font-weight:600;">{$nimi}</td></tr>
              <tr><td style="padding:6px 0;color:#8a7060;">Puhelin</td>    <td style="padding:6px 0;"><a href="tel:{$puhelin_h}" style="color:#c97d4e;">{$puhelin_h}</a></td></tr>
              <tr><td style="padding:6px 0;color:#8a7060;">Sähköposti</td> <td style="padding:6px 0;"><a href="mailto:{$email_h}" style="color:#c97d4e;">{$email_h}</a></td></tr>
              <tr><td style="padding:6px 0;color:#8a7060;">Päivämäärä</td> <td style="padding:6px 0;font-weight:600;">{$pvm_fi}</td></tr>
              <tr><td style="padding:6px 0;color:#8a7060;">Aika</td>       <td style="padding:6px 0;font-weight:600;">{$aika_h}</td></tr>
            </table>
            <p style="margin:20px 0 0;font-size:13px;color:#8a7060;">
              Hallitse varauksia: <a href="admin.php" style="color:#c97d4e;">admin.php</a>
            </p>
          </td>
        </tr>
        <tr>
          <td style="background:#f5ede3;border:1px solid #e0d5c8;border-top:none;
                      border-radius:0 0 10px 10px;padding:16px 36px;text-align:center;">
            <p style="margin:0;font-size:12px;color:#8a7060;">Karelia Ulkorakennus Oy – automaattinen ilmoitus</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}

// ════════════════════════════════════════════════════════════
//  PÄÄLOGIIKKA
// ════════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('invalid');

// Honeypot-bottifiltteri
if (!empty($_POST['website'])) redirect('ok');

// Lue kentät
$etunimi       = clean($_POST['etunimi']       ?? '');
$sukunimi      = clean($_POST['sukunimi']      ?? '');
$puhelin       = clean($_POST['puhelin']       ?? '');
$email         = clean($_POST['email']         ?? '');
$toivottu_pvm  = clean($_POST['toivottu_pvm']  ?? '');
$toivottu_aika = clean($_POST['toivottu_aika'] ?? '');
$palvelu       = clean($_POST['palvelu']       ?? '');
$lisatiedot    = clean($_POST['lisatiedot']    ?? '');

// Validointi
$errors = [];

if (mb_strlen($etunimi) < 1 || mb_strlen($etunimi) > 80)   $errors[] = 'etunimi';
if (mb_strlen($sukunimi) < 1 || mb_strlen($sukunimi) > 80) $errors[] = 'sukunimi';
if ($puhelin !== '' && !preg_match('/^[\+\d\s\-\(\)]{5,30}$/', $puhelin)) $errors[] = 'puhelin';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))              $errors[] = 'email';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toivottu_pvm)) {
    $errors[] = 'pvm';
} else {
    $pvm_obj = DateTime::createFromFormat('Y-m-d', $toivottu_pvm);
    if (!$pvm_obj || $pvm_obj < new DateTime('today')) $errors[] = 'pvm_past';
}

// Sallitut aikaslotit: 09:00–16:00, tunneittain, 30 min tapaaminen
$sallitut_ajat = [
    '09:00–09:30', '10:00–10:30', '11:00–11:30', '12:00–12:30',
    '13:00–13:30', '14:00–14:30', '15:00–15:30', '16:00–16:30',
];
if (!in_array($toivottu_aika, $sallitut_ajat, true)) $errors[] = 'aika';

$sallitut_palvelut = [
    '', 'Terassi', 'Pergola', 'Piharakennus',
    'Aita', 'Piharemontti', 'Muu / En osaa sanoa',
];
if (!in_array($palvelu, $sallitut_palvelut, true)) $errors[] = 'palvelu';
if (mb_strlen($lisatiedot) > 1000)                  $errors[] = 'lisatiedot';

if (!empty($errors)) redirect('invalid');

// Tietokantayhteys
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('DB-yhteys epäonnistui: ' . $e->getMessage());
    redirect('error');
}

// Luo taulu jos ei ole vielä olemassa
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS varaukset (
            id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            etunimi       VARCHAR(80)   NOT NULL,
            sukunimi      VARCHAR(80)   NOT NULL,
            puhelin       VARCHAR(30)   NOT NULL,
            email         VARCHAR(120)  NOT NULL,
            toivottu_pvm  DATE          NOT NULL,
            toivottu_aika VARCHAR(20)   NOT NULL,
            palvelu       VARCHAR(60)   NOT NULL DEFAULT '',
            lisatiedot    TEXT,
            tila          ENUM('uusi','vahvistettu','peruttu') NOT NULL DEFAULT 'vahvistettu',
            luotu_klo     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (PDOException $e) {
    error_log('Taulun luonti epäonnistui: ' . $e->getMessage());
    redirect('error');
}

// Tallenna varaus tietokantaan
try {
    $stmt = $pdo->prepare("
        INSERT INTO varaukset
            (etunimi, sukunimi, puhelin, email,
             toivottu_pvm, toivottu_aika, palvelu, lisatiedot)
        VALUES
            (:etunimi, :sukunimi, :puhelin, :email,
             :toivottu_pvm, :toivottu_aika, :palvelu, :lisatiedot)
    ");
    $stmt->execute([
        ':etunimi'       => $etunimi,
        ':sukunimi'      => $sukunimi,
        ':puhelin'       => $puhelin,
        ':email'         => $email,
        ':toivottu_pvm'  => $toivottu_pvm,
        ':toivottu_aika' => $toivottu_aika,
        ':palvelu'       => $palvelu,
        ':lisatiedot'    => $lisatiedot,
    ]);
} catch (PDOException $e) {
    error_log('INSERT epäonnistui: ' . $e->getMessage());
    redirect('error');
}

// Lähetä sähköpostit Brevon kautta
$koko_nimi = $etunimi . ' ' . $sukunimi;

// 1) Vahvistus asiakkaalle
lahetaSahkoposti(
    $email,
    $koko_nimi,
    'Suunnitteluaikasi on vahvistettu – Karelia Ulkorakennus Oy',
    rakennaSahkopostiAsiakkaalle(
        $etunimi, $sukunimi, $toivottu_pvm, $toivottu_aika
    )
);

// 2) Ilmoitus sinulle uudesta varauksesta
lahetaSahkoposti(
    NOTIFY_EMAIL,
    SENDER_NAME,
    'Uusi varaus: ' . $koko_nimi . ' – ' . date('d.m.Y', strtotime($toivottu_pvm)),
    rakennaSahkopostiAdminille(
        $etunimi, $sukunimi, $puhelin, $email,
        $toivottu_pvm, $toivottu_aika
    )
);

redirect('ok');