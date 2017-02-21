<?php
namespace Splitice\Locking\Adapter;

interface ILockAdapter
{
	function get($key, $timeout = null, $ttl = null);
	function release($key);
	function ttl($key, $ttl);
}