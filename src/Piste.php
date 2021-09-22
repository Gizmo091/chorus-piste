<?php

namespace PhpChorusPiste;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PhpChorusPiste\Parameter\LignePosteSoumettreInput;
use PhpChorusPiste\Parameter\ParametresRechercherServicesStructure;
use PhpChorusPiste\Parameter\SoumettreFactureCadreDeFacturation;
use PhpChorusPiste\Parameter\SoumettreFactureDestinataire;
use PhpChorusPiste\Parameter\SoumettreFactureFournisseur;
use PhpChorusPiste\Parameter\SoumettreFactureMontantTotal;
use PhpChorusPiste\Parameter\SoumettreFactureReferences;
use PhpChorusPiste\ParameterCollection\LignePosteSoumettreInputCollection;
use PhpChorusPiste\ParameterCollection\LigneTvaSoumettreInputCollection;
use PhpChorusPiste\ParameterCollection\PieceJointeComplentaireSoumettreInputCollection;
use PhpChorusPiste\ParameterCollection\SoumettreFacturePieceJointePrincipaleCollection;
use PhpChorusPiste\Retour\WsRetourRecupererCoordonneesBancairesValides;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Class d'execution des appels des api de piste depuis le reseau internet
 */
class Piste {

    // Url depuis internet
    const AUTH_SANDBOX_URL    = 'https://sandbox-oauth.piste.gouv.fr/api/oauth/token';
    const AUTH_PRODUCTION_URL = 'https://oauth.piste.gouv.fr/api/oauth/token';
    const API_SANDBOX_URL     = 'https://sandbox-api.piste.gouv.fr';
    const API_PRODUCTION_URL  = 'https://api.piste.gouv.fr';

    /** @var Client $client */
    private $client;

    /**
     * @return \GuzzleHttp\Client
     */
    public function Client(): Client {
        return $this->client;
    }


    /**
     * ChorusPiste constructor.
     *
     * @param string $client_id
     * @param string $client_secret
     * @param string $tech_username
     * @param string $tech_password
     * @param bool   $sandbox
     *
     * @throws \Exception
     */
    public function __construct(
        string $client_id,
        string $client_secret,
        string $tech_username,
        string $tech_password,
        bool $sandbox = true
    ) {
        $authClient = new Client();
        $auth       = $authClient->post(
            $sandbox ? static::AUTH_SANDBOX_URL : static::AUTH_PRODUCTION_URL,
            [
                'form_params' => [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret,
                    'scope'         => 'openid',
                ],
            ]
        );
        $response   = json_decode($auth->getBody()
                                      ->getContents(),
                                  false,
                                  512);
        if (null === $response) {
            throw new \Exception('json_decode exception');
        }

        $token = $response->access_token;
        //        var_dump($token);
        $this->client = new Client(
            [
                'base_uri'        => $sandbox ? static::API_SANDBOX_URL : static::API_PRODUCTION_URL,
                'allow_redirects' => true,
                'headers'         => [
                    'Authorization' => 'Bearer '.$token,
                    'cpro-account'  => base64_encode($tech_username.':'.$tech_password),
                    'Content-Type'  => 'application/json;charset=utf-8',
                    'Accept'        => 'application/json;charset=utf-8',
                ],
            ]
        );
    }

    /**
     * @param       $uri
     * @param array $options
     *
     * @return \PhpChorusPiste\Retour\WsRetour|array
     */
    public function post($uri, array $options = [], string $classe_objet_en_retour = null) {
        if (null !== $classe_objet_en_retour && !class_exists($classe_objet_en_retour)) {
            throw new PisteException('La classe fourni en parametre de la methode '.__FUNCTION__.' de la classe '.__CLASS__.' n\'existe pas !');
        }

        $request  = $this->Client()
            ->post($uri, $options);
        $response = $request->getBody()
            ->getContents();
        $data     = json_decode($response, true);
        if (null === $data) {
            throw new \Exception('json_decode exception');
        }
        if ($data['codeRetour'] !== 0) {
            throw new PisteException($data['libelle']);
        }

        return (null !== $classe_objet_en_retour) ? new $classe_objet_en_retour($data) : $data;
    }
}