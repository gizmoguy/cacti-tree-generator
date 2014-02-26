<?php

class OrderKey
{
	// Probably best to leave these at the defaults, these need to match cacti
	const CHARS_PER_TIER = 3;
	const MAX_TREE_DEPTH = 30;
	
	private $current_key;

	public function __construct($base_key)
	{
		$this->current_key = $base_key;
	}

	public function getChild()
	{
		$parent_root = '';
		$tier        = $this->tree_tier($this->current_key);
		
		if($tier > 0)
		{
			$parent_root = substr($this->current_key, 0, ($tier * self::CHARS_PER_TIER));
		}

		$complete_root     = substr($this->current_key, 0, ($tier * self::CHARS_PER_TIER) + self::CHARS_PER_TIER);
		$order_key_suffix  = (substr($complete_root, - self::CHARS_PER_TIER) + 1);
		$order_key_suffix  = str_pad($order_key_suffix, self::CHARS_PER_TIER, '0', STR_PAD_LEFT);
		$order_key_suffix  = str_pad($parent_root . $order_key_suffix, (self::MAX_TREE_DEPTH * self::CHARS_PER_TIER), '0', STR_PAD_RIGHT);
		$this->current_key = $order_key_suffix;

		return $this->current_key;
	}

	public function getSibling()
	{
		$parent_root = '';
		$tier        = $this->tree_tier($this->current_key);
		
		if($tier > 0)
		{
			$parent_root = substr($this->current_key, 0, ($tier * self::CHARS_PER_TIER) - self::CHARS_PER_TIER);
		}

		$complete_root     = substr($this->current_key, 0, ($tier * self::CHARS_PER_TIER));
		$order_key_suffix  = (substr($complete_root, - self::CHARS_PER_TIER) + 1);
		$order_key_suffix  = str_pad($order_key_suffix, self::CHARS_PER_TIER, '0', STR_PAD_LEFT);
		$order_key_suffix  = str_pad($parent_root . $order_key_suffix, (self::MAX_TREE_DEPTH * self::CHARS_PER_TIER), '0', STR_PAD_RIGHT);
		$this->current_key = $order_key_suffix;

		return $this->current_key;
	}

	public function tree_tier($order_key, $chars_per_tier = self::CHARS_PER_TIER) {
		$root_test = str_repeat('0', $chars_per_tier);

		$tier = 0;

		if (!preg_match("/^$root_test/", $order_key))
		{
			$tier = ceil(strlen(preg_replace("/0+$/",'',$order_key)) / $chars_per_tier);
		}

		return $tier;
	}
}

?>
