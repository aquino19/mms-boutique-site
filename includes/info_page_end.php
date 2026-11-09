<?php
/** Fecha a estrutura aberta por includes/info_page.php. */
if (!empty($infoPage['updated'])): ?>
    <p class="info-updated">Última atualização: <?= mmsH($infoPage['updated']) ?></p>
<?php endif; ?>
  </article>
</div>
<?php require __DIR__ . '/site_foot.php'; ?>
