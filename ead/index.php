<?php
// Segurança extra caso o servidor ignore o .htaccess.
header('Location: login', true, 302);
exit;
