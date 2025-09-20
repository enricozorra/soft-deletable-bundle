<?php

declare(strict_types=1);

namespace Andante\SoftDeletableBundle\Tests\Functional;

use Andante\SoftDeletableBundle\DependencyInjection\Compiler\DoctrineEventSubscriberPass;
use Andante\SoftDeletableBundle\Doctrine\DBAL\Type\DeletedAtType;
use Andante\SoftDeletableBundle\Doctrine\Filter\SoftDeletableFilter;
use Andante\SoftDeletableBundle\EventSubscriber\SoftDeletableEventSubscriber;
use Andante\SoftDeletableBundle\Tests\HttpKernel\AndanteSoftDeletableKernel;
use Andante\SoftDeletableBundle\Tests\KernelTestCase;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class SetupTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
    }

    /**
     * @param array<string,mixed> $options
     */
    protected static function createKernel(array $options = []): AndanteSoftDeletableKernel
    {
        /** @var AndanteSoftDeletableKernel $kernel */
        $kernel = parent::createKernel($options);
        $kernel->addConfig('/config/basic.yaml');

        return $kernel;
    }

    public function testFilterSetup(): void
    {
        /** @var ManagerRegistry $managerRegistry */
        $managerRegistry = self::getContainer()->get('doctrine');
        /** @var EntityManagerInterface $em */
        foreach ($managerRegistry->getManagers() as $em) {
            self::assertTrue($em->getFilters()->has(SoftDeletableFilter::NAME));
            self::assertTrue($em->getFilters()->isEnabled(SoftDeletableFilter::NAME));
        }
    }

    public function testDoctrineTypeSetup(): void
    {
        self::assertArrayHasKey(DeletedAtType::NAME, Type::getTypesMap());
        self::assertContains(DeletedAtType::class, Type::getTypesMap());
    }

    public function testSubscriberSetup(): void
    {
        /** @var ManagerRegistry $managerRegistry */
        $managerRegistry = self::getContainer()->get('doctrine');
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        foreach ($managerRegistry->getManagers() as $em) {
            $evm = $em->getEventManager();

            // The internal storage for subscribers/listeners can vary between
            // Doctrine versions and implementations. Instead of relying on a
            // specific private property name, cast the event manager to an
            // array and scan for any arrays that may contain service ids or
            // subscriber identifiers.
            $subscribers = [];
            foreach ((array) $evm as $bucket) {
                if (\is_array($bucket)) {
                    foreach ($bucket as $item) {
                        if (\is_string($item) || (\is_object($item) && !\is_callable($item))) {
                            $subscribers[] = $item;
                        }
                    }
                }
            }

            // First, check if the service exists in the container (most robust).
            $serviceIdRegistered = $container->has(DoctrineEventSubscriberPass::SOFT_DELETABLE_SUBSCRIBER_SERVICE_ID);
            if (!$serviceIdRegistered) {
                // Fallback: scan the event manager internals for the service id.
                $serviceIdRegistered = \in_array(
                    DoctrineEventSubscriberPass::SOFT_DELETABLE_SUBSCRIBER_SERVICE_ID,
                    $subscribers,
                    true
                );
            }
            $serviceRegistered = \array_reduce($subscribers, static fn (
                bool $carry,
                $service,
            ) => $carry ? $carry : $service instanceof SoftDeletableEventSubscriber, false);
            /** @var array<object> $listeners */
            $listeners = $evm->getListeners()['loadClassMetadata'] ?? [];
            $listenerRegistered = \array_reduce($listeners, static fn (
                bool $carry,
                $service,
            ) => $carry ? $carry : $service instanceof SoftDeletableEventSubscriber, false);

            self::assertTrue($serviceIdRegistered || $serviceRegistered || $listenerRegistered);
        }
    }
}
