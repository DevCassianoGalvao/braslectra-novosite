<?php
declare(strict_types=1);

/** Cabeçalhos CORS: libera o próprio domínio e as origens cadastradas em Configurações. */
function public_cors(array $methods = ['GET', 'POST', 'OPTIONS']): void
{
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '') {
        $allowed = false;
        $host = parse_url($origin, PHP_URL_HOST);
        $port = parse_url($origin, PHP_URL_PORT);
        $hostPort = $host . ($port ? ':' . $port : '');
        if ($hostPort === ($_SERVER['HTTP_HOST'] ?? '')) {
            $allowed = true;
        } else {
            foreach (preg_split('/[\s,]+/', setting('allowed_origins')) ?: [] as $o) {
                $o = rtrim(trim($o), '/');
                if ($o !== '' && strcasecmp($o, rtrim($origin, '/')) === 0) {
                    $allowed = true;
                    break;
                }
            }
        }
        if ($allowed) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            header('Access-Control-Allow-Methods: ' . implode(', ', $methods));
            header('Access-Control-Allow-Headers: Content-Type, Accept');
            header('Access-Control-Max-Age: 600');
        }
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/** Salva um currículo enviado pelo formulário. Retorna caminho relativo ao private_dir ou lança Exception. */
function save_resume(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no envio do arquivo.');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('O currículo deve ter no máximo 5 MB.');
    }
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $allowed = [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword', 'application/octet-stream', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'odt'  => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'rtf'  => ['text/rtf', 'application/rtf', 'text/plain'],
    ];
    if (!isset($allowed[$ext])) {
        throw new RuntimeException('Formato não aceito. Envie PDF, DOC ou DOCX.');
    }
    $mime = '';
    if (class_exists('finfo')) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
    }
    if ($mime !== '' && !in_array($mime, $allowed[$ext], true)) {
        throw new RuntimeException('O arquivo enviado não parece ser um ' . strtoupper($ext) . ' válido.');
    }
    $dir = rtrim((string) cfg('private_dir'), '/\\') . '/curriculos/' . date('Y-m');
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        throw new RuntimeException('Não foi possível salvar o arquivo.');
    }
    protect_dir(rtrim((string) cfg('private_dir'), '/\\'));
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Não foi possível salvar o arquivo.');
    }
    return 'curriculos/' . date('Y-m') . '/' . $name;
}
