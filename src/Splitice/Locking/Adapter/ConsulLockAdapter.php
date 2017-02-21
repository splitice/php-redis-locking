<?php
namespace Splitice\Locking\Adapter;

use SensioLabs\Consul\ServiceFactory;
use Splitice\Locking\Lock;
use Splitice\ResourceFactory;

class ConsulLockAdapter implements ILockAdapter
{
	const USLEEP = 250000;


	/**
	 * @var string
	 */
	private $key_prefix;
	/**
	 * @var int
	 */
	private $default_timeout;

	/**
	 * @var \SensioLabs\Consul\Services\KV
	 */
	private $kv;

	/**
	 * @var \SensioLabs\Consul\Services\Session
	 */
	private $session;

	/**
	 * RedisLockAdapter constructor.
	 * @param string $key_prefix
	 * @param ServiceFactory $client
	 * @param int $default_timeout
	 */
	function __construct($key_prefix = 'locks/', ServiceFactory $client = null, $default_timeout = 60)
	{
		if($client == null) {
			$client = ResourceFactory::getInstance()->get('consul');
		}

		$this->key_prefix = $key_prefix;
		$this->default_timeout = $default_timeout;

		$this->kv = $client->get('kv');
		$this->session = $client->get('session');
	}

	function get($key, $timeout = null, $ttl = null)
	{
		$key = $this->key($key);

		if ($ttl === null) {
			$ttl = $this->default_timeout;
		}
		if ($timeout === null) {
			$timeout = $ttl;
		}

		$session_id  = $sessionId = $this->session->create(array('TTL'=>$ttl.'s','LockDelay'=>$timeout.'s','Behavior'=>'delete'))->json()['ID'];
		if(!$session_id){
			throw new \Exception('Failed to get session ID');
		}
		$acquired = $this->kv->put($key, $session_id, ['acquire' => $session_id])->json();

		if($acquired){
			$expire = $this->expire_time($ttl);
			return new Lock($key, $expire, $this);
		}else{
			return null;
		}
	}

	function ttl($key, $ttl)
	{
		$session_id = $this->kv->get($key);
		if($session_id) {
			$response = $this->session->renew($session_id);
			return substr($response->json()[0]['TTL'],0,-1);
		}
		return 0;
	}

	function release($key)
	{
		$session_id = $this->kv->get($key);
		if($session_id){
			$this->kv->delete($key);
			$this->session->destroy($session_id);
		}
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
}