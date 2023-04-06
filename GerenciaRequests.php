<?php

class GerenciaRequests
{
    public function __construct(private ProcessaRequest $request)
    {
    } //Realizando o construct da classe PROCESSA REQUEST para fazer as operações CRUD n
    public function verificaRequest($method, $id): void
    {

        if ($id === null) { //O ID é nulo? Então o usuário está tratando um GET ou POST na API. 

            if ($method == "GET") { //Se o   método de escolha foi o GET, a classe PROCESSA REQUEST instanciada no construtor vai ser responsável por fazer o SELECT no banco através da função listaDados()

                if (isset($_GET['ListarAtividades']) && $_GET['ListarAtividades'] === 'Atividades') {
                    if ($dados = $this->request->getAtividades()) {
                        echo json_encode([
                            'Atividades' => $dados,
                            'Mensagem' => "Atividades cadastradas: "
                        ]);
                    }
                }

                if (isset($_GET['type']) && $_GET['type'] === 'ListarDados') {
                    if ($dados = $this->request->getTipos()) {
                        echo json_encode([
                            'Data' => $dados,
                            'Mensagem' => "Listando os tipos",
                        ]);
                    }
                }

                if (isset($_GET['ListarUsuarios']) && $_GET['ListarUsuarios'] === 'ListaUsers') {

                    echo json_encode($this->request->listaDados());
                }
            } elseif ($method == "POST") { //Se o método escolhido foi POST, o usuário quer criar dados. Então, a função create é chamada na classe PROCESSA REQUEST

                $dados = file_get_contents("php://input"); //Armazenando os dados do INPUT pela função file_get_contents
                $dados = json_decode($dados, true); //Pegando o conteúdo JSON obtido com o file_get_contents e armazenando como array associativo na var $dados

                if (isset($dados['descricao']) && isset($dados['choice'])) {
                    try {
                        $desc = $dados['descricao'];
                        $escolha = $dados['choice'];
                        if (empty($desc) or empty($escolha)) {
                            http_response_code(404);
                            throw new Exception("Faltaram dados para o cadastro.", 404);
                        } else if ($this->request->cadastraAtividade($desc, $escolha)) { //Chama-se o método da API cadastra tipo passando o tipo como parâmetro
                            http_response_code(200);
                            echo json_encode([
                                'Mensagem' => "Atividade tipo " . $desc . " cadastrada",
                            ]);
                        }
                    } catch (Exception $e) {
                        echo json_encode([
                            "Mensagem" => $e->getMessage(),
                            "Status" => $e->getCode(),
                        ]);
                    }
                }
                if (isset($dados['tipoAtividade'])) { //Condição de cadastro de TIPO DE ATIVIDADE
                    $tipoAtiv = $dados['tipoAtividade'];
                    try {
                        if (empty($tipoAtiv)) {
                            http_response_code(404);
                            throw new Exception("Faltaram dados para o cadastro.", 404);
                        } else {
                            if ($this->request->cadastraTipo($tipoAtiv)) {
                                http_response_code(200);
                                echo json_encode([
                                    'Mensagem' => "Atividade tipo " . $tipoAtiv . " cadastrada",
                                ]);
                            }
                        }
                    } catch (Exception $e) {
                        echo json_encode([
                            "Mensagem" => $e->getMessage(),
                            "Status" => $e->getCode(),
                        ]);
                    }
                }

                if (isset($dados['nome']) && isset($dados['senha'])) { //Condição para criação de users 
                    try {
                        if (empty($dados['nome']) or empty($dados['senha']) or empty($dados['email'])) {
                            http_response_code(400);
                            throw new Exception("Faltaram dados para o cadastro. Verifique sua entrada", 400);
                        } else {
                            if ($resposta = $this->request->validaPassword($dados)) {
                                if (!empty($resposta = $this->request->createUsuario($dados))) {
                                    http_response_code(201);
                                    throw new Exception("Usuario " . $dados['nome'] . " cadastrado", 201);
                                }
                            } 
                
                        }
                    } catch (Exception $e) {
                        echo json_encode([
                            "Mensagem" => $e->getMessage(),
                            "Status" => $e->getCode()
                        ]);
                    }
                }
                if (isset($dados['user']) && isset($dados['pwd'])) { //Condição de autenticação de login

                    $usuario = $this->request->autenticarLogin($dados);

                    if (!empty($usuario)) {
                        echo json_encode([
                            'Mensagem' => "Usuario " . $usuario[0]['nome'] . " Autenticado",
                            'Token' => $usuario[0]['token'],
                            'Nome' => $usuario[0]['nome']
                        ]);
                    } else {
                        http_response_code(403);
                        throw new Exception("Usuário ou senha inválidos.", 403);
                    }
                }

                if (isset($dados['id']) && isset($dados['excluirAtv'])) { //Condição para deletar ATIVIDADE cadastrada.
                    if ($this->request->deletaAtividade($dados['id'])) {
                        echo json_encode([
                            'Mensagem' => "Atividade deletada com sucesso."
                        ]);
                    } else {
                        http_response_code(403);
                        throw new Exception("Ocorreu um erro ao deletar a atividade.", 403);
                    }
                }

                if (isset($dados['id']) && isset($dados['excluirTipoAtv'])) { //Condição para deletar TIPO de ATIVIDADE cadastrado.
                    try {
                        if ($this->request->deletaTipoAtv($dados['id'])) {
                            echo json_encode([
                                'Mensagem' => "Tipo de atividade deletado com sucesso."
                            ]);
                        } else {
                            http_response_code(403);
                            throw new Exception("Ocorreu um erro ao deletar a atividade.", 403);
                        }
                    } catch (Exception $e) {
                        echo json_encode([
                            "Mensagem" => $e->getMessage(),
                            "Status" => $e->getCode(),
                        ]);
                    }
                }

                if (isset($dados['id']) && isset($dados['t'])) {
                    $userId = $dados['id'];
                    $token = $dados['t'];
                    $resposta = $this->request->getUserIdBeforeDelete($userId, $token);
                   try{
                        if (!empty($resposta)){
                            http_response_code(401);
                            throw new Exception("Você não pode excluir um usuário que está logado.", 401);
                        }
                        else {
                            http_response_code(200);
                            throw new Exception("Passou na validação 'before delete'");
                        }
                   }catch (Exception $e) {
                    echo json_encode([
                        "Mensagem" => $e->getMessage(),
                        "Status" => $e->getCode()
                    ]);
                   }
                }
            } else if ($method == 'PATCH') { //Método para fazer queries de UPDATE no banco.

                $dados = json_decode(file_get_contents("php://input"), true);

                if (isset($dados['novoNomeTipoAtv'])) { //Condição para fazer update nos TIPOS DE ATIVIDADE
                    try {
                        if (!empty($dados['novoNomeTipoAtv'])) {

                            $nomeAtividade = $dados['novoNomeTipoAtv'];
                            $id = $dados['id'];

                            if ($this->request->updateTipoAtv($nomeAtividade, $id)) {
                                http_response_code(200);
                                echo json_encode([
                                    'Mensagem' => "Tipo de atividade " . $dados['novoNomeTipoAtv'] . " atualizada com sucesso."
                                ]);
                            } else {
                                http_response_code(400);
                                throw new Exception("Você não preencheu dados obrigatórios.", 400);
                            }
                        } else {
                            http_response_code(400);
                            throw new Exception("Você não preencheu dados obrigatórios.", 400);
                        }
                    } catch (Exception $e) {
                        echo json_encode([
                            "Mensagem" => $e->getMessage(),
                            "Erro" => $e->getCode()
                        ]);
                    }
                } else if (isset($dados['novoNomeAtividade'])) { //Condição de UPDATE nas ATIVIDADES cadastradas
                    try {
                        if (!empty($dados['novoNomeAtividade'])) {
                            $nomeAtividade = $dados['novoNomeAtividade'];
                            $id = $dados['id'];

                            if ($this->request->updateAtividadeCadastrada($nomeAtividade, $id)) {
                                http_response_code(200);
                                echo json_encode([
                                    'Mensagem' => "Atividade " . $dados['novoNomeAtividade'] . " atualizada com sucesso."
                                ]);
                            } else {
                                http_response_code(400);
                                throw new Exception("Você não preencheu dados obrigatórios.", 400);
                            }
                        } else {
                            http_response_code(400);
                            throw new Exception("Você não preencheu dados obrigatórios.", 400);
                        }
                    } catch (Exception $e) {
                        echo json_encode([
                            "Mensagem" => $e->getMessage(),
                            "Status" => $e->getCode(),
                        ]);
                    }
                } else {
                    try {
                        if (empty($dados['nome']) or empty($dados['email'])) { //Condição de update nos USUÁRIOS cadastrados.
                            http_response_code(400);
                            throw new Exception("Você não preencheu dados obrigatórios.", 400);
                        } else {
                            $dados = $this->request->update($dados);
                            if (!empty($dados)) {
                                http_response_code(200);
                                echo json_encode([ //Exibe os dados o ID do usuário atualizado.
                                    'Status' => '200',
                                    'Request' => $method,
                                    'Mensagem' => "Usuário " . $dados['id'] . " atualizado.",
                                ]);
                            } else {
                                http_response_code(404);
                                throw new Exception("Falha ao atualizar usuário", 404);
                            }
                        }
                    } catch (Exception $e) {
                        echo json_encode([
                            "Mensagem" => $e->getMessage(),
                            "Status" => $e->getCode()
                        ]);
                    }
                }
            }
        } else {
            switch ($method) {
                case "DELETE":
                    try {
                        if ($this->request->delete($id)) {
                            http_response_code(200);
                            echo json_encode([
                                "Mensagem" => "Usuário " . $id . " deletado com sucesso." //Exibe ID do usuário deletado.
                            ]);
                        } else {
                            http_response_code(404);
                            throw new Exception("Erro ao deletar usuário.", 404);
                        }
                    } catch (Exception $e) {
                        echo json_encode([
                            "Mensagem" => $e->getMessage(),
                            "Status" => $e->getCode(),
                        ]);
                    }
                    break;
            }
        }
    }
}
