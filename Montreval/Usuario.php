<?php
require_once "conexao.php";

function efetuarLogin()
{
    try {
        $conexao = conexao();

        if (
            isset($_POST['Email'])
            && isset($_POST['senha'])
        ) {

            // Normaliza os dados recebidos
            $email = strtolower(
                trim($_POST['Email'])
            );

            $senha = trim(
                $_POST['senha']
            );

            if (
                empty($email)
                || empty($senha)
            ) {
                echo '<div class="mensagem mensagem-erro">
                        ⚠️ Preencha todos os campos.
                      </div>';
                return;
            }

            // Define a tabela conforme o domínio
            if (
                str_ends_with(
                    $email,
                    '@montreval.com'
                )
            ) {
                $tabela = "administradores";
                $is_admin = true;
            } else {
                $tabela = "usuario";
                $is_admin = false;
            }

            // Procura o usuário pelo e-mail
            $sql = "
                SELECT *
                FROM `$tabela`
                WHERE LOWER(TRIM(`Email`)) = ?
            ";

            $stmt = $conexao->prepare($sql);

            $stmt->execute([
                $email
            ]);

            $usuario = $stmt->fetch(
                PDO::FETCH_ASSOC
            );

            // Confirma usuário e senha
            if (
                $usuario
                && strcmp(
                    $usuario['senha'],
                    $senha
                ) === 0
            ) {

                // Inicia a sessão após autenticação
                session_start();

                // Regenera o ID da sessão por segurança
                session_regenerate_id(true);

                // Dados da sessão
                $_SESSION['logado'] = true;

                $_SESSION['nome_usuario'] =
                    $usuario['Nome'];

                $_SESSION['perfil'] =
                    $is_admin
                        ? 'admin'
                        : 'usuario';

                // Guarda o ID do usuário na sessão
                if (!$is_admin) {
                    $_SESSION['ID_Usuario'] =
                        $usuario['ID'];
                }

                // Redirecionamento
                if ($is_admin) {
                    header(
                        "Location: interfaceadm.php"
                    );
                } else {
                    header(
                        "Location: bemvindo.php"
                    );
                }

                exit();

            } else {

                echo '<div class="mensagem mensagem-erro">
                        ❌ E-mail ou senha incorretos.
                      </div>';
            }
        }

    } catch (PDOException $e) {

        echo '<div class="mensagem mensagem-erro">
                ⚠️ Erro ao processar o login.
              </div>';
    }
}


function cadastrarUsuario()
{
    try {
        $conexao = conexao();

        if (
            isset(
                $_POST['Cpf'],
                $_POST['Nome'],
                $_POST['Idade'],
                $_POST['Email'],
                $_POST['Endereco'],
                $_POST['senha']
            )
        ) {

            $cpf =
                trim($_POST['Cpf']);

            $nome =
                trim($_POST['Nome']);

            $idade =
                (int) $_POST['Idade'];

            $email =
                trim($_POST['Email']);

            $endereco =
                trim($_POST['Endereco']);

            $senha =
                trim($_POST['senha']);


            if (
                empty($cpf)
                || empty($nome)
                || empty($email)
                || empty($endereco)
                || empty($senha)
            ) {

                echo '<div class="mensagem mensagem-erro">
                        ⚠️ Preencha todos os campos corretamente.
                      </div>';

                return;
            }


            if ($idade < 18) {

                echo '<div class="mensagem mensagem-erro">
                        ⚠️ Por favor, insira uma idade válida.
                      </div>';

                return;
            }


            if (
                !filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                )
            ) {

                echo '<div class="mensagem mensagem-erro">
                        ⚠️ O e-mail digitado é inválido.
                      </div>';

                return;
            }


            if (strlen($senha) < 8) {

                echo '<div class="mensagem mensagem-erro">
                        🔒 A senha deve ter pelo menos 8 caracteres.
                      </div>';

                return;
            }


            $sql = "
                INSERT INTO usuario
                (`Cpf`, `Nome`, `Idade`, `Email`, `Endereço`, `senha`)
                VALUES (?, ?, ?, ?, ?, ?)
            ";

            $stmt =
                $conexao->prepare($sql);

            $stmt->execute([
                $cpf,
                $nome,
                $idade,
                $email,
                $endereco,
                $senha
            ]);


            echo '<div class="mensagem mensagem-sucesso">
                    ✅ Usuário cadastrado com sucesso!
                  </div>';

        } else {

            echo '<div class="mensagem mensagem-erro">
                    ⚠️ Todos os campos devem ser preenchidos.
                  </div>';
        }

    } catch (PDOException $e) {

        echo '<div class="mensagem mensagem-erro">
                ❌ Erro ao cadastrar usuário.
              </div>';
    }
}


$acao =
    isset($_POST['acao'])
        ? $_POST['acao']
        : '';


if ($acao === 'login') {

    efetuarLogin();

} else {

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
    ) {
        cadastrarUsuario();
    }
}

?>


<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="UTF-8">

    <meta
        http-equiv="X-UA-Compatible"
        content="IE=edge"
    >

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Mensagens</title>


    <style>

        /* ==============================
           MENSAGENS
        ============================== */

        .mensagem {
            width: 90%;
            max-width: 450px;

            margin: 20px auto;

            padding: 15px 20px;

            border-radius: 10px;

            font-family: Arial, sans-serif;

            font-size: 15px;

            font-weight: 600;

            text-align: center;

            box-sizing: border-box;

            animation: aparecer 0.4s ease;

            box-shadow:
                0 4px 10px
                rgba(0, 0, 0, 0.10);
        }


        /* ==============================
           MENSAGEM DE ERRO
        ============================== */

        .mensagem-erro {
            color: #842029;

            background-color: #f8d7da;

            border: 1px solid #f5c2c7;

            box-shadow:
                0 4px 10px
                rgba(220, 53, 69, 0.15);
        }


        /* ==============================
           MENSAGEM DE SUCESSO
        ============================== */

        .mensagem-sucesso {
            color: #0f5132;

            background-color: #d1e7dd;

            border: 1px solid #badbcc;

            box-shadow:
                0 4px 10px
                rgba(25, 135, 84, 0.15);
        }


        /* ==============================
           ANIMAÇÃO
        ============================== */

        @keyframes aparecer {

            from {
                opacity: 0;

                transform:
                    translateY(-10px);
            }

            to {
                opacity: 1;

                transform:
                    translateY(0);
            }

        }


        /* ==============================
           RESPONSIVO
        ============================== */

        @media (max-width: 500px) {

            .mensagem {
                width: 95%;

                font-size: 14px;

                padding: 13px 15px;
            }

        }

    </style>

</head>


<body>


</body>

</html>
