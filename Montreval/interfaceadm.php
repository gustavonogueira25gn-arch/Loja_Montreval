<?php
session_start();
include 'conexao.php';
$conexao = conexao();
// VERIFICA LOGIN
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: login.html");
    exit();
}
$mensagem = "";
// ATUALIZA PRODUTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_produto'])) {
    try {
        // ID DO PRODUTO
        $id = isset($_POST['id_produto'])
            ? (int) $_POST['id_produto']
            : 0;
        // NOME DO PRODUTO
        $nome = isset($_POST['nome_produto'])
            ? trim($_POST['nome_produto'])
            : '';
        // TAMANHOS
        $tamanhos = $_POST['Tamanho'] ?? [];
        // Garante que seja array
        if (!is_array($tamanhos)) {
            $tamanhos = [$tamanhos];
        }
        // Remove espaços
        $tamanhos = array_map('trim', $tamanhos);
        // Remove valores vazios
        $tamanhos = array_filter($tamanhos);
        // Remove tamanhos repetidos
        $tamanhos = array_unique($tamanhos);
        /*
         * Exemplo:
         * ['P', 'G']
         * vira:
         * P, G
         */
        $tamanho = implode(', ', $tamanhos);
        // ESTOQUE
        $estoque = isset($_POST['estoque_produto'])
            ? (int) $_POST['estoque_produto']
            : 0;
        // VALOR
        $valor_digitado = isset($_POST['valor_produto'])
            ? trim($_POST['valor_produto'])
            : '0';
        /*
         * Aceita:
         * 59,90
         * e transforma em:
         * 59.90
         */
        $valor_digitado = str_replace(',', '.', $valor_digitado);
        $valor = (float) $valor_digitado;
        // VERIFICA ID
        if ($id <= 0) {
            throw new Exception("ID do produto inválido.");
        }
        // ATUALIZA BANCO
        $sql_update = "
            UPDATE produto
            SET
                Nome = ?,
                Tamanho = ?,
                Quantidade_estoque = ?,
                Valor = ?
            WHERE id = ?
        ";
        $stmt = $conexao->prepare($sql_update);
        $stmt->execute([
            $nome,
            $tamanho,
            $estoque,
            $valor,
            $id
        ]);
        $mensagem = "
            <p class='notificacao-sucesso'>
                Produto atualizado com sucesso!
            </p>
        ";
    } catch (Exception $e) {
        $mensagem = "
            <p class='notificacao-erro'>
                Erro ao atualizar: "
                . htmlspecialchars($e->getMessage())
                . "
            </p>
        ";
    }
}
// BUSCA OS PRODUTOS
$sql = "
    SELECT
        id,
        Nome,
        Tamanho,
        Quantidade_estoque,
        Valor,
        imagens
    FROM produto
    ORDER BY id DESC
";
$resultado = $conexao->query($sql);
$produtos = $resultado->fetchAll(PDO::FETCH_ASSOC);
$imagem_padrao = "uploads/sem-foto.png";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>
        Painel Administrativo - Maison Montreval
    </title>
    <style>
        * {
           box-sizing: border-box;
        }
        body {
            font-family:
                'Times New Roman',
                Times,
                serif;
            background-color: #fcf9f2;
            color: #1a1a1a;
            margin: 0;
            padding: 40px 20px;
        }
        h1,
        h2,
        h3 {
            font-weight: 300;
            letter-spacing: 2px;
            text-transform: uppercase;
            text-align: center;
        }
        h1 {
            font-size: 22px;
            margin-bottom: 5px;
        }
        h2 {
            font-size: 14px;
            color: #777;
            margin-bottom: 40px;
        }
        h3 {
            font-size: 16px;
            border-bottom: 1px solid #1a1a1a;
            padding-bottom: 10px;
            margin: 40px auto 30px;
            max-width: 1200px;
        }
        /* NOTIFICAÇÕES */
        .notificacao-sucesso,
        .notificacao-erro {
            text-align: center;
            font-size: 14px;
            letter-spacing: 1px;
            margin: 20px auto;
            max-width: 400px;
            padding: 10px;
            text-transform: uppercase;
        }
        .notificacao-sucesso {
            color: #28a745;
        }
        .notificacao-erro {
            color: #dc3545;
        }
        /* PRODUTOS */
        .container-produtos {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 40px;
            max-width: 1200px;
            margin: 0 auto;
        }
        /* CARD */
        .card-produto {
            width: 260px;
            display: flex;
            flex-direction: column;
        }
        /* IMAGEM */
        .produto-foto-wrapper {
            width: 100%;
            height: 340px;
            background-color: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 15px;
        }
        .card-produto img {
            width: 100%;
            height: 100%;
           object-fit: cover;
        }
        /* FORMULÁRIOS */
        .form-group {
            margin-bottom: 14px;
        }
        .card-produto label {
            display: block;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #555;
            margin-bottom: 4px;
        }
        .card-produto input[type="text"],
        .card-produto input[type="number"] {
            width: 100%;
            background-color: transparent;
            border: none;
            border-bottom: 1px solid #ccc;
            padding: 6px 0;
            font-family: inherit;
            font-size: 14px;
            color: #1a1a1a;
            outline: none;
        }
        .card-produto input:focus {
            border-bottom-color: #1a1a1a;
        }
        /* TAMANHOS */
        .grade-tamanhos-wrapper {
            display: flex;
            gap: 6px;
            margin-top: 5px;
        }
        .tag-tamanho {
            cursor: pointer;
            flex: 1;
            text-align: center;
        }
        .tag-tamanho input {
            display: none;
        }
        .tag-tamanho span {
            display: block;
            padding: 6px 0;
            border: 1px solid #ccc;
            font-size: 11px;
            color: #555;
            transition: all 0.2s ease;
        }
        .tag-tamanho input:checked + span {
            background-color: #1a1a1a;
            color: #fff;
            border-color: #1a1a1a;
        }
        /* ESTOQUE E VALOR */
        .form-row-duplo {
            display: flex;
            gap: 20px;
                width: 100%;
        }
        .form-row-duplo .form-group {
            flex: 1;
            min-width: 0;
        }

.form-row-duplo input[type="number"],
.form-row-duplo input[type="text"] {
    width: 100%;
    max-width: 100%;
    background-color: transparent;
    border: none;
    border-bottom: 1px solid #ccc;
    padding: 6px 0;
    font-family: inherit;
    font-size: 14px;
    color: #1a1a1a;
    outline: none;
}

.form-row-duplo input:focus {
    border-bottom-color: #1a1a1a;
}
        /* BOTÃO */
        .btn-salvar {
            background-color: #1a1a1a;
            color: #fff;
            border: 1px solid #1a1a1a;
            padding: 12px;
            width: 100%;
            font-family: inherit;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.3s ease;
        }
        .btn-salvar:hover {
            background-color: transparent;
            color: #1a1a1a;
        }
        /* MENU */
        .botoes-controle {
            text-align: center;
            margin: 60px auto 20px;
            max-width: 1200px;
            border-top: 1px solid #e2ded6;
            padding-top: 40px;
        }
        .botao-link {
            display: inline-block;
            color: #1a1a1a;
            text-decoration: none;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 1px;
            margin: 0 10px 15px;
            padding: 10px 20px;
            border: 1px solid #1a1a1a;
            transition: all 0.3s;
        }
        .botao-link:hover {
            background-color: #1a1a1a;
            color: #fff;
        }
        .link-sair {
            display: block;
            margin-top: 20px;
            color: #777;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-decoration: none;
        }
        .link-sair:hover {
            color: #000;
        }
        /* VAZIO */
        .alerta-vazio {
            text-align: center;
            width: 100%;
            color: #666;
            font-style: italic;
            margin: 40px 0;
        }
    </style>
</head>
<body>
    <!-- CABEÇALHO -->
    <h1>
        Login efetuado com sucesso
    </h1>
    <h2>
        Bem vindo Administrador,
        <?php
        echo htmlspecialchars(
            $_SESSION['nome_usuario'] ?? 'Admin'
        );
        ?>!
    </h2>
    <h3>
        Produtos Da Loja
    </h3>
    <!-- MENSAGEM -->
    <?php echo $mensagem; ?>
    <!-- PRODUTOS -->
    <div class="container-produtos">
        <?php if (!empty($produtos)): ?>
            <?php foreach ($produtos as $item): ?>
                <?php
                // IMAGEM
                $foto = !empty($item['imagens'])
                    ? $item['imagens']
                    : $imagem_padrao;

                // TAMANHOS SALVOS
                $tamanhos_salvos = [];
                if (!empty($item['Tamanho'])) {
                    $tamanhos_salvos = array_map(
                        'trim',
                        explode(
                            ',',
                            $item['Tamanho']
                        )
                    );
                }
                ?>
                <!-- FORMULÁRIO -->
                <form
                    action=""
                    method="post"
                    class="card-produto"
                >
                    <!-- ID -->
                    <input
                        type="hidden"
                        name="id_produto"
                        value="<?php echo (int)$item['id']; ?>"
                    >
                    <!-- ATUALIZAÇÃO -->
                    <input
                        type="hidden"
                        name="atualizar_produto"
                        value="1"
                    >
                    <!-- IMAGEM -->
                    <div class="produto-foto-wrapper">
                        <img
                            src="<?php echo htmlspecialchars($foto); ?>"
                            alt="Imagem de <?php echo htmlspecialchars($item['Nome']); ?>"
                        >
                    </div>
                    <!-- NOME -->
                    <div class="form-group">
                        <label>
                            Nome do Produto
                        </label>
                        <input
                            type="text"
                            name="nome_produto"
                            value="<?php echo htmlspecialchars($item['Nome']); ?>"
                            required
                            placeholder="Ex: Camiseta Básica"
                        >
                    </div>
                    <!-- TAMANHOS -->
                    <div class="form-group">
                        <label>
                            Tamanhos Disponíveis
                        </label>
                        <div class="grade-tamanhos-wrapper">
                            <?php foreach (
                                ['P', 'M', 'G', 'GG']
                                as $tam
                            ): ?>
                                <label class="tag-tamanho">
                                    <input
                                        type="checkbox"
                                        name="Tamanho[]"
                                        value="<?php echo htmlspecialchars($tam); ?>"
                                        <?php
                                        echo in_array(
                                            $tam,
                                            $tamanhos_salvos,
                                            true
                                        )
                                            ? 'checked'
                                            : '';
                                        ?>
                                    >
                                    <span>
                                        <?php echo htmlspecialchars($tam); ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <!-- ESTOQUE E VALOR -->
                    <div class="form-row-duplo">
                        <div class="form-group">
                            <label>
                                Estoque (Qtd)
                            </label>
                            <input
                                type="number"
                                name="estoque_produto"
                                value="<?php echo (int)$item['Quantidade_estoque']; ?>"
                                min="0"
                                required
                            >
                        </div>
                        <div class="form-group">
                            <label>
                                Valor (R$)
                            </label>
                            <input
                                type="text"
                                name="valor_produto"
                                value="<?php echo number_format(
                                    (float)$item['Valor'],
                                    2,
                                    ',',
                                    ''
                                ); ?>"
                                required
                                placeholder="0,00"
                            >
                        </div>
                    </div>
                    <!-- BOTÃO -->
                    <button
                        type="submit"
                        class="btn-salvar"
                    >
                        Salvar Alterações
                    </button>
                </form>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alerta-vazio">
                <p>
                    Nenhum produto cadastrado no banco de dados.
                </p>
            </div>
        <?php endif; ?>
    </div>
    <!-- MENU -->
    <div class="botoes-controle">
        <a
            href="caddelproduto.php"
            class="botao-link"
        >
            atualizar produto na loja
        </a>
        <a
            href="logout.php"
            class="link-sair"
            Sair da conta
        >
        </a>
    </div>
</body>
</html>