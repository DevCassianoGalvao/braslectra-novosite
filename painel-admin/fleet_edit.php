<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_perm('fleet');

$cats = fleet_categories();
$id = (int) ($_GET['id'] ?? 0);
$v = $id ? row('SELECT * FROM vehicles WHERE id = ?', [$id]) : null;
if ($id && !$v) {
    flash('err', 'Veículo não encontrado.');
    redirect('fleet.php');
}
$defCat = isset($cats[$_GET['cat'] ?? '']) ? $_GET['cat'] : 'carros';
$p = $v ?: ['id' => 0, 'category' => $defCat, 'grp' => $defCat === 'carros' ? 'passeio' : '', 'name' => '', 'year' => '', 'specs' => '[]', 'image' => '', 'gallery' => '[]', 'active' => 1];
$specsArr = json_decode((string) $p['specs'], true) ?: [];
$gallery = json_decode((string) $p['gallery'], true) ?: [];
$errors = [];

/** Divide as informações em campos fixos (Classe, Lotação, Banheiro) e "outras". */
$known = ['classe' => 'Classe', 'lotacao' => 'Lotação', 'banheiro' => 'Banheiro'];
$fields = ['classe' => '', 'lotacao' => '', 'banheiro' => ''];
$extras = [];
foreach ($specsArr as $s) {
    $matched = false;
    foreach ($known as $k => $label) {
        if (preg_match('/^' . preg_quote($label, '/') . '\s*:\s*(.*)$/iu', (string) $s, $m)) {
            $fields[$k] = trim($m[1]);
            $matched = true;
            break;
        }
    }
    if (!$matched) {
        $extras[] = (string) $s;
    }
}

if (is_post()) {
    csrf_check();
    $p['category'] = isset($cats[post('category')]) ? post('category') : 'carros';
    $groups = $cats[$p['category']]['groups'];
    $p['grp'] = isset($groups[post('grp')]) ? post('grp') : (string) array_key_first($groups);
    $p['name'] = mb_substr(post('name'), 0, 80);
    $p['year'] = mb_substr(post('year'), 0, 20);
    $p['active'] = !empty($_POST['active']) ? 1 : 0;
    foreach (array_keys($known) as $k) {
        $fields[$k] = mb_substr(post('spec_' . $k), 0, 40);
    }
    $extras = array_values(array_filter(array_map(static fn ($l) => mb_substr(trim($l), 0, 60), preg_split('/\R/', (string) ($_POST['extras'] ?? '')) ?: []), static fn ($l) => $l !== ''));
    $image = post('image');
    $newGallery = [];
    foreach ((array) ($_POST['gallery'] ?? []) as $g) {
        if (is_string($g) && fleet_valid_path($g) && !in_array($g, $newGallery, true)) {
            $newGallery[] = $g;
        }
    }
    if ($p['name'] === '') {
        $errors[] = 'Informe o nome do veículo.';
    }
    if ($image === '' || !fleet_valid_path($image)) {
        $errors[] = 'Envie a foto principal do veículo.';
    }
    $specsNew = [];
    foreach (fleet_spec_fields($p['category']) as $label) {
        $k = array_search($label, $known, true);
        if ($k !== false && $fields[$k] !== '') {
            $specsNew[] = $label . ': ' . $fields[$k];
        }
    }
    $specsNew = array_merge($specsNew, $extras);
    if (!$errors) {
        $now = now();
        if ($p['id']) {
            $old = [(string) $v['image'], ...(json_decode((string) $v['gallery'], true) ?: [])];
            q(
                'UPDATE vehicles SET category=?, grp=?, name=?, year=?, specs=?, image=?, gallery=?, active=?, updated_at=? WHERE id=?',
                [$p['category'], $p['grp'], $p['name'], $p['year'], json_encode($specsNew, JSON_UNESCAPED_UNICODE), $image, json_encode($newGallery, JSON_UNESCAPED_SLASHES), $p['active'], $now, $p['id']]
            );
            foreach (array_diff($old, [$image], $newGallery) as $gone) {
                fleet_delete_file((string) $gone);   // só apaga arquivos enviados pelo painel
            }
        } else {
            $next = (int) scalar('SELECT COALESCE(MAX(sort_order), 0) FROM vehicles WHERE category = ? AND grp = ?', [$p['category'], $p['grp']]) + 10;
            q(
                'INSERT INTO vehicles (category, grp, name, year, specs, image, gallery, sort_order, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$p['category'], $p['grp'], $p['name'], $p['year'], json_encode($specsNew, JSON_UNESCAPED_UNICODE), $image, json_encode($newGallery, JSON_UNESCAPED_SLASHES), $next, $p['active'], $now, $now]
            );
            $p['id'] = insert_id();
        }
        flash('ok', 'Veículo salvo. Já aparece no site.');
        redirect('fleet.php?cat=' . $p['category']);
    }
    $p['image'] = $image;
    $gallery = $newGallery;
}

$GLOBALS['extra_scripts'] = ['assets/fleet.js'];
view_header([
    'title'   => $p['id'] ? 'Editar veículo' : 'Novo veículo',
    'active'  => 'fleet',
    'actions' => '<a class="btn-ghost sm" href="' . e(url('fleet.php?cat=' . $p['category'])) . '">← Voltar</a>',
]);
foreach ($errors as $er) {
    echo '<div class="flash flash-err">' . e($er) . '</div>';
}
$groupsJs = [];
foreach ($cats as $k => $c) {
    $groupsJs[$k] = ['groups' => $c['groups'], 'fields' => array_map(static fn ($l) => array_search($l, $known, true), fleet_spec_fields($k))];
}
?>
<form method="post" id="fleet-form" data-upload="<?= e(url('fleet_upload.php')) ?>" data-cats="<?= e(json_encode($groupsJs, JSON_UNESCAPED_UNICODE)) ?>" data-group="<?= e($p['grp']) ?>">
  <?= csrf_field() ?>
  <div class="layout-2">
    <div>
      <div class="card">
        <h2>Dados do veículo</h2>
        <div class="grid g2" style="margin-top:14px">
          <label class="f"><span class="h">Tipo</span>
            <select name="category" id="f-cat"><?php foreach ($cats as $k => $c): ?><option value="<?= e($k) ?>" <?= $p['category'] === $k ? 'selected' : '' ?>><?= e($c['label']) ?></option><?php endforeach; ?></select>
          </label>
          <label class="f" id="f-grp-wrap"><span class="h">Grupo na página</span><select name="grp" id="f-grp"></select></label>
        </div>
        <div class="grid g2">
          <label class="f"><span class="h">Nome / modelo</span><input type="text" name="name" value="<?= e($p['name']) ?>" maxlength="80" required placeholder="Ex.: Transit 460"></label>
          <label class="f"><span class="h">Ano</span><input type="text" name="year" value="<?= e($p['year']) ?>" maxlength="20" placeholder="Ex.: 2024/2025"></label>
        </div>
        <div class="grid g3" id="f-specs">
          <label class="f" data-spec="classe"><span class="h">Classe</span><input type="text" name="spec_classe" value="<?= e($fields['classe']) ?>" maxlength="40" placeholder="Ex.: Executiva"></label>
          <label class="f" data-spec="lotacao"><span class="h">Lotação (passageiros)</span><input type="text" name="spec_lotacao" value="<?= e($fields['lotacao']) ?>" maxlength="40" placeholder="Ex.: 15"></label>
          <label class="f" data-spec="banheiro"><span class="h">Banheiro</span>
            <select name="spec_banheiro"><option value="">Não informar</option><?php foreach (['Disponível', 'Indisponível'] as $o): ?><option <?= $fields['banheiro'] === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select>
          </label>
        </div>
        <label class="f"><span class="h">Outras informações <small style="display:inline">(opcional, uma por linha, aparecem como etiquetas no cartão)</small></span>
          <textarea name="extras" rows="3" placeholder="Ex.: Ar-condicionado&#10;Poltronas reclináveis"><?= e(implode("\n", $extras)) ?></textarea>
        </label>
      </div>

      <div class="card">
        <h2>Galeria de fotos</h2>
        <p class="sub">Aparece ao clicar em “Ver fotos”. A primeira é a que abre primeiro. Use ← → para mudar a ordem.</p>
        <div class="fl-gal" id="gal"><?php foreach ($gallery as $g): ?>
          <div class="fl-item" data-path="<?= e($g) ?>"><img src="<?= e(fleet_img_url(fleet_thumb($g))) ?>" alt=""><input type="hidden" name="gallery[]" value="<?= e($g) ?>">
            <div class="fl-ctl"><button type="button" data-mv="-1" aria-label="Mover para trás">←</button><button type="button" data-mv="1" aria-label="Mover para frente">→</button><button type="button" data-rm aria-label="Remover foto">×</button></div></div>
        <?php endforeach; ?></div>
        <div class="actions"><button class="btn-ghost sm" type="button" id="btn-gal"><?= icon('plus') ?> Adicionar fotos</button><span class="muted" id="gal-status" style="font-size:13px"></span></div>
        <input type="file" id="gal-file" accept="image/jpeg,image/png,image/webp" multiple style="display:none">
        <small class="muted">JPG, PNG ou WebP, até 10 MB cada. Pode escolher várias de uma vez.</small>
      </div>
    </div>

    <div>
      <div class="card">
        <h2>Publicação</h2>
        <label class="chk" style="margin-top:12px"><input type="checkbox" name="active" value="1" <?= $p['active'] ? 'checked' : '' ?>><span>Visível no site<small>Desmarque para esconder sem apagar.</small></span></label>
        <div class="actions"><button class="btn" type="submit">Salvar</button></div>
      </div>
      <div class="card">
        <h2>Foto principal</h2>
        <p class="sub">A imagem do cartão na página da frota.</p>
        <div class="fl-main"><img id="main-prev" src="<?= e($p['image'] ? fleet_img_url($p['image']) : 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7') ?>" alt=""></div>
        <input type="hidden" name="image" id="main-path" value="<?= e($p['image']) ?>">
        <div class="actions"><button class="btn-ghost sm" type="button" id="btn-main">Enviar foto</button><span class="muted" id="main-status" style="font-size:13px"></span></div>
        <input type="file" id="main-file" accept="image/jpeg,image/png,image/webp" style="display:none">
        <small class="muted"><b>Melhor resultado:</b> PNG com o veículo recortado (fundo transparente). O painel centraliza, ajusta o tamanho para ficar igual ao dos outros veículos e alinha ao chão. Foto comum (com fundo) é apenas cortada no formato do cartão.</small>
      </div>
    </div>
  </div>
</form>
<?php view_footer();
