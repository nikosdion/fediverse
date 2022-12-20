<?php

/*
 * This file is part of the ActivityPhp package.
 *
 * Copyright (c) landrok at github.com/landrok
 *
 * For the full copyright and license information, please see
 * <https://github.com/landrok/activitypub/blob/master/LICENSE>.
 */

namespace ActivityPhp\Server\Http;

use ActivityPhp\Server;
use ActivityPhp\Type\Util;
use Joomla\CMS\Uri\Uri;
use Psr\Http\Message\RequestInterface;
use phpseclib3\Crypt\RSA;

/**
 * HTTP signatures tool
 */ 
class HttpSignature
{
    public const SIGNATURE_PATTERN = '/^
        keyId="(?P<keyId>
            (https?:\/\/[\w\-\.]+[\w]+)
            (:[\d]+)?
            ([\w\-\.#\/@]+)
        )",
        (algorithm="(?P<algorithm>[\w\s-]+)",)?
        (headers="\(request-target\) (?P<headers>[\w\s-]+)",)?
        signature="(?P<signature>[\w+\/]+={0,2})"
    /x';       

    /**
     * Allowed keys when splitting signature
     *
     * @var array
     */
    private $allowedKeys = [
        'keyId',
        'algorithm', // optional
        'headers',   // optional
        'signature',
    ];

    /**
     * @var \ActivityPhp\Server
     */
    protected $server;

    /**
     * Inject a server instance
     */
    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    /**
     * Verify an incoming message based upon its HTTP signature
     *
     * @param  RequestInterface $request
     * @return bool True if signature has been verified. Otherwise false 
     */
    public function verify(RequestInterface $request): bool
    {
        // Read the Signature header,
        $signature = $request->headers->get('signature');

        if (!$signature) {
            $this->server->logger()->info(
                'Signature header not found',
                [$request->headers->all()]
            );
            return false;
        }

        // Split it into its parts (keyId, headers and signature)
        $parts = $this->splitSignature($signature);
        if (!count($parts)) {
            return false;
        }

        extract($parts);

        $this->server->logger()->debug('Signature', [$signature]);

        // Build a server-oriented actor
        // Fetch the public key linked from keyId
        $actor = $this->server->actor($keyId);

        $publicKeyPem = $actor->getPublicKeyPem();

        $this->server->logger()->debug('publicKeyPem', [$publicKeyPem]);

        // Create a comparison string from the plaintext headers we got 
        // in the same order as was given in the signature header, 
        $data = $this->getPlainText(
            explode(' ', trim($headers)), 
            $request
        );

        // Verify that string using the public key and the original 
        // signature.
        $rsa = RSA::createKey()
                  ->loadPublicKey($publicKeyPem)
                  ->withHash('sha256'); 

        return $rsa->verify($data, base64_decode($signature, true)); 
    }

    /**
     * Split HTTP signature into its parts (keyId, headers and signature)
     */
    public function splitSignature(string $signature): array
    {        
        if (!preg_match(self::SIGNATURE_PATTERN, $signature, $matches)) {
            $this->server->logger()->info(
                'Signature pattern failed',
                [$signature]
            );

            return [];
        }

        // Headers are optional
        if (!isset($matches['headers']) || $matches['headers'] == '') {
            $matches['headers'] = 'date';
        }

        return array_filter($matches, function($key) {
                return !is_int($key) && in_array($key, $this->allowedKeys);
        },  ARRAY_FILTER_USE_KEY );        
    }

    /**
     * Get plain text that has been originally signed
     * 
     * @param  array $headers HTTP header keys
     * @param  RequestInterface $request
     */
    private function getPlainText(array $headers, RequestInterface $request): string
    {
		$uri = new Uri($request->getUri());
        $strings = [];
        $strings[] = sprintf(
            '(request-target) %s %s%s',
            strtolower($request->getMethod()),
	        $uri->getPath(),
	        $uri->getQuery()
                ? '?' . $uri->getQuery() : ''
        );

        foreach ($headers as $key) {
            if ($request->headers->has($key)) {
                $strings[] = "$key: " . $request->headers->get($key);
            }
        }

        return implode("\n", $strings);   
    }
}
