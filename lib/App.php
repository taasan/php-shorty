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

class Database
{
    private readonly string $_dsn;
    private readonly \PDO $_pdo;
    public readonly bool $IS_SQLITE;
    public readonly bool $IS_MYSQL;
    public readonly bool $IS_POSTGRES;

    public function __construct(string $dsn)
    {
        $this->_dsn = $dsn;
    }

    private function _init_pdo(): \PDO
    {
        $rp = new ReflectionProperty(get_class($this), '_pdo');
        if (!$rp->isInitialized($this)) {
            $this->_pdo = new \PDO($this->_dsn);
            $driver_name = $this->_pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $this->IS_SQLITE = 'sqlite' === $driver_name;
            $this->IS_MYSQL = 'mysql' === $driver_name;
            $this->IS_POSTGRES = 'pgsql' === $driver_name;
            $this->_pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            if ($this->IS_SQLITE) {
                $this->_pdo->setAttribute(PDO::SQLITE_ATTR_OPEN_FLAGS, PDO::SQLITE_OPEN_READONLY);
            }
        }
        return $this->_pdo;
    }

    public function getPdo(): \PDO
    {
        return $this->_init_pdo();
    }
}

class App
{
    private readonly Database $_db;
    public readonly bool $DEV_MODE;

    public function __construct(string $dsn, bool $dev_mode)
    {
        $this->DEV_MODE = $dev_mode;
        $this->_db = new Database($dsn);
    }

    public function getUrl(string $shortUrl): string|false
    {
        $pdo = $this->_db->getPdo();
        $stmt = $pdo->prepare('SELECT url FROM urls WHERE shortUrl = :shortUrl');
        $stmt->execute(['shortUrl' => $shortUrl]);
        $result = $stmt->fetchColumn();
        return $result;
    }

    public function get_random_quotation(): array|false
    {
        $pdo = $this->_db->getPdo();
        if ($this->_db->IS_SQLITE) {
            $stmt = $pdo->query('SELECT * FROM quotations ORDER BY RANDOM() LIMIT 1');
        } elseif ($this->_db->IS_MYSQL) {
            $stmt = $pdo->query('SELECT * FROM quotations ORDER BY RAND() LIMIT 1');
        } elseif ($this->_db->IS_POSTGRES) {
            // CREATE EXTENSION tsm_system_rows;
            $stmt = $pdo->query('SELECT * FROM quotations TABLESAMPLE SYSTEM_ROWS(1)');
        } else {
            return false;
        }
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function view_random_quotation(): string
    {
        try {
            $q = $this->get_random_quotation();
        } catch (\Throwable $exc) {
            $q = false;
            if ($this->DEV_MODE) {
                throw $exc;
            }
        }
        if ($q === false) {
            $q = ['collection' => 'hardcoded', 'quote' => 'Don\'t panic' . "\n\n" . '–Douglas Adams'];
        }
        $res = '<blockquote>' . htmlspecialchars($q['quote']) . '</blockquote>';
        return $res;
    }
}

function alwaysRedirect(): bool
{
    $cookie_name = 'shorty_redirect';
    return (isset($_GET['redirect']) && 'always' === $_GET['redirect'])
        || (isset($_COOKIE[$cookie_name]) && 'always' === $_COOKIE[$cookie_name]);
}

function qrCode(): QRCode
{
    include_once __DIR__ . '/qrcode.php';
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
