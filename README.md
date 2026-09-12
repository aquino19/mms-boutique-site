# MM'S Boutique — Site + Admin

Repositório do site da MM'S Boutique: home institucional, loja pública (catálogo/carrinho/checkout) e painel de admin do lojista.

## Estrutura de pastas

```
mms-boutique-home.html      → Página inicial (estática, sem PHP)
/admin                      → Painel do lojista (login, produtos, pedidos, configurações)
  /includes
    config.example.php      → Copie para config.php e preencha com dados reais do banco
    config.php              → NÃO versionado (está no .gitignore) — dados reais do banco
/public                     → Site que o cliente final vê (catálogo, carrinho, checkout)
  /includes
    config.example.php      → Mesma lógica do admin
    config.php               → NÃO versionado
    site_head.php            → Cabeçalho/identidade visual compartilhada entre as páginas
    site_foot.php            → Rodapé compartilhado
/database
  mms-boutique-schema.sql    → Script completo para criar o banco do zero
  migration-multi-loja.sql   → Script para atualizar um banco já existente (retirada/entrega por loja)
/docs                        → Documentação de arquitetura, schema e specs de pagamento
```

## Como rodar localmente (para desenvolvimento/testes)

Requisitos: PHP 8+, MySQL/MariaDB.

```bash
# 1. Criar o banco
mysql -u root -e "CREATE DATABASE mms_boutique;"
mysql -u root mms_boutique < database/mms-boutique-schema.sql

# 2. Configurar credenciais
cp admin/includes/config.example.php admin/includes/config.php
cp public/includes/config.example.php public/includes/config.php
# edite os dois arquivos com usuário/senha do seu MySQL local

# 3. Criar o primeiro usuário admin (rode isso uma vez, ajuste o e-mail/senha)
php -r "echo password_hash('sua_senha', PASSWORD_DEFAULT);"
# copie o hash gerado e rode no MySQL:
# INSERT INTO admin_users (email, password_hash) VALUES ('seu@email.com', 'HASH_AQUI');

# 4. Subir os servidores locais (em terminais separados)
cd admin && php -S 127.0.0.1:8899
cd public && php -S 127.0.0.1:8898
```

## Como fica em produção (Hostinger)

Mesma lógica, sem o `php -S`: os arquivos vão para o diretório do site (via FTP ou Gerenciador de Arquivos do hPanel), e o `config.php` de cada pasta aponta para o banco MySQL criado no painel da Hostinger.

## Para quem for mexer só no visual (design/UX)

Os arquivos `.php` misturam lógica (blocos `<?php ... ?>`) com HTML normal. Você pode editar livremente tudo que é HTML/CSS — cores, fontes, estrutura, espaçamento. **Não apague nem altere o conteúdo dentro dos blocos `<?php ... ?>`** — é o que busca os dados reais (produtos, preços, estoque) do banco. Na dúvida se algo é conteúdo fixo ou dado dinâmico, pergunte antes de mudar.

## Documentos de referência

- `docs/arquitetura-completa-loja.md` — visão geral da arquitetura (hospedagem, VPS, stack)
- `docs/schema-banco-loja.md` — detalhamento das tabelas do banco
- `docs/spec-adaptadores-gateway-pagamento.md` — especificação dos adaptadores Mercado Pago/PagSeguro
- `docs/mapa-projeto-mms.md` — status geral do projeto, o que falta, percentual de conclusão
