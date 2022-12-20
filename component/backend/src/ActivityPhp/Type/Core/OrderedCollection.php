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

namespace ActivityPhp\Type\Core;

/**
 * \ActivityPhp\Type\Core\OrderedCollection is an implementation of one
 * of the Activity Streams Core Types.
 *
 * A subtype of Collection in which members of the logical collection
 * are assumed to always be strictly ordered.
 *
 * @see https://www.w3.org/TR/activitystreams-core/#collections
 */
class OrderedCollection extends Collection
{
    /**
     * @var string
     */
    protected $type = 'OrderedCollection';
}
