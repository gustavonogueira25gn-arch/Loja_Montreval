<?php
//bemvindo
session_start();
require_once "conexao.php";
$conexao = conexao();
/* =========================================================
   CONFIGURAÇÃO DA SESSÃO
   ========================================================= */
if (!isset($_SESSION['logado'])) {
    $_SESSION['logado'] = false;
}
if (!isset($_SESSION['tipo_usuario'])) {
    $_SESSION['tipo_usuario'] = 'convidado';
}
/* =========================================================
   SAIR DA CONTA
   ========================================================= */
if (isset($_GET['acao']) && $_GET['acao'] === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
    header("Location: login.php");
    exit();
}
/* =========================================================
   CARRINHO
   ========================================================= */
if (!isset($_SESSION['carrinho']) || !is_array($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}
/* =========================================================
   CSRF
   CSRF = Cross-Site Request Forgery
   Proteção contra falsificação de requisições.
   ========================================================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
/* =========================================================
   MENSAGENS
   ========================================================= */
$mensagem = $_SESSION['mensagem_loja'] ?? '';
$tipoMensagem = $_SESSION['tipo_mensagem_loja'] ?? '';
unset($_SESSION['mensagem_loja'], $_SESSION['tipo_mensagem_loja']);
/* =========================================================
   FUNÇÃO DE MENSAGEM
   ========================================================= */
function mensagemLoja(string $mensagem, string $tipo = 'erro'): void {
    $_SESSION['mensagem_loja'] = $mensagem;
    $_SESSION['tipo_mensagem_loja'] = $tipo;
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}
/* =========================================================
   VALIDAÇÃO CSRF
   ========================================================= */
function validarCSRF(): void {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        mensagemLoja('A solicitação expirou. Tente novamente.');
    }
}
/* =========================================================
   OBTÉM ID DO PRODUTO
   ========================================================= */
function obterIdProduto(): int {
    if (isset($_POST['id_produto'])) {
        return (int) $_POST['id_produto'];
    }
    if (isset($_POST['id'])) {
        return (int) $_POST['id'];
    }
    return 0;
}
/* =========================================================
   TAMANHOS
   ========================================================= */
function obterTamanhosDisponiveis(?string $tamanhos): array {
    if ($tamanhos === null || trim($tamanhos) === '') {
        return [];
    }

    $lista = preg_split('/[,;\/|]+/', $tamanhos);
    $lista = array_map('trim', $lista);
    $lista = array_filter($lista, static fn($tamanho) => $tamanho !== '');

    $resultado = [];
    foreach ($lista as $tamanho) {
        $chave = function_exists('mb_strtoupper') ? mb_strtoupper($tamanho, 'UTF-8') : strtoupper($tamanho);
        $resultado[$chave] = $tamanho;
    }

    return array_values($resultado);
}

function obterChaveCarrinho(int $idProduto, string $tamanho): string {
    $tamanhoNormalizado = function_exists('mb_strtoupper')
        ? mb_strtoupper(trim($tamanho), 'UTF-8')
        : strtoupper(trim($tamanho));

    return $idProduto . '|' . $tamanhoNormalizado;
}

/* =========================================================
   AÇÕES DO CARRINHO
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'comprar') {
        $idProduto = obterIdProduto();
        if ($idProduto <= 0) {
            mensagemLoja('Produto inválido.');
        }

        $stmt = $conexao->prepare("
            SELECT id, Nome, Valor, imagens, Tamanho, Quantidade_estoque
            FROM produto
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$idProduto]);
        $produto = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$produto) {
            mensagemLoja('Produto não encontrado.');
        }

        $estoque = (int) $produto['Quantidade_estoque'];
        if ($estoque <= 0) {
            mensagemLoja('Não há unidades disponíveis deste produto.');
        }

        $tamanhosDisponiveis = obterTamanhosDisponiveis($produto['Tamanho'] ?? null);
        $tamanhoSelecionado = trim((string) ($_POST['tamanho'] ?? ''));

        if (empty($tamanhosDisponiveis)) {
            $tamanhoSelecionado = 'Único';
        } else {
            $tamanhoValido = false;
            foreach ($tamanhosDisponiveis as $tamanho) {
                if (strcasecmp($tamanho, $tamanhoSelecionado) === 0) {
                    $tamanhoSelecionado = $tamanho;
                    $tamanhoValido = true;
                    break;
                }
            }

            if (!$tamanhoValido) {
                mensagemLoja('Selecione um tamanho válido.');
            }
        }

        $chaveCarrinho = obterChaveCarrinho($idProduto, $tamanhoSelecionado);
        $quantidadeProdutoNoCarrinho = 0;

        foreach ($_SESSION['carrinho'] as $itemCarrinho) {
            if (!is_array($itemCarrinho)) {
                continue;
            }
            if ((int) ($itemCarrinho['id_produto'] ?? 0) === $idProduto) {
                $quantidadeProdutoNoCarrinho += (int) ($itemCarrinho['quantidade'] ?? 0);
            }
        }

        if (($quantidadeProdutoNoCarrinho + 1) > $estoque) {
            mensagemLoja('Não há unidades disponíveis suficientes para este produto.');
        }

        $quantidadeAtual = (int) ($_SESSION['carrinho'][$chaveCarrinho]['quantidade'] ?? 0);
        $_SESSION['carrinho'][$chaveCarrinho] = [
            'id_produto' => $idProduto,
            'tamanho' => $tamanhoSelecionado,
            'quantidade' => $quantidadeAtual + 1
        ];

        mensagemLoja('Produto adicionado ao carrinho.', 'sucesso');
    }

    if ($acao === 'remover') {
        $chaveCarrinho = trim((string) ($_POST['chave_carrinho'] ?? ''));

        if ($chaveCarrinho !== '' && isset($_SESSION['carrinho'][$chaveCarrinho])) {
            unset($_SESSION['carrinho'][$chaveCarrinho]);
        }

        mensagemLoja('Produto removido do carrinho.', 'sucesso');
    }
}
/* =========================================================
   PRODUTOS DISPONÍVEIS
   ========================================================= */
$stmtProdutos = $conexao->query("
    SELECT
        id,
        Nome,
        Valor,
        imagens,
        Tamanho,
        Quantidade_estoque
    FROM produto
    WHERE Quantidade_estoque > 0
    ORDER BY id DESC
");
$produtos = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);
/* =========================================================
   ITENS DO CARRINHO
   ========================================================= */
$itensCarrinho = [];
$totalCarrinho = 0;

if (!empty($_SESSION['carrinho'])) {
    $idsValidos = [];

    foreach ($_SESSION['carrinho'] as $itemCarrinho) {
        if (!is_array($itemCarrinho)) {
            continue;
        }

        $id = (int) ($itemCarrinho['id_produto'] ?? 0);
        if ($id > 0) {
            $idsValidos[$id] = $id;
        }
    }

    if (!empty($idsValidos)) {
        $idsValidos = array_values($idsValidos);
        $placeholders = implode(',', array_fill(0, count($idsValidos), '?'));

        $stmtCarrinho = $conexao->prepare("
            SELECT id, Nome, Valor, imagens, Tamanho, Quantidade_estoque
            FROM produto
            WHERE id IN ($placeholders)
        ");
        $stmtCarrinho->execute($idsValidos);
        $produtosCarrinho = $stmtCarrinho->fetchAll(PDO::FETCH_ASSOC);

        $produtosPorId = [];
        foreach ($produtosCarrinho as $produto) {
            $produtosPorId[(int) $produto['id']] = $produto;
        }

        foreach ($_SESSION['carrinho'] as $chaveCarrinho => $itemCarrinho) {
            if (!is_array($itemCarrinho)) {
                unset($_SESSION['carrinho'][$chaveCarrinho]);
                continue;
            }

            $id = (int) ($itemCarrinho['id_produto'] ?? 0);
            $quantidade = (int) ($itemCarrinho['quantidade'] ?? 0);

            if ($id <= 0 || $quantidade <= 0 || !isset($produtosPorId[$id])) {
                unset($_SESSION['carrinho'][$chaveCarrinho]);
                continue;
            }

            $produto = $produtosPorId[$id];
            $estoque = (int) $produto['Quantidade_estoque'];

            if ($estoque <= 0) {
                unset($_SESSION['carrinho'][$chaveCarrinho]);
                continue;
            }

            $tamanho = trim((string) ($itemCarrinho['tamanho'] ?? 'Único'));
            $tamanhosDisponiveis = obterTamanhosDisponiveis($produto['Tamanho'] ?? null);

            if (!empty($tamanhosDisponiveis)) {
                $tamanhoValido = false;
                foreach ($tamanhosDisponiveis as $tamanhoDisponivel) {
                    if (strcasecmp($tamanhoDisponivel, $tamanho) === 0) {
                        $tamanho = $tamanhoDisponivel;
                        $tamanhoValido = true;
                        break;
                    }
                }
                if (!$tamanhoValido) {
                    unset($_SESSION['carrinho'][$chaveCarrinho]);
                    continue;
                }
            } else {
                $tamanho = 'Único';
            }

            $produto['quantidade_carrinho'] = $quantidade;
            $produto['tamanho_carrinho'] = $tamanho;
            $produto['chave_carrinho'] = $chaveCarrinho;
            $produto['subtotal'] = $quantidade * (float) $produto['Valor'];

            $itensCarrinho[] = $produto;
            $totalCarrinho += $produto['subtotal'];
        }
    }
}
/* =========================================================
   QUANTIDADE TOTAL DE ITENS
   ========================================================= */
$quantidadeItensCarrinho = 0;
foreach ($itensCarrinho as $item) {
    $quantidadeItensCarrinho += (int) $item['quantidade_carrinho'];
}
/* =========================================================
   SITUAÇÃO DO USUÁRIO
   ========================================================= */
$usuarioLogado = $_SESSION['logado'] === true;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Maison Montreval</title>
<style>
:root {
    --marfim: #F4F0E6;
    --marfim-escuro: #E8E1D4;
    --preto: #111111;
    --marrom: #5A3E32;
    --turquesa: #24C6C8;
    --roxo: #5B3A8E;
    --dourado: #C9A227;
    --cinza: #777777;
    --cinza-claro: #B9B2A7;
}
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
html {
    scroll-behavior: smooth;
}
body {
    min-height: 100vh;
    background: radial-gradient(circle at center, #FAF7F0 0%, var(--marfim) 55%, #EDE7DC 100%);
    color: var(--preto);
    font-family: "Times New Roman", Times, serif;
    padding-bottom: 50px;
}
body::before {
    content: "";
    position: fixed;
    top: 0;
    left: 0;
    width: 6px;
    height: 100vh;
    background: linear-gradient(to bottom, var(--roxo) 0%, var(--roxo) 33%, var(--turquesa) 33%, var(--turquesa) 66%, var(--marrom) 66%, var(--marrom) 100%);
    z-index: 1000;
}
header {
    width: 100%;
    background: rgba(244, 240, 230, 0.96);
    border-bottom: 1px solid var(--marfim-escuro);
    box-shadow: 0 8px 30px rgba(17, 17, 17, 0.06);
    padding: 24px 35px;
}
.cabecalho {
    max-width: 1250px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 30px;
}
.logo-area {
    display: flex;
    align-items: center;
    gap: 20px;
}
.logo-montreval {
    display: block;
    width: 88px;
    height: auto;
    border: 2px solid var(--dourado);
    outline: 1px solid rgba(201, 162, 39, 0.35);
    outline-offset: 5px;
    box-shadow: 0 8px 25px rgba(17, 17, 17, 0.12);
}
.marca-site span {
    display: block;
    margin-bottom: 7px;
    color: var(--roxo);
    font-family: Arial, Helvetica, sans-serif;
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 3px;
    text-transform: uppercase;
}
.marca-site h1 {
    color: var(--preto);
    font-family: Didot, "Bodoni MT", "Times New Roman", serif;
    font-size: 27px;
    font-weight: 400;
    letter-spacing: 2px;
    text-transform: uppercase;
}
.menu {
    display: flex;
    align-items: center;
    gap: 14px;
}
.botao-menu {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 125px;
    height: 46px;
    padding: 0 22px;
    border: 1px solid var(--preto);
    background: var(--preto);
    color: var(--marfim);
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 2px;
    text-transform: uppercase;
    text-decoration: none;
    cursor: pointer;
    transition: background-color 0.25s ease, border-color 0.25s ease;
}
.botao-menu:hover {
    background: var(--roxo);
    border-color: var(--roxo);
}
.conteudo {
    width: 100%;
    max-width: 1250px;
    margin: 0 auto;
    padding: 48px 30px 70px;
}
.mensagem {
    max-width: 850px;
    margin: 0 auto 30px;
    padding: 15px 20px;
    text-align: center;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 13px;
    letter-spacing: 0.3px;
    border: 1px solid var(--marfim-escuro);
    background: rgba(244, 240, 230, 0.94);
}
.mensagem.erro {
    color: var(--marrom);
    border-color: rgba(90, 62, 50, 0.35);
}
.mensagem.sucesso {
    color: #267678;
    border-color: rgba(36, 198, 200, 0.45);
}
.titulo-secao {
    text-align: center;
    margin-bottom: 35px;
    color: var(--preto);
    font-family: Didot, "Bodoni MT", "Times New Roman", serif;
    font-size: 32px;
    font-weight: 400;
    letter-spacing: 2px;
    text-transform: uppercase;
}
.titulo-secao::after {
    content: "";
    display: block;
    width: 55px;
    height: 2px;
    margin: 18px auto 0;
    background: linear-gradient(to right, var(--roxo) 0%, var(--roxo) 33%, var(--turquesa) 33%, var(--turquesa) 66%, var(--marrom) 66%, var(--marrom) 100%);
}
.area-busca {
    max-width: 700px;
    margin: 0 auto 38px;
}
.campo-busca {
    width: 100%;
    height: 46px;
    padding: 0 4px;
    border: none;
    border-bottom: 1px solid var(--cinza-claro);
    background: transparent;
    outline: none;
    color: var(--preto);
    font-family: Arial, Helvetica, sans-serif;
    font-size: 14px;
}
.campo-busca:focus {
    border-bottom: 2px solid var(--dourado);
}
.campo-busca::placeholder {
    color: #A09A90;
    font-size: 13px;
}
.produtos {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 30px;
    align-items: stretch;
}
.produto-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: rgba(244, 240, 230, 0.94);
    border: 1px solid var(--marfim-escuro);
    box-shadow: 0 12px 35px rgba(17, 17, 17, 0.07);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}
.produto-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 18px 40px rgba(17, 17, 17, 0.11);
}
.produto-imagem {
    width: 100%;
    height: 260px;
    min-height: 260px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #EDE7DC;
    overflow: hidden;
    border-bottom: 1px solid var(--marfim-escuro);
    flex-shrink: 0;
}
.produto-imagem img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
.sem-imagem {
    color: var(--cinza);
    font-family: Arial, Helvetica, sans-serif;
    font-size: 12px;
    letter-spacing: 1px;
    text-transform: uppercase;
}
.produto-info {
    padding: 22px;
    display: flex;
    flex-direction: column;
    flex: 1;
}
.produto-nome {
    margin-bottom: 10px;
    color: var(--preto);
    font-family: Didot, "Bodoni MT", "Times New Roman", serif;
    font-size: 21px;
    font-weight: 400;
    letter-spacing: 0.5px;
    overflow-wrap: break-word;
}
.seletor-tamanho {
    margin: 0 0 18px;
}
.rotulo-tamanho {
    margin-bottom: 10px;
    color: var(--preto);
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 1.6px;
    text-transform: uppercase;
}
.tamanhos {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}
.tamanhos input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
    pointer-events: none;
}
.tamanhos label {
    min-width: 43px;
    height: 38px;
    padding: 0 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--cinza-claro);
    background: transparent;
    color: var(--preto);
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 1px;
    cursor: pointer;
    transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}
.tamanhos label:hover {
    border-color: var(--preto);
}
.tamanhos input:checked + label {
    border-color: var(--preto);
    background: var(--preto);
    color: var(--marfim);
}
.tamanhos input:focus-visible + label {
    outline: 2px solid var(--dourado);
    outline-offset: 2px;
}
.produto-preco {
    margin-bottom: 8px;
    color: var(--marrom);
    font-size: 20px;
    font-weight: bold;
}
.produto-estoque {
    margin-bottom: 18px;
    color: var(--cinza);
    font-family: Arial, Helvetica, sans-serif;
    font-size: 11px;
    letter-spacing: 0.5px;
}
.produto-info form {
    margin-top: auto;
    width: 100%;
}
.botao-comprar {
    display: block;
    width: 100%;
    height: 46px;
    min-height: 46px;
    padding: 0;
    border: 1px solid var(--preto);
    background: var(--preto);
    color: var(--marfim);
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 2px;
    text-transform: uppercase;
    cursor: pointer;
    transition: background-color 0.25s ease, border-color 0.25s ease;
}
.botao-comprar:hover {
    background: var(--roxo);
    border-color: var(--roxo);
}
.carrinho {
    margin-top: 65px;
    padding: 30px;
    background: rgba(244, 240, 230, 0.94);
    border: 1px solid var(--marfim-escuro);
    box-shadow: 0 12px 35px rgba(17, 17, 17, 0.06);
}
.carrinho h2 {
    margin-bottom: 22px;
    color: var(--preto);
    font-family: Didot, "Bodoni MT", "Times New Roman", serif;
    font-size: 27px;
    font-weight: 400;
    letter-spacing: 1.5px;
    text-transform: uppercase;
}
.item-carrinho {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 18px 0;
    border-bottom: 1px solid var(--marfim-escuro);
}
.item-carrinho:last-child {
    border-bottom: none;
}
.item-nome {
    font-size: 18px;
    color: var(--preto);
}
.item-detalhes {
    margin-top: 5px;
    color: var(--cinza);
    font-family: Arial, Helvetica, sans-serif;
    font-size: 12px;
}
.item-subtotal {
    color: var(--marrom);
    font-size: 18px;
    font-weight: bold;
}
.botao-remover {
    padding: 8px 14px;
    border: 1px solid var(--marrom);
    background: transparent;
    color: var(--marrom);
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 1px;
    text-transform: uppercase;
    cursor: pointer;
    transition: background-color 0.25s ease, color 0.25s ease;
}
.botao-remover:hover {
    background: var(--marrom);
    color: var(--marfim);
}
.total-carrinho {
    display: flex;
    justify-content: space-between;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 2px solid var(--dourado);
    color: var(--preto);
    font-size: 22px;
    font-weight: bold;
}
.botao-finalizar {
    display: block;
    width: 100%;
    height: 50px;
    margin-top: 22px;
    padding: 16px;
    border: 1px solid var(--preto);
    background: var(--preto);
    color: var(--marfim);
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 2px;
    text-align: center;
    text-transform: uppercase;
    text-decoration: none;
    transition: background-color 0.25s ease, border-color 0.25s ease;
}
.botao-finalizar:hover {
    background: var(--roxo);
    border-color: var(--roxo);
}
.login-finalizar {
    margin-top: 22px;
    padding: 22px;
    border: 1px solid var(--marfim-escuro);
    background: rgba(232, 225, 212, 0.45);
    text-align: center;
}
.login-finalizar-texto {
    margin-bottom: 16px;
    color: var(--cinza);
    font-family: Arial, Helvetica, sans-serif;
    font-size: 13px;
    line-height: 1.6;
    letter-spacing: 0.3px;
}
.botao-login-finalizar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 0 28px;
    border: 1px solid var(--preto);
    background: var(--preto);
    color: var(--marfim);
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 2px;
    text-transform: uppercase;
    text-decoration: none;
    cursor: pointer;
    transition: background-color 0.25s ease, border-color 0.25s ease;
}
.botao-login-finalizar:hover {
    background: var(--roxo);
    border-color: var(--roxo);
}
.carrinho-vazio {
    padding: 15px 0;
    color: var(--cinza);
    font-family: Arial, Helvetica, sans-serif;
    font-size: 13px;
    text-align: center;
}
footer {
    margin-top: 30px;
    padding: 28px 20px;
    border-top: 1px solid var(--marfim-escuro);
    color: var(--cinza);
    font-family: Arial, Helvetica, sans-serif;
    font-size: 11px;
    letter-spacing: 1px;
    text-align: center;
}
@media (max-width: 700px) {
    body::before {
        width: 4px;
    }
    header {
        padding: 22px 20px;
    }
    .cabecalho {
        flex-direction: column;
        text-align: center;
    }
    .logo-area {
        flex-direction: column;
    }
    .menu {
        width: 100%;
        justify-content: center;
    }
    .botao-menu {
        min-width: 120px;
    }
    .conteudo {
        padding: 38px 20px 60px;
    }
    .produto-imagem {
        height: 240px;
        min-height: 240px;
    }
    .item-carrinho {
        align-items: flex-start;
        flex-direction: column;
    }
    .total-carrinho {
        flex-direction: column;
        gap: 10px;
    }
    .botao-login-finalizar {
        width: 100%;
    }
}
</style>
</head>
<body>
<header>
<div class="cabecalho">
<div class="logo-area">
<img src="imagens/logo-montreval.png" alt="Maison Montreval" class="logo-montreval">
<div class="marca-site">
<span>Maison Montreval</span>
<h1>Nossa Coleção</h1>
</div>
</div>
<nav class="menu">
<a href="#carrinho" class="botao-menu">
Carrinho
<?php if ($quantidadeItensCarrinho > 0): ?>
(
<?= $quantidadeItensCarrinho ?>
)
<?php endif; ?>
</a>
<?php if ($usuarioLogado): ?>
<a href="bemvindo.php?acao=logout" class="botao-menu">Sair</a>
<?php else: ?>
<a href="login.php" class="botao-menu">Login</a>
<?php endif; ?>
</nav>
</div>
</header>
<main class="conteudo">
<?php if ($mensagem !== ''): ?>
<div class="mensagem <?= htmlspecialchars($tipoMensagem) ?>">
<?= htmlspecialchars($mensagem) ?>
</div>
<?php endif; ?>
<h2 class="titulo-secao">Nossa Coleção</h2>
<div class="area-busca">
<input type="text" id="busca" class="campo-busca" placeholder="Pesquisar produto..." autocomplete="off">
</div>
<section class="produtos" id="listaProdutos">
<?php if (empty($produtos)): ?>
<div class="mensagem sucesso">
Não há unidades disponíveis no momento.
</div>
<?php else: ?>
<?php foreach ($produtos as $produto): ?>
<article class="produto-card" data-nome="<?= htmlspecialchars(strtolower($produto['Nome'])) ?>">
<div class="produto-imagem">
<?php if (!empty($produto['imagens'])): ?>
<img src="<?= htmlspecialchars($produto['imagens']) ?>" alt="<?= htmlspecialchars($produto['Nome']) ?>">
<?php else: ?>
<span class="sem-imagem">Sem imagem</span>
<?php endif; ?>
</div>
<div class="produto-info">
<h3 class="produto-nome">
<?= htmlspecialchars($produto['Nome']) ?>
</h3>
<?php $tamanhosDisponiveis = obterTamanhosDisponiveis($produto['Tamanho'] ?? null); ?>
<div class="produto-preco">
R$
<?= number_format((float) $produto['Valor'], 2, ',', '.') ?>
</div>
<div class="produto-estoque">
<?= (int) $produto['Quantidade_estoque'] ?>
unidade(s) disponível(is)
</div>
<form method="POST">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
<input type="hidden" name="acao" value="comprar">
<input type="hidden" name="id_produto" value="<?= (int) $produto['id'] ?>">
<?php if (!empty($tamanhosDisponiveis)): ?>
<div class="seletor-tamanho">
<div class="rotulo-tamanho">Selecionar tamanho</div>
<div class="tamanhos" role="radiogroup" aria-label="Tamanho de <?= htmlspecialchars($produto['Nome']) ?>">
<?php foreach ($tamanhosDisponiveis as $indiceTamanho => $tamanho): ?>
<?php $idTamanho = 'tamanho_' . (int) $produto['id'] . '_' . $indiceTamanho; ?>
<input
    type="radio"
    name="tamanho"
    id="<?= htmlspecialchars($idTamanho) ?>"
    value="<?= htmlspecialchars($tamanho) ?>"
    <?= $indiceTamanho === 0 ? 'checked' : '' ?>
>
<label for="<?= htmlspecialchars($idTamanho) ?>">
<?= htmlspecialchars($tamanho) ?>
</label>
<?php endforeach; ?>
</div>
</div>
<?php else: ?>
<input type="hidden" name="tamanho" value="Único">
<?php endif; ?>
<button type="submit" class="botao-comprar">
Comprar
</button>
</form>
</div>
</article>
<?php endforeach; ?>
<?php endif; ?>
</section>
<section class="carrinho" id="carrinho">
<h2>Carrinho</h2>
<?php if (empty($itensCarrinho)): ?>
<div class="carrinho-vazio">
Seu carrinho está vazio.
</div>
<?php else: ?>
<?php foreach ($itensCarrinho as $item): ?>
<div class="item-carrinho">
<div>
<div class="item-nome">
<?= htmlspecialchars($item['Nome']) ?>
</div>
<div class="item-detalhes">
Tamanho: <?= htmlspecialchars($item['tamanho_carrinho']) ?>
<br>
<?= (int) $item['quantidade_carrinho'] ?>
x
R$
<?= number_format((float) $item['Valor'], 2, ',', '.') ?>
</div>
</div>
<div class="item-subtotal">
R$
<?= number_format((float) $item['subtotal'], 2, ',', '.') ?>
</div>
<form method="POST">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
<input type="hidden" name="acao" value="remover">
<input type="hidden" name="chave_carrinho" value="<?= htmlspecialchars($item['chave_carrinho']) ?>">
<button type="submit" class="botao-remover">
Remover
</button>
</form>
</div>
<?php endforeach; ?>
<div class="total-carrinho">
<span>Total</span>
<span>
R$
<?= number_format($totalCarrinho, 2, ',', '.') ?>
</span>
</div>
<?php if ($usuarioLogado): ?>
<a href="Pagamento.php" class="botao-finalizar">
Finalizar compra
</a>
<?php else: ?>
<div class="login-finalizar">
<div class="login-finalizar-texto">
Para finalizar sua compra,
é necessário estar conectado à sua conta.
</div>
<a href="login.php" class="botao-login-finalizar">
Fazer login para continuar
</a>
</div>
<?php endif; ?>
<?php endif; ?>
</section>
</main>
<footer>
Maison Montreval —
Elegância em cada detalhe.
</footer>
<script>
const campoBusca = document.getElementById('busca');
const produtos = document.querySelectorAll('.produto-card');
campoBusca.addEventListener('input', function () {
    const termo = this.value.toLowerCase().trim();
    produtos.forEach(function (produto) {
        const nome = produto.dataset.nome || '';
        produto.style.display = nome.includes(termo) ? '' : 'none';
    });
});
</script>
</body>
</html>