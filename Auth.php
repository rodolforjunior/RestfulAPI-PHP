<?php
class Auth
{
    public function __construct()
    {
    }

    public function criarToken(string $email, string $senha)
    {

        $header = [
             //Array definindo o cabeçalho -> ALG especifíca o tipo de algoritmo HMAC-SHA256 como o responsável para assinatura do token
             //a key 'typ' define que será um JWT 
            'alg' => 'HS256',
            'typ' => 'JWT'
        ];

        $key =  "9LL3KKFMKSM39!!??043949"; //A var $key é a chave para descriptografar o algoritmo.
        $header = json_encode($header); //Fazendo encoding para o formato json
        $header = base64_encode($header); 
        //Base64_encoding transforma a string JSON, convertendo em uma string de caracteres ASCII, isso facilita o processo de transmissão de dados em diferentes redes e também previne que caracteres especiais como " / etc.. não sejam reconhecidos em certos contextos.
         
         $exp = time() - (3 * 60 * 60) + 50000; //Definindo tempo de expiração do token, recebendo o tempo atual no formato UNIX (UTC) e subtraindo 3hrs
        
        $payload = [
            'senha' => $senha,
            'email' => $email,
            'exp' => $exp
        ];
        //Definindo o payload do token, atribuindo ao array as variáveis senha, nome e tempo. Após, é parseado em formato JSON e depois base64 assim como foi feito com o header.

        $payload = json_encode($payload);

        $payload = base64_encode($payload);

        $signature = hash_hmac('sha256', "$header.$payload", $key, true); //Utilizando a função hash_hmac para gerar um "hash" dos dados passados. O primeiro parametro é o tipo de alg, o segundo uma strin de dados, a key definida e true o tipo de retorno ser o valor "raw" 

        $signature  = base64_encode($signature); //A $signature é uma strin de bytes que não é fácilmente armazenada ou decodificada, por isso se realiza encoding no formato base64

        $token =  "$header.$payload.$signature"; //Finalmente definindo e retornando $token
    
        return $token;
    }


  
}

