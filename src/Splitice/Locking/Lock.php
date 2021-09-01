<?php
namespace Splitice\Locking;

use Splitice\Locking\Adapter\ILockAdapter;

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
	 * Should the lock be persisted?
	 *
	 * @var bool
	 */
	private $persist;
	/**
	 * @var ILockAdapter
	 */
	private $adapter;

	/**
	 * Lock constructor.
	 * @param $key
	 * @param $expire
	 * @param ILockAdapter $adapter
	 */
	function __construct($key, $expire, ILockAdapter $adapter)
	{
		if (!$key) throw new LockException("Invalid Key");
		$this->key = $key;
		$this->expire = $expire;

		$this->adapter = $adapter;
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
	* Prevent the expiration of the lock for $ttl seconds
	* @param $ttl integer seconds to potentially increase lock by
	* @param null $only_if only if less than this remains
	*/
	function bump($ttl, $only_if = null){
		if ($this->released) return;

		// Check if expired
		$now = time();
		if($only_if !== null){
			if($this->expire - $now >= $only_if){
				return;
			}
		}
		$new_expire = $now + $ttl;
		if($new_expire > $this->expire){
			$this->adapter->ttl($this->key, $ttl);
			$this->expire = $new_expire;
		}
	}

	/**
	 * Releases the lock, if it has not been released already
	 */
	public function release()
	{
		if ($this->released) return;

		// Only release the lock if it hasn't expired
		if ($this->expire >= time()) {
			$this->adapter->release($this->key);
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
