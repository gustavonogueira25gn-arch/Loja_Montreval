<?php
//login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
/*
 * Mensagem temporária de erro.
 * Ela é lida uma única vez e depois apagada.
 */
$mensagemErro = '';
if (isset($_SESSION['erro_login'])) {
    $mensagemErro = $_SESSION['erro_login'];
    unset($_SESSION['erro_login']);
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
    <title>
        Maison Montreval | Acesso
    </title>
    <style>
        :root {
            --marfim: #F4F0E6;
            --marfim-escuro: #E8E1D4;
            --preto: #111111;
            --marrom: #5A3E32;
            --turquesa: #24C6C8;
            --roxo: #5B3A8E;
            --dourado: #C9A227;
            --dourado-claro: #E2C45C;
            --cinza: #777777;
            --cinza-claro: #B9B2A7;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html {
            min-height: 100%;
        }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(
                    circle at center,
                    #FAF7F0 0%,
                    var(--marfim) 55%,
                    #EDE7DC 100%
                );
            color:
                var(--preto);
            font-family:
                "Times New Roman",
                Times,
                serif;
            padding:
                40px 25px;
        }
        /* =================================================
           FAIXA LATERAL DA IDENTIDADE
           ================================================= */
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 6px;
            height: 100vh;
            background:
                linear-gradient(
                    to bottom,
                    var(--roxo) 0%,
                    var(--roxo) 33%,
                    var(--turquesa) 33%,
                    var(--turquesa) 66%,
                    var(--marrom) 66%,
                    var(--marrom) 100%
                );
            z-index: 0;
        }
        /* =================================================
           CONTAINER
           ================================================= */
        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 480px;
            padding:
                48px 48px 50px;
            background-color:
                rgba(
                    244,
                    240,
                    230,
                    0.94
                );
            border:
                1px solid
                var(--marfim-escuro);
            box-shadow:
                0 20px 60px
                rgba(
                    17,
                    17,
                    17,
                    0.08
                );
        }
        /* =================================================
           LOGO
           ================================================= */
        .logo-area {
            display: flex;
            justify-content: center;
            margin-bottom: 42px;
        }
        .logo-montreval {
            display: block;
            width:
                min(
                    100%,
                    330px
                );
            height: auto;
            border:
                2px solid
                var(--dourado);
            outline:
                1px solid
                rgba(
                    201,
                    162,
                    39,
                    0.35
                );
            outline-offset: 5px;
            box-shadow:
                0 8px 25px
                rgba(
                    17,
                    17,
                    17,
                    0.12
                );
        }
        /* =================================================
           MARCA
           ================================================= */
        .marca {
            margin-bottom: 42px;
            text-align: center;
        }
        .marca span {
            display: block;
            margin-bottom: 15px;
            color:
                var(--roxo);
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 4px;
            text-transform: uppercase;
        }
        .marca h1 {
            color:
                var(--preto);
            font-family:
                Didot,
                "Bodoni MT",
                "Times New Roman",
                serif;
            font-size: 32px;
            font-weight: 400;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .subtitulo {
            max-width: 330px;
            margin:
                12px auto 0;
            color:
                var(--cinza);
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            font-size: 13px;
            line-height: 1.7;
        }
        .linha-cor {
            width: 55px;
            height: 2px;
            margin:
                22px auto 0;
            background:
                linear-gradient(
                    to right,
                    var(--roxo) 0%,
                    var(--roxo) 33%,
                    var(--turquesa) 33%,
                    var(--turquesa) 66%,
                    var(--marrom) 66%,
                    var(--marrom) 100%
                );
        }
        /* =================================================
           MENSAGEM DE ERRO
           ================================================= */
        .mensagem-erro {
            margin-bottom: 25px;
            padding:
                12px 15px;
            border:
                1px solid
                var(--dourado);
            background:
                rgba(
                    201,
                    162,
                    39,
                    0.08
                );
            color:
                var(--marrom);
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            font-size: 12px;
            text-align: center;
            letter-spacing: 0.3px;
        }
        /* =================================================
           FORMULÁRIO
           ================================================= */
        form {
            width: 100%;
        }
        .campo {
            margin-bottom: 29px;
        }
        .campo label {
            display: block;
            margin-bottom: 9px;
            color:
                var(--marrom);
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 1.8px;
            text-transform: uppercase;
        }
        .campo input {
            width: 100%;
            height: 46px;
            padding:
                0 3px;
            border: none;
            border-bottom:
                1px solid
                var(--cinza-claro);
            background:
                transparent;
            outline: none;
            color:
                var(--preto);
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            font-size: 14px;
            transition:
                border-color 0.25s ease;
        }
        .campo input:focus {
            border-bottom:
                2px solid
                var(--dourado);
        }
        .campo input::placeholder {
            color:
                #A09A90;
            font-size: 13px;
        }
        /* =================================================
           AÇÕES
           ================================================= */
        .acoes {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-top: 40px;
        }
        /* =================================================
           BOTÃO ENTRAR
           ================================================= */
        .botao-entrar {
            width: 100%;
            height: 50px;
            border:
                1px solid
                var(--preto);
            background:
                var(--preto);
            color:
                var(--marfim);
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 2.2px;
            text-transform: uppercase;
            cursor: pointer;
            transition:
                background-color 0.25s ease,
                border-color 0.25s ease;
        }
        .botao-entrar:hover {
            background:
                var(--roxo);
            border-color:
                var(--roxo);
        }
        /* =================================================
           BOTÃO CRIAR CONTA
           ================================================= */
        .botao-cadastro {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 46px;
            padding:
                0 20px;
            border:
                1px solid
                var(--marrom);
            background:
                transparent;
            color:
                var(--marrom);
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 1.8px;
            text-align: center;
            text-transform: uppercase;
            text-decoration: none;
            cursor: pointer;
            transition:
                background-color 0.25s ease,
                border-color 0.25s ease,
                color 0.25s ease;
        }
        .botao-cadastro:hover {
            background:
                var(--marrom);
            border-color:
                var(--marrom);
            color:
                var(--marfim);
        }
        /* =================================================
           DIVISOR
           ================================================= */
        .divisor {
            display: flex;
            align-items: center;
            gap: 15px;
            margin:
                7px 0;
        }
        .divisor::before,
        .divisor::after {
            content: "";
            flex: 1;
            height: 1px;
            background:
                var(--marfim-escuro);
        }
        .divisor span {
            color:
                var(--cinza);
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            font-size: 9px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        /* =================================================
           BOTÃO CONVIDADO
           ================================================= */
        .botao-convidado {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 46px;
            padding:
                0 20px;
            border:
                1px solid
                var(--marrom);
            background:
                transparent;
            color:
                var(--marrom);
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 1.8px;
            text-align: center;
            text-transform: uppercase;
            text-decoration: none;
            cursor: pointer;
            transition:
                background-color 0.25s ease,
                border-color 0.25s ease,
                color 0.25s ease;
        }
        .botao-convidado:hover {
            background:
                var(--marrom);
            border-color:
                var(--marrom);
            color:
                var(--marfim);
        }
        /* =================================================
           RESPONSIVIDADE
           ================================================= */
        @media (max-width: 600px) {
            body {
                padding:
                    25px 18px;
            }
            body::before {
                width: 4px;
            }
            .login-container {
                max-width: 100%;
                padding:
                    35px 28px 40px;
            }
            .logo-montreval {
                width:
                    min(
                        100%,
                        290px
                    );
            }
            .marca h1 {
                font-size: 27px;
            }
        }
    </style>
</head>
<body>
    <main class="login-container">
        <!-- =================================================
             LOGO
             ================================================= -->
        <div class="logo-area">
            <img
                src="imagens/logo-montreval.png"
                alt="Maison Montreval"
                class="logo-montreval"
            >
        </div>
        <!-- =================================================
             MARCA
             ================================================= -->
        <div class="marca">
            <span>
                Área exclusiva
            </span>
            <h1>
                Bem-vindo
            </h1>
            <p class="subtitulo">
                Entre com seus dados para acessar
                sua conta Maison Montreval.
            </p>
            <div class="linha-cor"></div>
        </div>
        <?php if ($mensagemErro !== ''): ?>
            <div class="mensagem-erro">
                <?= htmlspecialchars(
                    $mensagemErro,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>
        <?php endif; ?>
        <!-- =================================================
             LOGIN
             ================================================= -->
        <form
            action="usuario.php"
            method="post"
        >
            <input
                type="hidden"
                name="acao"
                value="login"
            >
            <!-- E-MAIL -->
            <div class="campo">
                <label for="Email">
                    E-mail
                </label>
                <input
                    type="email"
                    id="Email"
                    name="Email"
                    placeholder="seuemail@exemplo.com"
                    autocomplete="email"
                    required
                >
            </div>
            <!-- SENHA -->
            <div class="campo">
                <label for="senha">
                    Senha
                </label>
                <input
                    type="password"
                    id="senha"
                    name="senha"
                    minlength="8"
                    placeholder="Mínimo 8 caracteres"
                    autocomplete="current-password"
                    required
                >
            </div>
            <!-- =================================================
                 AÇÕES
                 ================================================= -->
            <div class="acoes">
                <!-- ENTRAR -->
                <button
                    type="submit"
                    class="botao-entrar"
                >
                    Entrar
                </button>
                <!-- CRIAR CONTA -->
                <a
                    href="cadastro.php"
                    class="botao-cadastro"
                >
                    Criar uma conta
                </a>
                <!-- DIVISOR -->
                <div class="divisor">
                    <span>
                        ou
                    </span>
                </div>
                <!-- CONVIDADO -->
                <a
                    href="bemvindo.php"
                    class="botao-convidado"
                >
                    Continuar como convidado
                </a>
            </div>
        </form>
    </main>
</body>
</html>