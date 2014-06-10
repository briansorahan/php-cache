<?php

class Cache {
  // entry is too large
  public static $err_too_large = -10;
  // attempted to add a key that already exists
  public static $err_already_exists = -20;

  private static $max_key_size = 64;
  private static $max_value_size = 256;
  private static $max_entry_size;
  private static $max_mem;

  private $mem_used;
  private $entries;

  function __construct() {
    $mem_used = 0;
    $entries = array();
    self::$max_entry_size = self::$max_key_size + self::$max_value_size;
    self::$max_mem = 16 * 1024 * 1024;
  }

  public function set($key, $value, $expire) {
    $entry = new CacheEntry($value, $expire);

    if ($entry->size() > MAX_ENTRY_SIZE) {
      return self::$err_too_large;
    } else if ($mem_used + $entry->size() > $max_mem) {
      // try to free up memory for this entry
      $this->evict($entry->size());
    } else {
      $entries[$key] = $entry;
      $this->mem_used += $entry->size();
    }

    return $this;
  }

  public function add($key, $value, $expire) {
    $pre = $entries[$key];
    if ($pre == NULL) {
      return $this->set($key, $value, $expire);
    } else {
      return
    }
  }

  public function get($key) {
    return $entries[$key]->value();
  }

  // free up storage for an object
  // favors (from highest to lowest)
  // 1. Already expired
  // 2. Least Recently Used
  // 3. Soonest expiration
  private function evict($size) {
    // key to be removed
    $remove = NULL;
    // key of least recently used item
    $lru_key = NULL;
    $lru_time = 0;
    // key for entry with soonest expiration
    $soonest_key = NULL;
    $soonest_time = PHP_INT_MAX;
    $now = time();

    foreach($entries as $key => $en) {
      if ($now <= $en->expire()) {
        $remove = $key;
        break;
      }
      $last_used = $en->last_used();
      if ($last_used > $lru_time) {
        $lru_time = $last_used;
      }
      $expire = $en->expire();
      if ($expire
    }
  }

  private function add($entry) {
  }
}

class CacheEntry {
  private $m_value;
  private $m_size;
  private $m_expire;
  private $m_last_used;

  function __construct($value, $expire) {
    $now = time();
    $m_value = $value;
    $m_expire = $now + $expire;
    $m_size = strlen($m_value);
  }

  public function size() { return $m_size; }
  public function expire() { return $m_expire; }
  public function value() { return $m_value; }
  public function last_used() { return $m_last_used; }

  public function mark_used() {
    $m_last_used = time();
  }
}

$cache = new Cache();

echo "> ";

while ($c = fread(STDIN, 512)) {
  echo "> ";
}

?>
