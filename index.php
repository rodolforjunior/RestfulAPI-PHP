<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");
header("Access-Control-Allow-Headers: X-Requested-With");
header('Access-Control-Expose-Headers: Authorization');
header('Content-Type: application/json; charset=utf-8');




require_once './Database.php';
require_once './GerenciaRequests.php';
require_once './ProcessaRequest.php';
require_once './Auth.php';
require_once './GerenciaAutenticacao.php';



$id = isset($_GET['id']) ? intval($_GET['id']) : null; //Checando a query se houve passagem de um parâmetro chamado 'id', se sim faço um cast no valor para int, se não : o id receberá null

$banco = new Database("localhost", "user_control", "root", ""); //Criando o banco de dados a partir do objeto 'Database'
$req = new ProcessaRequest($banco);

$isLoggingOn = false; //Variável de controle para estabelecer se o usuário está tentando logar no portal
$getAuthHeader = ""; //Inicializando a variável que recebe o token (caso exista)

try {
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Existe um header de autenticação?
        $getAuthHeader = $_SERVER['HTTP_AUTHORIZATION']; //Armazena-se na variável getAuthHeader
        $isTokenSet = explode(" ", $getAuthHeader); //Para checar se o usuário está tentando logar, utilizo o explode para ver se existe um token na posição [1] do array gerado pela função

        if (!isset($isTokenSet[1])) { //O array explodido contém somente o modo de autenticação bearer na posição [0]? Então esse usuário quer fazer login
            $isLoggingOn = true;
        }
    }
    if ($isLoggingOn === true) {
        $req = new ProcessaRequest($banco);
        $processaLogin = new GerenciaAutenticacao($banco, $req);

        $processaLogin = $processaLogin->isLogin();

        if ($processaLogin != false) { //Se entrar nessa condição, processaLogin recebeu o token como retorno
            $getAuthHeader = $processaLogin;
        }
    }
    if ($getAuthHeader) {
        
        $token = $getAuthHeader;
        $validaToken = new GerenciaAutenticacao($banco, $req);
        $salt = ""; 
        if ($validaToken->isTokenValid($token)) {
        } else {
            throw new Exception("Token de autenticação expirado.", 401);
        }
    } else {
        throw new Exception("Usuário não autenticado.", 401);
    }
} catch (Exception $e) {
    echo json_encode([
        "Mensagem" => $e->getMessage(). " Verifique seus dados ou contate o administrador.",
        "Status" => $e->getCode(),
     
    ]);
    exit();
}

$procReq = new ProcessaRequest($banco);  //Criando objeto ProcessaRequest e passando o banco como args. A classe ProcessaRequest contém as funções da API
$checkRequest = new GerenciaRequests($procReq); //Criando novo objetdo da classe GerenciaRequests para fazer o handling dos métodos HTTP (Passando o objeto processaRequest como parametro) 
$banco->conecta(); //Chamando o método conecta da Classe Database a partir do objeto banco

$checkRequest->verificaRequest($_SERVER['REQUEST_METHOD'], $id); //Chamando a função VERIFICA REQUEST da classe GERENCIA REQUESTS (Handling de métodos) 
