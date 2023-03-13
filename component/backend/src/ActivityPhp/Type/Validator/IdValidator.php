<?php

declare(strict_types=1);

/*
 * This file is part of the ActivityPhp package.
 *
 * Copyright (c) landrok at github.com/landrok
 *
 * For the full copyright and license information, please see
 * <https://github.com/landrok/activitypub/blob/master/LICENSE>.
 */

namespace ActivityPhp\Type\Validator;

use ActivityPhp\Type\Util;
use ActivityPhp\Type\ValidatorInterface;

/**
 * \ActivityPhp\Type\Validator\IdValidator is a dedicated
 * validator for id attribute.
 */
class IdValidator implements ValidatorInterface
{
    /**
     * Validate an ID attribute value
     *
     * @param mixed  $value
     * @param mixed  $container An object
     */
    public function validate($value, $container): bool
    {
        return Util::validateUrl($value)
            || Util::validateOstatusTag($value);
    }
}
