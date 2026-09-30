<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>Maison Montreval | Cadastro</title>
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
            color: var(--preto);
            font-family:
                "Times New Roman",
                Times,
                serif;
            padding: 40px 25px;
        }
        /* Faixa lateral da identidade */
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
        }
        /* Container principal */
        .cadastro-container {
            width: 100%;
            max-width: 520px;
            padding: 45px 48px 48px;
            background-color: rgba(244, 240, 230, 0.94);
            border: 1px solid var(--marfim-escuro);
            box-shadow:
                0 20px 60px rgba(17, 17, 17, 0.08);
        }
        /* Marca */
        .marca {
            margin-bottom: 38px;
            text-align: center;
        }
        .marca span {
            display: block;
            margin-bottom: 15px;
            color: var(--roxo);
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
            color: var(--preto);
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
            max-width: 360px;
            margin: 12px auto 0;
            color: var(--cinza);
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
            margin: 22px auto 0;
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
        /* Formulário */
        form {
            width: 100%;
        }
        .campo {
            margin-bottom: 24px;
        }
        .campo label {
            display: block;
            margin-bottom: 8px;
            color: var(--marrom);
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
            height: 44px;
            padding: 0 3px;
            border: none;
            border-bottom:
                1px solid var(--cinza-claro);
            background: transparent;
            outline: none;
            color: var(--preto);
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
                2px solid var(--dourado);
        }
        .campo input::placeholder {
            color: #A09A90;
            font-size: 13px;
        }
        /* Campos lado a lado */
        .linha-campos {
            display: grid;
            grid-template-columns:
                1fr 120px;
            gap: 28px;
        }
        /* Ações */
        .acoes {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 34px;
        }
        .botao-cadastrar {
            width: 100%;
            height: 50px;
            border:
                1px solid var(--preto);
            background-color: var(--preto);
            color: var(--marfim);
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
        .botao-cadastrar:hover {
            background-color: var(--roxo);
            border-color: var(--roxo);
        }
        .botao-link {
            align-self: center;
            color: var(--marrom);
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            text-decoration: none;
            border-bottom:
                1px solid transparent;
            transition:
                color 0.25s ease,
                border-color 0.25s ease;
        }
        .botao-link:hover {
            color: var(--roxo);
            border-bottom-color:
                var(--roxo);
        }
        /* Responsividade */
        @media (max-width: 600px) {
            body {
                padding: 25px 18px;
            }
            body::before {
                width: 4px;
            }
            .cadastro-container {
                max-width: 100%;
                padding:
                    35px 28px 40px;
            }
            .marca h1 {
                font-size: 27px;
            }
            .linha-campos {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <main class="cadastro-container">
        <div class="marca">
            <span>Área exclusiva</span>
            <h1>Cadastro</h1>
            <p class="subtitulo">
                Crie sua conta para acessar
                a experiência Maison Montreval.
            </p>
            <div class="linha-cor"></div>
        </div>
        <form
            action="Usuario.php"
            method="POST"
        >
            <!-- CPF e idade -->
            <div class="linha-campos">
                <div class="campo">
                    <label for="Cpf">
                        CPF
                    </label>
                    <input
                        type="text"
                        id="Cpf"
                        name="Cpf"
                        maxlength="14"
                        placeholder="000.000.000-00"
                        autocomplete="off"
                        required
                    >
                </div>
                <div class="campo">
                    <label for="Idade">
                        Idade
                    </label>
                    <input
                        type="number"
                        id="Idade"
                        name="Idade"
                        min="18"
                        placeholder="18+"
                        required
                    >
                </div>
            </div>
            <!-- Nome -->
            <div class="campo">
                <label for="Nome">
                    Nome
                </label>
                <input
                    type="text"
                    id="Nome"
                    name="Nome"
                    placeholder="Seu nome completo"
                    autocomplete="name"
                    required
                >
            </div>
            <!-- E-mail -->
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
            <!-- Senha -->
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
                    autocomplete="new-password"
                    required
                >
            </div>
            <!-- Endereço -->
            <div class="campo">
                <label for="Endereco">
                    Endereço completo
                </label>
                <input
                    type="text"
                    id="Endereco"
                    name="Endereco"
                    placeholder="Rua, número, bairro e cidade"
                    autocomplete="street-address"
                    required
                >
            </div>
            <!-- Ações -->
            <div class="acoes">
                <button
                    type="submit"
                    class="botao-cadastrar"
                >
                    Cadastrar
                </button>
                <a
                    href="login.php"
                    class="botao-link"
                >
                    Já tenho uma conta
                </a>
            </div>
        </form>
    </main>
</body>
</html>