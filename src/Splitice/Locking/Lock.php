<?php
namespace Splitice\Locking;

class Lock
{
	/**
	 * @var string The key name of the lock
	 */
	public $key;

	/**
	 * Stores the expire time of the currently held lock
	 *
	 * @var int
	 */
	public $expire;

	/**
	 * If the lock is released or not
	 *
	 * @var bool
	 */
	public $released = false;

	/**
	 * @var \Predis\ClientInterface
	 */
	private $redis;

	/**
	 * Should the lock be persisted?
	 *
	 * @var bool
	 */
	private $persist;

	function __construct($key, $expire, \Predis\ClientInterface $redis)
	{
		if (!$key) throw new LockException("Invalid Key");
		$this->key = $key;
		$this->expire = $expire;
		$this->redis = $redis;
	}

	/**
	 * Should the lock persist the lifetime of the lock object?
	 *
	 * @param bool $value
	 */
	public function persist($value = true)
	{
		$this->persist = $value;
	}

	/**
	 * Releases the lock, if it has not been released already
	 */
	public function release()
	{
		if ($this->released) return;

		// Only release the lock if it hasn't expired
		if ($this->expire >= time()) {
			$this->redis->del($this->key);
		}
		$this->released = true;
	}

	function __destruct()
	{
		if (!$this->released) {
			$this->release();
		}
	}
}
