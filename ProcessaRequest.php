<?php

class ProcessaRequest
{
    private PDO $conn;
    public function __construct(Database $database)
    { //A classe processaRequest precisa acessar o banco para fazer o select e retornar os dados. É processado uma instância do objeto Database para fazer a conexão
        $this->conn = $database->conecta();
    }

    public function listaDados() //Lista usuários
    {
        $sql = "SELECT * FROM users"; //Preparando a consulta no banco, armazenando a query na var SQL para executar a query e armazenar na var STMT abaixo 
        $stmt = $this->conn->query($sql); //Armazena resultado da query

        return $stmt->fetchAll(PDO::FETCH_ASSOC); //Retornar o resultado com o método PDO fetch all
    }
    public function getUserIdBeforeDelete($id, $token) { 
        //Função para verificar o ID do usuário antes de fazer um delete no DB. Para chegar até aqui o usuário está listando os usuários cadastrados, se ele tentar excluir o próprio
        //Usuário uma exceção será expedida.
        
        $query = "SELECT token FROM tokens WHERE token = :t and user_id = :u";
        $query = $this->conn->prepare($query);

        $id = (int)$id;
        
        $query->bindValue(":t", $token, PDO::PARAM_STR);
        $query->bindValue(":u", $id, PDO::PARAM_INT);

        $query->execute();

        $res = $query->fetchAll(PDO::FETCH_ASSOC);

        return $res;



    }
    /*-------------------------------------------------------------------------------------------------------------------------*/
    // Autenticação & Validações no CRUD do usuário.  
    public function createUsuario($dados)
    {
        $sql = "INSERT INTO 
        users (nome, sobrenome, email, senha, salt) 
        VALUES (:nome, :sobrenome, :email, :senha, :salt)";

        $geraSalt = bin2hex(random_bytes(16));
        $salt = $geraSalt;
        $senha = $dados['senha'] . $geraSalt;
        $senha = hash('sha256', $senha);

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(":nome", $dados["nome"], PDO::PARAM_STR);
            $stmt->bindValue(":sobrenome", $dados["sobrenome"], PDO::PARAM_STR);
            $stmt->bindValue(":email", $dados["email"], PDO::PARAM_STR);
            $stmt->bindValue(":senha", $senha, PDO::PARAM_STR);

            $stmt->bindValue(":salt", $salt, PDO::PARAM_STR);
            $stmt->execute();

            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            http_response_code(409);
            throw new Exception("O email informado já existe.", 409);
        }
    }

    public function validaPassword($dados, $minTimes = 30)
    {
        $isPasswordValid = true;
        $senha = substr(sha1($dados['senha']), 0, 5);
        $comparaSenha = sha1($dados['senha']);
        $comparaSenha = substr($comparaSenha, -20);

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.pwnedpasswords.com/range/" . $senha);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $resposta = curl_exec($ch);

            if (curl_errno($ch)) {
                http_response_code(500);
                throw new Exception("Erro ao acessar a API \n" . curl_error($ch). "\n Contate o administrador.\n", 500);
            } else {

                $fetchedResposta = explode(PHP_EOL, $resposta);

                for ($i = 0; $i < count($fetchedResposta); $i++) {

                    $objDados = explode(":", $fetchedResposta[$i]);
                    $stringTeste = substr($objDados[0], -20); //Atribuindo os 10 últimos caracteres do SHA1 para a variável res para comparar com a var (if (compare...)) durante a iteração      

                    if ((strtolower($comparaSenha) == strtolower($stringTeste) && $objDados[1] > $minTimes)) {
                        $isPasswordValid = false;
                        http_response_code(403);
                        throw new Exception("Atenção! Sua senha já foi exposta em vazamentos de dados mais de " . $objDados[1] . " vezes. Defina uma nova senha", 403);
                    }
                }
            }
            if ($isPasswordValid) {
                return true;
            }
        } catch (Exception $e) {
            echo json_encode([
                "Mensagem" => $e->getMessage(),
                "Status" => $e->getCode()
            ]);
        }
    }
    public function update($dados)
    {
        $id = intval($dados['id']);

        $sql = "UPDATE users 
        SET nome = :nome, sobrenome = :sobrenome, email = :email 
        WHERE id = :id";

        $preparaQuery = $this->conn->prepare($sql);

        $preparaQuery->bindValue(":nome", $dados["nome"]);
        $preparaQuery->bindValue(":sobrenome", $dados["sobrenome"]);
        $preparaQuery->bindValue(":email", $dados["email"]);

        $preparaQuery->bindValue(":id", $id, PDO::PARAM_INT);

        $preparaQuery->execute();

        return $dados;
    }

    public function delete($id)
    {

        $sql = "DELETE FROM users WHERE id = :id";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue(":id", $id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return true;
        } else {
            return false;
        }
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Criação de Token e Validação de login de usuários.
    public function autenticarLogin($dados) //Autenticação de LOGIN
    {

        $sql = "SELECT email, id, nome, senha, salt FROM users WHERE email = :email LIMIT 1"; //Preparando a consulta SQL, definindo o que precisa ser selecionado com o SELECT e quais campos precisam ser filtrados
        $buscaUsuario = $this->conn->prepare($sql);

        $buscaUsuario->bindValue(':email', $dados['user']);
        $buscaUsuario->execute();

        if (!$usuario = $buscaUsuario->fetchAll(PDO::FETCH_ASSOC)) {
            http_response_code(404);
            throw new Exception("Usuário não encontrado.", 404);
        }


        $senha = $usuario[0]['senha'];
        $salt = $usuario[0]['salt'];

        $senhaInput = hash('sha256', $dados['pwd'] . $salt);
        $sql = "SELECT email, id, nome, senha, salt FROM users WHERE email = :email AND senha = :senha AND salt = :salt LIMIT 1";
        $buscaUsuario = $this->conn->prepare($sql);


        $buscaUsuario->bindValue(':email', $dados['user']);
        $buscaUsuario->bindValue(':senha', $senhaInput);
        $buscaUsuario->bindValue(':salt', $salt);
        $buscaUsuario->execute();
        $usuario = $buscaUsuario->fetchAll(PDO::FETCH_ASSOC);



        if ($usuario) { //Usuário existe? Então vamos autentica-lo
            $token = $this->geraToken($usuario); //Chama a função da classe Auth passando os dados do usuário como parâmetro

            $idUsuario = $usuario[0]['id'];

            $idUsuario = (int)$idUsuario; //Armazenando o id do usuário na var IDus
            if ($this->gravarToken($token, $idUsuario)) { //O token foi registrado na tabela com sucesso? Então, true

                $usuario[0]['token'] = $token;

                return $usuario;
            } else {
                throw new Exception("OCORREU UM ERRO AO GRAVAR O TOKEN", 403);
            }
        } else {
            http_response_code(404);
            throw new Exception("Usuário não encontrado.", 404);
        }
    }


    public function geraToken($usuario)
    {
        $email = $usuario[0]['email'];
        $senha = $usuario[0]['senha'];

        $gerar = new Auth(); //Criando um objeto da classe Auth

        $token = $gerar->criarToken($email, $senha); //Atribuindo a resposta do retorno da função "CRIAR TOKEN", passandoo nome e a senha como param

        return $token; //Retorna o resultado do $token
    }

    public function gravarToken($token, $idUsuario) //Tentando gravar o token na tabela
    {

        $sql = "SELECT user_id FROM tokens WHERE user_id = :user_id";

        $userExists = $this->conn->prepare($sql);

        $userExists->bindValue(':user_id', $idUsuario);
        $userExists->execute();


        if ($userExists->fetch(PDO::FETCH_ASSOC)) {
            $sql = "UPDATE tokens SET token = :t  WHERE user_id = :u";
            $userExists = $this->conn->prepare($sql);

            // $geraSalt = bin2hex(random_bytes(16));
            // $salt = $geraSalt;
            // $token = hash('sha256', $token . $salt);

            $userExists->bindValue(':t', $token);
            $userExists->bindValue(':u', $idUsuario);

            if ($userExists->execute()) {

                setcookie("token", $token, time() - (3 * 60 * 60) + 50000);
                return true;
            }
        } else {
            $sql = "INSERT INTO tokens (token, user_id) VALUES (:t, :i)";

            // $geraSalt = bin2hex(random_bytes(16));
            // $salt = $geraSalt;
            // $token = hash('sha256', $token . $salt);

            $gravarToken = $this->conn->prepare($sql);
            $gravarToken->bindValue(':t', $token);
            $gravarToken->bindValue(':i', $idUsuario);
            // $gravarToken->bindValue(':s', $salt);


            if ($gravarToken->execute()) {
                setcookie("token", $token, time() - (3 * 60 * 60) + 50000);
                return true;
            } else {
                throw new Exception("Token não gravou", 404);
            }
        }
    }
    // --------------------------------------------------------------------------------------------------------

    // CRUDs da tabela ATIVIDADE

    public function cadastraAtividade($desc, $escolha)
    {
        $sql = "INSERT INTO 
    atividade (descricao,tipo)  
    VALUES (:descricao, :tipo)";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue(":descricao", $desc, PDO::PARAM_STR);
        $stmt->bindValue(":tipo", $escolha, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return true;
        } else {
            return false;
        }
    }

    public function updateAtividadeCadastrada($nome, $id): bool
    {
        $id = intval($id);

        $sql = "UPDATE atividade SET descricao = :d WHERE id = :id";

        $preparaUpdate = $this->conn->prepare($sql);
        $preparaUpdate->bindValue(":d", $nome, PDO::PARAM_STR);
        $preparaUpdate->bindValue(":id", $id, PDO::PARAM_INT);

        if ($preparaUpdate->execute()) {
            return true;
        } else {
            return false;
        }
    }
    public function deletaAtividade($id): bool
    {
        $id = intval($id);

        $sql = "DELETE from atividade where id = :id";

        $preparaDelete = $this->conn->prepare($sql);
        $preparaDelete->bindValue(":id", $id, PDO::PARAM_INT);

        if ($preparaDelete->execute()) {
            return true;
        } else {
            return false;
        }
    }

    public function getAtividades()
    {
        $consultaAtividades = $this->conn->prepare("SELECT descricao, tipo,id from atividade");
        $consultaAtividades->execute();
        return $consultaAtividades->fetchAll(PDO::FETCH_ASSOC);
    }
    // --------------------------------------------------------------------------------------------------------

    // CRUDs da tabela tipo_atividade
    public function cadastraTipo($tipo)
    {
        $sql = "INSERT INTO 
    tipo_atividade (info)  
    VALUES (:tipo)";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue(":tipo", $tipo, PDO::PARAM_STR);

        $stmt->execute();

        return true;
    }
    public function updateTipoAtv($nome, $id): bool
    {
        $id = intval($id);

        $sql = "UPDATE tipo_atividade SET info = :i WHERE id = :id";

        $preparaUpdate = $this->conn->prepare($sql);
        $preparaUpdate->bindValue(":i", $nome, PDO::PARAM_STR);
        $preparaUpdate->bindValue(":id", $id, PDO::PARAM_INT);

        if ($preparaUpdate->execute()) {
            return true;
        } else {
            return false;
        }
    }
    public function deletaTipoAtv($id): bool
    {
        $id = intval($id);

        $sql = "DELETE from tipo_atividade where id = :id";

        $preparaDelete = $this->conn->prepare($sql);
        $preparaDelete->bindValue(":id", $id, PDO::PARAM_INT);

        try {
            if ($preparaDelete->execute()) {
                return true;
            } else {
                return false;
            }
        } catch (PDOException $e) {
            http_response_code(403);
            throw new Exception("Não é possível deletar tipos associados a atividades ativas na tabela acima.\n Verifique a tabela de atividades e ajuste ou exclua a atividade antes de excluir seu tipo.", 403);
        }
    }
    public function getTipos() //Lista tipos de atividade
    {
        $sql = "SELECT * FROM tipo_atividade"; //Preparando a consulta no banco
        $stmt = $this->conn->query($sql); //Método query
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // --------------------------------------------------------------------------------------------------------

    public function listaById($id)
    { //Passando o ID como parâmetro

        $sql = "SELECT *
        FROM users 
        WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(":id", $id, PDO::PARAM_INT);
        if ($stmt->execute()) {
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            return $data;
        } else {
            return false;
        }
    }
}
