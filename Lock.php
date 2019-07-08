<?php
/**
 *
 * 实现分布式锁
 * (1) 进程Asetnx，值为超时的时间戳(t1)，如果返回true，获得锁。
 * (2) 进程B用get 命令获取t1，与当前时间戳比较，判断是否超时，没超时false，如果已超时执行步骤3
 * (3) 计算新的超时时间t2，使用getset命令返回t3(这个值可能其他线程已经修改过)，如果t1==t3,获得锁,如果t1!=t3说明锁被其他进程获取了
 * (4) 获取锁后，处理完业务逻辑，再去判断锁是否超时，如果没超时删除锁，如果已超时，不用处理（防止删除其他进程的锁）
 */


class Lock
{

    protected $objRedis = null;

    public function __construct() {
        $this->setRedis();
    }

    private function setRedis() {
        if (is_null($this->objRedis)) {
            $this->objRedis = new Redis();
            $this->objRedis->connect('127.0.0.1', 6688);
        }
    }


    /**
     * @param $lockname
     * @param int $expiretime
     * @return bool
     */
    function getLock($lockname, $expiretime = 5) {
        $expireTime = time() + $expiretime;
        $getLock    = $this->objRedis->setnx($lockname, $expireTime);

        if (true == $getLock) { //获取锁
            return true;
        }

        //获取锁失败
        //判断锁超时
        $t1 = $this->objRedis->get($lockname);
        if ($t1 > time()) {
            return false;
        }

        //锁已经超时
        $t2 = time() + $expiretime;
        $t3 = $this->objRedis->getset($lockname, $t2);
        if ($t1 == $t3) {
            return true;
        }

        //代表被别的进程获取到锁，需要把时间set回去
        $this->objRedis->set($lockname, $t3);
        return false;
    }

    /**
     * @param $lockname
     * @return bool
     */
    public function unLock($lockname) {
        $expireTime = $this->objRedis->get($lockname);
        if ($expireTime > time()) {
            $this->objRedis->del($lockname);
        }

        return true;
    }
}
