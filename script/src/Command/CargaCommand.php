<?php

namespace Gg\KeycloakCarga\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use GuzzleHttp\Client;

/**
 * Class FolhaPagamentoComentaristaCommand
 * @package App\Command
 */
class CargaCommand extends Command
{
    /** @var string */
    protected static $defaultName = "app:carga";

    /** @var string  */
    protected $strKeycloakBaseUrl = "http://localhost:8080/";

    /** @var object */
    protected $objToken;

    /** @var Client */
    protected $objClient;

    /**
     * CargaCommand constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->objClient = new Client([
            // Base URI is used with relative requests
            'base_uri' => 'http://httpbin.org',
            // You can set any number of default request options.
            'timeout'  => 2.0,
        ]);
    }

    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setDescription('Carga de usuários via CSV.')
            ->setHelp('Este comando realiza a carga de usuário através de um CSV.')
            ->addArgument('arquivo', InputArgument::REQUIRED, 'Arquivo CSV');
    }

    /**
     * @see https://www.keycloak.org/docs-api/5.0/rest-api/index.html#_userrepresentation
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Iniciando processo de carga...');

        $arquivo = $input->getArgument('arquivo');

        $fp         = file($arquivo, FILE_SKIP_EMPTY_LINES);
        unset($fp);
        $output->writeln("Total de registros: {$total}.");
        // autentica client
        $objResponse = $this->authenticate();
        $objResponseJSON = json_decode($objResponse->getBody()->getContents());

        if(is_object($objResponseJSON) && property_exists($objResponseJSON, 'access_token')) {
            $this->objToken = $objResponseJSON;
        }

        // Inicializa a barra de progresso
        $progressBar  = new ProgressBar($output, $total);
        $progressBar->start();
        $row = 0;

        // Interando CSV
        if (($handle = fopen($arquivo, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if ($data && $row > 0) {
                    // Chamada API
                    $usuario = [
                        "firstName"  => $data[0],
                        "email"      => $data[1],
                        "username"   => $data[1],
                        "enabled"    => "true",
                        "attributes" => [
                            "legado" => "{$data[2]}"
                        ],
                        "credentials" => [
                            [
                                "type"      => "password",
                                "temporary" => true,
                                "value"     => $this->gerar_senha(8)
                            ]
                        ]
                    ];
                    try {
                        $objResponse = $this->createUserPost($usuario);
                        if ($objResponse->getStatusCode() == 201) {

                        }
                    } catch(\Exception $e) {

                    }
                    $progressBar->advance(1);
                }
                $row++;
            }
            fclose($handle);
        }

        $output->writeln(PHP_EOL);
        $output->writeln('Finalizado!.');

        return 1;
    }

    /**
     * @return void
     */
    protected function authenticate()
    {
        return $this->objClient->post($this->strKeycloakBaseUrl . "auth/realms/master/protocol/openid-connect/token", [
            'form_params' => [
                "client_id"         => "admin-client",
                "client_secret"     => "a9275ff7-ab9a-4ffa-ab9f-a18ec4bfab86",
                "grant_type"        => "client_credentials"
            ]
        ]);
    }

    /**
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function createUserPost($usuario)
    {
        return $this->objClient->post($this->strKeycloakBaseUrl . "auth/admin/realms/master/users", [
                'json' => $usuario,
                'headers' => [
                    'content-type'  => 'application/json',
                    'Authorization' => "Bearer {$this->objToken->access_token}"
                ]
            ]);
    }

    /**
     * Gerador de senha
     *
     * @param $tamanho
     * @return string
     */
    protected function gerar_senha($tamanho){
        $chars      = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890!@#$%*(){}?";
        $var_size   = strlen($chars);
        $random_str = "";
        for( $x = 0; $x < $tamanho; $x++ ) {
            $random_str .= $chars[ rand( 0, $var_size - 1 ) ];
        }
        return $random_str;
    }
}