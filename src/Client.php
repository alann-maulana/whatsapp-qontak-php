<?php

declare(strict_types=1);

namespace Inisiatif\WhatsappQontakPhp;

use Http\Client\HttpClient;
use Webmozart\Assert\Assert;
use Http\Discovery\HttpClientDiscovery;
use Http\Client\Common\HttpMethodsClient;
use Http\Discovery\Psr17FactoryDiscovery;
use Inisiatif\WhatsappQontakPhp\Message\Message;
use Http\Client\Common\HttpMethodsClientInterface;
use Http\Message\MultipartStream\MultipartStreamBuilder;
use Psr\Http\Message\RequestFactoryInterface;

final class Client implements ClientInterface
{
    /**
     * @var HttpMethodsClientInterface
     */
    private $httpClient;

    /**
     * @var string|null
     */
    private $accessToken = null;

    /**
     * @var HttpClient
     */
    private $client;

    /**
     * @var Credential
     */
    private $credential;

    /**
     * @var RequestFactoryInterface
     */
    private $requestFactory;

    /**
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    public function __construct(Credential $credential, HttpClient $httpClient = null)
    {
        $this->client = HttpClientDiscovery::find();
        $this->requestFactory = Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = Psr17FactoryDiscovery::findStreamFactory();

        /** @psalm-suppress PropertyTypeCoercion */
        $this->httpClient = $httpClient ?? new HttpMethodsClient(
            $this->client,
            $this->requestFactory,
            $this->streamFactory,
        );

        $this->credential = $credential;
    }

    public function send(string $templateId, string $channelId, Message $message, $bulk = false): Response
    {
        $this->getAccessToken();

        $response = $this->httpClient->post(
            "https://service-chat.qontak.com/api/open/v1/broadcasts/whatsapp" . ($bulk ? '' : '/direct'),
            [
                'content-type' => 'application/json',
                'Authorization' => \sprintf('Bearer %s', $this->accessToken ?? ''),
            ],
            \json_encode(
                [
                    'message_template_id' => $templateId,
                    'channel_integration_id' => $channelId,
                ] + $this->makeRequestBody($message)
            )
        );

        /** @var array $responseBody */
        $responseBody = \json_decode((string) $response->getBody(), true);

        $id = null;
        $name = null;

        if (isset($responseBody['data'])) {
            $responseData = $responseBody['data'];

            if (isset($responseData['id'])) {
                $id = $responseData['id'];
            }

            if (isset($responseData['name'])) {
                $name = $responseData['name'];
            }
        }

        return new Response($id, $name, $responseBody);
    }

    public function getBroadcastLog(string $id, $filters = NULL): array
    {
        $this->getAccessToken();

        $url = \sprintf(
            'https://service-chat.qontak.com/api/open/v1/broadcasts/%s/whatsapp/log%s',
            $id,
            isset($filters) ? \sprintf('?filters=%s', $filters) : ''
        );
        $header = ['Authorization' => \sprintf('Bearer %s', $this->accessToken ?? '')];
        $response = $this->httpClient->get($url, $header);

        /** @var array $responseBody */
        return \json_decode((string) $response->getBody(), true);
    }

    public function createContactList(string $name, $file, string $source_type = 'spreadsheet')
    {
        $this->getAccessToken();

        $builder = new MultipartStreamBuilder($this->streamFactory);
        $builder
            ->addResource('name', $name)
            ->addResource('source_type', $source_type)
            ->addResource('file', $file, ['filename' => 'contact-list.xls']);

        $multipartStream = $builder->build();
        $boundary = $builder->getBoundary();

        $request = $this->requestFactory
            ->createRequest(
                'POST',
                'https://service-chat.qontak.com/api/open/v1/contacts/contact_lists/async',
            )
            ->withBody($multipartStream)
            ->withHeader('Content-Type', \sprintf('multipart/form-data; boundary=%s', $boundary))
            ->withHeader('Authorization', \sprintf('Bearer %s', $this->accessToken ?? ''));

        $response = $this->client->sendRequest($request);

        return \json_decode((string) $response->getBody(), true);
    }

    public function uploadFile($file, $filename)
    {
        $this->getAccessToken();

        $builder = new MultipartStreamBuilder($this->streamFactory);
        $builder->addResource('file', $file, ['filename' => $filename]);

        $multipartStream = $builder->build();
        $boundary = $builder->getBoundary();

        $request = $this->requestFactory
            ->createRequest(
                'POST',
                'https://service-chat.qontak.com/api/open/v1/file_uploader',
            )
            ->withBody($multipartStream)
            ->withHeader('Content-Type', \sprintf('multipart/form-data; boundary=%s', $boundary))
            ->withHeader('Authorization', \sprintf('Bearer %s', $this->accessToken ?? ''));

        $response = $this->client->sendRequest($request);

        return \json_decode((string) $response->getBody(), true);
    }

    private function getAccessToken(): void
    {
        if ($this->accessToken === null) {
            $response = $this->httpClient->post(
                'https://service-chat.qontak.com/oauth/token',
                [
                    'content-type' => 'application/json',
                ],
                \json_encode($this->credential->getOAuthCredential())
            );

            /** @var array<array-key, string> $body */
            $body = \json_decode((string) $response->getBody(), true);

            Assert::keyExists($body, 'access_token');

            $this->accessToken = $body['access_token'];
        }
    }

    private function makeRequestBody(Message $message): array
    {
        return MessageUtil::makeRequestBody($message);
    }
}
