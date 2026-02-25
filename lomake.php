<?php
/**
 * lomake.php — Yhteydenottolomakkeen käsittely
 * Karelia Ulkorakennus Oy
 *
 * Vaihtoehto A: Käyttää PHP:n mail()-funktiota (toimii useimmilla palvelimilla).
 * Vaihtoehto B: Brevo (SendinBlue) API — kommentit alla.
 *
 * Muuta $vastaanottaja-muuttuja oikeaan sähköpostiosoitteeseen ennen käyttöä.
 */

// Sallitaan vain POST-pyyntöjä
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: yhteydenotto.html');
    exit;
}

// --- Sanitointi ja validointi ---
$nimi     = htmlspecialchars(trim($_POST['nimi']     ?? ''));
$email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$puhelin  = htmlspecialchars(trim($_POST['puhelin']  ?? ''));
$palvelu  = htmlspecialchars(trim($_POST['palvelu']  ?? ''));
$sijainti = htmlspecialchars(trim($_POST['sijainti'] ?? ''));
$viesti   = htmlspecialchars(trim($_POST['viesti']   ?? ''));

$virheet = [];

if (empty($nimi))   $virheet[] = 'Nimi on pakollinen.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $virheet[] = 'Tarkista sähköpostiosoite.';
if (empty($viesti)) $virheet[] = 'Viesti on pakollinen.';

// Jos virheitä, palataan lomakkeelle (yksinkertainen toteutus)
if (!empty($virheet)) {
    $virheilmoitus = implode(' ', $virheet);
    header("Location: yhteydenotto.html?virhe=" . urlencode($virheilmoitus));
    exit;
}

// --- Sähköpostin lähetys (Vaihtoehto A: mail()) ---
$vastaanottaja = 'info@kareliarakennus.fi'; // VAIHDA TÄHÄN OIKEA OSOITE
$aihe = 'Uusi yhteydenotto verkkosivuilta – ' . $nimi;

$sisalto  = "Uusi yhteydenotto Karelia Ulkorakennus Oy:n verkkosivuilta.\n\n";
$sisalto .= "Nimi:       $nimi\n";
$sisalto .= "Sähköposti: $email\n";
$sisalto .= "Puhelin:    " . ($puhelin ?: '–') . "\n";
$sisalto .= "Palvelu:    " . ($palvelu ?: '–') . "\n";
$sisalto .= "Paikkakunta:" . ($sijainti ?: '–') . "\n\n";
$sisalto .= "Viesti:\n$viesti\n";

$otsikot  = "From: noreply@kareliarakennus.fi\r\n";
$otsikot .= "Reply-To: $email\r\n";
$otsikot .= "Content-Type: text/plain; charset=UTF-8\r\n";

// Send with PHP mail() (simple fallback). For production, replace with SMTP or API.
$lahetetty = mail($vastaanottaja, $aihe, $sisalto, $otsikot);

/*
 * --- Vaihtoehto B: Brevo (SendinBlue) API ---
 * Poista kommentit alta ja kommentoi mail()-kutsu pois, jos käytät Brevoa.
 *
 * $apiKey = 'SINUN_BREVO_API_AVAIN';
 * $data = json_encode([
 *   'sender'     => ['name' => 'Karelia Ulkorakennus', 'email' => 'noreply@kareliarakennus.fi'],
 *   'to'         => [['email' => $vastaanottaja]],
 *   'replyTo'    => ['email' => $email],
 *   'subject'    => $aihe,
 *   'textContent'=> $sisalto
 * ]);
 * $ch = curl_init('https://api.brevo.com/v3/smtp/email');
 * curl_setopt_array($ch, [
 *   CURLOPT_RETURNTRANSFER => true,
 *   CURLOPT_POST           => true,
 *   CURLOPT_POSTFIELDS     => $data,
 *   CURLOPT_HTTPHEADER     => [
 *     'accept: application/json',
 *     'api-key: ' . $apiKey,
 *     'content-type: application/json'
 *   ]
 * ]);
 * $response = curl_exec($ch);
 * curl_close($ch);
 * $lahetetty = ($response !== false);
 */

// --- Ohjaus kiitos-/virhe-sivulle ---
if ($lahetetty) {
    header('Location: yhteydenotto.html?kiitos=1');
} else {
    header('Location: yhteydenotto.html?virhe=' . urlencode('Viestin lähetys epäonnistui. Yritä uudelleen tai ota yhteyttä puhelimitse.'));
}
exit;
