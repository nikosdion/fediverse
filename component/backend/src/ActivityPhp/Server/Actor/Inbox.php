<?php

/*
 * This file is part of the ActivityPhp package.
 *
 * Copyright (c) landrok at github.com/landrok
 *
 * For the full copyright and license information, please see
 * <https://github.com/landrok/activitypub/blob/master/LICENSE>.
 */

namespace ActivityPhp\Server\Actor;

use ActivityPhp\Server;
use ActivityPhp\Server\Actor;
use ActivityPhp\Server\Helper;
use ActivityPhp\Server\Http\HttpSignature;
use ActivityPhp\Type;
use ActivityPhp\Type\Util;
use Exception;
use Joomla\Http\Response;
use Psr\Http\Message\RequestInterface;

/**
 * A server-side inbox
 */ 
class Inbox extends AbstractBox
{
    /**
     * Inbox constructor
     * 
     * @param  \ActivityPhp\Server\Actor $actor An actor
     * @param  \ActivityPhp\Server $server
     */
    public function __construct(Actor $actor, Server $server)
    {
        $server->logger()->info(
            $actor->get('preferredUsername') . ':' . __METHOD__
        );
        parent::__construct($actor, $server);
    }

    /**
     * Post a message to current actor
     * 
     * @param  RequestInterface $request
     * @return Response
     */
    public function post(RequestInterface $request)
    {
        $this->server->logger()->info(
            $this->actor->get('preferredUsername') . ':' . __METHOD__
        );

        try {
            // Check accept header
            Helper::validateAcceptHeader(
                $request->headers->get('accept'),
                true
            );

            // Check current actor can post
            
            
            // Get content
            $payload = Util::decodeJson(
                (string)$request->getBody()
            );

            // Cast as an ActivityStreams type
            $activity = Type::create($payload);

        } catch (Exception $exception) {
            $this->getServer()->logger()->error(
                $this->actor->get()->preferredUsername. ':' . __METHOD__, [
                    $exception->getMessage()
                ]
            );

            return new Response('', 400);
        }

        $httpSignature = new HttpSignature($this->server);
        if ($httpSignature->verify($request)) {
            return new Response('', 201);
        }

        return new Response('', 403);
    }
}
