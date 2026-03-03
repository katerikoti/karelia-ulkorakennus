<?php
// ============================================================
//  admin.php – Karelia Ulkorakennus Oy
//  Salasanasuojattu hallintapaneeli varausten tarkasteluun.
//  Muuta ADMIN_PASSWORD ennen käyttöönottoa!
// ============================================================

// ── Asetukset ────────────────────────────────────────────────
define('ADMIN_PASSWORD', 'kujoensuu26');   // ← VAIHDA tämä!

define('DB_HOST',    'localhost');
define('DB_NAME',    'karelia-db');
define('DB_USER',    'karelia-user');
define('DB_PASS',    'iq3A5oHZX8w9izVw7jH2');
define('DB_CHARSET', 'utf8mb4');

session_start();

// ── Kirjautuminen / uloskirjautuminen ────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin_ok'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $login_error = true;
    }
}

$logged_in = !empty($_SESSION['admin_ok']);

// ── Statuksen päivitys (vahvista / peruuta) ──────────────────
$update_msg = '';
if ($logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_id'])) {
    $update_id   = (int) $_POST['update_id'];
    $update_tila = $_POST['update_tila'] ?? '';
    $sallitut    = ['uusi', 'vahvistettu', 'peruttu'];

    if (in_array($update_tila, $sallitut, true) && $update_id > 0) {
        try {
            $pdo2 = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $s = $pdo2->prepare('UPDATE varaukset SET tila = :tila WHERE id = :id');
            $s->execute([':tila' => $update_tila, ':id' => $update_id]);
            $update_msg = 'Tila päivitetty.';
        } catch (PDOException $e) {
            $update_msg = 'Virhe: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// ── Hae varaukset jos kirjautunut ────────────────────────────
$varaukset  = [];
$db_error   = '';
$filter_tila = $_GET['tila'] ?? 'kaikki';

if ($logged_in) {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        $sallitut_tilat = ['uusi', 'vahvistettu', 'peruttu'];
        if (in_array($filter_tila, $sallitut_tilat, true)) {
            $stmt = $pdo->prepare(
                'SELECT * FROM varaukset WHERE tila = :tila ORDER BY toivottu_pvm ASC, toivottu_aika ASC'
            );
            $stmt->execute([':tila' => $filter_tila]);
        } else {
            $stmt = $pdo->query(
                'SELECT * FROM varaukset ORDER BY toivottu_pvm ASC, toivottu_aika ASC'
            );
        }
        $varaukset = $stmt->fetchAll();

    } catch (PDOException $e) {
        $db_error = 'Tietokantavirhe: ' . htmlspecialchars($e->getMessage());
    }
}

// ── Tilavärien apufunktio ────────────────────────────────────
function tilaBadge(string $tila): string {
    return match($tila) {
        'vahvistettu' => '<span class="badge badge-green">Vahvistettu</span>',
        'peruttu'     => '<span class="badge badge-red">Peruttu</span>',
        default       => '<span class="badge badge-orange">Uusi</span>',
    };
}
?>
<!DOCTYPE html>
<html lang="fi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hallintapaneeli – Karelia Ulkorakennus Oy</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --accent:  #c97d4e;
      --accent2: #b06a3a;
      --bg:      #faf7f4;
      --card:    #ffffff;
      --border:  #e0d5c8;
      --text:    #2c1f14;
      --muted:   #8a7060;
      --green:   #2e7d32;
      --green-bg:#eef7ee;
      --red:     #8b2e1e;
      --red-bg:  #fdf0ee;
      --orange:  #7a4a10;
      --orange-bg:#fff3e0;
      --radius:  8px;
    }

    body {
      font-family: system-ui, -apple-system, sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
    }

    /* ── Top bar ── */
    .topbar {
      background: var(--text);
      color: #fff;
      padding: .85rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .topbar .brand { font-weight: 700; font-size: 1.1rem; letter-spacing: .02em; }
    .topbar .brand span { color: var(--accent); }
    .topbar a { color: #d9c4b0; font-size: .85rem; text-decoration: none; }
    .topbar a:hover { color: #fff; }

    /* ── Main wrapper ── */
    .wrap { max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem; }

    /* ── Login card ── */
    .login-card {
      max-width: 360px;
      margin: 6rem auto;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 2rem;
    }
    .login-card h1 { font-size: 1.3rem; margin-bottom: 1.2rem; }
    .login-card label { display: block; font-size: .85rem; font-weight: 600; margin-bottom: .3rem; }
    .login-card input[type=password] {
      width: 100%; padding: .65rem .8rem;
      border: 1.5px solid var(--border); border-radius: 6px;
      font-size: .95rem; margin-bottom: 1rem;
      outline: none;
    }
    .login-card input[type=password]:focus { border-color: var(--accent); }
    .login-card .btn {
      width: 100%; padding: .75rem;
      background: var(--accent); color: #fff;
      border: none; border-radius: 6px;
      font-size: 1rem; font-weight: 700; cursor: pointer;
    }
    .login-card .btn:hover { background: var(--accent2); }
    .login-error {
      background: var(--red-bg); color: var(--red);
      border: 1px solid #e08070; border-radius: 6px;
      padding: .6rem .9rem; font-size: .88rem; margin-bottom: 1rem;
    }

    /* ── Page header ── */
    .page-header {
      display: flex; align-items: center; justify-content: space-between;
      flex-wrap: wrap; gap: 1rem;
      margin-bottom: 1.5rem;
    }
    .page-header h1 { font-size: 1.5rem; }

    /* ── Stats row ── */
    .stats { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
    .stat-card {
      background: var(--card); border: 1px solid var(--border);
      border-radius: var(--radius); padding: 1rem 1.4rem; min-width: 130px;
    }
    .stat-card .num { font-size: 2rem; font-weight: 800; color: var(--accent); }
    .stat-card .lbl { font-size: .8rem; color: var(--muted); margin-top: .15rem; }

    /* ── Filter tabs ── */
    .filters { display: flex; gap: .5rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
    .filter-btn {
      padding: .45rem 1rem; border-radius: 20px;
      border: 1.5px solid var(--border);
      background: var(--card); color: var(--text);
      font-size: .85rem; font-weight: 600; cursor: pointer;
      text-decoration: none; transition: background .15s, border-color .15s;
    }
    .filter-btn:hover { border-color: var(--accent); color: var(--accent); }
    .filter-btn.active { background: var(--accent); border-color: var(--accent); color: #fff; }

    /* ── Update message ── */
    .update-msg {
      background: var(--green-bg); color: var(--green);
      border: 1px solid #7bbf7b; border-radius: 6px;
      padding: .6rem .9rem; font-size: .88rem; margin-bottom: 1rem;
    }

    /* ── Table ── */
    .table-wrap {
      background: var(--card); border: 1px solid var(--border);
      border-radius: var(--radius); overflow-x: auto;
    }
    table { width: 100%; border-collapse: collapse; font-size: .88rem; }
    thead { background: #f5ede3; }
    thead th {
      padding: .75rem 1rem; text-align: left;
      font-size: .78rem; font-weight: 700;
      color: var(--muted); text-transform: uppercase; letter-spacing: .06em;
      white-space: nowrap;
    }
    tbody tr { border-top: 1px solid var(--border); }
    tbody tr:hover { background: #fdf7f2; }
    td { padding: .7rem 1rem; vertical-align: top; }

    /* ── Badges ── */
    .badge {
      display: inline-block; padding: .2rem .65rem;
      border-radius: 20px; font-size: .75rem; font-weight: 700;
      white-space: nowrap;
    }
    .badge-green  { background: var(--green-bg);  color: var(--green); }
    .badge-red    { background: var(--red-bg);    color: var(--red); }
    .badge-orange { background: var(--orange-bg); color: var(--orange); }

    /* ── Status form inside table ── */
    .tila-form { display: flex; gap: .4rem; align-items: center; flex-wrap: wrap; }
    .tila-form select {
      padding: .3rem .5rem; border: 1px solid var(--border);
      border-radius: 5px; font-size: .8rem; cursor: pointer;
    }
    .tila-form button {
      padding: .3rem .75rem; background: var(--accent); color: #fff;
      border: none; border-radius: 5px; font-size: .8rem;
      font-weight: 600; cursor: pointer;
    }
    .tila-form button:hover { background: var(--accent2); }

    /* ── Empty state ── */
    .empty {
      text-align: center; padding: 3rem 1rem;
      color: var(--muted); font-size: .95rem;
    }

    /* ── DB error ── */
    .db-error {
      background: var(--red-bg); color: var(--red);
      border: 1px solid #e08070; border-radius: var(--radius);
      padding: 1rem 1.2rem; margin-bottom: 1.5rem;
    }

    @media (max-width: 600px) {
      .topbar { padding: .75rem 1rem; }
      .wrap   { padding: 1.5rem 1rem; }
      thead th, td { padding: .6rem .7rem; }
    }
  </style>
</head>
<body>

<div class="topbar">
  <div class="brand">Karelia <span>Ulkorakennus</span> – Hallinta</div>
  <?php if ($logged_in): ?>
    <a href="admin.php?logout=1">Kirjaudu ulos</a>
  <?php endif; ?>
</div>

<?php if (!$logged_in): ?>
<!-- ══ KIRJAUTUMISLOMAKE ══ -->
<div class="wrap">
  <div class="login-card">
    <h1>Hallintapaneeli</h1>
    <?php if (!empty($login_error)): ?>
      <div class="login-error">Väärä salasana. Yritä uudelleen.</div>
    <?php endif; ?>
    <form method="POST">
      <label for="pw">Salasana</label>
      <input type="password" id="pw" name="password" autofocus required>
      <button type="submit" class="btn">Kirjaudu sisään</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ══ HALLINTANÄKYMÄ ══ -->
<div class="wrap">

  <div class="page-header">
    <h1>Varaukset</h1>
    <span style="font-size:.85rem;color:var(--muted)">Karelia Ulkorakennus Oy</span>
  </div>

  <?php if ($update_msg): ?>
    <div class="update-msg">✓ <?= htmlspecialchars($update_msg) ?></div>
  <?php endif; ?>

  <?php if ($db_error): ?>
    <div class="db-error">⚠ <?= $db_error ?></div>
  <?php else: ?>

    <?php
      // Laske tilastot kaikista varauksista
      $kaikki_maara       = 0;
      $uusi_maara         = 0;
      $vahvistettu_maara  = 0;
      $peruttu_maara      = 0;
      try {
          $statsStmt = $pdo->query("
              SELECT tila, COUNT(*) AS maara FROM varaukset GROUP BY tila
          ");
          foreach ($statsStmt->fetchAll() as $row) {
              $kaikki_maara += $row['maara'];
              if ($row['tila'] === 'uusi')        $uusi_maara        = $row['maara'];
              if ($row['tila'] === 'vahvistettu') $vahvistettu_maara = $row['maara'];
              if ($row['tila'] === 'peruttu')     $peruttu_maara     = $row['maara'];
          }
      } catch (PDOException) {}
    ?>

    <!-- Tilastokortit -->
    <div class="stats">
      <div class="stat-card"><div class="num"><?= $kaikki_maara ?></div><div class="lbl">Varauksia yhteensä</div></div>
      <div class="stat-card"><div class="num" style="color:var(--orange)"><?= $uusi_maara ?></div><div class="lbl">Uusia</div></div>
      <div class="stat-card"><div class="num" style="color:var(--green)"><?= $vahvistettu_maara ?></div><div class="lbl">Vahvistettuja</div></div>
      <div class="stat-card"><div class="num" style="color:var(--red)"><?= $peruttu_maara ?></div><div class="lbl">Peruutettuja</div></div>
    </div>

    <!-- Suodattimet -->
    <div class="filters">
      <a href="admin.php?tila=kaikki" class="filter-btn <?= $filter_tila === 'kaikki'      ? 'active' : '' ?>">Kaikki</a>
      <a href="admin.php?tila=uusi"   class="filter-btn <?= $filter_tila === 'uusi'        ? 'active' : '' ?>">Uudet</a>
      <a href="admin.php?tila=vahvistettu" class="filter-btn <?= $filter_tila === 'vahvistettu' ? 'active' : '' ?>">Vahvistetut</a>
      <a href="admin.php?tila=peruttu"     class="filter-btn <?= $filter_tila === 'peruttu'     ? 'active' : '' ?>">Peruutetut</a>
    </div>

    <!-- Varaukset-taulukko -->
    <div class="table-wrap">
      <?php if (empty($varaukset)): ?>
        <div class="empty">Ei varauksia tällä suodattimella.</div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Nimi</th>
              <th>Yhteystiedot</th>
              <th>Toivottu aika</th>
              <th>Tila</th>
              <th>Varattu</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($varaukset as $v): ?>
            <tr>
              <td><?= $v['id'] ?></td>
              <td>
                <strong><?= htmlspecialchars($v['etunimi'] . ' ' . $v['sukunimi']) ?></strong>
              </td>
              <td>
                <a href="mailto:<?= htmlspecialchars($v['email']) ?>"><?= htmlspecialchars($v['email']) ?></a><br>
                <a href="tel:<?= htmlspecialchars($v['puhelin']) ?>"><?= htmlspecialchars($v['puhelin']) ?></a>
              </td>
              <td>
                <?= htmlspecialchars(date('d.m.Y', strtotime($v['toivottu_pvm']))) ?><br>
                <span style="color:var(--muted)"><?= htmlspecialchars($v['toivottu_aika']) ?></span>
              </td>
              <td>
                <?= tilaBadge($v['tila']) ?>
                <form method="POST" class="tila-form" style="margin-top:.4rem">
                  <input type="hidden" name="update_id" value="<?= $v['id'] ?>">
                  <select name="update_tila">
                    <option value="uusi"        <?= $v['tila'] === 'uusi'        ? 'selected' : '' ?>>Uusi</option>
                    <option value="vahvistettu" <?= $v['tila'] === 'vahvistettu' ? 'selected' : '' ?>>Vahvistettu</option>
                    <option value="peruttu"     <?= $v['tila'] === 'peruttu'     ? 'selected' : '' ?>>Peruttu</option>
                  </select>
                  <button type="submit">Tallenna</button>
                </form>
              </td>
              <td style="white-space:nowrap;color:var(--muted);font-size:.8rem">
                <?= htmlspecialchars(date('d.m.Y H:i', strtotime($v['luotu_klo']))) ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  <?php endif; ?>
</div>
<?php endif; ?>

</body>
</html>