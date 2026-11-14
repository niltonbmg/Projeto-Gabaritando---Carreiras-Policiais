<?php
// meu-plano.php
// O "Meu plano" e a Missão do dia agora são o cronograma (uma fonte só): esta página só redireciona.
declare(strict_types=1);

$qs = isset($_GET['curso_id']) ? '?curso_id=' . (int)$_GET['curso_id'] : '';
header('Location: meu-cronograma.php' . $qs, true, 301);
exit;
