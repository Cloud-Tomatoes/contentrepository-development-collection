<?php

namespace Neos\StandaloneCrExample;


use Neos\ContentGraph\DoctrineDbalAdapter\Domain\Projection\GraphProjector;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamEventStreamName;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\Event\ContentStreamWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\CreateRootWorkspace;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\WorkspaceCommandHandler;
use Neos\EventSourcedContentRepository\Domain\ValueObject\UserIdentifier;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceDescription;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceName;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceTitle;
use Neos\EventSourcing\Event\Decorator\EventWithIdentifier;
use Neos\EventSourcing\Event\DomainEvents;
use Neos\EventSourcing\Event\EventTypeResolver;
use Neos\EventSourcing\EventListener\EventListenerLocator;
use Neos\EventSourcing\EventStore\EventListenerTrigger\EventListenerTrigger;
use Neos\EventSourcing\EventStore\EventNormalizer;
use Neos\EventSourcing\EventStore\EventStore;
use Neos\EventSourcing\EventStore\Storage\Doctrine\DoctrineEventStorage;
use Neos\EventSourcing\EventStore\Storage\Doctrine\Factory\ConnectionFactory;
use Neos\EventSourcing\EventStore\Storage\EventStorageInterface;
use Neos\Utility\ObjectAccess;

class EventSourcingFactories
{

    public static function buildConnectionFactory(): ConnectionFactory
    {
        $connectionFactory = new ConnectionFactory();
        ObjectAccess::setProperty($connectionFactory, 'defaultFlowDatabaseConfiguration', [], true);
        return $connectionFactory;
    }

    public static function buildEventStorage(ConnectionFactory $connectionFactory): DoctrineEventStorage
    {
        $storage = new DoctrineEventStorage([
            'backendOptions' => [
                'driver' => 'pdo_mysql',
                'dbname' => 'escr-standalone',
                'user' => 'root',
                'password' => '',
                'host' => 'localhost',
            ],
            'mappingTypes' => [
                'flow_json_array' => [
                    'dbType' => 'json_array',
                    'className' => 'Neos\Flow\Persistence\Doctrine\DataTypes\JsonArrayType'
                ]
            ]
        ]);

        ObjectAccess::setProperty($storage, 'connectionFactory', $connectionFactory, TRUE);
        ObjectAccess::setProperty($storage, 'now', new \DateTimeImmutable(), TRUE);
        $storage->initializeObject();

        return $storage;
    }

    public static function buildEventStore(EventStorageInterface $eventStorage, EventTypeResolver $eventTypeResolver, EventNormalizer $eventNormalizer, EventListenerTrigger $eventListenerTrigger): EventStore
    {
        $eventStore = new EventStore($eventStorage);
        ObjectAccess::setProperty($eventStore, 'eventTypeResolver', $eventTypeResolver, true);
        ObjectAccess::setProperty($eventStore, 'eventNormalizer', $eventNormalizer, true);
        ObjectAccess::setProperty($eventStore, 'eventListenerTrigger', $eventListenerTrigger, true);
        return $eventStore;
    }

    public static function buildEventNormalizer(EventTypeResolver $eventTypeResolver): EventNormalizer
    {
        $eventNormalizer = new EventNormalizer();
        ObjectAccess::setProperty($eventNormalizer, 'eventTypeResolver', $eventTypeResolver, true);
        $initializeObjectOfNormalizer = new \ReflectionMethod($eventNormalizer, 'initializeObject');
        $initializeObjectOfNormalizer->setAccessible(true);
        $initializeObjectOfNormalizer->invoke($eventNormalizer);

        return $eventNormalizer;
    }

    public static function buildEventListenerTrigger(EventListenerLocator $eventListenerLocator): EventListenerTrigger
    {
        $eventListenerTrigger = new EventListenerTrigger();
        ObjectAccess::setProperty($eventListenerTrigger, 'eventListenerLocator', $eventListenerLocator, true);
        return $eventListenerTrigger;
    }

    public static function buildEventListenerLocator(): EventListenerLocator
    {
        $eventListenerLocator = unserialize('O:' . strlen(EventListenerLocator::class) . ':"' . EventListenerLocator::class . '":0:{};');

        // array in the format ['<eventClassName>' => ['<listenerClassName>' => '<listenerMethodName>', '<listenerClassName2>' => '<listenerMethodName2>', ...]]
        $eventClassNamesAndListeners = [];

        $listenerClassNames = [
            GraphProjector::class
        ];

        foreach ($listenerClassNames as $listenerClassName) {
            var_dump($listenerClassName);
            $methods = get_class_methods($listenerClassName);
            var_dump($methods);
            foreach ($methods as $listenerMethodName) {
                if (strpos($listenerMethodName, 'handle') === 0) {
                    // method starts with "handle"

                    $listenerMethod = new \ReflectionMethod($listenerClassName, $listenerMethodName);
                    $params = $listenerMethod->getParameters();
                    $eventClassName = $params[0]->getType()->getName();

                    $eventClassNamesAndListeners[$eventClassName][$listenerClassName] = $listenerMethodName;
                }
            }
        }

        var_dump($eventClassNamesAndListeners);


        ObjectAccess::setProperty($eventListenerLocator, 'eventClassNamesAndListeners', $eventClassNamesAndListeners, true);

        return $eventListenerLocator;
    }

    public function run()
    {
        echo "Hallo";

        $cs = ContentStreamIdentifier::create();
        $streamName = ContentStreamEventStreamName::fromContentStreamIdentifier($cs)->getEventStreamName();
        $event = new ContentStreamWasCreated(
            $cs,
            UserIdentifier::forSystemUser()
        );
        $event = EventWithIdentifier::create($event);
        $eventStore = self::prepareEventStore();

        $eventStore->setup();

        $eventStore->commit($streamName, DomainEvents::withSingleEvent($event));

        $command = new CreateRootWorkspace(
            WorkspaceName::forLive(),
            new WorkspaceTitle('live'),
            new WorkspaceDescription('The live WS'),
            UserIdentifier::forSystemUser(),
            $cs
        );

        $cmd = new WorkspaceCommandHandler();
        $cmd->handleCreateRootWorkspace($command);
    }


}
