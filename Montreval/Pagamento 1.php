<?php
declare(strict_types=1);

// Pagamento

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| CONEXÃO
|--------------------------------------------------------------------------
*/

$pdo = null;

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbName = getenv('DB_NAME') ?: 'montreval';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO(
        'mysql:host=' . $dbHost .
        ';dbname=' . $dbName .
        ';charset=utf8mb4',
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log(
        'Erro de conexão com o banco montreval: ' .
        $e->getMessage()
    );

    $pdo = null;
}

/*
|--------------------------------------------------------------------------
| FUNÇÕES
|--------------------------------------------------------------------------
*/

function valorNumerico(mixed $valor): float
{
    if (is_int($valor) || is_float($valor)) {
        return (float) $valor;
    }

    if (!is_string($valor)) {
        return 0.0;
    }

    $valor = trim($valor);

    if ($valor === '') {
        return 0.0;
    }

    $valor = preg_replace(
        '/[^\d,.-]/',
        '',
        $valor
    );

    if ($valor === null || $valor === '') {
        return 0.0;
    }

    if (str_contains($valor, ',')) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    }

    return (float) $valor;
}

function obterNomeProduto(array $produto): string
{
    return (string) (
        $produto['nome']
        ?? $produto['produto']
        ?? $produto['titulo']
        ?? $produto['Nome']
        ?? 'Produto'
    );
}

/*
|--------------------------------------------------------------------------
| ESTADO
|--------------------------------------------------------------------------
*/

$sucesso = false;
$mensagemErro = '';
$frete = null;
$totalFinal = 0.0;
$valorFreteFixo = 20.00;

/*
|--------------------------------------------------------------------------
| CARRINHO
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['carrinho']) ||
    !is_array($_SESSION['carrinho'])
) {
    $_SESSION['carrinho'] = [];
}

$carrinho = $_SESSION['carrinho'];

/*
|--------------------------------------------------------------------------
| TOTAL DOS PRODUTOS
|--------------------------------------------------------------------------
*/

$totalProdutos = 0.0;

foreach ($carrinho as $produto) {
    if (!is_array($produto)) {
        continue;
    }

    if (isset($produto['valor'])) {
        $valor = valorNumerico($produto['valor']);
    } elseif (isset($produto['preco'])) {
        $valor = valorNumerico($produto['preco']);
    } elseif (isset($produto['Preco'])) {
        $valor = valorNumerico($produto['Preco']);
    } else {
        $valor = 0.0;
    }

    $quantidade = isset($produto['quantidade'])
        ? max(1, (int) $produto['quantidade'])
        : 1;

    $totalProdutos += $valor * $quantidade;
}

$totalFinal = $totalProdutos;

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token'])
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

/*
|--------------------------------------------------------------------------
| AÇÃO
|--------------------------------------------------------------------------
*/

$acao = $_POST['acao'] ?? '';

/*
|--------------------------------------------------------------------------
| VALIDAÇÃO CSRF
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenRecebido = $_POST['csrf_token'] ?? '';

    if (
        !is_string($tokenRecebido) ||
        !hash_equals(
            $csrfToken,
            $tokenRecebido
        )
    ) {
        $mensagemErro =
            'A sessão expirou. Atualize a página e tente novamente.';
    }
}

/*
|--------------------------------------------------------------------------
| AJAX - CÁLCULO DE FRETE
|--------------------------------------------------------------------------
|
| Esta parte precisa vir antes da finalização normal, porque a requisição
| AJAX espera receber JSON e não HTML.
|
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $acao === 'calcular_frete'
) {
    header(
        'Content-Type: application/json; charset=utf-8'
    );

    $tokenRecebido =
        $_POST['csrf_token'] ?? '';

    if (
        !is_string($tokenRecebido) ||
        !hash_equals(
            $csrfToken,
            $tokenRecebido
        )
    ) {
        http_response_code(403);

        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Sessão inválida.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $cep = preg_replace(
        '/\D/',
        '',
        (string) ($_POST['cep'] ?? '')
    );

    if (strlen($cep) !== 8) {
        http_response_code(422);

        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'CEP inválido.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    echo json_encode([
        'sucesso' => true,
        'fretes' => [
            [
                'id' => 'frete_fixo',
                'empresa' => 'Maison Montreval',
                'servico' => 'Entrega padrão',
                'preco' => 20.00,
                'prazo' => 0
            ]
        ]
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

/*
|--------------------------------------------------------------------------
| FINALIZAR PEDIDO
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $acao === 'finalizar' &&
    $mensagemErro === ''
) {
    $metodoPagamento =
        $_POST['metodo_pagamento'] ?? '';

    $freteId =
        $_POST['frete_id'] ?? '';

    $cepPagamento = preg_replace(
        '/\D/',
        '',
        (string) ($_POST['cep'] ?? '')
    );

    $metodosPermitidos = [
        'pix',
        'cartao',
        'boleto'
    ];

    /*
    |--------------------------------------------------------------------------
    | VALIDAÇÕES
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $metodoPagamento,
            $metodosPermitidos,
            true
        )
    ) {
        $mensagemErro =
            'Selecione uma forma de pagamento válida.';

    } elseif ($freteId !== 'frete_fixo') {

        $mensagemErro =
            'Selecione uma opção de frete válida.';

    } elseif (strlen($cepPagamento) !== 8) {

        $mensagemErro =
            'Informe um CEP válido.';

    } elseif (empty($carrinho)) {

        $mensagemErro =
            'O carrinho está vazio.';

    } elseif (
        !isset($pdo) ||
        !($pdo instanceof PDO)
    ) {

        $mensagemErro =
            'Não foi possível conectar ao banco de dados.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | USUÁRIO
        |--------------------------------------------------------------------------
        */

        $idUsuario =
            $_SESSION['ID_Usuario']
            ?? $_SESSION['id_usuario']
            ?? $_SESSION['usuario_id']
            ?? null;

        if (
            $idUsuario === null ||
            !filter_var(
                $idUsuario,
                FILTER_VALIDATE_INT
            )
        ) {
            $mensagemErro =
                'Usuário não identificado na sessão.';

        } else {

            $totalComFrete =
                $totalProdutos +
                $valorFreteFixo;

            try {

                /*
                |--------------------------------------------------------------------------
                | INICIA TRANSAÇÃO
                |--------------------------------------------------------------------------
                */

                $pdo->beginTransaction();

                /*
                |--------------------------------------------------------------------------
                | BAIXA ATÔMICA DO ESTOQUE
                |--------------------------------------------------------------------------
                */

                $stmtEstoque = $pdo->prepare(
                    'UPDATE produto
                     SET Quantidade_estoque =
                         Quantidade_estoque - ?
                     WHERE id = ?
                     AND Quantidade_estoque >= ?'
                );

                /*
                |--------------------------------------------------------------------------
                | INSERT DO PAGAMENTO
                |--------------------------------------------------------------------------
                */

                $sqlPagamento = <<<SQL
INSERT INTO pagamento
(
    Parcela,
    ID_Usuario,
    ID_Produto,
    Valor,
    Total,
    cep
)
VALUES
(
    :parcela,
    :id_usuario,
    :id_produto,
    :valor,
    :total,
    :cep
)
SQL;

                $stmtPagamento =
                    $pdo->prepare(
                        $sqlPagamento
                    );

                /*
                |--------------------------------------------------------------------------
                | PROCESSA CADA PRODUTO
                |--------------------------------------------------------------------------
                */

                foreach ($carrinho as $produto) {

                    if (!is_array($produto)) {
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | ID DO PRODUTO
                    |--------------------------------------------------------------------------
                    */

                    $idProduto =
                        $produto['ID_Produto']
                        ?? $produto['id_produto']
                        ?? $produto['id']
                        ?? $produto['ID']
                        ?? null;

                    if (
                        $idProduto === null ||
                        !filter_var(
                            $idProduto,
                            FILTER_VALIDATE_INT
                        )
                    ) {
                        throw new RuntimeException(
                            'Um produto do carrinho possui ID inválido.'
                        );
                    }

                    $idProduto =
                        (int) $idProduto;

                    /*
                    |--------------------------------------------------------------------------
                    | QUANTIDADE
                    |--------------------------------------------------------------------------
                    */

                    $quantidade =
                        isset($produto['quantidade'])
                        ? (int) $produto['quantidade']
                        : 1;

                    if ($quantidade <= 0) {
                        throw new RuntimeException(
                            'Quantidade inválida para um produto.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | BAIXA DO ESTOQUE
                    |--------------------------------------------------------------------------
                    */

                    $stmtEstoque->execute([
                        $quantidade,
                        $idProduto,
                        $quantidade
                    ]);

                    if ($stmtEstoque->rowCount() !== 1) {
                        throw new RuntimeException(
                            'Estoque insuficiente para o produto: ' .
                            obterNomeProduto($produto)
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | VALOR UNITÁRIO
                    |--------------------------------------------------------------------------
                    */

                    if (isset($produto['valor'])) {

                        $valorUnitario =
                            valorNumerico(
                                $produto['valor']
                            );

                    } elseif (isset($produto['preco'])) {

                        $valorUnitario =
                            valorNumerico(
                                $produto['preco']
                            );

                    } elseif (isset($produto['Preco'])) {

                        $valorUnitario =
                            valorNumerico(
                                $produto['Preco']
                            );

                    } else {

                        $valorUnitario = 0.0;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | VALOR TOTAL DO PRODUTO
                    |--------------------------------------------------------------------------
                    */

                    $valor =
                        $valorUnitario *
                        $quantidade;

                    /*
                    |--------------------------------------------------------------------------
                    | REGISTRA PAGAMENTO
                    |--------------------------------------------------------------------------
                    */

                    $stmtPagamento->execute([
                        ':parcela' => 1,
                        ':id_usuario' => (int) $idUsuario,
                        ':id_produto' => $idProduto,
                        ':valor' => $valor,
                        ':total' => $totalComFrete,
                        ':cep' => $cepPagamento,
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | CONFIRMA TRANSAÇÃO
                |--------------------------------------------------------------------------
                */

                $pdo->commit();

                /*
                |--------------------------------------------------------------------------
                | LIMPA O CARRINHO
                |--------------------------------------------------------------------------
                */

                $_SESSION['carrinho'] = [];

                $carrinho = [];

                $sucesso = true;

                $frete = [
                    'empresa' =>
                        'Maison Montreval',

                    'servico' =>
                        'Entrega padrão',

                    'preco' =>
                        $valorFreteFixo
                ];

                $totalFinal =
                    $totalComFrete;

                $totalProdutos = 0.0;

            } catch (Throwable $e) {

                /*
                |--------------------------------------------------------------------------
                | ROLLBACK
                |--------------------------------------------------------------------------
                */

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $mensagemErro =
                    'Não foi possível finalizar o pedido. ' .
                    $e->getMessage();

                error_log(
                    'Erro ao finalizar pedido: ' .
                    $e->getMessage()
                );
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Maison Montreval | Finalizar Compra
    </title>

    <style>

        :root {
            --marfim: #f5f0e6;
            --preto: #171717;
            --marrom: #4a3022;
            --marrom-claro: #72513c;
            --turquesa: #168b88;
            --roxo: #5b447a;
            --dourado: #b99a5b;
            --branco: #ffffff;
            --cinza: #77706a;
            --borda: #ddd3c4;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;

            background:
                radial-gradient(
                    circle at top,
                    #fffdf8 0,
                    var(--marfim) 55%,
                    #eee5d6 100%
                );

            color: var(--preto);

            font-family:
                Georgia,
                "Times New Roman",
                serif;
        }

        .pagina {
            width: min(1180px, 94%);
            margin: 0 auto;
            padding: 45px 0 70px;
        }

        .cabecalho {
            text-align: center;
            margin-bottom: 42px;
        }

        .cabecalho img {
            width: 190px;

            border:
                2px solid
                var(--dourado);

            outline:
                1px solid
                rgba(201, 162, 39, 0.35);

            outline-offset: 5px;

            max-width: 70%;

            margin-bottom: 20px;
        }

        .linha-dourada {
            width: 70px;
            height: 2px;

            margin: 15px auto;

            background:
                var(--dourado);
        }

        .cabecalho h1 {
            margin: 0;

            color:
                var(--marrom);

            font-size: 34px;

            font-weight: 500;

            letter-spacing: 1px;
        }

        .cabecalho p {
            margin-top: 10px;

            color:
                var(--cinza);

            font-size: 15px;

            font-style: italic;
        }

        .layout {
            display: grid;

            grid-template-columns:
                minmax(0, 1.5fr)
                minmax(300px, 0.8fr);

            gap: 28px;

            align-items: start;
        }

        .cartao {
            padding: 30px;

            background:
                rgba(255, 255, 255, 0.96);

            border:
                1px solid
                var(--borda);

            border-radius: 4px;

            box-shadow:
                0 14px 40px
                rgba(74, 48, 34, 0.08);
        }

        .cartao + .cartao {
            margin-top: 24px;
        }

        .cartao h2 {
            margin: 0 0 25px;

            color:
                var(--marrom);

            font-size: 22px;

            font-weight: 500;
        }

        .campo {
            margin-bottom: 17px;
        }

        .campo label {
            display: block;

            margin-bottom: 7px;

            color:
                var(--marrom);

            font-size: 14px;
        }

        .campo input {
            width: 100%;

            height: 45px;

            padding: 0 13px;

            border:
                1px solid
                var(--borda);

            border-radius: 2px;

            background:
                #fffdf9;

            color:
                var(--preto);

            font-family:
                Arial,
                sans-serif;

            font-size: 14px;

            outline: none;
        }

        .campo input:focus {
            border-color:
                var(--dourado);
        }

        .linha {
            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 17px;
        }

        .linha-cep {
            display: grid;

            grid-template-columns:
                1fr auto;

            gap: 10px;

            align-items: end;
        }

        .botao-frete {
            height: 45px;

            padding: 0 22px;

            border: 0;

            border-radius: 2px;

            background:
                var(--turquesa);

            color:
                white;

            font-family:
                Georgia,
                serif;

            font-size: 14px;

            cursor: pointer;

            transform:
                translateY(-17px);
        }

        .botao-frete:hover {
            background:
                #117572;
        }

        .botao-frete:disabled {
            opacity: .6;

            cursor: wait;
        }

        .fretes {
            margin-top: 25px;
        }

        .fretes-titulo {
            margin-bottom: 12px;

            color:
                var(--marrom);

            font-size: 16px;
        }

        .opcao-frete {
            display: block;

            padding: 16px;

            margin-bottom: 10px;

            border:
                1px solid
                var(--borda);

            border-radius: 3px;

            background:
                #fffdf9;

            cursor: pointer;
        }

        .opcao-frete.selecionado {
            border-color:
                var(--turquesa);

            box-shadow:
                0 0 0 1px
                var(--turquesa);
        }

        .nome-frete {
            color:
                var(--marrom);

            font-weight: bold;
        }

        .detalhe-frete {
            margin-top: 5px;

            color:
                var(--cinza);

            font-family:
                Arial,
                sans-serif;

            font-size: 13px;
        }

        .pagamento label {
            display: block;

            margin-bottom: 10px;

            padding: 15px;

            border:
                1px solid
                var(--borda);

            background:
                #fffdf9;

            cursor: pointer;
        }

        .resumo-produto {
            display: flex;

            justify-content:
                space-between;

            gap: 15px;

            padding: 12px 0;

            border-bottom:
                1px solid
                #eee7dc;

            font-family:
                Arial,
                sans-serif;

            font-size: 14px;
        }

        .resumo-linha {
            display: flex;

            justify-content:
                space-between;

            gap: 20px;

            padding-top: 16px;

            font-family:
                Arial,
                sans-serif;

            font-size: 14px;
        }

        .resumo-total {
            display: flex;

            justify-content:
                space-between;

            gap: 20px;

            margin-top: 20px;

            padding-top: 20px;

            border-top:
                1px solid
                var(--dourado);

            color:
                var(--marrom);

            font-size: 21px;
        }

        /*
        |--------------------------------------------------------------------------
        | BOTÃO FINALIZAR
        |--------------------------------------------------------------------------
        */

        .botao-finalizar {
            width: 100%;

            margin-top: 25px;

            padding: 16px;

            border: 0;

            border-radius: 2px;

            background:
                var(--roxo);

            color:
                white;

            font-family:
                Georgia,
                serif;

            font-size: 15px;

            cursor: pointer;

            transition:
                background-color 0.2s ease;
        }

        .botao-finalizar:hover {
            background:
                #493564;
        }

        .botao-finalizar:disabled {
            opacity: .55;

            cursor: not-allowed;
        }

        /*
        |--------------------------------------------------------------------------
        | BOTÃO CONTINUAR COMPRANDO
        |--------------------------------------------------------------------------
        */

        .botao-continuar {
            display: block;

            width: 100%;

            margin-top: 12px;

            padding: 14px 16px;

            border:
                1px solid
                var(--dourado);

            border-radius: 2px;

            background:
                transparent;

            color:
                var(--marrom);

            font-family:
                Georgia,
                serif;

            font-size: 14px;

            text-align: center;

            text-decoration: none;

            cursor: pointer;

            transition:
                background-color 0.2s ease,
                color 0.2s ease,
                border-color 0.2s ease;
        }

        .botao-continuar:hover {
            background:
                var(--dourado);

            color:
                var(--branco);

            border-color:
                var(--dourado);
        }

        .mensagem {
            padding: 15px 18px;

            margin-bottom: 25px;

            border-radius: 3px;

            font-family:
                Arial,
                sans-serif;

            font-size: 14px;
        }

        .erro {
            border:
                1px solid
                #e0b5b5;

            background:
                #fff2f2;

            color:
                #8d2727;
        }

        .sucesso {
            border:
                1px solid
                #b7d7c2;

            background:
                #f0faf3;

            color:
                #28643c;
        }

        .carregando {
            display: none;

            margin-top: 12px;

            color:
                var(--turquesa);

            font-family:
                Arial,
                sans-serif;

            font-size: 13px;
        }

        .texto-frete {
            color:
                var(--cinza);

            font-family:
                Arial,
                sans-serif;

            font-size: 13px;
        }

        @media (max-width: 850px) {

            .layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {

            .pagina {
                padding-top: 25px;
            }

            .cartao {
                padding: 22px;
            }

            .linha,
            .linha-cep {
                grid-template-columns: 1fr;
            }

            .botao-frete {
                width: 100%;

                transform: none;
            }

            .cabecalho h1 {
                font-size: 28px;
            }
        }

    </style>

</head>

<body>

<div class="pagina">

    <header class="cabecalho">

        <img
            src="imagens/logo-montreval.png"
            alt="Maison Montreval"
        >

        <div class="linha-dourada"></div>

        <h1>
            Finalizar Compra
        </h1>

        <p>
            Elegância em cada detalhe.
        </p>

    </header>


    <?php if ($mensagemErro !== ''): ?>

        <div class="mensagem erro">

            <?= htmlspecialchars(
                $mensagemErro,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <?php if ($sucesso && is_array($frete)): ?>

        <div class="mensagem sucesso">

            Pedido finalizado com sucesso.

            <br>

            Frete:

            <strong>

                <?= htmlspecialchars(
                    $frete['empresa'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

                -

                <?= htmlspecialchars(
                    $frete['servico'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </strong>

            <br>

            Total:

            <strong>

                R$

                <?= number_format(
                    $totalFinal,
                    2,
                    ',',
                    '.'
                ) ?>

            </strong>

        </div>

    <?php endif; ?>


    <form
        method="POST"
        id="formPagamento"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                $csrfToken,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

        <input
            type="hidden"
            name="acao"
            value="finalizar"
        >

        <input
            type="hidden"
            name="frete_id"
            id="frete_id"
            value=""
        >


        <div class="layout">

            <main>

                <section class="cartao">

                    <h2>
                        Endereço de Entrega
                    </h2>


                    <div class="linha-cep">

                        <div class="campo">

                            <label for="cep">
                                CEP
                            </label>

                            <input
                                type="text"
                                id="cep"
                                name="cep"
                                maxlength="9"
                                inputmode="numeric"
                                placeholder="00000-000"
                                required
                            >

                        </div>


                        <button
                            type="button"
                            class="botao-frete"
                            id="calcularFrete"
                        >
                            Calcular Frete
                        </button>

                    </div>


                    <div
                        id="carregando"
                        class="carregando"
                    >
                        Consultando o endereço e calculando o frete...
                    </div>


                    <div class="linha">

                        <div class="campo">

                            <label for="rua">
                                Rua
                            </label>

                            <input
                                type="text"
                                id="rua"
                                name="rua"
                            >

                        </div>


                        <div class="campo">

                            <label for="numero">
                                Número
                            </label>

                            <input
                                type="text"
                                id="numero"
                                name="numero"
                                required
                            >

                        </div>

                    </div>


                    <div class="linha">

                        <div class="campo">

                            <label for="bairro">
                                Bairro
                            </label>

                            <input
                                type="text"
                                id="bairro"
                                name="bairro"
                            >

                        </div>


                        <div class="campo">

                            <label for="complemento">
                                Complemento
                            </label>

                            <input
                                type="text"
                                id="complemento"
                                name="complemento"
                            >

                        </div>

                    </div>


                    <div class="linha">

                        <div class="campo">

                            <label for="cidade">
                                Cidade
                            </label>

                            <input
                                type="text"
                                id="cidade"
                                name="cidade"
                                required
                            >

                        </div>


                        <div class="campo">

                            <label for="estado">
                                Estado
                            </label>

                            <input
                                type="text"
                                id="estado"
                                name="estado"
                                maxlength="2"
                                required
                            >

                        </div>

                    </div>


                    <div
                        id="fretes"
                        class="fretes"
                    >

                        <div class="texto-frete">
                            Informe seu CEP para calcular o frete.
                        </div>

                    </div>

                </section>


                <section class="cartao pagamento">

                    <h2>
                        Forma de Pagamento
                    </h2>


                    <label>

                        <input
                            type="radio"
                            name="metodo_pagamento"
                            value="pix"
                            required
                        >

                        PIX

                    </label>


                    <label>

                        <input
                            type="radio"
                            name="metodo_pagamento"
                            value="cartao"
                        >

                        Cartão

                    </label>


                    <label>

                        <input
                            type="radio"
                            name="metodo_pagamento"
                            value="boleto"
                        >

                        Boleto

                    </label>

                </section>

            </main>


            <aside>

                <section class="cartao">

                    <h2>
                        Resumo
                    </h2>


                    <?php if (empty($carrinho)): ?>

                        <div
                            class="texto-frete"
                            style="padding: 10px 0;"
                        >
                            Seu carrinho está vazio.
                        </div>

                    <?php else: ?>

                        <?php foreach ($carrinho as $produto): ?>

                            <?php

                            if (!is_array($produto)) {
                                continue;
                            }

                            $nome =
                                obterNomeProduto(
                                    $produto
                                );

                            $valor =
                                isset($produto['valor'])
                                ? valorNumerico(
                                    $produto['valor']
                                )
                                : 0.0;

                            $quantidade =
                                isset($produto['quantidade'])
                                ? max(
                                    1,
                                    (int) $produto['quantidade']
                                )
                                : 1;

                            $subtotal =
                                $valor * $quantidade;

                            ?>

                            <div class="resumo-produto">

                                <span>

                                    <?= htmlspecialchars(
                                        $nome,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                    ×

                                    <?= $quantidade ?>

                                </span>


                                <strong>

                                    R$

                                    <?= number_format(
                                        $subtotal,
                                        2,
                                        ',',
                                        '.'
                                    ) ?>

                                </strong>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>


                    <div class="resumo-linha">

                        <span>
                            Produtos
                        </span>

                        <strong>

                            R$

                            <?= number_format(
                                $totalProdutos,
                                2,
                                ',',
                                '.'
                            ) ?>

                        </strong>

                    </div>


                    <div class="resumo-linha">

                        <span>
                            Frete
                        </span>

                        <strong id="valorFrete">
                            R$ 0,00
                        </strong>

                    </div>


                    <div class="resumo-total">

                        <span>
                            Total
                        </span>

                        <strong id="totalFinal">

                            R$

                            <?= number_format(
                                $totalProdutos,
                                2,
                                ',',
                                '.'
                            ) ?>

                        </strong>

                    </div>


                    <button
                        type="submit"
                        class="botao-finalizar"
                        id="botaoFinalizar"
                        disabled
                    >
                        Finalizar Compra
                    </button>


                    <a
                        href="bemvindo.php"
                        class="botao-continuar"
                    >
                        Continuar Comprando
                    </a>

                </section>

            </aside>

        </div>

    </form>

</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    () => {

        const cepInput =
            document.getElementById('cep');

        const calcularFreteBtn =
            document.getElementById(
                'calcularFrete'
            );

        const carregando =
            document.getElementById(
                'carregando'
            );

        const fretesContainer =
            document.getElementById(
                'fretes'
            );

        const freteIdInput =
            document.getElementById(
                'frete_id'
            );

        const valorFreteElement =
            document.getElementById(
                'valorFrete'
            );

        const totalFinalElement =
            document.getElementById(
                'totalFinal'
            );

        const formPagamento =
            document.getElementById(
                'formPagamento'
            );

        const botaoFinalizar =
            document.getElementById(
                'botaoFinalizar'
            );

        const csrfToken =
            document.querySelector(
                'input[name="csrf_token"]'
            ).value;

        const totalProdutos =
            <?= json_encode(
                $totalProdutos,
                JSON_UNESCAPED_UNICODE
            ) ?>;

        const rua =
            document.getElementById('rua');

        const bairro =
            document.getElementById('bairro');

        const cidade =
            document.getElementById('cidade');

        const estado =
            document.getElementById('estado');


        function limparCep(valor) {

            return String(valor)
                .replace(/\D/g, '')
                .slice(0, 8);
        }


        function formatarCep(valor) {

            const cep =
                limparCep(valor);

            if (cep.length <= 5) {
                return cep;
            }

            return (
                cep.slice(0, 5) +
                '-' +
                cep.slice(5)
            );
        }


        function formatarMoeda(valor) {

            return Number(valor || 0)
                .toLocaleString(
                    'pt-BR',
                    {
                        style: 'currency',
                        currency: 'BRL'
                    }
                );
        }


        function mostrarErro(mensagem) {

            fretesContainer.innerHTML = '';

            const erro =
                document.createElement(
                    'div'
                );

            erro.className =
                'mensagem erro';

            erro.style.marginBottom =
                '0';

            erro.textContent =
                mensagem;

            fretesContainer.appendChild(
                erro
            );
        }


        function limparFrete() {

            freteIdInput.value = '';

            valorFreteElement.textContent =
                'R$ 0,00';

            totalFinalElement.textContent =
                formatarMoeda(
                    totalProdutos
                );

            botaoFinalizar.disabled =
                true;
        }


        async function buscarEndereco() {

            const cep =
                limparCep(
                    cepInput.value
                );

            if (cep.length !== 8) {
                return false;
            }

            try {

                const resposta =
                    await fetch(
                        `https://viacep.com.br/ws/${cep}/json/`
                    );

                if (!resposta.ok) {

                    throw new Error(
                        'Não foi possível consultar o CEP.'
                    );
                }

                const dados =
                    await resposta.json();

                if (dados.erro) {

                    throw new Error(
                        'CEP não encontrado.'
                    );
                }

                rua.value =
                    dados.logradouro || '';

                bairro.value =
                    dados.bairro || '';

                cidade.value =
                    dados.localidade || '';

                estado.value =
                    dados.uf || '';

                return true;

            } catch (erro) {

                mostrarErro(
                    erro.message
                );

                return false;
            }
        }


        function selecionarFrete(
            radio,
            preco
        ) {

            document
                .querySelectorAll(
                    '.opcao-frete'
                )
                .forEach(
                    opcao => {

                        opcao.classList.remove(
                            'selecionado'
                        );
                    }
                );

            const opcao =
                radio.closest(
                    '.opcao-frete'
                );

            if (opcao) {

                opcao.classList.add(
                    'selecionado'
                );
            }

            const valor =
                Number(preco) || 0;

            freteIdInput.value =
                radio.value;

            valorFreteElement.textContent =
                formatarMoeda(valor);

            totalFinalElement.textContent =
                formatarMoeda(
                    totalProdutos + valor
                );

            botaoFinalizar.disabled =
                false;
        }


        async function calcularFrete() {

            const cep =
                limparCep(
                    cepInput.value
                );

            if (cep.length !== 8) {

                mostrarErro(
                    'Digite um CEP válido com 8 números.'
                );

                return;
            }

            limparFrete();

            calcularFreteBtn.disabled =
                true;

            carregando.style.display =
                'block';

            try {

                const enderecoOk =
                    await buscarEndereco();

                if (!enderecoOk) {

                    throw new Error(
                        'Não foi possível validar o CEP.'
                    );
                }

                const dados =
                    new URLSearchParams();

                dados.append(
                    'acao',
                    'calcular_frete'
                );

                dados.append(
                    'cep',
                    cep
                );

                dados.append(
                    'csrf_token',
                    csrfToken
                );

                const resposta =
                    await fetch(
                        window.location.href,
                        {
                            method: 'POST',

                            headers: {
                                'Content-Type':
                                    'application/x-www-form-urlencoded; charset=UTF-8',

                                'Accept':
                                    'application/json'
                            },

                            body:
                                dados.toString()
                        }
                    );

                const resultado =
                    await resposta.json();

                if (
                    !resposta.ok ||
                    !resultado.sucesso
                ) {

                    throw new Error(
                        resultado.mensagem ||
                        'Não foi possível calcular o frete.'
                    );
                }

                fretesContainer.innerHTML =
                    '';

                const titulo =
                    document.createElement(
                        'div'
                    );

                titulo.className =
                    'fretes-titulo';

                titulo.textContent =
                    'Entrega disponível:';

                fretesContainer.appendChild(
                    titulo
                );


                resultado.fretes.forEach(
                    frete => {

                        const preco =
                            Number(
                                frete.preco
                            ) || 0;

                        const label =
                            document.createElement(
                                'label'
                            );

                        label.className =
                            'opcao-frete';


                        const radio =
                            document.createElement(
                                'input'
                            );

                        radio.type =
                            'radio';

                        radio.name =
                            'frete_visual';

                        radio.value =
                            frete.id;


                        const conteudo =
                            document.createElement(
                                'span'
                            );


                        const nome =
                            document.createElement(
                                'span'
                            );

                        nome.className =
                            'nome-frete';

                        nome.textContent =
                            frete.empresa +
                            ' - ' +
                            frete.servico;


                        const detalhe =
                            document.createElement(
                                'div'
                            );

                        detalhe.className =
                            'detalhe-frete';

                        detalhe.textContent =
                            formatarMoeda(
                                preco
                            );


                        conteudo.appendChild(
                            nome
                        );

                        conteudo.appendChild(
                            detalhe
                        );

                        label.appendChild(
                            radio
                        );

                        label.appendChild(
                            conteudo
                        );


                        radio.addEventListener(
                            'change',
                            () => {

                                selecionarFrete(
                                    radio,
                                    preco
                                );
                            }
                        );


                        fretesContainer.appendChild(
                            label
                        );
                    }
                );

            } catch (erro) {

                limparFrete();

                mostrarErro(
                    erro.message ||
                    'Erro ao calcular o frete.'
                );

            } finally {

                calcularFreteBtn.disabled =
                    false;

                carregando.style.display =
                    'none';
            }
        }


        cepInput.addEventListener(
            'input',
            function () {

                this.value =
                    formatarCep(
                        this.value
                    );

                limparFrete();
            }
        );


        calcularFreteBtn.addEventListener(
            'click',
            calcularFrete
        );


        formPagamento.addEventListener(
            'submit',
            evento => {

                if (!freteIdInput.value) {

                    evento.preventDefault();

                    alert(
                        'Calcule e selecione uma opção de frete.'
                    );

                    return;
                }

                const pagamento =
                    document.querySelector(
                        'input[name="metodo_pagamento"]:checked'
                    );

                if (!pagamento) {

                    evento.preventDefault();

                    alert(
                        'Selecione uma forma de pagamento.'
                    );
                }
            }
        );

    }
);

</script>

</body>

</html>