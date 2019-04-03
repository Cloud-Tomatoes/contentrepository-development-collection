<?php
declare(strict_types=1);

namespace Neos\EventSourcedNeosAdjustments\NodeImportFromLegacyCR\Service;

/*
 * This file is part of the Neos.ContentRepositoryMigration package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentGraph\DoctrineDbalAdapter\Domain\Projection\GraphProjector;
use Neos\EventSourcedContentRepository\Domain\Projection\Workspace\WorkspaceProjector;
use Neos\EventSourcing\EventListener\AppliedEventsLogRepository;
use Neos\EventSourcing\EventListener\EventListenerInvoker;
use Neos\EventSourcing\EventStore\EventStoreManager;
use Neos\Flow\Annotations as Flow;

/**
 * During the import process of nodes, we need the projection updates interleaved with the event store updates,
 * in order for soft constraints to work properly.
 *
 * To speed up this process, we apply the following optimizations:
 *
 * - we disable the Job Queue for projection updates; and rather direcly call the projection catch-up (in the main call)
 * - we disable the "hasProcessed()" logic in the projectors (which works currently by writing cache files): When we run
 *   synchronously, we *always* know that the projection is up to date.
 *
 * This speeds up the process for importing tremendously.
 *
 * @Flow\Scope("singleton")
 */
class ImportProjectionPerformanceService
{

    /**
     * @Flow\Inject
     * @var AppliedEventsLogRepository
     */
    protected $appliedEventsLogRepository;

    /**
     * @Flow\Inject
     * @var EventStoreManager
     */
    protected $eventStoreManager;

    /**
     * @Flow\Inject
     * @var EventListenerInvoker
     */
    protected $eventListenerInvoker;

    /**
     * @Flow\Inject(lazy=false)
     * @var GraphProjector
     */
    protected $graphProjector;

    /**
     * @Flow\Inject(lazy=false)
     * @var WorkspaceProjector
     */
    protected $workspaceProjector;

    public function configureGraphAndWorkspaceProjectionsToRunSynchronously()
    {
        $this->appliedEventsLogRepository->ensureHighestAppliedSequenceNumbersAreInitialized();

        $this->disableJobQueueForProjectionUpdateAndEnsureProjectorsAreRunSynchronously();

        $this->graphProjector->assumeProjectorRunsSynchronously();
        $this->workspaceProjector->assumeProjectorRunsSynchronously();
    }

    private function disableJobQueueForProjectionUpdateAndEnsureProjectorsAreRunSynchronously()
    {
        $eventStore = $this->eventStoreManager->getEventStoreForBoundedContext('Neos.EventSourcedContentRepository');
        $eventStore->enableEventListenerTrigger(false);
        $eventStore->onPostCommit(function () {
            $this->eventListenerInvoker->catchUp($this->graphProjector);
            $this->eventListenerInvoker->catchUp($this->workspaceProjector);
        });
    }
}