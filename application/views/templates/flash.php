<?php foreach (array('ok' => 'success', 'err' => 'danger') as $k => $cls): ?>
<?php if ($msg = $this->session->flashdata($k)): ?>
<div class="alert alert-<?= $cls ?> small"><?= html_escape($msg) ?></div>
<?php endif; ?>
<?php endforeach; ?>
