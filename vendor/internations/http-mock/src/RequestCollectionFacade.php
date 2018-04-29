<?php
namespace InterNations\Component\HttpMock;

use Countable;
use Guzzle\Http\ClientInterface;
use Guzzle\Http\Message\EntityEnclosingRequestInterface;
use Guzzle\Http\Message\RequestFactory;
use Guzzle\Http\Message\RequestInterface;
use Guzzle\Http\Message\Response;
use InterNations\Component\HttpMock\Request\UnifiedRequest;
use UnexpectedValueException;

class RequestCollectionFacade implements Countable
{
    private $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * @return UnifiedRequest
     */
    public function latest()
    {
        return $this->getRecordedRequest('/_request/last');
    }

    /**
     * @return UnifiedRequest
     */
    public function last()
    {
        return $this->getRecordedRequest('/_request/last');
    }

    /**
     * @return UnifiedRequest
     */
    public function first()
    {
        return $this->getRecordedRequest('/_request/first');
    }

    /**
     * @param integer $position
     * @return UnifiedRequest
     */
    public function at($position)
    {
       return $this->getRecordedRequest('/_request/' . $position);
    }

    /**
     * @return UnifiedRequest
     */
    public function pop()
    {
        return $this->deleteRecordedRequest('/_request/last');
    }

    /**
     * @return UnifiedRequest
     */
    public function shift()
    {
        return $this->deleteRecordedRequest('/_request/first');
    }

    public function count()
    {
        $response = $this->client
            ->get('/_request/count')
            ->send();

        return (int) $response->getBody(true);
    }

    /**
     * @param Response $response
     * @param string $path
     * @throws UnexpectedValueException
     * @return UnifiedRequest
     */
    private function parseRequestFromResponse(Response $response, $path)
    {
        try {
            $requestInfo = Util::deserialize($response->getBody());
        } catch (UnexpectedValueException $e) {
            throw new UnexpectedValueException(
                sprintf('Cannot deserialize response from "%s": "%s"', $path, $response->getBody()),
                null,
                $e
            );
        }

        $request = RequestFactory::getInstance()->fromMessage($requestInfo['request']);
        $params = $this->configureRequest(
            $request,
            $requestInfo['server'],
            isset($requestInfo['enclosure']) ? $requestInfo['enclosure'] : []
        );

        return new UnifiedRequest($request, $params);
    }

    private function configureRequest(RequestInterface $request, array $server, array $enclosure)
    {
        if (isset($server['HTTP_HOST'])) {
            $request->setHost($server['HTTP_HOST']);
        }

        if (isset($server['HTTP_PORT'])) {
            $request->setPort($server['HTTP_PORT']);
        }

        if (isset($server['PHP_AUTH_USER'])) {
            $request->setAuth($server['PHP_AUTH_USER'], isset($server['PHP_AUTH_PW']) ? $server['PHP_AUTH_PW'] : null);
        }

        $params = [];

        if (isset($server['HTTP_USER_AGENT'])) {
            $params['userAgent'] = $server['HTTP_USER_AGENT'];
        }

        if ($request instanceof EntityEnclosingRequestInterface) {
            $request->addPostFields($enclosure);
        }

        return $params;
    }

    private function getRecordedRequest($path)
    {
        $response = $this->client
            ->get($path)
            ->send();

        return $this->parseResponse($response, $path);
    }

    private function deleteRecordedRequest($path)
    {
        $response = $this->client
            ->delete($path)
            ->send();

        return $this->parseResponse($response, $path);
    }

    private function parseResponse(Response $response, $path)
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            throw new UnexpectedValueException(
                sprintf('Expected status code 200 from "%s", got %d', $path, $statusCode)
            );
        }

        $contentType = $response->hasHeader('content-type')
            ? $response->getContentType()
            : '';

        if (substr($contentType, 0, 10) !== 'text/plain') {
            throw new UnexpectedValueException(
                sprintf('Expected content type "text/plain" from "%s", got "%s"', $path, $contentType)
            );
        }

        return $this->parseRequestFromResponse($response, $path);
    }
}
