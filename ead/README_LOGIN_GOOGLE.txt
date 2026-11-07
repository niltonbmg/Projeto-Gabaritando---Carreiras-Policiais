LOGIN + BANCO PRONTO - PROJETO GABARITANDO

Banco configurado nos arquivos:
- DB_HOST=localhost
- DB_DATABASE=u427032381_gabaritando
- DB_USERNAME=u427032381_gabaritando
- DB_PASSWORD=senha informada no chat

ARQUIVOS PRINCIPAIS
RAIZ DO PROJETO
- login.php
- login-web.php
- login-mobile.php
- cadastro.php
- .env
- .env.example
- README_LOGIN_GOOGLE.txt

PASTA api/
- api/conexao.php
- api/authenticador.php
- api/processa_cadastro.php

PASTA auth/
- auth/config_google.php
- auth/google.php
- auth/google_callback.php

PASTA includes/
- includes/barra-superior.php

PASTA database/
- database/usuarios.sql
- database/instalar_gabaritando.sql

ORDEM NA HOSTINGER
1) Criar o banco MySQL com o nome: u427032381_gabaritando
2) Criar/vincular o usuário MySQL: u427032381_gabaritando
3) Colocar a senha informada.
4) Abrir o phpMyAdmin do banco u427032381_gabaritando.
5) Importar database/usuarios.sql.
   Se estiver importando fora do banco selecionado, pode usar database/instalar_gabaritando.sql.
6) Enviar os arquivos do projeto para a hospedagem mantendo as pastas.
7) O arquivo .env já está preenchido com o banco. Só falta preencher GOOGLE_CLIENT_ID e GOOGLE_CLIENT_SECRET.

GOOGLE LOGIN
No Google Cloud Console, crie uma credencial OAuth 2.0 do tipo Aplicativo da Web.
Cadastre esta URL em URI de redirecionamento autorizado:
https://SEU-DOMINIO.com.br/auth/google_callback.php

Depois cole no .env:
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...

OBSERVAÇÕES
- O login salva a sessão em $_SESSION['usuario'], $_SESSION['usuario_id'], $_SESSION['usuario_nome'] e $_SESSION['usuario_email'].
- As telas continuam acessíveis enquanto o projeto ainda está sendo criado.
- Quando quiser proteger alguma página, coloque no topo dela:

require_once __DIR__ . '/api/authenticador.php';
auth_require_login();

SE DER ERRO DE BANCO
- Confira se o banco foi criado exatamente como u427032381_gabaritando.
- Confira se o usuário do banco também é u427032381_gabaritando.
- Na Hostinger, normalmente DB_HOST é localhost. Se a Hostinger mostrar outro host MySQL no painel, substitua DB_HOST no .env.
