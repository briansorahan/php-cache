<?php

class Cache {
  private $key_size;
  private $value_size;
  private $max_mem;
  // capacity in number of nodes
  private $capacity;
  // head and tail of doubly-linked list used to
  // track least recently used values
  private $head;
  private $tail;
  // storage
  private $hash;

  // params
  // 1. memory limit in Mb
  // 2. key size in number of characters
  // 3. value size in number of characters
  function __construct($mm, $ks, $vs) {
    $this->key_size = $ks;
    $this->value_size = $vs;
    $this->max_mem = $mm * 1024 * 1024;
    $this->capacity = floor($this->max_mem / ($ks + $vs));
    
    $this->head = new CacheNode(NULL, NULL, NULL);
    $this->tail = new CacheNode(NULL, NULL, NULL);
    $this->hash = array();
  }

  // get the value at key
  // returns NULL if there is no value
  public function get($key) {
    if (empty($this->hash[$key])) {
      return NULL;
    } else {
      $node = $this->hash[$key];
      $expires = $node->expires();
      $now = time();

      if (is_integer($expires) && $expires <= $now) {
        // remove the expired value
        unset($this->hash[$key]);
        return NULL;
      } else {
        $this->setHead($node);
        return $this->hash[$key]->getValue();
      }
    }
  }

  // set key to value
  // expires in seconds from now
  public function set($key, $value, $expires) {
    if (count($this->hash) == $this->capacity) {
      // get rid of the tail node
      $this->removeNode($this->tail->getPrev());
    }

    $node = new CacheNode(NULL, NULL, $expires);

    $iv = intval($value);
    $fv = floatval($value);

    if ($iv == 0) {
      // handle intval's shortcomings
      if ($value == "0") {
        $value = 0;
      }
    } else {
      // don't convert to int if it's a float
      if ($fv == $iv) {
        $value = $iv;
      }
    }

    $node->setValue($value);
    $this->setHead($node);
    $this->hash[$key] = $node;
    return $this;
  }

  // add key, value pair if it doesn't already exist
  // if it does exist, return false
  // return true otherwise
  public function add($key, $value, $expires) {
    if (! empty($this->hash[$key])) {
      return false;
    }
    $this->set($key, $value, $expires);
    return true;
  }

  // return true/false if the value was removed/absent
  public function delete($key) {
    if (empty($this->hash[$key])) {
      return false;
    }
    // remove it from the lru list
    $this->removeNode($this->hash[$key]);
    // remove it from storage
    unset($this->hash[$key]);
    return true;
  }

  // attempt to increment an integer value
  // return true if the value at key is an integer
  // return false otherwise
  public function increment($key, $amount = 1) {
    if (empty($this->hash[$key])) {
      return false;
    }

    $node = $this->hash[$key];
    $val = $node->getValue();

    if (!is_integer($val)) {
      return false;
    }

    $node->setValue($val + $amount);
    $this->setHead($node);
    return true;
  }

  // attempt to decrement an integer value
  // return true if the value at key is an integer
  // return false otherwise
  public function decrement($key, $amount = 1) {
    if (empty($this->hash[$key])) {
      return false;
    }

    $node = $this->hash[$key];
    $val = $node->getValue();

    if (!is_integer($val)) {
      return false;
    }

    $node->setValue($val - $amount);
    $this->setHead($node);
    return true;
  }

  private function setHead($node) {
    // remove it
    $this->removeNode($node);
    // add it to the head
    $headNext = $this->head->getNext();
    $this->head = $node;
    $node->setNext($headNext);
    $node->setPrev(NULL);
  }

  // remove a node from the lru list
  private function removeNode($node) {
    $prev = $node->getPrev();
    $next = $node->getNext();
    if (is_object($prev)) {
      $prev->setNext($next);
    }
    if (is_object($next)) {
      $next->setPrev($prev);
    }
  }
}

// doubly linked-list node
class CacheNode {
  private $prev;
  private $next;
  private $expires;
  private $value;

  function __construct($p, $n, $exp) {
    $this->prev = $p;
    $this->next = $n;
    
    if (is_null($exp) || empty($exp)) {
      $this->expires = NULL;
    } else {
      $this->expires = time() + $exp;
    }
  }

  public function getPrev() { return $this->prev; }
  public function setPrev($p) { $this->prev = $p; }

  public function getNext() { return $this->next; }
  public function setNext($n) { $this->next = $n; }

  public function getValue() { return $this->value; }
  public function setValue($v) { $this->value = $v; }

  public function expires() { return $this->expires; }
}

$cache = new Cache(16, 64, 255);

echo "> ";

while ($c = fread(STDIN, 512)) {
  $stored = "STORED\n";
  $error = "ERROR\n";

  $pieces = preg_split("/\s+/", $c);
  $num_pieces = count($pieces);

  if ($num_pieces > 0) {
    $cmd = $pieces[0];
    $key = $pieces[1];

    if (empty($cmd)) {
      echo "> ";
      continue;
    }

    if (empty($key)) {
      echo "ERROR\n> ";
      continue;
    }

    switch(strtolower($cmd)) {
      case "get":
        $val = $cache->get($key);
        if (!is_null($val)) {
          echo $val . "\n";
        }
        break;
      case "set":
        if ($num_pieces < 3 || $pieces[2] == "") {
          // set requires key and value
          echo $error;
          break;
        }

        $expires = NULL;
        if ($num_pieces >= 4) {
          $expires = $pieces[3];
        }

        $cache->set($key, $pieces[2], $expires);
        echo $stored;
        break;
      case "add":
        if ($num_pieces < 3 || $pieces[2] == "") {
          // add requires key and value
          echo $error;
          break;
        }

        $expires = NULL;
        if ($num_pieces >= 4) {
          $expires = $pieces[3];
        }

        if ($cache->add($key, $pieces[2], $expires)) {
          echo $stored;
        } else {
          echo $error;
        }
        break;
      case "increment":
        $amount = 1;
        if ($num_pieces >= 3 && !empty($pieces[2])) {
          $amount = intval($pieces[2]);
        }

        if ($cache->increment($key, $amount)) {
          echo $cache->get($key) . "\n";
        } else {
          echo $error;
        }
        break;
      case "decrement":
        $amount = 1;
        if ($num_pieces >= 3 && !empty($pieces[2])) {
          $amount = intval($pieces[2]);
        }

        if ($cache->decrement($key, $amount)) {
          echo $cache->get($key) . "\n";
        } else {
          echo $error;
        }
        break;
      case "delete":
        if ($cache->delete($key)) {
          echo "DELETED\n";
        } else {
          echo $error;
        }
        break;
      default:
        echo "Unrecognized command " . $cmd . "\n";
    }
  }
  echo "> ";
}

?>
