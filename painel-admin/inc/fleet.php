<?php
declare(strict_types=1);

/**
 * Gerenciador de veículos da frota.
 * Categorias e grupos são fixos (as páginas do site dependem deles); os veículos são livres.
 */

function fleet_categories(): array
{
    return [
        'carros'       => ['label' => 'Carros', 'page' => 'frota-carros', 'groups' => ['passeio' => 'Carros de passeio', 'suv' => 'SUVs', 'carga' => 'Carros de carga', 'blindados' => 'Carros Blindados']],
        'vans'         => ['label' => 'Vans', 'page' => 'frota-vans', 'groups' => ['' => '']],
        'micro-onibus' => ['label' => 'Micro-ônibus', 'page' => 'frota-micro-onibus', 'groups' => ['' => '']],
        'onibus'       => ['label' => 'Ônibus', 'page' => 'frota-onibus', 'groups' => ['' => '']],
    ];
}

/** Campos de ficha por categoria (viram os "chips" do cartão no site). */
function fleet_spec_fields(string $cat): array
{
    return match ($cat) {
        'vans', 'onibus' => ['Classe', 'Lotação'],
        'micro-onibus'   => ['Classe', 'Lotação', 'Banheiro'],
        default          => [],
    };
}

function fleet_group_label(string $cat, string $group): string
{
    return fleet_categories()[$cat]['groups'][$group] ?? '';
}

/** URL para exibir uma imagem no painel (as do site ficam na raiz; as enviadas, em uploads/). */
function fleet_img_url(string $path): string
{
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    if (str_starts_with($path, 'uploads/')) {
        return url($path);
    }
    $site = preg_replace('#/[^/]*$#', '', rtrim(admin_base(), '/'));   // pasta do site = pasta acima do painel
    return $site . '/' . ltrim($path, '/');
}

/** URL para o site público (relativa ao site para as imagens originais; absoluta para as enviadas pelo painel). */
function fleet_img_public(string $path): string
{
    if ($path === '' || preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return str_starts_with($path, 'uploads/') ? abs_url($path) : $path;
}

function fleet_thumb(string $path): string
{
    return preg_replace('/\.webp$/i', '-t.webp', $path) ?? $path;
}

function fleet_vehicle_public(array $v): array
{
    $gallery = json_decode((string) $v['gallery'], true) ?: [];
    $specs = json_decode((string) $v['specs'], true) ?: [];
    return [
        'id'       => (int) $v['id'],
        'category' => $v['category'],
        'group'    => $v['grp'],
        'name'     => $v['name'],
        'year'     => $v['year'],
        'specs'    => array_values(array_filter(array_map('strval', $specs), static fn ($s) => $s !== '')),
        'image'    => fleet_img_public((string) $v['image']),
        'gallery'  => array_map('fleet_img_public', array_values(array_filter($gallery, 'is_string'))),
    ];
}

/** Lista pública, na ordem de exibição (grupos na ordem da categoria, depois a ordem escolhida). */
function fleet_public_list(?string $cat = null): array
{
    $where = 'active = 1';
    $params = [];
    if ($cat !== null && isset(fleet_categories()[$cat])) {
        $where .= ' AND category = ?';
        $params[] = $cat;
    }
    $out = [];
    foreach (rows("SELECT * FROM vehicles WHERE $where ORDER BY sort_order, id", $params) as $v) {
        $out[] = fleet_vehicle_public($v);
    }
    $order = [];
    foreach (fleet_categories() as $c => $def) {
        $order[$c] = array_flip(array_keys($def['groups']));
    }
    $idx = array_flip(array_column($out, 'id'));
    usort($out, static function ($a, $b) use ($order, $idx) {
        $ca = array_search($a['category'], array_keys($order), true);
        $cb = array_search($b['category'], array_keys($order), true);
        return [$ca, $order[$a['category']][$a['group']] ?? 99, $idx[$a['id']]] <=> [$cb, $order[$b['category']][$b['group']] ?? 99, $idx[$b['id']]];
    });
    return $out;
}

/** Importa os veículos já existentes nas páginas (uma única vez, quando a tabela está vazia). */
function fleet_seed(PDO $pdo): void
{
    if ((int) $pdo->query('SELECT COUNT(*) FROM vehicles')->fetchColumn() > 0) {
        return;
    }
    $file = __DIR__ . '/fleet_seed.json';
    $seed = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    if (!is_array($seed)) {
        return;
    }
    $st = $pdo->prepare('INSERT INTO vehicles (category, grp, name, year, specs, image, gallery, sort_order, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)');
    $t = date('Y-m-d H:i:s');
    foreach ($seed as $v) {
        $st->execute([$v['category'], $v['group'] ?? '', $v['name'], $v['year'] ?? '', json_encode($v['specs'] ?? [], JSON_UNESCAPED_UNICODE), $v['image'], json_encode($v['gallery'] ?? [], JSON_UNESCAPED_SLASHES), (int) ($v['sort'] ?? 0), $t, $t]);
    }
}

// ---------- Imagens (GD) ----------

function fleet_load_image(string $file, int $type)
{
    $img = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
        IMAGETYPE_PNG  => @imagecreatefrompng($file),
        IMAGETYPE_WEBP => @imagecreatefromwebp($file),
        default        => false,
    };
    if (!$img) {
        return false;
    }
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $o = (int) (@exif_read_data($file)['Orientation'] ?? 1);
        $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($rot && ($r = imagerotate($img, $rot, 0))) {
            $img = $r;
        }
    }
    imagepalettetotruecolor($img);
    return $img;
}

function fleet_has_alpha($img): bool
{
    $w = imagesx($img);
    $h = imagesy($img);
    $step = max(1, intdiv(max($w, $h), 120));
    for ($y = 0; $y < $h; $y += $step) {
        for ($x = 0; $x < $w; $x += $step) {
            if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) > 100) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Foto principal do cartão. Imagem com fundo transparente (recorte) é recortada, normalizada
 * (mesma "área visual" para todos os veículos) e alinhada ao chão; foto comum é cortada em 900×563.
 * @return array{0:bool,1:string} [ok, mensagem]
 */
function fleet_save_main($img, string $outFile): array
{
    $CW = 900;
    $CH = 563;
    $w = imagesx($img);
    $h = imagesy($img);
    $canvas = imagecreatetruecolor($CW, $CH);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

    if (fleet_has_alpha($img)) {
        $step = max(1, intdiv(max($w, $h), 700));
        $minX = $w; $minY = $h; $maxX = 0; $maxY = 0;
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) < 90) {
                    $minX = min($minX, $x); $maxX = max($maxX, $x);
                    $minY = min($minY, $y); $maxY = max($maxY, $y);
                }
            }
        }
        if ($maxX <= $minX || $maxY <= $minY) {
            return [false, 'A imagem parece estar totalmente transparente.'];
        }
        $minX = max(0, $minX - $step); $minY = max(0, $minY - $step);
        $bw = min($w - $minX, $maxX - $minX + 2 * $step); $bh = min($h - $minY, $maxY - $minY + 2 * $step);
        $scale = min(575 / sqrt($bw * $bh), 0.94 * $CW / $bw, 0.88 * $CH / $bh);
        $dw = max(1, (int) round($bw * $scale));
        $dh = max(1, (int) round($bh * $scale));
        imagealphablending($canvas, false);
        imagecopyresampled($canvas, $img, intdiv($CW - $dw, 2), $CH - 22 - $dh, $minX, $minY, $dw, $dh, $bw, $bh);
    } else {
        $scale = max($CW / $w, $CH / $h);
        $sw = (int) ceil($CW / $scale);
        $sh = (int) ceil($CH / $scale);
        imagecopyresampled($canvas, $img, 0, 0, intdiv($w - $sw, 2), intdiv($h - $sh, 2), $CW, $CH, $sw, $sh);
    }
    if (!@imagewebp($canvas, $outFile, 82)) {
        return [false, 'Não foi possível gerar a imagem (extensão GD/WebP indisponível no servidor).'];
    }
    return [true, ''];
}

/** Foto da galeria (até 1400 px de largura) + miniatura (-t, 320 px). */
function fleet_save_gallery($img, string $outFile): array
{
    $w = imagesx($img);
    $h = imagesy($img);
    $save = static function ($src, int $maxW, string $file, int $q) use ($w, $h): bool {
        $nw = min($maxW, $w);
        $nh = (int) round($h * $nw / $w);
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return (bool) @imagewebp($dst, $file, $q);
    };
    if (!$save($img, 1400, $outFile, 80) || !$save($img, 320, fleet_thumb($outFile), 72)) {
        return [false, 'Não foi possível gerar a foto (extensão GD/WebP indisponível no servidor).'];
    }
    return [true, ''];
}

function fleet_upload_dir(): array
{
    $root = rtrim((string) cfg('upload_dir'), '/\\');
    $sub = 'fleet/' . date('Y/m');
    $dir = $root . '/' . $sub;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return [null, null];
    }
    $ht = $root . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "Options -Indexes -ExecCGI\n<FilesMatch \"\\.(php[0-9]?|phtml|phar|cgi|pl|py|sh)$\">\nRequire all denied\n</FilesMatch>\nphp_flag engine off\n");
    }
    return [$dir, 'uploads/' . $sub];
}

/** Apaga do disco um arquivo enviado pelo painel (nunca mexe nas imagens originais do site). */
function fleet_delete_file(string $rel): void
{
    if (!str_starts_with($rel, 'uploads/fleet/') || str_contains($rel, '..')) {
        return;
    }
    $root = rtrim((string) cfg('upload_dir'), '/\\');
    $abs = $root . '/' . substr($rel, strlen('uploads/'));
    foreach ([$abs, fleet_thumb($abs)] as $f) {
        if (is_file($f)) {
            @unlink($f);
        }
    }
}

/** Caminhos de imagem aceitos num veículo: originais do site (midia/…) ou enviados pelo painel (uploads/fleet/…). */
function fleet_valid_path(string $p): bool
{
    return (bool) preg_match('#^(midia/[A-Za-z0-9._/\-]+|uploads/fleet/[A-Za-z0-9._/\-]+)$#', $p) && !str_contains($p, '..');
}
