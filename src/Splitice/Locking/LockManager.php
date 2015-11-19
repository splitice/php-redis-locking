<?php
namespace Splitice\Locking;

class LockManager
{
	const USLEEP = 250000;

	private $redis_name;
	private $default_timeout;
	private $key_prefix;

	function __construct($default_timeout = 60, $redis_name = 'redis', $key_prefix = 'Lock:')
	{
		$this->default_timeout = $default_timeout;
		$this->redis_name = $redis_name;
		$this->key_prefix = $key_prefix;
	}

	/**
	 * Gets a lock or waits for it to become available
	 * @param  mixed $key Item to lock
	 * @param  int $timeout Time to wait for the key (seconds)
	 * @param  int $ttl TTL (seconds)
	 * @return Lock    The key
	 * @throws LockException If the key is invalid
	 * @throws LockTimeoutException If the lock is not acquired before the method times out
	 */
	public function get($key, $timeout = null, $ttl = null)
	{
		if (!$key) throw new LockException("Invalid Key");

		/** @var \Predis\Client $redis */
		$redis = \Splitice\ResourceFactory::getInstance()->get($this->redis_name);
		$end_time = time() + $timeout;
		$key = $this->key($key);

		do {
			$expire = $this->timeout($ttl);
			if ($acquired = ($redis->setnx($key, $expire))) {
				$acquired = new Lock($key, $expire, $redis);
				break;
			}
			if ($acquired = ($this->recover($key))) {
				break;
			}
			if ($timeout === 0) break;

			usleep(self::USLEEP);
		} while (!is_numeric($timeout) || time() < $end_time);

		if (!$acquired) throw new LockTimeoutException("Timeout exceeded");
		return $acquired;
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
	protected function timeout($ttl)
	{
		if ($ttl === null) {
			$ttl = $this->default_timeout;
		}
		return (int)(time() + $ttl + 1);
	}

	/**
	 * Recover an abandoned lock
	 * @param  mixed $key Item to lock
	 * @param null $ttl
	 * @return bool    Was the lock acquired?
	 */
	protected function recover($key, $ttl = null)
	{
		/** @var \Predis\Client $redis */
		$redis = \Splitice\ResourceFactory::getInstance()->get($this->redis_name);

		if (($lockTimeout = $redis->get($key)) > time()) return false;

		$expire = $this->timeout($ttl);
		$currentTimeout = $redis->getset($key, $expire);

		if ($currentTimeout != $lockTimeout) return false;

		return new Lock($key, $expire, $redis);
	}
}