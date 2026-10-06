# Grupo Braslectra — site novo

Site em páginas `.dc.html` (layout criado no Claude Design) + **painel admin em PHP** (`painel-admin/`).

```
index.html                 redireciona para a Home
Braslectra Home.dc.html    Home (e demais páginas *.dc.html: Sobre, Frota, Serviços, Blog, Contato…)
support.js / image-slot.js runtime das páginas (não apagar)
braslectra-config.js       liga o site ao painel (detecta sozinho ./painel-admin)
midia/  uploads/           imagens usadas pelas páginas
painel-admin/              CRM de leads, blog, configurações, scripts, usuários (PHP 8 + SQLite)
```

## Rodar localmente
Só o site: abra `Braslectra Home.dc.html` no Chrome.
Site + painel juntos (precisa de PHP 8): `php -S 127.0.0.1:8000 -t .` e abra http://127.0.0.1:8000/

## Publicar (cPanel)
1. Coloque o conteúdo desta pasta **dentro do `public_html`** (ex.: `public_html/novosite/`). Pastas fora do `public_html` não são acessíveis pela internet.
2. Não publique a pasta `.git` dentro do `public_html` (ou bloqueie o acesso a ela).
3. Em "Selecionar versão do PHP": 8.0 ou superior (`pdo_sqlite`, `curl`, `fileinfo`, `mbstring`).
4. Abra `/novosite/painel-admin/` → cria o administrador. Detalhes em `painel-admin/README.md`.

O site funciona sem o painel, mas formulários, blog dinâmico, dados de contato e rastreamento dependem dele.

## SEO e endereços (`.htaccess`)
- URLs amigáveis: `/sobre`, `/frota`, `/frota-vans`, `/servicos-turismo`, `/blog-post?slug=…` etc. (os arquivos `.dc.html` continuam os mesmos; quem acessa o `.dc.html` é redirecionado com 301 para a URL amigável).
- Redirecionamentos 301 das URLs do WordPress antigo (páginas, landing pages de SEO local e os 20 artigos do blog) para as páginas novas — preserva o ranqueamento no Google.
- Funciona na raiz do domínio e em subpasta (`/novosite/`). Testado em Apache 2.4.
- Cada página tem título, descrição, canonical e Open Graph; a Home tem dados estruturados (JSON-LD) da empresa.
- `sitemap.xml` e `robots.txt` na raiz. Depois de publicar no domínio final, envie `https://braslectra.com.br/sitemap.xml` no Google Search Console.
- Para ativar HTTPS obrigatório, descomente as 2 linhas indicadas no `.htaccess`.
- Se algo der errado com os endereços, renomeie `.htaccess` para `.htaccess-off` — o site volta a funcionar pelos nomes `.dc.html`.
