<?php
// require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../lib/App.php';

$url = null;
$exc = null;
$requestPath = null;

define('DEV_MODE', $_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1');
// define('DEV_MODE', false);

function get_dsn()
{
    $dsn = getenv('SHORTY_DSN');
    if (!$dsn) {
        $dsn =  'sqlite:' . __DIR__ . '/../shorty.db';
    }
    return $dsn;
}
try {
    $app = new App(get_dsn(), DEV_MODE);
    // Use parse_url() to get only the path component of the URI
    $requestPath = trim(substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), 1));

    if ($requestPath) {
        if (!str_starts_with($requestPath, '-/')) {
            $url = $app->getUrl($requestPath);
            if (!$url) {
                header('HTTP/1.0 404 Not Found');
            } elseif (alwaysRedirect()) {
                // Expires in 10 years
                setcookie('shorty_redirect', 'always', time() + (10 * 365 * 24 * 60 * 60));
                header('HTTP/1.1 302 Found');
                header("Location: $url");
                exit;
            }
        }
    }
} catch (\Exception $exc) {
    header('HTTP/1.0 500 Internal Server Error');
}
?><!DOCTYPE html>
<html>

<head>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Shorty</title>
  <style>
<?php
  require __DIR__ . '/../inc/modern-normalize.css';
?>
  </style>
  <style>
<?php
  require __DIR__ . '/../inc/app.css';
?>
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
          <p>Oh noes! Database error</p>
        <?php else : ?>
          <p>Something went horribly wrong!</p>
          <blockquote>Lu, lu, lu, I've got some apples, lu, lu, lu, you've got some too…</blockquote>
        <?php endif; ?>
        <?php if ($app->DEV_MODE) : ?>
          <pre class="error"><?= print_r($exc, true) ?></pre>
        <?php endif; ?>
      <?php elseif ($requestPath) : ?>
        <p><?= $requestPath ?> not found</p>
        <?php
      else :
          try {
              echo $app->view_random_quotation();
          } catch (\Exception $exc) {
              if ($app->DEV_MODE) {
                  ?> <pre class="error"><?= print_r($exc, true) ?></pre> <?php
              } else {
                  ?> <span class="error"><?= $exc ?></span> <?php
              }
          }
      endif; ?>

    </main>
  </section>
  <footer>
    <p>🄯 2024 Trond Aasan</p>
  </footer>
</body>

</html>
