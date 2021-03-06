<?php
/*************************************************************************************
 *   MIT License                                                                     *
 *                                                                                   *
 *   Copyright (C) 2009-2017 by Nurlan Mukhanov <nurike@gmail.com>                   *
 *                                                                                   *
 *   Permission is hereby granted, free of charge, to any person obtaining a copy    *
 *   of this software and associated documentation files (the "Software"), to deal   *
 *   in the Software without restriction, including without limitation the rights    *
 *   to use, copy, modify, merge, publish, distribute, sublicense, and/or sell       *
 *   copies of the Software, and to permit persons to whom the Software is           *
 *   furnished to do so, subject to the following conditions:                        *
 *                                                                                   *
 *   The above copyright notice and this permission notice shall be included in all  *
 *   copies or substantial portions of the Software.                                 *
 *                                                                                   *
 *   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR      *
 *   IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,        *
 *   FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE     *
 *   AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER          *
 *   LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,   *
 *   OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE   *
 *   SOFTWARE.                                                                       *
 ************************************************************************************/

namespace DBD\Cache;

use DBD\Cache as Cache;
use DBD\Cache\CacheInterface as CacheInterface;

class MemCache extends Cache implements CacheInterface
{
    /** @var \Memcache $link */
    public $link = null;

    public function close() {
        return $this->link->close();
    }

    public function delete($key) {
        return $this->link->delete($key);
    }

    public function disconnect() {
        return $this->close();
    }

    public function exist($key) {
        return $this->link->get($key) === false ? false : true;
    }

    public function get($key) {
        return $this->link->get($key);
    }

    public function getStats() {
        return $this->link->getStats();
    }

    /**
     * @return $this
     */
    public function open() {

        $this->link = new \Memcache();

        foreach($this->SERVERS as $server) {
            $this->link->addServer($server['host'], $server['port']);
        }

        return $this;
    }

    public function replace($key, $var, $expire = null) {
        $expire = preg_replace_callback("/(\d+)\s*(.*)?/", function($matches) {
            return Cache::secCalc($matches);
        }, $expire);

        if(!$expire)
            $expire = $this->EXPIRE;

        // If we trying to replace non exist cache, just set it
        if(!$this->link->replace($key, $var, $this->COMPRESS, $expire))
            $this->set($key, $var, $expire);
    }

    /**
     * @param string $key
     * @param mixed  $variable
     * @param null   $expire
     *
     * @return mixed
     */
    public function set($key, $variable, $expire = null) {
        $expire = preg_replace_callback("/(\d+)\s*(.*)?/", function($matches) {
            return Cache::secCalc($matches);
        }, $expire);

        if(!$expire)
            $expire = $this->EXPIRE;

        return $this->link->set($key, $variable, $this->COMPRESS, $expire);
    }
}