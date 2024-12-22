<?php

/*
CREATE TABLE IF NOT EXISTS urls (
     shortUrl TEXT NOT NULL UNIQUE COLLATE NOCASE,
     url TEXT NOT NULL
)
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
        $options = $dsn && str_starts_with($dsn, 'sqlite:') ? [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY] : null;
        $pdo = new \PDO($dsn, null, null, $options);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo = $pdo;
    }

    public function getUrl(string $shortUrl)
    {
        $stmt = $this->pdo->prepare('SELECT url FROM urls WHERE shortUrl = :shortUrl');
        $stmt->execute(['shortUrl' => $shortUrl]);
        $result = $stmt->fetchColumn();
        return $result;
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
    // Use parse_url() to get only the path component of the URI
    $requestPath = trim(substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), 1));

    if ($requestPath) {
        if (!str_starts_with($requestPath, '-/')) {
            $app = new App();
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
?>
<!DOCTYPE html>
<html>

<head>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Shorty</title>
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

        --svg-fill: var(--hf-color);
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
      }
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
      <?php else : ?>
        <p>Don't panic!</p>
      <?php endif; ?>
    </main>
  </section>
  <footer>
    <p>🄯 2024 Trond Aasan</p>
  </footer>
</body>

</html>
