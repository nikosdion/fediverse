<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2024 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\DataShape;

final class Request
{
	private string $url;

	private array|string|null $postData = null;

	private $callback = null;

	private $userData = null;

	private array $options = [];

	private array $headers = [];

	public ?array $optionsSet = null;

	private ?float $startTime = null;

	private ?float $endTime = null;

	public function __construct(
		string            $url,
		array|string|null $postData = null,
		callable|null     $callback = null,
		mixed             $userData = null,
		array             $options = [],
		array             $headers = [],
	)
	{
		$this->url        = $url;
		$this->postData   = $postData;
		$this->callback   = $callback;
		$this->userData   = $userData;
		$this->options    = $options;
		$this->headers    = $headers;
		$this->optionsSet = null;
		$this->startTime  = null;
		$this->endTime    = null;
	}

	public function startTimer(): void
	{
		$this->endTime   = null;
		$this->startTime = microtime(true);
	}

	public function stopTimer(): void
	{
		$this->endTime = microtime(true);
	}

	public function getElapsed(): float
	{
		if ($this->startTime === null || $this->endTime === null)
		{
			return -1;
		}

		return $this->endTime - $this->startTime;
	}

	public function __get(string $name)
	{
		if (property_exists($this, $name))
		{
			return $this->{$name};
		}

		throw new \InvalidArgumentException(
			sprintf(
				'Unknown property ‘%s’ on class %s',
				$name,
				__CLASS__
			)
		);
	}


}