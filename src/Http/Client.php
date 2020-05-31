<?php

namespace ErrorLogger\Http;

use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;

class Client
{
    /** @var ClientInterface|null */
    protected $client;

    /**
     * @var string
     */
    private $endpoint = 'http://localhost:8000/api/log';

    /**
     * @var string
     */
    private $api_key;

    /**
     * @param string $api_key
     * @param ClientInterface|null $client
     */
    public function __construct(string $api_key, ClientInterface $client = null)
    {
        $this->client = $client;
        $this->api_key = $api_key;
    }

    /**
     * @param array $exception
     *
     * @return PromiseInterface|ResponseInterface|null
     * @throws GuzzleException
     */
    public function report($exception)
    {
        try {
            return $this->client->request('POST', $this->endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key
                ],
                'form_params' => array_merge([], $exception)
            ]);
        } catch (RequestException $e) {
            return $e->getResponse();
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @return \GuzzleHttp\Client
     */
    public function getGuzzleHttpClient()
    {
        if (!isset($this->client)) {
            $this->client = new \GuzzleHttp\Client([
                'timeout' => 15
            ]);
        }

        return $this->client;
    }

    /**
     * @param ClientInterface $client
     *
     * @return $this
     */
    public function setGuzzleHttpClient(ClientInterface $client)
    {
        $this->client = $client;

        return $this;
    }
}
