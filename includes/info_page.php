<?php
/**
 * Estrutura comum das páginas institucionais e legais
 * (Quem Somos, FAQ, Trocas, Privacidade, Termos).
 *
 * Uso:
 *   $infoPage = ['slug' => 'faq', 'title' => '...', 'eyebrow' => '...', 'intro' => '...', 'updated' => '09/11/2026'];
 *   require __DIR__ . '/includes/info_page.php';   // abre a página
 *   ... conteúdo ...
 *   require __DIR__ . '/includes/info_page_end.php'; // fecha a página
 *
 * Trechos marcados com <mark class="todo"> são pendências que a loja precisa
 * definir ANTES de publicar a página.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ui.php';

/** Páginas listadas no menu lateral: slug => [arquivo, rótulo]. */
function infoPages(): array
{
    return [
        'quem-somos'  => ['quem-somos.php', 'Quem somos'],
        'faq'         => ['faq.php', 'Perguntas frequentes'],
        'trocas'      => ['trocas-e-devolucoes.php', 'Trocas e devoluções'],
        'privacidade' => ['privacidade.php', 'Política de privacidade'],
        'termos'      => ['termos-de-uso.php', 'Termos de uso'],
    ];
}

/** Dados da empresa usados nos textos legais (Decreto 7.962/2013, art. 2º). */
function mmsCompany(): array
{
    return [
        'razao'      => 'Duleyme Kely Oliveira de Aquino Costa',
        'fantasia'   => "MM'S Boutique",
        'cnpj'       => '45.913.840/0001-83',
        'endereco'   => 'Av. Ruy Pereira dos Santos, 258, Loja 005, Olho D\'Água, São Gonçalo do Amarante/RN, CEP 59295-444',
        'email'      => 'contato@mmsboutique.com.br',
        'whats'      => '(84) 99900-1179',
        'whats_link' => 'https://wa.me/5584999001179',
        'site'       => 'mmsboutique.com.br',
    ];
}

$infoPage = array_merge(['slug' => '', 'title' => '', 'eyebrow' => "MM'S Boutique", 'intro' => '', 'updated' => ''], $infoPage ?? []);
$pageTitle = $infoPage['title'];
require __DIR__ . '/site_head.php';
$co = mmsCompany();
?>
<div class="crumbs" style="padding:20px 0 0;"><a href="index.php">Início</a><span>›</span><b><?= mmsH($infoPage['title']) ?></b></div>

<div class="page-head">
  <span class="eyebrow"><?= mmsH($infoPage['eyebrow']) ?></span>
  <h1><?= mmsH($infoPage['title']) ?></h1>
  <?php if ($infoPage['intro']): ?><p><?= mmsH($infoPage['intro']) ?></p><?php endif; ?>
</div>
<hr>

<div class="info-layout">
  <nav class="info-nav" aria-label="Páginas de ajuda">
    <?php foreach (infoPages() as $slug => [$file, $label]): ?>
      <a href="<?= $file ?>"<?= $slug === $infoPage['slug'] ? ' class="on" aria-current="page"' : '' ?>><?= mmsH($label) ?></a>
    <?php endforeach; ?>
    <a href="<?= mmsH($co['whats_link']) ?>" target="_blank" rel="noopener" class="info-nav-cta">Falar no WhatsApp →</a>
  </nav>
  <article class="info-body">
