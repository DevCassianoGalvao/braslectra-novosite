# Grupo Braslectra — site novo

Site em páginas `.dc.html` (layout criado no Claude Design) + **painel admin em PHP** (`painel-admin/`).

```
index.html                 redireciona para a Home
Braslectra Home.dc.html    Home (e demais páginas *.dc.html: Sobre, Frota, Serviços, Blog, Contato…)
support.js                 runtime das páginas (não apagar)
braslectra-config.js       liga o site ao painel (detecta sozinho ./painel-admin)
midia/  uploads/           imagens usadas pelas páginas
painel-admin/              CRM de leads, blog, configurações, scripts, usuários (PHP 8 + SQLite)
```

## Rodar localmente
Só o site: abra `Braslectra Home.dc.html` no Chrome.
Site + painel juntos, com as URLs amigáveis (precisa de PHP 8): `php -S 127.0.0.1:8000 router.php` e abra http://127.0.0.1:8000/

## Publicar (cPanel)
1. Coloque o conteúdo desta pasta **dentro do `public_html`** (ex.: `public_html/novosite/`). Pastas fora do `public_html` não são acessíveis pela internet.
2. Não publique a pasta `.git` dentro do `public_html` (ou bloqueie o acesso a ela).
3. Em "Selecionar versão do PHP": 8.0 ou superior (`pdo_sqlite`, `curl`, `fileinfo`, `mbstring`).
4. Abra `/novosite/painel-admin/` → cria o administrador. Detalhes em `painel-admin/README.md`.

O site funciona sem o painel, mas formulários, blog dinâmico, dados de contato e rastreamento dependem dele.

## SEO, GEO e desempenho
**Domínio oficial:** https://www.braslectra.com.br — o `.htaccess` redireciona `braslectra.com.br` e `http://` para ele (só age nesse domínio; ambientes de teste não são afetados).

- **URLs amigáveis:** `/sobre`, `/frota-vans`, `/servicos-turismo`, `/blog-post?slug=…`. Os links internos já usam essas URLs; quem acessa um `.dc.html` é redirecionado (301).
- **301 do WordPress antigo:** páginas, landing pages de SEO local e os 20 artigos.
- **Metadados:** título, descrição, canonical, Open Graph/Twitter (imagens 1200×630 em `midia/og/`) e dados estruturados JSON-LD em todas as páginas (Organization, WebSite, LocalBusiness das sedes, Service, BreadcrumbList, FAQPage; BlogPosting nos artigos). Os textos ficam em `painel-admin/inc/seo_pages.json`.
- **Renderização no servidor:** `blog.php` e `blog-post.php` entregam a lista e o texto completo dos artigos já no HTML (Google e robôs de IA que não executam JavaScript).
- **sitemap.xml** dinâmico (`sitemap.php`): páginas + artigos publicados no painel, com imagens. Envie `https://www.braslectra.com.br/sitemap.xml` no Google Search Console e no Bing Webmaster Tools.
- **GEO (IAs):** `llms.txt` (resumo da empresa para ChatGPT, Claude, Perplexity, Gemini…) e `llms-full.txt` (dinâmico, com o texto de todos os artigos). O `robots.txt` libera os robôs de IA. Se mudar telefone, endereço ou frota, atualize também o `llms.txt` e o `seo_pages.json`.
- **Perguntas frequentes** visíveis na Home (e em JSON-LD).
- **Imagens:** WebP no tamanho de exibição, lazy-load, capa com prioridade e versão mobile, miniaturas leves nas galerias. Imagens enviadas pelo painel são convertidas para WebP (máx. 1600 px) automaticamente.
- **Cache e segurança:** cabeçalhos de cache para imagens/JS e `nosniff`/`Referrer-Policy` no `.htaccess`.
- Se algo der errado com os endereços, renomeie `.htaccess` para `.htaccess-off`.

