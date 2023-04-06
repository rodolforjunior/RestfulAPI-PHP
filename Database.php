<?php
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");

class Database {
    public function __construct(private  $host, //Criando obj da classe usando o método __construct
                                private  $dbname,
                                private  $username,
                                private  $password
                                )
    {}

    public function conecta():PDO {
        try {
            $conn = new PDO("mysql:host=$this->host;dbname=$this->dbname;charset=utf8","$this->username", "$this->password"); 
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); //Passando os atributos do PDO para o handling de erros na conexão com o DB
        }catch (PDOException $exception){ //Caso houver erros durante a conexão, emita o JSON abaixo com os detalhes do erro.
             echo json_encode([
                "Mensagem do erro: " => $exception->getMessage(),
                "Código do erro: " => $exception->getCode(),
                "Linha do erro: " => $exception->getLine(),
                "Arquivo: " => $exception->getFile()
            ]);
        }
        return $conn; //Retornando a conexão, de objeto e tipo PDO assim como especificado após a declaração da função conecta()

    }
}
