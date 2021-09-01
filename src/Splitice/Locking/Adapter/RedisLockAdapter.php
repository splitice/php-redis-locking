<?php
namespace Splitice\Locking\Adapter;

use Splitice\Locking\Lock;
use Splitice\ResourceFactory;

class RedisLockAdapter implements ILockAdapter
{
	const USLEEP = 250000;

	/**
	 * @var \Predis\Client
	 */
	private $client;

	/**
	 * @var string
	 */
	private $key_prefix;
	/**
	 * @var int
	 */
	private $default_timeout;

	/**
	 * RedisLockAdapter constructor.
	 * @param string $key_prefix
	 * @param \Predis\Client|null $client
	 * @param int $default_timeout
	 */
	function __construct($key_prefix = 'Lock:', \Predis\Client $client = null, $default_timeout = 60)
	{
		if($client == null) {
			$client = ResourceFactory::getInstance()->get('redis');
		}

		$this->client = $client;
		$this->key_prefix = $key_prefix;
		$this->default_timeout = $default_timeout;
	}

	function get($key, $timeout = null, $ttl = null)
	{
		$end_time = time() + $timeout;
		$key = $this->key($key);

		do {
			$expire = $this->expire_time($ttl);
			if ($acquired = ($this->client->setnx($key, $expire))) {
				$acquired = new Lock($key, $expire, $this);
				break;
			}
			if ($acquired = ($this->recover($key))) {
				break;
			}
			if ($timeout === 0) break;

			usleep(self::USLEEP);
		} while (!is_numeric($timeout) || time() < $end_time);

		return $acquired;
	}

	function release($key)
	{
		$this->client->del($key);
	}

	/**
	 * Recover an abandoned lock
	 * @param  mixed $key Item to lock
	 * @param null $ttl
	 * @return Lock
	 */
	protected function recover($key, $ttl = null)
	{
		if (($lockTimeout = $this->client->get($key)) > time()) return null;

		$expire = $this->expire_time($ttl);
		$currentTimeout = $this->client->getset($key, $expire);

		if ($currentTimeout != $lockTimeout) return null;

		return new Lock($key, $expire, $this);
	}

	protected function key($key)
	{
		return $this->key_prefix . $key;
	}

	/**
	 * Generates an expire time based on the current time
	 * @param $ttl
	 * @return int    timeout
	 */
	protected function expire_time($ttl)
	{
		if ($ttl === null) {
			$ttl = $this->default_timeout;
		}
		return (int)(time() + $ttl + 1);
	}

	function ttl($key, $ttl)
	{
		$this->client->expire($key, $ttl);
		return $ttl;
	}
}