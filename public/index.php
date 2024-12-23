<?php

/*
CREATE TABLE IF NOT EXISTS urls (
     shortUrl TEXT NOT NULL UNIQUE COLLATE NOCASE,
     url TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS quotations (
     collection TEXT NOT NULL COLLATE NOCASE,
     quote TEXT NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS collection_quote ON quotations(collection, quote);
*/

declare(strict_types=1);

$DEV_MODE = ($_SERVER['REMOTE_ADDR'] === '127.0.0.1'
    || $_SERVER['REMOTE_ADDR'] === '::1');

class App
{
    private \PDO $pdo;

    public function __construct()
    {
        $dsn = getenv('SHORTY_DSN');
        if (!$dsn) {
            $dsn =  ('sqlite:' . __DIR__ . '/../shorty.db');
        }
        $pdo = new \PDO($dsn);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        if ($pdo instanceof \Pdo\Sqlite) {
            $pdo->setAttribute(PDO::SQLITE_ATTR_OPEN_FLAGS, PDO::SQLITE_OPEN_READONLY);
        }
        $this->pdo = $pdo;
    }

    public function getUrl(string $shortUrl): string|false
    {
        $stmt = $this->pdo->prepare('SELECT url FROM urls WHERE shortUrl = :shortUrl');
        $stmt->execute(['shortUrl' => $shortUrl]);
        $result = $stmt->fetchColumn();
        return $result;
    }

    public function get_random_quotation(): array|false
    {
        if ($this->isSqlite()) {
            $stmt = $this->pdo->query('SELECT * FROM quotations ORDER BY RANDOM() LIMIT 1');
        } elseif ($this->isMysql()) {
            $stmt = $this->pdo->query('SELECT * FROM quotations ORDER BY RAND() LIMIT 1');
        } elseif($this->isPostgres()) {
            $stmt = $this->pdo->query('SELECT * FROM quotations ORDER BY RANDOM() LIMIT 1');
        }
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function view_random_quotation(): string
    {
        try {
            $q = $this->get_random_quotation();
        } catch (\Throwable $exc) {
            $q = false;
        }
        if ($q === false) {
            $q = ['collection' => 'hardcoded', 'quote' => 'Don\'t panic' . "\n\n" . '–Douglas Adams'];
        }
        $res = '<blockquote>' . htmlspecialchars($q['quote']);
        if ($q['source']) {
            $res .= '<p>' . htmlspecialchars($q['source']) . '</p>';
        }
        $res .= '</blockquote>';
        return $res;
    }

    private function getDriverName(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    private function isMysql(): bool
    {
        return $this->getDriverName() === 'mysql';
    }

    private function isSqlite(): bool
    {
        return $this->getDriverName() === 'sqlite';
    }

    private function isPostgres(): bool
    {
        return $this->getDriverName() === 'pgsql';
    }
}

function alwaysRedirect(): bool
{
    return (isset($_GET['redirect']) && 'always' === $_GET['redirect'])
        || (isset($_COOKIE['redirect']) && 'always' === $_COOKIE['redirect']);
}

$url = null;
$exc = null;
$requestPath = null;

try {
    $app = new App();
    // Use parse_url() to get only the path component of the URI
    $requestPath = trim(substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), 1));

    if ($requestPath) {
        if (!str_starts_with($requestPath, '-/')) {
            $url = $app->getUrl($requestPath);
            if (!$url) {
                header('HTTP/1.0 404 Not Found');
            } elseif (alwaysRedirect()) {
                // Expires in 10 years
                setcookie('redirect', 'always', time() + (10 * 365 * 24 * 60 * 60));
                header('HTTP/1.1 302 Found');
                header("Location: $url");
                exit;
            }
        }
    }
} catch (\Exception $exc) {
    header('HTTP/1.0 500 Internal Server Error');
}

function qrCode(): QRCode
{
    include_once '../lib/qrcode.php';

    $qr = new QRCode();
    // QR_ERROR_CORRECT_LEVEL_L : 7%
    // QR_ERROR_CORRECT_LEVEL_M : 15%
    // QR_ERROR_CORRECT_LEVEL_Q : 25%
    // QR_ERROR_CORRECT_LEVEL_H : 30%
    $qr->setErrorCorrectLevel(QR_ERROR_CORRECT_LEVEL_L);
    $qr->setTypeNumber(4);
    $url = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $qr->addData($url);
    $qr->make();
    return $qr;
}

function qrBlob(): string
{
    ob_start();
    qrCode()->printSVG();
    $data = ob_get_clean();
    return '<img alt="QR code" src="data:image/svg+xml;base64,' . base64_encode($data) . '"/>';
}

?><!DOCTYPE html>
<html>

<head>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Shorty</title>
  <style>
    /*! modern-normalize v3.0.1 | MIT License | https://github.com/sindresorhus/modern-normalize */

    /*
    Document
    ========
    */

    /**
    Use a better box model (opinionated).
    */

    *,
    ::before,
    ::after {
      box-sizing: border-box;
    }

    html {
      /* Improve consistency of default fonts in all browsers. (https://github.com/sindresorhus/modern-normalize/issues/3) */
      font-family:
        system-ui,
        'Segoe UI',
        Roboto,
        Helvetica,
        Arial,
        sans-serif,
        'Apple Color Emoji',
        'Segoe UI Emoji';
      line-height: 1.15; /* 1. Correct the line height in all browsers. */
      -webkit-text-size-adjust: 100%; /* 2. Prevent adjustments of font size after orientation changes in iOS. */
      tab-size: 4; /* 3. Use a more readable tab size (opinionated). */
    }

    /*
    Sections
    ========
    */

    body {
      margin: 0; /* Remove the margin in all browsers. */
    }

    /*
    Text-level semantics
    ====================
    */

    /**
    Add the correct font weight in Chrome and Safari.
    */

    b,
    strong {
      font-weight: bolder;
    }

    /**
    1. Improve consistency of default fonts in all browsers. (https://github.com/sindresorhus/modern-normalize/issues/3)
    2. Correct the odd 'em' font sizing in all browsers.
    */

    code,
    kbd,
    samp,
    pre {
      font-family:
        ui-monospace,
        SFMono-Regular,
        Consolas,
        'Liberation Mono',
        Menlo,
        monospace; /* 1 */
      font-size: 1em; /* 2 */
    }

    /**
    Add the correct font size in all browsers.
    */

    small {
      font-size: 80%;
    }

    /**
    Prevent 'sub' and 'sup' elements from affecting the line height in all browsers.
    */

    sub,
    sup {
      font-size: 75%;
      line-height: 0;
      position: relative;
      vertical-align: baseline;
    }

    sub {
      bottom: -0.25em;
    }

    sup {
      top: -0.5em;
    }

    /*
    Tabular data
    ============
    */

    /**
    Correct table border color inheritance in Chrome and Safari. (https://issues.chromium.org/issues/40615503, https://bugs.webkit.org/show_bug.cgi?id=195016)
    */

    table {
      border-color: currentcolor;
    }

    /*
    Forms
    =====
    */

    /**
    1. Change the font styles in all browsers.
    2. Remove the margin in Firefox and Safari.
    */

    button,
    input,
    optgroup,
    select,
    textarea {
      font-family: inherit; /* 1 */
      font-size: 100%; /* 1 */
      line-height: 1.15; /* 1 */
      margin: 0; /* 2 */
    }

    /**
    Correct the inability to style clickable types in iOS and Safari.
    */

    button,
    [type='button'],
    [type='reset'],
    [type='submit'] {
      -webkit-appearance: button;
    }

    /**
    Remove the padding so developers are not caught out when they zero out 'fieldset' elements in all browsers.
    */

    legend {
      padding: 0;
    }

    /**
    Add the correct vertical alignment in Chrome and Firefox.
    */

    progress {
      vertical-align: baseline;
    }

    /**
    Correct the cursor style of increment and decrement buttons in Safari.
    */

    ::-webkit-inner-spin-button,
    ::-webkit-outer-spin-button {
      height: auto;
    }

    /**
    1. Correct the odd appearance in Chrome and Safari.
    2. Correct the outline style in Safari.
    */

    [type='search'] {
      -webkit-appearance: textfield; /* 1 */
      outline-offset: -2px; /* 2 */
    }

    /**
    Remove the inner padding in Chrome and Safari on macOS.
    */

    ::-webkit-search-decoration {
      -webkit-appearance: none;
    }

    /**
    1. Correct the inability to style clickable types in iOS and Safari.
    2. Change font properties to 'inherit' in Safari.
    */

    ::-webkit-file-upload-button {
      -webkit-appearance: button; /* 1 */
      font: inherit; /* 2 */
    }

    /*
    Interactive
    ===========
    */

    /*
    Add the correct display in Chrome and Safari.
    */

    summary {
      display: list-item;
    }
  </style>
  <style>
    @media (prefers-color-scheme: dark) {
      :root {
        --background-color: #252b34;
        --color: white;
        --link-color: #0b7cc5;

        /* Header and footer */
        --hf-background-color: #1e2429;
        --hf-color: white;
        --hf-link-color: #1991d8;

        /* Quote */
        --q-background-color: inherit;
        --q-color: inherit;
        --q-border-color: #78C0A8;
      }
    }
    @media (prefers-color-scheme: light) {
      :root {
        --background-color: #E2E6EA;
        --color: #333;
        --link-color: #2D6A9F;

        /* Header and footer */
        --hf-background-color: #DADFE5;
        --hf-color: #333;
        --hf-link-color: #327AB7;

        /* Quote */
        --q-background-color: inherit;
        --q-color: inherit;
        --q-border-color: #78C0A8;
      }
    }
    :root {
      --svg-fill: var(--hf-color);
    }
    a {
        color: var(--link-color);
    }
    body {
      background-color: var(--background-color);
      color: var(--color);
      font-family: sans-serif;
      margin: 0;
      padding: 0;
      display: flex;
      flex-direction: column;
      height: 100vh;
      justify-content: space-between;
    }
    header {
      background-color: var(--hf-background-color);
      color: var(--hf-color);
      padding: 1em;
      text-align: center;
    }
    header a {
      color: var(--hf-link-color);
      text-decoration: none;
    }
    footer a {
      color: var(--hf-link-color);
      text-decoration: none;
    }
    main {
      padding: 1em;
    }
    footer {
      background-color: var(--hf-background-color);
      color: var(--hf-color);
      padding: 1em;
      text-align: center;
    }
    pre {
      padding: 1em;
    }
    .error {
      background-color: salmon;
    }
    .qr {
      margin-top: 3em;
      max-width: fit-content;
    }
    .qr img {
      width: 250px;
      max-width: 100%;
    }
    blockquote{
      white-space: pre;
      overflow-x: auto;
      font-size: 1.4em;
      width: 100%;
      max-width: fit-content;
      margin: 0;
      font-family: Open Sans;
      font-style: italic;
      color: var(--q-color);
      padding: 1.2em;
      border-left: 8px solid var(--q-border-color);
      line-height: 1.6;
      background: var(--q-background-color);
    }
  </style>
</head>

<body>
  <section>
    <header>
      <h1>Shorty</h1>
    </header>
    <main>
      <?php if ($url) : ?>
        <p><a href="<?= $url ?>">Go to <?= $requestPath ?></a></p>
        <form action="<?= $_SERVER['REQUEST_URI'] ?>" method="get">
          <input type="hidden" name="redirect" value="always">
          <input type="submit" value="Always redirect">
        </form>
        <div class="qr">
          <?php echo qrBlob(); ?>
        </div>
      <?php elseif ($exc) : ?>
        <?php if ($exc instanceof \PDOException) : ?>
          <p>Oh noes! <q><?= $exc->errorInfo[2] ?></q></p>
        <?php else : ?>
          <p>Something went horribly wrong!</p>
          <blockquote>Lu, lu, lu, I've got some apples, lu, lu, lu, you've got some too…</blockquote>
        <?php endif; ?>
        <?php if ($DEV_MODE) : ?>
          <pre class="error"><?= print_r($exc, true) ?></pre>
        <?php endif; ?>
      <?php elseif ($requestPath) : ?>
        <p><?= $requestPath ?> not found</p>
        <?php
      else :
          echo $app->view_random_quotation();
      endif; ?>

    </main>
  </section>
  <footer>
    <p>🄯 2024 Trond Aasan</p>
  </footer>
</body>

</html>
