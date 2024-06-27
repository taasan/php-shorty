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
?>
<!DOCTYPE html>
<html>

<head>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Shorty</title>
  <style>
    :root {
      --hf-background-color: #24292f;
      --hf-color: #0074d9;
    }
    body {
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
      color: white;
      padding: 1em;
      text-align: center;
    }
    header a {
      color: var(--hf-color);
      text-decoration: none;
    }
    footer a {
      color: var(--hf-color);
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
  </style>
</head>

<body>
  <section>
    <header>
      <h1>Shorty</h1>
    </header>
    <main>
      <?php if ($url) : ?>
        <p><a href="<?= $url ?>"><?= $url ?></a></p>
        <form action="<?= $_SERVER['REQUEST_URI'] ?>" method="get">
          <input type="hidden" name="redirect" value="always">
          <input type="submit" value="Always redirect">
        </form>
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
    <svg viewBox="0 0 98 96" width="16" height="16" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M48.854 0C21.839 0 0 22 0 49.217c0 21.756 13.993 40.172 33.405 46.69 2.427.49 3.316-1.059 3.316-2.362 0-1.141-.08-5.052-.08-9.127-13.59 2.934-16.42-5.867-16.42-5.867-2.184-5.704-5.42-7.17-5.42-7.17-4.448-3.015.324-3.015.324-3.015 4.934.326 7.523 5.052 7.523 5.052 4.367 7.496 11.404 5.378 14.235 4.074.404-3.178 1.699-5.378 3.074-6.6-10.839-1.141-22.243-5.378-22.243-24.283 0-5.378 1.94-9.778 5.014-13.2-.485-1.222-2.184-6.275.486-13.038 0 0 4.125-1.304 13.426 5.052a46.97 46.97 0 0 1 12.214-1.63c4.125 0 8.33.571 12.213 1.63 9.302-6.356 13.427-5.052 13.427-5.052 2.67 6.763.97 11.816.485 13.038 3.155 3.422 5.015 7.822 5.015 13.2 0 18.905-11.404 23.06-22.324 24.283 1.78 1.548 3.316 4.481 3.316 9.126 0 6.6-.08 11.897-.08 13.526 0 1.304.89 2.853 3.316 2.364 19.412-6.52 33.405-24.935 33.405-46.691C97.707 22 75.788 0 48.854 0z" fill="#fff"/></svg>
    <a href="https://github.com/taasan/php-shorty">Source code</a>
  </footer>
</body>

</html>