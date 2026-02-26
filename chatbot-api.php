<?php
/**
 * chatbot-api.php — Karelia Ulkorakennus Oy
 * Local, rule-based chatbot middleware. Does NOT call external AI.
 * Returns short Finnish replies based on a built-in knowledge base.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$message = isset($body['message']) ? trim($body['message']) : '';

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid message']);
    exit;
}

// Normalize
$msg = mb_strtolower($message, 'UTF-8');

// Simple rule-based KB (short, Finnish replies). Keep answers concise (1-3 sentences).
$KB = [
    [
        'keywords' => ['terassi','terassit','terassin','terasseja'],
        'reply' => 'Terassin hinta vaihtelee koon ja materiaalin mukaan. Esimerkiksi ~15 m² puuterassi 2 500–4 500 €, tarkka hinta selviää ilmaisessa tarjouksessa.'
    ],
    [
        'keywords' => ['komposiitti','komposiittiterassi'],
        'reply' => 'Komposiittiterassi on tyypillisesti 20–30% kalliimpi kuin puuterassi. Pyydä tarjous, niin laskemme juuri teille sopivan vaihtoehdon.'
    ],
    [
        'keywords' => ['pergola','pergolat'],
        'reply' => 'Pergolan hinta riippuu rakenteesta ja mitoista; suuntaa-antava hinta 2 000–5 000 €. Sovitaan ilmainen suunnitteluaika tarvittaessa.'
    ],
    [
        'keywords' => ['piharakennus','varasto','vierasmaja','puuvaja'],
        'reply' => 'Piharakennuksen hinnat vaihtelevat laajuuden mukaan; esimerkiksi pieni varasto 1 500–3 500 €, isompi 4 000–9 000 €. Pyydä kirjallinen tarjous.'
    ],
    [
        'keywords' => ['aita','aidat'],
        'reply' => 'Aidan hinta riippuu materiaalista; arvio noin 80–200 €/juoksumetri. Tarkempi arvio tehdään paikan päällä tai tarjouspyynnön perusteella.'
    ],
    [
        'keywords' => ['hinnoittelu','hinta','kustannus','kuinka paljon'],
        'reply' => 'Hinnoittelu riippuu työn laajuudesta ja materiaaleista; hinnat ovat suuntaa-antavia ja tarkka hinta selviää ilmaisesta tarjouksesta.'
    ],
    [
        'keywords' => ['toimialue','alue','joensuu','kontiolahti','liperi','outokumpu','polvijärvi','ilomantsi'],
        'reply' => 'Toimimme pääasiassa Pohjois-Karjalassa: Joensuu, Kontiolahti, Liperi, Outokumpu, Polvijärvi ja Ilomantsi.'
    ],
    [
        'keywords' => ['kotitalousvähennys','vähennys'],
        'reply' => 'Kotitalousvähennys on käytettävissä työn osuuteen; asiakkaan kannattaa tarkistaa verovähennysoikeus omasta verotilanteestaan.'
    ],
    [
        'keywords' => ['yhteydenotto','yhteystiedot','soittakaa','soita'],
        'reply' => 'Ota yhteyttä: numero 050 123 4567 (ma–pe 7–17) tai sähköposti info@kareliarakennus.fi. Käytä myös yhteydenottolomaketta verkkosivulla.'
    ],
    [
        'keywords' => ['ajanvaraus','varaa aika','suunnitteluaika'],
        'reply' => 'Voit varata ilmaisen 30 minuutin suunnitteluajan verkkosivun ajanvarauslomakkeella (ajanvaraus.html).'}
];

// Find best match by counting keyword occurrences
$bestScore = 0; $bestReply = '';
foreach ($KB as $entry) {
    $score = 0;
    foreach ($entry['keywords'] as $kw) {
        if (mb_strpos($msg, $kw) !== false) $score++;
    }
    if ($score > $bestScore) {
        $bestScore = $score;
        $bestReply = $entry['reply'];
    }
}

if ($bestScore > 0) {
    echo json_encode(['reply' => $bestReply]);
    exit;
}

// Generic FAQ fallback: suggest contacting or checking UKK
$fallback = 'Pahoittelen, en löytänyt suoraa vastausta. Käy UKK-sivulla tai ota yhteyttä: 050 123 4567 tai info@kareliarakennus.fi.';
echo json_encode(['reply' => $fallback]);

?>
<?php
/**
 * chatbot-api.php — Karelia Ulkorakennus Oy
 * Gemini AI middleware — API key never exposed to browser.
 *
 * Paste your Google Gemini API key below.
 */

// ── PASTE YOUR KEY HERE ───────────────────────────────────────────────────────
define('GEMINI_API_KEY', 'AIzaSyCh6lolK3JSlldeH3g2Ii-HSHyzzOt5J-Q');
// ─────────────────────────────────────────────────────────────────────────────

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get and validate input
$body = json_decode(file_get_contents('php://input'), true);
$message = isset($body['message']) ? trim($body['message']) : '';
$messageLength = function_exists('mb_strlen') ? mb_strlen($message) : strlen($message);

if (empty($message) || $messageLength > 500) {
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
- UKK-sivu: ukk.html (usein kysytyt kysymykset)
- Ajanvaraus: ajanvaraus.html (ilmainen suunnitteluaika, 30 min, voi varata verkossa)
- Yhteydenottolomake: yhteydenotto.html

Suuntaa-antavat hinnat (hinnat vaihtelevat projektin koon ja materiaalien mukaan, aina pyydettävä tarjous):
- Yksinkertainen puuterassi (n. 15 m²): 2 500–4 500 €
- Keskikokoinen terassi (n. 25 m²): 4 500–8 000 €
- Komposiittiterassi: 20–30% kalliimpi kuin puuterassi
- Pergola: 2 000–5 000 € koosta ja materiaaleista riippuen
- Piharakennus / varasto (n. 10–15 m²): 4 000–9 000 €
- Vierasmaja: 8 000–18 000 €
- Puuvaja / pieni varasto: 1 500–3 500 €
- Aita (per juoksumetri): 80–200 €/jm materiaalista riippuen
- Piharemontti: 500–5 000 € laajuudesta riippuen
- Kaikki hinnat sisältävät materiaalit, työn ja siivoustyöt
- Kotitalousvähennys vähentää työn osuutta jopa 40%

Muista aina mainita, että hinnat ovat suuntaa-antavia ja tarkka hinta selviää ilmaisesta tarjouksesta.

Vastaa aina suomeksi. Pidä vastaukset lyhyinä ja selkeinä (max 2-3 lausetta). Ole ystävällinen ja ammattimainen. Kun mainitset hinnoista, lisää aina että ne ovat arvioita ja tarkka hinta selviää ilmaisesta tarjouksesta. Jos et pysty auttamaan, ohjaa asiakas yhteydenottolomakkeelle (yhteydenotto.html), ajanvaraussivulle (ajanvaraus.html) tai soittamaan numeroon 050 123 4567.";

// ── Call Gemini API ───────────────────────────────────────────────────────────
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . GEMINI_API_KEY;

if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === '') {
    http_response_code(500);
    echo json_encode([
        'error' => 'Missing GEMINI_API_KEY',
        'reply' => 'Chatbotin asetuksissa on virhe. Ota yhteyttä: 050 123 4567 tai info@kareliarakennus.fi'
    ]);
    exit;
}

$data = json_encode([
    'system_instruction' => [
        'parts' => [['text' => $systemPrompt]]
    ],
    'contents' => [
        ['role' => 'user', 'parts' => [['text' => $message]]]
    ],
    'generationConfig' => [
        'maxOutputTokens' => 150,
        'temperature'     => 0.4,
    ]
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $data,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 10,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if (!$response || $httpCode !== 200) {
    http_response_code(500);
    echo json_encode([
        'reply' => 'Pahoittelen, chatbot ei ole juuri nyt käytettävissä. Ota yhteyttä suoraan: 050 123 4567 tai info@kareliarakennus.fi',
        'debug' => [
            'http_code' => $httpCode,
            'curl_error' => $curlError ?: null,
            'gemini_response_snippet' => $response ? substr($response, 0, 700) : null
        ]
    ]);
    exit;
}

$result = json_decode($response, true);
$reply  = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'Pahoittelen, en pysty vastaamaan juuri nyt. Ota yhteyttä lomakkeella tai soittamalla!';

// Clean up any markdown
$reply = preg_replace('/\*\*(.*?)\*\*/', '$1', $reply);
$reply = preg_replace('/\*(.*?)\*/', '$1', $reply);

echo json_encode(['reply' => trim($reply)]);
