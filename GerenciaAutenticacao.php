<?php

class GerenciaAutenticacao
{
    private PDO $conn;
    private ProcessaRequest $request;
    public function __construct(Database $database, ProcessaRequest $request)
    {
        $this->conn = $database->conecta();
        $this->request = $request;
    }
    public function isLogin() //User está tentando fazer login? Então vamos validar os dados.
    {
        $dados = file_get_contents("php://input");
        $dados = json_decode($dados, true);

        if (isset($dados['user']) && isset($dados['pwd'])) { //O usuário informou o email & password?
            $usuario = $this->request->autenticarLogin($dados);
           
            if (!empty($usuario)) {
                $token = $usuario[0]['token'];
                $salt = $usuario[0]['salt'];
               
                if ($this->isTokenValid($token)) {
                    return $token;
                } else {
                    return false;
                }
            } else {
                http_response_code(403);
                throw new Exception("Usuário ou senha inválidos.", 403);
            }
        } else {
            return false;
        }
    }

    public function isTokenValid($t)
    {
      
        if (strpos($t, 'Bearer ') !== false) {
            $t = str_replace('Bearer ', '', $t);
        }

        $sql = "SELECT token, salt from tokens WHERE token = :t";

        $preparaTokenVerif = $this->conn->prepare($sql);
        $preparaTokenVerif->bindParam(":t",  $t, PDO::PARAM_STR);

        $preparaTokenVerif->execute();

        $coluna = $preparaTokenVerif->fetch(PDO::FETCH_ASSOC);
        
        if ($coluna) {
            $validadeToken = $this->getTokenExpiration($coluna);

            if ($validadeToken === true) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function getTokenExpiration($t)
    {
      
        $t = implode(". ", $t);

        list($hdr, $pay, $sig) = explode(".", $t);

        $payload = base64_decode($pay, true);

        $payload =  json_decode($payload);

        $tokenTime = $payload->exp;
        $dataAtual = time() - (3 * 60 * 60);    

        if ($tokenTime > $dataAtual) {
            return true;
        } else {
            return false;
        }
    }
}
