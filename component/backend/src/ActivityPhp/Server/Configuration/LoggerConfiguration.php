<?php

/*
 * This file is part of the ActivityPhp package.
 *
 * Copyright (c) landrok at github.com/landrok
 *
 * For the full copyright and license information, please see
 * <https://github.com/landrok/activitypub/blob/master/LICENSE>.
 */

namespace ActivityPhp\Server\Configuration;

use Joomla\CMS\Log\Log;

/**
 * Logger configuration stack
 */ 
class LoggerConfiguration extends AbstractConfiguration
{
    /**
     * Create logger instance
     * 
     * @return \Psr\Log\LoggerInterface
     */
    public function createLogger()
    {
		return Log::createDelegatedLogger();
    }
}
