<?php
//Usuario
require_once "conexao.php";
function erroLogin($tipo){
    if(session_status()===PHP_SESSION_NONE){
        session_start();
    }
    switch($tipo){
        case 'credenciais':
            $_SESSION['erro_login']='E-mail ou senha incorretos.';
            break;
        case 'campos':
            $_SESSION['erro_login']='Preencha o e-mail e a senha.';
            break;
        case 'sistema':
            $_SESSION['erro_login']='Não foi possível realizar o login. Tente novamente.';
            break;
        default:
            $_SESSION['erro_login']='Não foi possível realizar o login. Tente novamente.';
            break;
    }
    header("Location: login.php");
    exit;
}
function efetuarLogin(){
    try{
        $conexao=conexao();
        if(!isset($_POST['Email'])||!isset($_POST['senha'])){
            erroLogin('campos');
        }
        $email=strtolower(trim($_POST['Email']));
        $senha=$_POST['senha'];
        if(empty($email)||empty($senha)){
            erroLogin('campos');
        }
        if(str_ends_with($email,'@montreval.com')){
            $tabela="administradores";
            $is_admin=true;
        }else{
            $tabela="usuario";
            $is_admin=false;
        }
        $sql="
            SELECT *
            FROM `$tabela`
            WHERE LOWER(TRIM(`Email`))=?
        ";
        $stmt=$conexao->prepare($sql);
        $stmt->execute([$email]);
        $usuario=$stmt->fetch(PDO::FETCH_ASSOC);
        if($usuario&&isset($usuario['senha'])&&strcmp($usuario['senha'],$senha)===0){
            if(session_status()===PHP_SESSION_NONE){
                session_start();
            }
            session_regenerate_id(true);
            $_SESSION['logado']=true;
            $_SESSION['nome_usuario']=$usuario['Nome'];
            $_SESSION['perfil']=$is_admin?'admin':'usuario';
            if(!$is_admin){
                $_SESSION['ID_Usuario']=$usuario['ID'];
            }
            if($is_admin){
                header("Location: interfaceadm.php");
            }else{
                header("Location: bemvindo.php");
            }
            exit;
        }
        erroLogin('credenciais');
    }catch(PDOException $e){
        erroLogin('sistema');
    }
}
function cadastrarUsuario(){
    try{
        $conexao=conexao();
        if(isset($_POST['Cpf'],$_POST['Nome'],$_POST['Idade'],$_POST['Email'],$_POST['Endereco'],$_POST['senha'])){
            $cpf=trim($_POST['Cpf']);
            $nome=trim($_POST['Nome']);
            $idade=(int)$_POST['Idade'];
            $email=strtolower(trim($_POST['Email']));
            $endereco=trim($_POST['Endereco']);
            $senha=$_POST['senha'];
            if(empty($cpf)||empty($nome)||empty($email)||empty($endereco)||empty($senha)){
                header("Location: cadastro.php?erro=campos");
                exit;
            }
            if($idade<18){
                header("Location: cadastro.php?erro=idade");
                exit;
            }
            if(!filter_var($email,FILTER_VALIDATE_EMAIL)){
                header("Location: cadastro.php?erro=email");
                exit;
            }
            if(strlen($senha)<8){
                header("Location: cadastro.php?erro=senha");
                exit;
            }
            $sql="
                INSERT INTO usuario
                (`Cpf`,`Nome`,`Idade`,`Email`,`Endereço`,`senha`)
                VALUES(?,?,?,?,?,?)
            ";
            $stmt=$conexao->prepare($sql);
            $stmt->execute([
                $cpf,
                $nome,
                $idade,
                $email,
                $endereco,
                $senha
            ]);
            header("Location: cadastro.php?sucesso=1");
            exit;
        }else{
            header("Location: cadastro.php?erro=campos");
            exit;
        }
    }catch(PDOException $e){
        header("Location: cadastro.php?erro=sistema");
        exit;
    }
}
$acao=isset($_POST['acao'])?$_POST['acao']:'';
if($acao==='login'){
    efetuarLogin();
}elseif($_SERVER['REQUEST_METHOD']==='POST'){
    cadastrarUsuario();
}
?>