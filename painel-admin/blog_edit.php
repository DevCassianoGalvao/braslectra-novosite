<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_perm('blog');

$id = (int) ($_GET['id'] ?? 0);
$post = $id ? row('SELECT * FROM posts WHERE id = ?', [$id]) : null;
if ($id && !$post) {
    flash('err', 'Artigo não encontrado.');
    redirect('blog.php');
}
$p = $post ?: [
    'id' => 0, 'title' => '', 'slug' => '', 'excerpt' => '', 'content' => '', 'image' => '', 'status' => 'draft',
    'seo_title' => '', 'seo_description' => '', 'published_at' => null,
];
$errors = [];

if (is_post()) {
    csrf_check();
    $p['title'] = mb_substr(post('title'), 0, 200);
    $p['slug'] = slugify(post('slug') !== '' ? post('slug') : $p['title']);
    $p['excerpt'] = mb_substr(post('excerpt'), 0, 400);
    $p['image'] = mb_substr(post('image'), 0, 500);
    $p['seo_title'] = mb_substr(post('seo_title'), 0, 120);
    $p['seo_description'] = mb_substr(post('seo_description'), 0, 200);
    $p['status'] = post('status') === 'published' ? 'published' : 'draft';
    $p['content'] = sanitize_html((string) ($_POST['content'] ?? ''));
    $when = post('published_at');
    $p['published_at'] = $when !== '' && strtotime($when) ? date('Y-m-d H:i:s', strtotime($when)) : null;

    // imagem: aceita caminho enviado pelo painel (uploads/…) ou URL https
    if ($p['image'] !== '' && !preg_match('#^(https://|uploads/)#', $p['image'])) {
        $errors[] = 'A imagem de capa deve ser enviada pelo painel ou ser um endereço https://.';
    }
    if ($p['title'] === '') {
        $errors[] = 'Informe o título.';
    }
    if (trim(strip_tags($p['content'])) === '' && stripos($p['content'], '<img') === false) {
        $errors[] = 'O artigo está sem conteúdo.';
    }
    // slug único
    $base = $p['slug'];
    $n = 2;
    while (scalar('SELECT 1 FROM posts WHERE slug = ? AND id != ?', [$p['slug'], $p['id']])) {
        $p['slug'] = $base . '-' . $n++;
    }
    if ($p['status'] === 'published' && !$p['published_at']) {
        $p['published_at'] = now();
    }
    if (!$errors) {
        if ($excerptEmpty = ($p['excerpt'] === '')) {
            $p['excerpt'] = truncate($p['content'], 180);
        }
        if ($p['id']) {
            q(
                'UPDATE posts SET title=?, slug=?, excerpt=?, content=?, image=?, status=?, seo_title=?, seo_description=?, published_at=?, updated_at=? WHERE id=?',
                [$p['title'], $p['slug'], $p['excerpt'], $p['content'], $p['image'], $p['status'], $p['seo_title'], $p['seo_description'], $p['published_at'], now(), $p['id']]
            );
        } else {
            q(
                'INSERT INTO posts (title, slug, excerpt, content, image, status, seo_title, seo_description, published_at, author_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$p['title'], $p['slug'], $p['excerpt'], $p['content'], $p['image'], $p['status'], $p['seo_title'], $p['seo_description'], $p['published_at'], $u['id'], now(), now()]
            );
            $p['id'] = insert_id();
        }
        flash('ok', $p['status'] === 'published' ? 'Artigo publicado.' : 'Rascunho salvo.');
        redirect('blog_edit.php?id=' . $p['id']);
    }
}

$GLOBALS['extra_scripts'] = ['assets/editor.js'];
view_header([
    'title'   => $p['id'] ? 'Editar artigo' : 'Novo artigo',
    'active'  => 'blog',
    'actions' => '<a class="btn-ghost sm" href="' . e(url('blog.php')) . '">← Voltar</a>',
]);
foreach ($errors as $er) {
    echo '<div class="flash flash-err">' . e($er) . '</div>';
}
$dt = $p['published_at'] ? date('Y-m-d\TH:i', strtotime($p['published_at'])) : '';
?>
<form method="post" id="post-form" data-upload="<?= e(url('upload.php')) ?>">
  <?= csrf_field() ?>
  <div class="layout-2">
    <div>
      <div class="card">
        <label class="f"><span class="h">Título</span><input type="text" name="title" value="<?= e($p['title']) ?>" maxlength="200" required data-slug-from="#slug" style="font-size:1.2rem;font-weight:700"></label>
        <label class="f"><span class="h">Resumo <small style="display:inline">(aparece na lista do blog; se vazio, é gerado do texto)</small></span><textarea name="excerpt" rows="2" maxlength="400"><?= e($p['excerpt']) ?></textarea></label>
        <span class="h" style="display:block;font-weight:700;font-size:13px;margin-bottom:6px">Conteúdo</span>
        <div class="editor-bar" id="editor-bar">
          <button type="button" data-cmd="bold" title="Negrito"><b>N</b></button>
          <button type="button" data-cmd="italic" title="Itálico"><i>I</i></button>
          <button type="button" data-cmd="underline" title="Sublinhado"><u>S</u></button>
          <span class="sep"></span>
          <button type="button" data-block="h2" title="Título">H2</button>
          <button type="button" data-block="h3" title="Subtítulo">H3</button>
          <button type="button" data-block="p" title="Parágrafo">¶</button>
          <span class="sep"></span>
          <button type="button" data-cmd="insertUnorderedList" title="Lista">• Lista</button>
          <button type="button" data-cmd="insertOrderedList" title="Lista numerada">1. Lista</button>
          <button type="button" data-block="blockquote" title="Citação">“ ”</button>
          <span class="sep"></span>
          <button type="button" id="btn-link" title="Link">Link</button>
          <button type="button" data-cmd="unlink" title="Remover link">Sem link</button>
          <button type="button" id="btn-img" title="Inserir imagem">Imagem</button>
          <button type="button" data-cmd="insertHorizontalRule" title="Linha">—</button>
          <span class="sep"></span>
          <button type="button" data-cmd="removeFormat" title="Limpar formatação">Limpar</button>
          <button type="button" id="btn-src" title="Editar HTML">&lt;/&gt;</button>
        </div>
        <div class="editor-area" id="editor" contenteditable="true"><?= $p['content'] /* já sanitizado */ ?></div>
        <textarea class="code editor-src" id="editor-src" style="display:none" spellcheck="false"></textarea>
        <textarea name="content" id="content" style="display:none"></textarea>
        <input type="file" id="img-file" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none">
        <p class="muted" style="margin:8px 0 0;font-size:12.5px">Dica: cole texto do Word/Google Docs — a formatação é limpa ao salvar. Imagens: até 6 MB.</p>
      </div>
      <div class="card">
        <h2>SEO (Google)</h2><p class="sub">Opcional. Se vazio, usamos o título e o resumo.</p>
        <label class="f"><span class="h">Título para o Google</span><input type="text" name="seo_title" maxlength="120" value="<?= e($p['seo_title']) ?>"></label>
        <label class="f"><span class="h">Descrição para o Google</span><textarea name="seo_description" rows="2" maxlength="200"><?= e($p['seo_description']) ?></textarea></label>
      </div>
    </div>
    <div>
      <div class="card">
        <h2>Publicação</h2>
        <label class="f" style="margin-top:12px"><span class="h">Status</span>
          <select name="status"><option value="draft" <?= $p['status'] === 'draft' ? 'selected' : '' ?>>Rascunho</option><option value="published" <?= $p['status'] === 'published' ? 'selected' : '' ?>>Publicado</option></select>
        </label>
        <label class="f"><span class="h">Data de publicação</span><input type="datetime-local" name="published_at" value="<?= e($dt) ?>"><small>Vazio = agora, ao publicar. Data futura agenda o artigo.</small></label>
        <label class="f"><span class="h">Endereço (slug)</span><input type="text" name="slug" id="slug" value="<?= e($p['slug']) ?>" maxlength="80"></label>
        <div class="actions"><button class="btn" type="submit">Salvar</button></div>
      </div>
      <div class="card">
        <h2>Imagem de capa</h2>
        <img class="thumb" id="cover-prev" src="<?= e($p['image'] ? post_img_src($p['image']) : 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7') ?>" alt="" style="margin:12px 0">
        <input type="hidden" name="image" id="cover" value="<?= e($p['image']) ?>">
        <div class="actions"><button class="btn-ghost sm" type="button" id="btn-cover">Enviar imagem</button><button class="btn-ghost sm" type="button" id="btn-cover-rm">Remover</button></div>
        <input type="file" id="cover-file" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none">
        <small>Recomendado: 1200 × 675 px (16:9).</small>
      </div>
    </div>
  </div>
</form>
<?php view_footer();
