<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/blog_import.php';
$u = require_perm('blog');

if (is_post() && post('action') === 'fetch_images' && is_admin()) {
    csrf_check();
    @set_time_limit(120);
    $r = fetch_remote_blog_images(15);
    flash($r['failed'] && !$r['done'] ? 'err' : 'ok', $r['done'] . ' imagem(ns) baixada(s) para o painel' . ($r['failed'] ? ', ' . $r['failed'] . ' falhou/falharam (o site antigo pode estar fora do ar)' : '') . '. Restam ' . $r['remaining'] . '.');
    redirect('blog.php');
}

// Excluir
if (is_post() && post('action') === 'delete') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id && row('SELECT id FROM posts WHERE id = ?', [$id])) {
        q('DELETE FROM posts WHERE id = ?', [$id]);
        flash('ok', 'Artigo excluído.');
    }
    redirect('blog.php');
}

$status = (string) ($_GET['status'] ?? '');
$term = trim((string) ($_GET['q'] ?? ''));
$where = '1=1';
$params = [];
if (in_array($status, ['published', 'draft'], true)) {
    $where .= ' AND status = ?';
    $params[] = $status;
} else {
    $status = '';
}
if ($term !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
    $where .= " AND (title LIKE ? ESCAPE '\\' OR slug LIKE ? ESCAPE '\\')";
    array_push($params, $like, $like);
}
$total = (int) scalar("SELECT COUNT(*) FROM posts WHERE $where", $params);
$pg = pagination($total, (int) ($_GET['p'] ?? 1), 20);
$list = rows("SELECT * FROM posts WHERE $where ORDER BY COALESCE(published_at, created_at) DESC, id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
$nPub = (int) scalar("SELECT COUNT(*) FROM posts WHERE status = 'published'");
$nDraft = (int) scalar("SELECT COUNT(*) FROM posts WHERE status = 'draft'");

view_header([
    'title'   => 'Blog',
    'active'  => 'blog',
    'actions' => '<a class="btn sm" href="' . e(url('blog_edit.php')) . '">' . icon('plus') . ' Novo artigo</a>',
]);
?>
<div class="chips">
  <a class="chip <?= $status === '' ? 'on' : '' ?>" href="<?= e(query_with(['status' => '', 'p' => ''])) ?>">Todos <em><?= $nPub + $nDraft ?></em></a>
  <a class="chip <?= $status === 'published' ? 'on' : '' ?>" href="<?= e(query_with(['status' => 'published', 'p' => ''])) ?>">Publicados <em><?= $nPub ?></em></a>
  <a class="chip <?= $status === 'draft' ? 'on' : '' ?>" href="<?= e(query_with(['status' => 'draft', 'p' => ''])) ?>">Rascunhos <em><?= $nDraft ?></em></a>
</div>
<form class="filters" method="get">
  <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
  <input type="search" name="q" value="<?= e($term) ?>" placeholder="Buscar por título…">
  <button class="btn dark sm" type="submit"><?= icon('search') ?> Buscar</button>
</form>

<?php if (!$list): ?>
  <div class="card empty">Nenhum artigo encontrado.</div>
<?php else: ?>
<div class="tbl-wrap">
  <table class="tbl">
    <thead><tr><th>Artigo</th><th>Status</th><th>Publicação</th><th class="right">Ações</th></tr></thead>
    <tbody>
    <?php foreach ($list as $p): ?>
      <tr>
        <td>
          <div class="cell-flex">
            <?php if ($p['image']): ?><img class="thumb-sm" src="<?= e(post_img_src($p['image'])) ?>" alt="" loading="lazy"><?php else: ?><span class="thumb-sm"></span><?php endif; ?>
            <div><a class="t-main" href="<?= e(url('blog_edit.php?id=' . $p['id'])) ?>"><?= e($p['title']) ?></a><div class="t-sub">/<?= e($p['slug']) ?></div></div>
          </div>
        </td>
        <td><?= $p['status'] === 'published' ? '<span class="badge" style="--c:#16803C">Publicado</span>' : '<span class="badge" style="--c:#8A8377">Rascunho</span>' ?></td>
        <td class="muted nowrap"><?= e(fmt_date($p['published_at'] ?: $p['created_at'], false)) ?></td>
        <td class="right nowrap">
          <a class="btn-ghost sm" href="<?= e(url('blog_edit.php?id=' . $p['id'])) ?>">Editar</a>
          <form method="post" style="display:inline" data-confirm="Excluir o artigo “<?= e($p['title']) ?>”? Esta ação não pode ser desfeita.">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button class="btn-danger sm" type="submit" aria-label="Excluir"><?= icon('trash') ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?= pager_html($pg) ?>
<?php endif; ?>
<?php $nRemote = is_admin() ? count(remote_blog_images()) : 0; if ($nRemote): ?>
<div class="hint" style="margin-top:20px;display:flex;gap:14px;align-items:center;justify-content:space-between;flex-wrap:wrap">
  <span><b><?= $nRemote ?> imagem(ns) de artigos</b> ainda são carregadas do site antigo. Baixe para o painel antes de desligar o site antigo.</span>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="fetch_images"><button class="btn dark sm" type="submit"><?= icon('download') ?> Baixar agora</button></form>
</div>
<?php endif; ?>
<div class="hint" style="margin-top:20px">O site lê os artigos pela API pública <code><?= e(abs_url('api/blog.php')) ?></code> (lista) e <code>?slug=…</code> (artigo completo).</div>
<?php view_footer();
