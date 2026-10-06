<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login();

view_header(['title' => 'Visão geral', 'active' => 'dashboard']);

$hasLeads = can('leads');
if (is_admin() && setting('notify_enabled') !== '1') {
    echo '<div class="hint"><b>Notificações de lead desativadas.</b> Configure o e-mail que recebe os avisos em <a href="' . e(url('settings.php#notificacoes')) . '">Configurações → Notificações</a>.</div>';
}

if (is_admin()) {
    echo '<div class="hint" id="sec-check" data-url="' . e(url('data/index.html')) . '" style="display:none;border-color:#B42318;background:#FEF3F2"><b>Atenção de segurança:</b> a pasta <code>data/</code> (banco de dados e currículos) está acessível pela internet. Bloqueie o acesso no servidor (veja o README) ou mova-a para fora da pasta pública com <code>db_path</code> / <code>private_dir</code> em <code>inc/config.local.php</code>.</div>';
}

if ($hasLeads) {
    [$w, $p] = lead_scope_sql();
    $today = date('Y-m-d');
    $count = static fn (string $extra, array $params = []) => (int) scalar("SELECT COUNT(*) FROM leads WHERE $w AND $extra", array_merge($p, $params));
    $nToday = $count('date(created_at) = ?', [$today]);
    $n7 = $count('created_at >= ?', [date('Y-m-d 00:00:00', strtotime('-6 days'))]);
    $n30 = $count('created_at >= ?', [date('Y-m-d 00:00:00', strtotime('-29 days'))]);
    $nNew = $count("status = 'novo'");

    $series = [];
    foreach (rows("SELECT date(created_at) d, COUNT(*) c FROM leads WHERE $w AND created_at >= ? GROUP BY d", array_merge($p, [date('Y-m-d 00:00:00', strtotime('-13 days'))])) as $r) {
        $series[$r['d']] = (int) $r['c'];
    }
    $max = max(1, $series ? max($series) : 1);

    $bySource = rows("SELECT source, COUNT(*) c FROM leads WHERE $w AND created_at >= ? GROUP BY source ORDER BY c DESC", array_merge($p, [date('Y-m-d 00:00:00', strtotime('-29 days'))]));
    $maxS = max(1, $bySource ? (int) $bySource[0]['c'] : 1);
    $recent = rows("SELECT * FROM leads WHERE $w ORDER BY id DESC LIMIT 8", $p);
    ?>
<div class="grid g4" style="margin-bottom:18px">
  <div class="stat hl"><div class="n"><?= $nNew ?></div><div class="l">Leads novos (sem atendimento)</div></div>
  <div class="stat"><div class="n"><?= $nToday ?></div><div class="l">Hoje</div></div>
  <div class="stat"><div class="n"><?= $n7 ?></div><div class="l">Últimos 7 dias</div></div>
  <div class="stat"><div class="n"><?= $n30 ?></div><div class="l">Últimos 30 dias</div></div>
</div>

<div class="layout-2">
  <div>
    <div class="card">
      <h2>Leads por dia</h2><p class="sub">Últimos 14 dias</p>
      <div class="bars">
        <?php for ($i = 13; $i >= 0; $i--):
            $d = date('Y-m-d', strtotime("-$i days"));
            $c = $series[$d] ?? 0; ?>
          <div class="bar" title="<?= e(fmt_date($d, false)) ?>: <?= $c ?>">
            <b><?= $c ?: '' ?></b><i style="height:<?= max(3, (int) round(($c / $max) * 100)) ?>%"></i><span><?= e(date('d', strtotime($d))) ?></span>
          </div>
        <?php endfor; ?>
      </div>
    </div>
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px"><h2>Últimos leads</h2><a class="btn-ghost sm" href="<?= e(url('leads.php')) ?>">Ver todos</a></div>
      <?php if (!$recent): ?><div class="empty">Nenhum lead ainda. Assim que o primeiro formulário for enviado ele aparece aqui.</div><?php else: ?>
      <div class="tbl-wrap" style="margin-top:14px"><table class="tbl compact"><tbody>
        <?php foreach ($recent as $l): ?>
          <tr class="<?= $l['status'] === 'novo' ? 'is-new' : '' ?>">
            <td><a class="t-main" href="<?= e(url('lead.php?id=' . $l['id'])) ?>"><?= e($l['name'] ?: '(sem nome)') ?></a><div class="t-sub"><?= e($l['phone'] ?: $l['email']) ?></div></td>
            <td><span class="src"><?= e(source_label($l['source'])) ?></span></td>
            <td><?= status_badge($l['status']) ?></td>
            <td class="right muted nowrap"><?= e(time_ago($l['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody></table></div>
      <?php endif; ?>
    </div>
  </div>
  <div>
    <div class="card">
      <h2>Por origem</h2><p class="sub">Últimos 30 dias</p>
      <?php if (!$bySource): ?><div class="empty" style="padding:16px">Sem dados.</div><?php else: ?>
      <div class="srclist">
        <?php foreach ($bySource as $s): ?>
          <a class="srcrow" href="<?= e(url('leads.php?source=' . rawurlencode($s['source']))) ?>" style="text-decoration:none;color:inherit">
            <span class="nm"><?= e(source_label($s['source'])) ?></span>
            <span class="tr"><i style="width:<?= max(4, (int) round(((int) $s['c'] / $maxS) * 100)) ?>%"></i></span>
            <span class="ct"><?= (int) $s['c'] ?></span>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  <?php } else { ?>
<div class="layout-2">
  <div><div class="card"><h2>Olá, <?= e(explode(' ', $u['name'])[0]) ?>!</h2><p class="sub">Use o menu ao lado para acessar as áreas liberadas para o seu usuário.</p></div></div>
  <div><?php } ?>

    <?php if (can('blog')):
        $np = (int) scalar("SELECT COUNT(*) FROM posts WHERE status = 'published'");
        $nd = (int) scalar("SELECT COUNT(*) FROM posts WHERE status = 'draft'"); ?>
    <div class="card">
      <h2>Blog</h2><p class="sub"><?= $np ?> publicado<?= $np === 1 ? '' : 's' ?> · <?= $nd ?> rascunho<?= $nd === 1 ? '' : 's' ?></p>
      <div class="actions"><a class="btn sm" href="<?= e(url('blog_edit.php')) ?>"><?= icon('plus') ?> Novo artigo</a><a class="btn-ghost sm" href="<?= e(url('blog.php')) ?>">Gerenciar</a></div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php view_footer();
