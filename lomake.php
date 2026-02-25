<?php
// --- Brevo settings ---
$apiKey = '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';
$listId = 2;

// Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: yhteydenotto.html');
    exit;
}

// --- Sanitize & validate ---
$nimi     = htmlspecialchars(trim($_POST['nimi'] ?? ''));
$email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$puhelin  = htmlspecialchars(trim($_POST['puhelin'] ?? ''));
$palvelu  = htmlspecialchars(trim($_POST['palvelu'] ?? ''));
$sijainti = htmlspecialchars(trim($_POST['sijainti'] ?? ''));
$viesti   = htmlspecialchars(trim($_POST['viesti'] ?? ''));

$virheet = [];

if (empty($nimi)) $virheet[] = 'Nimi on pakollinen.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $virheet[] = 'Tarkista sähköpostiosoite.';
if (empty($viesti)) $virheet[] = 'Viesti on pakollinen.';

if (!empty($virheet)) {
    header("Location: yhteydenotto.html?virhe=" . urlencode(implode(' ', $virheet)));
    exit;
}

// --- Email content ---
$aihe = 'Uusi yhteydenotto verkkosivuilta – ' . $nimi;

$sisalto  = "Uusi yhteydenotto verkkosivuilta.\n\n";
$sisalto .= "Nimi: $nimi\n";
$sisalto .= "Sähköposti: $email\n";
$sisalto .= "Puhelin: " . ($puhelin ?: '–') . "\n";
$sisalto .= "Palvelu: " . ($palvelu ?: '–') . "\n";
$sisalto .= "Paikkakunta: " . ($sijainti ?: '–') . "\n\n";
$sisalto .= "Viesti:\n$viesti\n";

// --- 1. Send email via Brevo ---
$emailData = [
  'sender' => [
      'name' => 'Karelia Ulkorakennus',
      'email' => 'kskrausova@gmail.com'
  ],
  'to' => [
      ['email' => 'kskrausova@gmail.com']
  ],
  'replyTo' => ['email' => $email],
  'subject' => $aihe,
  'textContent' => $sisalto
];

$ch = curl_init('https://api.brevo.com/v3/smtp/email');

curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => json_encode($emailData),
  CURLOPT_HTTPHEADER => [
    'accept: application/json',
    'api-key: ' . $apiKey,
    'content-type: application/json'
  ]
]);

curl_exec($ch);
curl_close($ch);

// --- 2. Add contact to Brevo list ---
$contactData = [
  "email" => $email,
  "attributes" => [
    "FIRSTNAME" => $nimi,
    "PHONE" => $puhelin
  ],
  "listIds" => [$listId],
  "updateEnabled" => true
];

$ch = curl_init("https://api.brevo.com/v3/contacts");

curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => json_encode($contactData),
  CURLOPT_HTTPHEADER => [
    'accept: application/json',
    'api-key: ' . $apiKey,
    'content-type: application/json'
  ]
]);

curl_exec($ch);
curl_close($ch);

// --- Redirect ---
header('Location: yhteydenotto.html?kiitos=1');
exit;