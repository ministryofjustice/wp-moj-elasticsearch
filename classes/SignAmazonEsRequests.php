<?php

namespace MOJElasticSearch;

use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;
use GuzzleHttp\Psr7\Request;

class SignAmazonEsRequests
{
    use Debug;

    public function __construct()
    {
        $this->actions();
    }

    public function actions()
    {
        add_filter('http_request_args', [$this, 'signAwsRequest'], 10, 2);
    }

    /**
     * Intercepts all requests to AWS ES and signs the URL for access
     * @param array $args
     * @param string $url
     * @return array
     */
    public function signAwsRequest(array $args, string $url) : array
    {
        $ori_url = parse_url($url, PHP_URL_HOST);
        $ep_host = parse_url(get_option('ep_host', false), PHP_URL_HOST);

        if ($ep_host !== $ori_url) {
            return $args;
        }

        // sign the request using the AWS SDK and return the $args array
        $request = new Request($args['method'], $url, $args['headers'], $args['body']);
        $signer = new SignatureV4('es', 'eu-west-1'); // region specific

        if (env('AWS_ACCESS_KEY_ID')) {
            $signed_request = $signer->signRequest(
                $request,
                new Credentials(env('AWS_ACCESS_KEY_ID'), env('AWS_SECRET_ACCESS_KEY'))
            );
            $args['headers']['Authorization'] = $signed_request->getHeader('Authorization')[0];
            $args['headers']['X-Amz-Date'] = $signed_request->getHeader('X-Amz-Date')[0];
        }

        return $args;
    }
}
