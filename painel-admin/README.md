# Painel Braslectra — CRM de leads, blog, configurações e scripts

Painel administrativo em **PHP 8+ puro** (sem framework, sem Composer) com banco **SQLite** (um arquivo, sem instalar MySQL).

## O que tem

| Área | Para quê |
|---|---|
| **Leads (mini CRM)** | Cada formulário do site cai numa *origem* separada (Turismo, Fretamento, Executivo, Contato, Trabalhe Conosco…). Filtros, busca, status (Novo → Em atendimento → Proposta → Fechado/Perdido), anotações, botão de WhatsApp, exportar CSV, download de currículo. |
| **Blog** | Os 20 artigos do site antigo já vêm importados e editáveis (editor visual, capa, SEO, rascunho/agendamento). |
| **Configurações** | Telefones, WhatsApp, e-mails, endereços, redes sociais, frase do rodapé, números (anos/colaboradores), link dos cursos. O site se atualiza sozinho. |
| **Notificações** | E-mail via **API da Brevo** a cada lead novo, com destinatários padrão e por origem. |
| **Scripts e rastreamento** | GTM, GA4, Pixel da Meta, Google Ads + códigos personalizados (head/body). Dispara `generate_lead` / `Lead` automaticamente. |
| **Usuários** | Administrador (tudo) e colaboradores com acessos escolhidos: só Blog, só Scripts, só Configurações, leads de **todas** as origens ou **somente** de algumas (ex.: só Turismo e Fretamento). |

## Requisitos
PHP **8.0+** com `pdo_sqlite`, `mbstring`, `curl`, `fileinfo`, `dom` (todos vêm ativos na maioria das hospedagens). Apache (`.htaccess` incluído) ou Nginx.

## Instalação
1. Envie a pasta `painel-admin/` para o servidor (ex.: `https://www.braslectra.com.br/painel-admin/`).
2. Dê permissão de escrita ao PHP em `data/` e `uploads/`.
3. Abra `/painel-admin/` no navegador → cria o **administrador** (e importa os artigos do site antigo).
4. Entre em **Configurações** e preencha *Notificações* (chave da Brevo, e-mail remetente validado, destinatários).

### Segurança em produção (importante)
- Use **HTTPS**. (No `.htaccess`, descomente o redirecionamento.)
- A pasta `data/` guarda o banco e os currículos. No Apache ela já é bloqueada por `.htaccess`. **No Nginx** adicione:
  ```nginx
  location ~ ^/painel-admin/(data|inc|tools)/ { deny all; }
  location ~* ^/painel-admin/uploads/.*\.(php|phtml|phar)$ { deny all; }
  ```
- O painel mostra um **aviso vermelho** na Visão geral se detectar que `data/` está acessível.
- Ideal: mover o banco e os currículos para fora da pasta pública. Crie `inc/config.local.php`:
  ```php
  <?php return [
    'db_path'     => '/home/USUARIO/painel-dados/painel.sqlite',
    'private_dir' => '/home/USUARIO/painel-dados/private',
    'site_url'    => 'https://www.braslectra.com.br',
  ];
  ```
- **Backup:** copie `data/painel.sqlite` (ou o caminho definido) e a pasta `uploads/` regularmente.

## Ligando o site
No site, inclua uma vez (já está nas 18 páginas do layout via `braslectra-config.js`):
```html
<script src="/painel-admin/api/embed.js.php" async></script>
```
Isso:
- troca os dados marcados com `data-site-*` (telefone, e-mail, endereço…);
- instala GTM/Pixel/GA4/códigos do painel;
- expõe `window.BraslectraLeads.submit(form, 'origem')`.

### Formulários
```js
BraslectraLeads.submit(formElement, 'turismo')   // 'contato', 'executivo', 'fretamento', 'turismo', 'rodoviario', 'trabalhe-conosco'…
  .then(() => mostrarSucesso())
  .catch((err) => mostrarErro(err.message));
```
Os campos são lidos pelo texto do `<label>` (Nome, E-mail, WhatsApp, Empresa, Mensagem; o resto vira “campos extras” do lead). Para o Trabalhe Conosco, o arquivo (`input[type=file]`) vai como currículo (PDF/DOC/DOCX, até 5 MB).
Proteções: honeypot, tempo mínimo, limite de envios por IP, validação e `source` desconhecido cai em “Outros”.

Para criar uma **nova origem** (ex.: landing page): *Configurações → Origens dos leads → Criar nova origem* e use o código gerado como `source`.

### Marcação de dados do site
```html
<a data-site-tel="phone_main"></a>         <!-- link tel: + número -->
<a data-site-wa="whatsapp">WhatsApp</a>    <!-- link wa.me -->
<a data-site-mail="email_orcamento"></a>   <!-- link mailto: -->
<span data-site="address_macae"></span>    <!-- troca o texto -->
<a data-site-href="social_instagram"></a>  <!-- troca o href -->
```
Chaves: `company_name, phone_main, whatsapp, email_orcamento, email_atendimento, email_rh, email_vagas, address_macae, address_rio, support_cities, social_instagram, social_facebook, social_linkedin, footer_text, stat_years, stat_employees, url_courses`.

### Blog (API pública)
- `GET /painel-admin/api/blog.php?page=1&per=9&q=termo` → lista
- `GET /painel-admin/api/blog.php?slug=meu-artigo` → artigo completo (+ anterior/próximo)
- `GET /painel-admin/api/site.php` → dados de contato em JSON

As páginas `Blog.dc.html` e `Blog-Post.dc.html` do layout já consomem essa API (com fallback para o conteúdo de exemplo).

Se o **site estiver em outro domínio** que o painel, informe-o em *Configurações → Sites autorizados* (CORS) e defina `window.BRASLECTRA_ADMIN_URL` antes do `braslectra-config.js`.

## Imagens do site antigo
Imagens que ainda apontam para `braslectra.com.br/wp-content/uploads/…` aparecem no **Blog** com o botão **“Baixar agora”** (ou `php tools/import_blog.php images`). Faça isso **antes de desligar o site antigo**.

## Estrutura
```
api/        endpoints públicos (lead, blog, site, embed.js.php)
assets/     CSS/JS do painel
inc/        núcleo (banco, auth, sanitização, Brevo…)
tools/      importação do blog (CLI) e blog_seed.json
data/       banco SQLite e currículos (protegida)
uploads/    imagens do blog (públicas)
```
