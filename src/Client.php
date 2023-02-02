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
     * @var Credential
     */
    private $credential;

    public function __construct(Credential $credential, HttpClient $httpClient = null)
    {
        /** @psalm-suppress PropertyTypeCoercion */
        $this->httpClient = $httpClient ?? new HttpMethodsClient(
            HttpClientDiscovery::find(),
            Psr17FactoryDiscovery::findRequestFactory(),
            Psr17FactoryDiscovery::findStreamFactory()
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
