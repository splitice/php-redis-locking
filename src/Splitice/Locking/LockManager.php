<?php
namespace Splitice\Locking;

use Predis\Client as RedisClient;
use Splitice\Locking\Adapter\ILockAdapter;
use Splitice\ResourceFactory;

class LockManager
{
	/**
	 * @var ILockAdapter
	 */
	private $adapter;

	function __construct(ILockAdapter $adapter)
	{
		$this->adapter = $adapter;
	}

	/**
	 * Gets a lock or waits for it to become available
	 * @param  mixed $key Item to lock
	 * @param  int $timeout Time to wait for the key (seconds)
	 * @param  int $ttl TTL (seconds)
	 * @param bool $ex
	 * @return Lock The key
	 */
	public function get($key, $timeout = null, $ttl = null, $ex = false)
	{
		if (!$key) throw new LockException("Invalid Key");

		$acquired = $this->adapter->get($key, $timeout, $ttl);

		if (!$acquired && $ex) throw new LockTimeoutException("Timeout exceeded");
		return $acquired;
	}
}