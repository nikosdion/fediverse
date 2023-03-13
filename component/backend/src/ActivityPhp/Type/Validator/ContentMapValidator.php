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

use ActivityPhp\Type\ValidatorTools;

/**
 * \ActivityPhp\Type\Validator\ContentMapValidator is a dedicated
 * validator for contentMap attribute.
 */
class ContentMapValidator extends ValidatorTools
{
    /**
     * Validate a contentMap value
     *
     * @param string  $value
     * @param mixed   $container
     */
    public function validate($value, $container): bool
    {
        return $this->validateMap('content', $value, $container);
    }
}
