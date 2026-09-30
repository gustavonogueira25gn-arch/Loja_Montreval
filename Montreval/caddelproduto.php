<?php
require_once "conexao.php";
// MENSAGEM
$mensagem = "";
// CONTROLADOR DE REQUISIÇÕES
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['btn_cadastrar'])) {
        cadastrarProduto();
    } elseif (isset($_POST['btn_apagar'])) {
        apagarProduto();
    }
}
// CADASTRAR PRODUTO
function cadastrarProduto()
{
    try {
        $conexao = conexao();
        // VERIFICA SE OS CAMPOS EXISTEM
        if (
            !isset(
                $_POST['Nome'],
                $_POST['Valor'],
                $_POST['Quantidade_estoque'],
                $_POST['Tamanho'],
                $_POST['categoria']
            )
        ) {
            echo "
                <p class='notificacao-erro'>
                    Erro: Preencha todos os campos.
                </p>
            ";
            return;
        }
        // NOME
        $nome = trim($_POST['Nome']);
        // VALOR
        $valor_digitado = trim($_POST['Valor']);
        // Permite valor como 59,90
        $valor_digitado = str_replace(',', '.', $valor_digitado);
        $valor = (float) $valor_digitado;
        // ESTOQUE
        $quantidade_estoque = (int) $_POST['Quantidade_estoque'];
        // CATEGORIA
        $categoria = trim($_POST['categoria']);
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
        // TAMANHOS PERMITIDO
        $tamanhos_permitidos = [
            'P',
            'M',
            'G',
            'GG'
        ];
        // Mantém somente tamanhos válidos
        $tamanhos = array_intersect(
            $tamanhos,
            $tamanhos_permitidos
        );
        // VERIFICA TAMANHOS
        if (empty($tamanhos)) {
            echo "
                <p class='notificacao-erro'>
                    Erro: Selecione pelo menos um tamanho.
                </p>
            ";
            return;
        }
        $tamanho = implode(', ', $tamanhos);
        // VALIDAÇÕES
        if (
            empty($nome) ||
            empty($categoria)
        ) {
            echo "
                <p class='notificacao-erro'>
                    Erro: Preencha todos os campos corretamente.
                </p>
            ";
            return;
        }
        if ($valor <= 0) {
            echo "
                <p class='notificacao-erro'>
                    Erro: O valor do produto deve ser maior que zero.
                </p>
            ";
            return;
        }
        if ($quantidade_estoque < 0) {
            echo "
                <p class='notificacao-erro'>
                    Erro: A quantidade em estoque não pode ser negativa.
                </p>
            ";
            return;
        }
        // INSERT
        $sql = "
            INSERT INTO produto
            (
                `Nome`,
                `Valor`,
                `Quantidade_estoque`,
                `Tamanho`,
                `categoria`
            )
            VALUES (?, ?, ?, ?, ?)
        ";
        $stmt = $conexao->prepare($sql);
        $stmt->execute([
            $nome,
            $valor,
            $quantidade_estoque,
            $tamanho,
            $categoria
        ]);
        // SUCESSO
        echo "
            <p class='notificacao-sucesso'>
                Produto cadastrado com sucesso!
            </p>
        ";
    } catch (PDOException $e) {
        echo "
            <p class='notificacao-erro'>
                Erro ao cadastrar produto:
                "
                . htmlspecialchars($e->getMessage())
                . "
            </p>
        ";
    }
} 
// APAGAR PRODUTO
function apagarProduto()
{
    try {
        $conexao = conexao();
        // VERIFICA ID
        if (!isset($_POST['id_produto'])) {
            echo "
                <p class='notificacao-erro'>
                    Erro: ID do produto não informado.
                </p>
            ";
            return;
        }
        $id = (int) $_POST['id_produto'];
        // VALIDA ID
        if ($id <= 0) {
            echo "
                <p class='notificacao-erro'>
                    Erro: ID do produto inválido.
                </p>
            ";
            return;
        }
        // VERIFICA SE O PRODUTO EXISTE
        $sql = "
            SELECT id
            FROM produto
            WHERE id = ?
        ";
        $stmt = $conexao->prepare($sql);
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            echo "
                <p class='notificacao-erro'>
                    Erro: Produto não encontrado.
                </p>
            ";
            return;
        }
        // DELETE
        $sql = "
            DELETE FROM produto
            WHERE id = ?
        ";
        $stmt = $conexao->prepare($sql);
        $stmt->execute([$id]);

        // SUCESSO

        echo "
            <p class='notificacao-sucesso'>
                Produto apagado com sucesso!
            </p>
        ";
    } catch (PDOException $e) {
        echo "
            <p class='notificacao-erro'>
                Erro ao apagar produto:
                "
                . htmlspecialchars($e->getMessage())
                . "
            </p>
        ";
    }
}
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
        Gerenciamento de Produtos
    </title>
    <style>
        /*
           CONFIGURAES GERAIS
        */
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
        /*
           TÍTULOS
        */
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
            margin:
                0 0 5px;
        }
        h2 {
            font-size: 14px;
            color: #777;
            margin:
                0 0 40px;
        }
        h3 {
            font-size: 16px;
            border-bottom:
                1px solid #1a1a1a;
            padding-bottom: 10px;
            margin:
                40px auto 30px;
            max-width: 800px;
        }
        /*
           CONTAINER
        */
        .pagina {
            max-width: 900px;
            margin: 0 auto;
        }
        /*
           FORMULÁRIOS
        */
        .area-formularios {
            display: grid;
            grid-template-columns:
                1fr 1fr;
            gap: 60px;
            max-width: 800px;
            margin: 0 auto;
        }
        .formulario-box {
            width: 100%;
        }
        .form-group {
            margin-bottom: 18px;
        }
        /*
           LABELS
        */
        label {
            display: block;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #555;
            margin-bottom: 5px;
        }
        /*
           INPUTS
        */
        input[type="text"],
        input[type="number"] {
            width: 100%;
            background-color:
                transparent;
            border: none;
            border-bottom:
                1px solid #ccc;
            padding: 7px 0;
            font-family: inherit;
            font-size: 14px;
            color: #1a1a1a;
            outline: none;
        }
        input[type="text"]:focus,
        input[type="number"]:focus {
            border-bottom-color:
                #1a1a1a;
        }
        /*
           TAMANHOS
        */
        .grade-tamanhos-wrapper {
            display: flex;
            gap: 6px;
            margin-top: 5px;
        }
        .tag-tamanho {
            cursor: pointer;
            flex: 1;
            text-align: center;
            margin: 0;
        }
        .tag-tamanho input {
            display: none;
        }
        .tag-tamanho span {
            display: block;
            padding: 8px 0;
            border:
                1px solid #ccc;
            font-size: 11px;
            color: #555;
            transition:
                all 0.2s ease;
        }
        .tag-tamanho:hover span {
            border-color:
                #1a1a1a;
        }
        .tag-tamanho input:checked + span {
            background-color:
                #1a1a1a;
            color: #fff;
            border-color:
                #1a1a1a;
        }
        /*
           ESTOQUE E VALOR
        */
        .form-row-duplo {
            display: flex;
            gap: 15px;
        }
        .form-row-duplo .form-group {
            flex: 1;
        }
        /*
           BOTÕES
        */
        .btn-principal {
            background-color:
                #1a1a1a;
            color: #fff;
            border:
                1px solid #1a1a1a;
            padding: 12px;
            width: 100%;
            font-family: inherit;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
            cursor: pointer;
            margin-top: 10px;
            transition:
                all 0.3s ease;
        }
        .btn-principal:hover {
            background-color:
                transparent;
            color:
                #1a1a1a;
        }
        .btn-apagar {
            background-color:
                transparent;
            color:
                #1a1a1a;
            border:
                1px solid #1a1a1a;
        }
        .btn-apagar:hover {
            background-color:
                #1a1a1a;
            color: #fff;
        }
        /*
           DIVISÓRIA
        */
        .divisoria {
            max-width: 800px;
            margin:
                60px auto 40px;
            border: none;
            border-top:
                1px solid #e2ded6;
        }
        /*
           NOTIFICAÇÕES
        */
        .notificacao-sucesso,
        .notificacao-erro {
            text-align: center;
            font-size: 13px;
            letter-spacing: 1px;
            margin:
                20px auto;
            max-width: 500px;
            padding: 10px;
            text-transform: uppercase;
        }
        .notificacao-sucesso {
            color: #28a745;
        }
        .notificacao-erro {
            color: #dc3545;
        }
        /*
           MENU
        */
        .botoes-controle {
            text-align: center;
            margin:
                40px auto 20px;
            max-width: 800px;
            border-top:
                1px solid #e2ded6;
            padding-top: 30px;
        }
        .botao-link {
            display: inline-block;
            color: #1a1a1a;
            text-decoration: none;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 1px;
            margin:
                0 10px 15px;
            padding:
                10px 20px;
            border:
                1px solid #1a1a1a;
            transition:
                all 0.3s;
        }
        .botao-link:hover {

            background-color:
                #1a1a1a;

            color: #fff;
        }
    </style>
</head>
<body>
    <main class="pagina">
        <!--
             CABEÇALHO
        -->
        <h1>
            Cadastro de Produtos
        </h1>
        <h2>
            Gerenciamento da loja
        </h2>
        <!--
             FORMULÁRIOS
        -->
        <section class="area-formularios">
            <!--
                 CADASTRAR
            -->
            <div class="formulario-box">
                <h3>
                    Novo Produto
                </h3>
                <form
                    action=""
                    method="POST"
                >
                    <!-- NOME -->
                    <div class="form-group">
                        <label for="Nome">
                            Nome do Produto
                        </label>
                        <input
                            type="text"
                            id="Nome"
                            name="Nome"
                            placeholder="Ex: Camiseta Básica"
                            required
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
                                <label
                                    class="tag-tamanho"
                                >
                                    <input
                                        type="checkbox"
                                        name="Tamanho[]"
                                        value="<?php
                                            echo htmlspecialchars($tam);
                                        ?>"
                                    >
                                    <span>
                                        <?php
                                        echo htmlspecialchars($tam);
                                        ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <!-- ESTOQUE E VALOR -->
                    <div class="form-row-duplo">
                        <div class="form-group">
                            <label
                                for="Quantidade_estoque"
                            >
                                Estoque
                            </label>
                            <input
                                type="number"
                                id="Quantidade_estoque"
                                name="Quantidade_estoque"
                                min="0"
                                placeholder="0"
                                required
                            >
                        </div>
                        <div class="form-group">
                            <label for="Valor">
                                Valor (R$)
                            </label>
                            <input
                                type="text"
                                id="Valor"
                                name="Valor"
                                placeholder="0,00"
                                required
                            >
                        </div>
                    </div>
                    <!-- CATEGORIA -->
                    <div class="form-group">
                        <label for="categoria">
                            Categoria
                        </label>
                        <input
                            type="text"
                            id="categoria"
                            name="categoria"
                            placeholder="Ex: Calça"
                            required
                        >
                    </div>
                    <!-- BOTÃO -->
                    <button
                        type="submit"
                        name="btn_cadastrar"
                        class="btn-principal"
                    >
                        Cadastrar Produto
                    </button>
                </form>
            </div>
            <!--
                 APAGAR
            -->
            <div class="formulario-box">
                <h3>
                    Apagar Produto
                </h3>
                <form
                    action=""
                    method="POST"
                    onsubmit="
                        return confirm(
                            'Tem certeza que deseja apagar este produto?'
                        );
                    "
                >
                    <div class="form-group">
                        <label for="id_produto">
                            ID do Produto
                        </label>
                        <input
                            type="number"
                            id="id_produto"
                            name="id_produto"
                            min="1"
                            placeholder="Digite o ID"
                            required
                        >
                    </div>
                    <button
                        type="submit"
                        name="btn_apagar"
                        class="btn-principal btn-apagar"
                    >
                        Apagar Produto
                    </button>
                </form>
            </div>
        </section>
        <!--
             MENU
        -->
        <div class="botoes-controle">
            <a
                href="interfaceadm.php"
                class="botao-link"
            >
                Voltar aos produtos
            </a>
        </div>
    </main>
</body>
</html>