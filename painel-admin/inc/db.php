<?php
declare(strict_types=1);

const SCHEMA_VERSION = 1;

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $path = (string) cfg('db_path');
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        throw new RuntimeException('Não foi possível criar a pasta do banco: ' . $dir);
    }
    protect_dir($dir);
    $fresh = !is_file($path);
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA journal_mode = WAL');
    migrate($pdo);
    if ($fresh) {
        @chmod($path, 0640);
    }
    return $pdo;
}

/** Cria arquivos que impedem acesso web direto à pasta (Apache e IIS). */
function protect_dir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
    }
    $wc = $dir . '/web.config';
    if (!is_file($wc)) {
        @file_put_contents($wc, '<?xml version="1.0" encoding="UTF-8"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>');
    }
    $ix = $dir . '/index.html';
    if (!is_file($ix)) {
        @file_put_contents($ix, '');
    }
}

function migrate(PDO $pdo): void
{
    $v = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    if ($v >= SCHEMA_VERSION) {
        return;
    }
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE COLLATE NOCASE,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'collab',
        perms TEXT NOT NULL DEFAULT '{}',
        active INTEGER NOT NULL DEFAULT 1,
        last_login TEXT,
        created_at TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS lead_sources (
        slug TEXT PRIMARY KEY,
        label TEXT NOT NULL,
        notify_emails TEXT NOT NULL DEFAULT '',
        sort_order INTEGER NOT NULL DEFAULT 0,
        active INTEGER NOT NULL DEFAULT 1
    );
    CREATE TABLE IF NOT EXISTS leads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        source TEXT NOT NULL,
        name TEXT NOT NULL DEFAULT '',
        email TEXT NOT NULL DEFAULT '',
        phone TEXT NOT NULL DEFAULT '',
        company TEXT NOT NULL DEFAULT '',
        message TEXT NOT NULL DEFAULT '',
        data TEXT NOT NULL DEFAULT '{}',
        status TEXT NOT NULL DEFAULT 'novo',
        utm TEXT NOT NULL DEFAULT '{}',
        page_url TEXT NOT NULL DEFAULT '',
        referrer TEXT NOT NULL DEFAULT '',
        ip TEXT NOT NULL DEFAULT '',
        user_agent TEXT NOT NULL DEFAULT '',
        attachment TEXT,
        assigned_to INTEGER,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS idx_leads_source ON leads(source);
    CREATE INDEX IF NOT EXISTS idx_leads_status ON leads(status);
    CREATE INDEX IF NOT EXISTS idx_leads_created ON leads(created_at);
    CREATE TABLE IF NOT EXISTS lead_notes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lead_id INTEGER NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
        user_id INTEGER,
        kind TEXT NOT NULL DEFAULT 'note',
        body TEXT NOT NULL,
        created_at TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS idx_notes_lead ON lead_notes(lead_id);
    CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        excerpt TEXT NOT NULL DEFAULT '',
        content TEXT NOT NULL DEFAULT '',
        image TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'draft',
        seo_title TEXT NOT NULL DEFAULT '',
        seo_description TEXT NOT NULL DEFAULT '',
        published_at TEXT,
        old_id INTEGER,
        author_id INTEGER,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS idx_posts_status ON posts(status, published_at);
    CREATE TABLE IF NOT EXISTS settings (
        k TEXT PRIMARY KEY,
        v TEXT NOT NULL DEFAULT ''
    );
    CREATE TABLE IF NOT EXISTS notify_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lead_id INTEGER,
        recipients TEXT NOT NULL DEFAULT '',
        ok INTEGER NOT NULL DEFAULT 0,
        message TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS rate_limits (
        bucket TEXT NOT NULL,
        ts INTEGER NOT NULL
    );
    CREATE INDEX IF NOT EXISTS idx_rate ON rate_limits(bucket, ts);
    ");

    $ins = $pdo->prepare('INSERT OR IGNORE INTO lead_sources (slug, label, sort_order) VALUES (?, ?, ?)');
    $sources = [
        ['contato', 'Página de Contato'],
        ['executivo', 'Serviço Executivo'],
        ['fretamento', 'Fretamento'],
        ['turismo', 'Turismo'],
        ['rodoviario', 'Rodoviário'],
        ['trabalhe-conosco', 'Trabalhe Conosco'],
        ['outros', 'Outros'],
    ];
    foreach ($sources as $i => [$slug, $label]) {
        $ins->execute([$slug, $label, $i]);
    }
    $pdo->exec('PRAGMA user_version = ' . SCHEMA_VERSION);
}

// ---------- Atalhos de consulta ----------

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function row(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function rows(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function scalar(string $sql, array $params = [])
{
    $r = q($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}

function insert_id(): int
{
    return (int) db()->lastInsertId();
}

// ---------- Configurações ----------

function settings_defaults(): array
{
    return [
        'company_name'      => 'Grupo Braslectra',
        'phone_main'        => '(22) 2773-2800',
        'whatsapp'          => '(22) 9.9758-6858',
        'email_orcamento'   => 'orcamento@braslectra.com.br',
        'email_atendimento' => 'atendimento@braslectra.com.br',
        'email_rh'          => 'rh@braslectra.com.br',
        'email_vagas'       => 'vagas@braslectra.com.br',
        'address_macae'     => 'Diretriz Locações e Transportes - R. Antenor Maciel de Azevedo, 284 - Sol y Mar, Macaé - RJ, 27940-530',
        'address_rio'       => 'Eco Polo Brasil Transportes - R. Bulhões Marcial, 973 - Vigário Geral, Rio de Janeiro - RJ, 21241-369',
        'support_cities'    => 'Itaguaí (RJ), Niterói (RJ), Cabo Frio (RJ), Campos dos Goytacazes (RJ), Vitória (ES)',
        'social_instagram'  => 'https://www.instagram.com/grupobraslectra/',
        'social_facebook'   => 'https://www.facebook.com/grupobraslectra',
        'social_linkedin'   => 'https://www.linkedin.com/company/grupobraslectra/',
        'footer_text'       => 'Transporte executivo de pessoas com segurança, conforto e pontualidade há 29 anos.',
        'stat_years'        => '29',
        'stat_employees'    => '250',
        'url_courses'       => 'https://treinamentos.braslectra.com.br/',
        // Notificações
        'notify_enabled'    => '0',
        'notify_emails'     => '',
        'brevo_api_key'     => '',
        'brevo_sender_email' => '',
        'brevo_sender_name' => 'Site Grupo Braslectra',
        // Formulários
        'allowed_origins'   => '',
        // Rastreamento
        'trk_enabled'       => '1',
        'trk_gtm'           => '',
        'trk_ga4'           => '',
        'trk_meta_pixel'    => '',
        'trk_google_ads'    => '',
        'trk_head'          => '',
        'trk_body_start'    => '',
        'trk_body_end'      => '',
    ];
}

function all_settings(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = settings_defaults();
        foreach (rows('SELECT k, v FROM settings') as $r) {
            $cache[$r['k']] = $r['v'];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = all_settings();
    return array_key_exists($key, $all) ? (string) $all[$key] : $default;
}

function save_settings(array $kv): void
{
    $defaults = settings_defaults();
    $st = db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v');
    foreach ($kv as $k => $v) {
        if (array_key_exists($k, $defaults)) {
            $st->execute([$k, (string) $v]);
        }
    }
}

function lead_sources(bool $onlyActive = true): array
{
    return rows('SELECT * FROM lead_sources' . ($onlyActive ? ' WHERE active = 1' : '') . ' ORDER BY sort_order, label');
}

function source_label(string $slug): string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (lead_sources(false) as $s) {
            $map[$s['slug']] = $s['label'];
        }
    }
    return $map[$slug] ?? ucfirst(str_replace('-', ' ', $slug));
}

function rate_limit_hit(string $bucket, int $max, int $windowSec): bool
{
    $now = time();
    q('DELETE FROM rate_limits WHERE ts < ?', [$now - 86400]);
    $n = (int) scalar('SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND ts > ?', [$bucket, $now - $windowSec]);
    if ($n >= $max) {
        return true;
    }
    q('INSERT INTO rate_limits (bucket, ts) VALUES (?, ?)', [$bucket, $now]);
    return false;
}
