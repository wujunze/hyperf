<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://hyperf.org
 * @document https://wiki.hyperf.org
 * @contact  group@hyperf.org
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

namespace HyperfTest\Event;

use Mockery;
use SplPriorityQueue;
use Hyperf\Config\Config;
use PHPUnit\Framework\TestCase;
use HyperfTest\Event\Event\Beta;
use Hyperf\Event\EventDispatcher;
use HyperfTest\Event\Event\Alpha;
use Hyperf\Event\ListenerProvider;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Hyperf\Event\ListenerProviderFactory;
use HyperfTest\Event\Listener\BetaListener;
use HyperfTest\Event\Listener\AlphaListener;
use Psr\EventDispatcher\ListenerProviderInterface;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Hyperf\Event\Annotation\Listener as ListenerAnnotation;

/**
 * @internal
 * @covers \Hyperf\Event\Annotation\Listener
 * @covers \Hyperf\Event\EventDispatcher
 * @covers \Hyperf\Event\ListenerProvider
 * @covers \Hyperf\Event\ListenerProviderFactory
 */
class ListenerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testInvokeListenerProvider()
    {
        $listenerProvider = new ListenerProvider();
        $this->assertInstanceOf(ListenerProviderInterface::class, $listenerProvider);
        $this->assertTrue(is_array($listenerProvider->listeners));
    }

    public function testInvokeListenerProviderWithListeners()
    {
        $listenerProvider = new ListenerProvider();
        $this->assertInstanceOf(ListenerProviderInterface::class, $listenerProvider);

        $listenerProvider->on(Alpha::class, [new AlphaListener(), 'process']);
        $listenerProvider->on(Beta::class, [new BetaListener(), 'process']);
        $this->assertTrue(is_array($listenerProvider->listeners));
        $this->assertSame(2, count($listenerProvider->listeners));
        $this->assertInstanceOf(SplPriorityQueue::class, $listenerProvider->getListenersForEvent(new Alpha()));
    }

    public function testListenerProcess()
    {
        $listenerProvider = new ListenerProvider();
        $listenerProvider->on(Alpha::class, [$listener = new AlphaListener(), 'process']);
        $this->assertSame(1, $listener->value);

        $dispatcher = new EventDispatcher($listenerProvider);
        $dispatcher->dispatch(new Alpha());
        $this->assertSame(2, $listener->value);
    }

    public function testListenerInvokeByFactory()
    {
        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('get')->once()->with(ConfigInterface::class)->andReturn(new Config([]));
        $container->shouldReceive('get')
            ->once()
            ->with(ListenerProviderInterface::class)
            ->andReturn((new ListenerProviderFactory())($container));
        $listenerProvider = $container->get(ListenerProviderInterface::class);
        $this->assertInstanceOf(ListenerProviderInterface::class, $listenerProvider);
    }

    public function testListnerInvokeByFactoryWithConfig()
    {
        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('get')->once()->with(ConfigInterface::class)->andReturn(new Config([
            'listeners' => [
                AlphaListener::class,
                BetaListener::class,
            ],
        ]));
        $container->shouldReceive('get')
            ->with(AlphaListener::class)
            ->andReturn($alphaListener = new AlphaListener());
        $container->shouldReceive('get')
            ->with(BetaListener::class)
            ->andReturn($betaListener = new BetaListener());
        $container->shouldReceive('get')
            ->once()
            ->with(ListenerProviderInterface::class)
            ->andReturn((new ListenerProviderFactory())($container));
        $listenerProvider = $container->get(ListenerProviderInterface::class);
        $this->assertInstanceOf(ListenerProviderInterface::class, $listenerProvider);
        $this->assertSame(2, count($listenerProvider->listeners));

        $dispatcher = new EventDispatcher($listenerProvider);
        $this->assertSame(1, $alphaListener->value);
        $dispatcher->dispatch(new Alpha());
        $this->assertSame(2, $alphaListener->value);
        $this->assertSame(1, $betaListener->value);
        $dispatcher->dispatch(new Beta());
        $this->assertSame(2, $betaListener->value);
    }

    public function testListnerInvokeByFactoryWithAnnotationConfig()
    {
        $listenerAnnotation = new ListenerAnnotation();
        $listenerAnnotation->collectClass(AlphaListener::class, ListenerAnnotation::class);
        $listenerAnnotation->collectClass(BetaListener::class, ListenerAnnotation::class);

        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('get')->once()->with(ConfigInterface::class)->andReturn(new Config([]));
        $container->shouldReceive('get')
            ->with(AlphaListener::class)
            ->andReturn($alphaListener = new AlphaListener());
        $container->shouldReceive('get')
            ->with(BetaListener::class)
            ->andReturn($betaListener = new BetaListener());
        $container->shouldReceive('get')
            ->once()
            ->with(ListenerProviderInterface::class)
            ->andReturn((new ListenerProviderFactory())($container));

        $listenerProvider = $container->get(ListenerProviderInterface::class);
        $this->assertInstanceOf(ListenerProviderInterface::class, $listenerProvider);
        $this->assertSame(2, count($listenerProvider->listeners));

        $dispatcher = new EventDispatcher($listenerProvider);
        $this->assertSame(1, $alphaListener->value);
        $dispatcher->dispatch(new Alpha());
        $this->assertSame(2, $alphaListener->value);
        $this->assertSame(1, $betaListener->value);
        $dispatcher->dispatch(new Beta());
        $this->assertSame(2, $betaListener->value);
    }

    public function testListenerAnnotationWithPriority()
    {
        // Default value.
        $listenerAnnotation = new ListenerAnnotation();
        $this->assertSame(1, $listenerAnnotation->priority);
        // With a integer.
        $listenerAnnotation = new ListenerAnnotation(2);
        $this->assertSame(1, $listenerAnnotation->priority);
        // Define the priority.
        $listenerAnnotation = new ListenerAnnotation([
            'priority' => 2,
        ]);
        $this->assertSame(2, $listenerAnnotation->priority);
        // String number
        $listenerAnnotation = new ListenerAnnotation([
            'priority' => '2',
        ]);
        $this->assertSame(2, $listenerAnnotation->priority);
        // Non-number
        $listenerAnnotation = new ListenerAnnotation([
            'priority' => 'string',
        ]);
        $this->assertSame(1, $listenerAnnotation->priority);
    }
}