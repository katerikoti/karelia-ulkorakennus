<?php
/**
 * chatbot-api.php — Karelia Ulkorakennus Oy
 * AI middleware using Groq API (free, no credit card needed)
 * API key never exposed to browser.
 */

// ── PASTE YOUR GROQ KEY HERE ──────────────────────────────────────────────────
define('GROQ_API_KEY', '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');
// ─────────────────────────────────────────────────────────────────────────────

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body    = json_decode(file_get_contents('php://input'), true);
$message = isset($body['message']) ? trim($body['message']) : '';

if (empty($message) || mb_strlen($message) > 500) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid message']);
    exit;
}

// ── System prompt ─────────────────────────────────────────────────────────────
$systemPrompt = "Olet Karelia Ulkorakennus Oy:n asiakaspalvelubotti. Vastaat AINOASTAAN kysymyksiin, jotka liittyvät Karelia Ulkorakennus Oy:n palveluihin, hinnoitteluun, toimialueeseen tai yhteydenottoon. Jos kysymys ei liity näihin aiheisiin, kieltäydyt kohteliaasti ja ohjaat asiakkaan ottamaan yhteyttä.

Tietoja yrityksestä:
- Yritys: Karelia Ulkorakennus Oy, Joensuu, Pohjois-Karjala
- Palvelut: terassit, pergolat, piharakennukset, aidat, piharemontit
- Asiakkaat: omakotitaloasujat, mökkiläiset, pienet taloyhtiöt
- Toimialue: Joensuu, Kontiolahti, Liperi, Outokumpu, Polvijärvi, Ilomantsi, koko Pohjois-Karjala
- Tarjoukset: aina ilmaisia ja kirjallisia, ei piilokustannuksia
- Materiaalit: painekyllästetty puu, lämpöpuu, komposiitti
- Kokemus: yli 10 vuotta, yli 200 projektia, asiakasarvosana 4.9/5
- Puhelin: 050 123 4567 (ma-pe 7-17)
- Sähköposti: info@kareliarakennus.fi
- Kotitalousvähennys: käytettävissä työn osuuteen
- Toimikausi: pääasiassa huhti-lokakuu
- UKK-sivu: ukk.html
- Ajanvaraus: ajanvaraus.html (ilmainen 30 min suunnitteluaika)
- Yhteydenottolomake: yhteydenotto.html

Suuntaa-antavat hinnat (aina mainittava että tarkka hinta selviää ilmaisesta tarjouksesta):
- Yksinkertainen puuterassi (n. 15 m²): 2 500–4 500 €
- Keskikokoinen terassi (n. 25 m²): 4 500–8 000 €
- Komposiittiterassi: 20–30% kalliimpi kuin puuterassi
- Pergola: 2 000–5 000 €
- Piharakennus / varasto (n. 10–15 m²): 4 000–9 000 €
- Vierasmaja: 8 000–18 000 €
- Puuvaja / pieni varasto: 1 500–3 500 €
- Aita: 80–200 €/jm materiaalista riippuen
- Piharemontti: 500–5 000 €
- Kotitalousvähennys vähentää työn osuutta jopa 40%

Vastaa aina suomeksi. Pidä vastaukset lyhyinä (max 2-3 lausetta). Ole ystävällinen ja ammattimainen. Ohjaa aina yhteydenottoon tai ajanvaraukseen jos et pysty auttamaan.";

// ── Call Groq API ─────────────────────────────────────────────────────────────
$data = json_encode([
    'model'       => 'llama-3.1-8b-instant',
    'messages'    => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user',   'content' => $message]
    ],
    'max_tokens'  => 150,
    'temperature' => 0.4,
]);

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $data,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY,
    ],
    CURLOPT_TIMEOUT        => 10,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($httpCode !== 200 || !$response) {
    http_response_code(500);
    echo json_encode(['reply' => 'Pahoittelen, chatbot ei ole juuri nyt käytettävissä. Ota yhteyttä suoraan: 050 123 4567 tai info@kareliarakennus.fi']);
    exit;
}

$result = json_decode($response, true);
$reply  = $result['choices'][0]['message']['content'] ?? 'Pahoittelen, en pysty vastaamaan juuri nyt. Ota yhteyttä lomakkeella tai soittamalla!';

// Clean up markdown
$reply = preg_replace('/\*\*(.*?)\*\*/', '$1', $reply);
$reply = preg_replace('/\*(.*?)\*/',     '$1', $reply);

echo json_encode(['reply' => trim($reply)]);