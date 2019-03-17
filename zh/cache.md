# Cache

本模块是基于Aspect的缓存模块，与SimpleCacheInterface不太一样。
[https://github.com/hyperf-cloud/cache](https://github.com/hyperf-cloud/cache)

## 默认配置

~~~php
<?php

return [
    'default' => [
        'driver' => Hyperf\Cache\Driver\RedisDriver::class,
        'packer' => Hyperf\Cache\Packer\PhpSerializer::class,
    ],
];
~~~

## 使用

组件提供Cacheable注解，作用于类方法，可以配置对应的缓存前缀、失效时间、监听器和缓存组。
例如，UserService提供一个user方法，可以查询对应id的用户信息。当加上Cacheable注解后，会自动生成对应的Redis缓存，key值为`user:id`，超时时间为9000秒。首次查询时，会从DB中查，后面查询时，会从Cache中查。

~~~php
<?php

namespace App\Services;

use App\Models\User;
use Hyperf\Cache\Annotation\Cacheable;

class UserService
{
    /**
     * @Cacheable(key="user", ttl=9000, listener="user-update")
     */
    public function user($id)
    {
        $user =  User::query()->where('id',$id)->first();

        if($user){
            return $user->toArray();
        }

        return null;
    }
}
~~~

## 清理缓存

当然，如果我们DB中的数据改变了，如果删除缓存呢？这里就需要用到后面的监听器。下面新建一个Service提供一方法，来帮我们处理缓存。

~~~php
<?php

declare(strict_types=1);

namespace App\Service;

use Hyperf\Di\Annotation\Inject;
use Hyperf\Cache\Listener\DeleteListenerEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

class SystemService
{
    /**
     * @Inject
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    public function flushCache($userId)
    {
        $this->dispatcher->dispatch(new DeleteListenerEvent('user-update', [$userId]));

        return true;
    }
}
~~~