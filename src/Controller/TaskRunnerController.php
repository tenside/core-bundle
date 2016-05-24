<?php

/**
 * This file is part of tenside/core-bundle.
 *
 * (c) Christian Schiffler <c.schiffler@cyberspectrum.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    tenside/core-bundle
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Yanick Witschi <yanick.witschi@terminal42.ch>
 * @copyright  2015 Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @license    https://github.com/tenside/core-bundle/blob/master/LICENSE MIT
 * @link       https://github.com/tenside/core-bundle
 * @filesource
 */

namespace Tenside\CoreBundle\Controller;

use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tenside\Core\Util\PhpProcessSpawner;
use Tenside\CoreBundle\Annotation\ApiDescription;
use Tenside\Core\Task\Task;
use Tenside\Core\Util\JsonArray;

/**
 * Lists and executes queued tasks.
 */
class TaskRunnerController extends AbstractController
{
    /**
     * Retrieve the task list.
     *
     * @return JsonResponse
     *
     * @ApiDoc(
     *   section="tasks",
     *   statusCodes = {
     *     200 = "When everything worked out ok"
     *   },
     *   authentication = true,
     *   authenticationRoles = {
     *     "ROLE_MANIPULATE_REQUIREMENTS"
     *   },
     * )
     * @ApiDescription(
     *   response={
     *     "status" = {
     *       "dataType" = "string",
     *       "description" = "OK on success"
     *     },
     *     "tasks" = {
     *         "id" = {
     *           "dataType" = "string",
     *           "description" = "The task id."
     *         },
     *         "status" = {
     *           "dataType" = "string",
     *           "description" = "The task status."
     *         },
     *         "type" = {
     *           "dataType" = "string",
     *           "description" = "The type of the task."
     *         },
     *         "created_at" = {
     *           "dataType" = "string",
     *           "description" = "The date the task was created in ISO 8601 format."
     *         },
     *         "output" = {
     *            "dataType" = "string",
     *            "description" = "The command line output of the task."
     *         }
     *   }
     * )
     */
    public function getTasksAction()
    {
        $tasks = [];
        $list   = $this->getTensideTasks();
        foreach ($list->getIds() as $taskId) {
            $tasks[$taskId] = $this->convertTaskToArray($list->getTask($taskId));
        }

        return $this->createJsonResponse($tasks, true);
    }

    /**
     * Retrieve the given task task.
     *
     * @param string  $taskId  The id of the task to retrieve.
     *
     * @param Request $request The request.
     *
     * @return JsonResponse
     *
     * @throws NotFoundHttpException When the task could not be found.
     *
     * @ApiDoc(
     *   section="tasks",
     *   statusCodes = {
     *     200 = "When everything worked out ok"
     *   },
     *   authentication = true,
     *   authenticationRoles = {
     *     "ROLE_MANIPULATE_REQUIREMENTS"
     *   },
     *   filters = {
     *     {
     *       "name"="offset",
     *       "dataType" = "int",
     *       "description"="If present, the output will be returned from the given byte offset."
     *     }
     *   }
     * )
     * @ApiDescription(
     *   response={
     *     "status" = {
     *       "dataType" = "string",
     *       "description" = "OK on success"
     *     },
     *     "task" = {
     *         "id" = {
     *           "dataType" = "string",
     *           "description" = "The task id."
     *         },
     *         "status" = {
     *           "dataType" = "string",
     *           "description" = "The task status."
     *         },
     *         "type" = {
     *           "dataType" = "string",
     *           "description" = "The type of the task."
     *         },
     *         "created_at" = {
     *           "dataType" = "string",
     *           "description" = "The date the task was created in ISO 8601 format."
     *         },
     *         "output" = {
     *            "dataType" = "string",
     *            "description" = "The command line output of the task."
     *         }
     *   }
     * )
     */
    public function getTaskAction($taskId, Request $request)
    {
        // Retrieve the status file of the task.
        $task   = $this->getTensideTasks()->getTask($taskId);
        $offset = null;

        if (!$task) {
            throw new NotFoundHttpException('No such task.');
        }

        if ($request->query->has('offset')) {
            $offset = (int) $request->query->get('offset');
        }

        return $this->createJsonResponse([$this->convertTaskToArray($task, $offset)]);
    }

    /**
     * Queue a task in the list.
     *
     * @param Request $request The request.
     *
     * @return JsonResponse
     *
     * @throws NotAcceptableHttpException When the payload is invalid.
     *
     * @ApiDoc(
     *   section="tasks",
     *   statusCodes = {
     *     201 = "When everything worked out ok"
     *   },
     *   authentication = true,
     *   authenticationRoles = {
     *     "ROLE_MANIPULATE_REQUIREMENTS"
     *   },
     * )
     * @ApiDescription(
     *   response={
     *     "status" = {
     *       "dataType" = "string",
     *       "description" = "OK on success"
     *     },
     *     "task" = {
     *         "id" = {
     *           "dataType" = "string",
     *           "description" = "The task id."
     *         },
     *         "status" = {
     *           "dataType" = "string",
     *           "description" = "The task status."
     *         },
     *         "type" = {
     *           "dataType" = "string",
     *           "description" = "The type of the task."
     *         },
     *         "created_at" = {
     *           "dataType" = "string",
     *           "description" = "The date the task was created in ISO 8601 format."
     *         },
     *         "output" = {
     *            "dataType" = "string",
     *            "description" = "The command line output of the task."
     *         }
     *   }
     * )
     */
    public function addTaskAction(Request $request)
    {
        $metaData = null;
        $content  = $request->getContent();
        if (empty($content)) {
            throw new NotAcceptableHttpException('Invalid payload');
        }
        $metaData = new JsonArray($content);
        if (!$metaData->has('type')) {
            throw new NotAcceptableHttpException('Invalid payload');
        }

        try {
            $taskId = $this->getTensideTasks()->queue($metaData->get('type'), $metaData);
        } catch (\InvalidArgumentException $exception) {
            throw new NotAcceptableHttpException($exception->getMessage());
        }

        return $this->createJsonResponse(
            [$this->convertTaskToArray($this->getTensideTasks()->getTask($taskId))],
            false,
            'OK',
            JsonResponse::HTTP_CREATED
        );
    }

    /**
     * Remove a task from the list.
     *
     * @param string $taskId The id of the task to remove.
     *
     * @return JsonResponse
     *
     * @throws NotFoundHttpException      When the given task could not be found.
     * @throws NotAcceptableHttpException When trying to delete a running task.
     *
     * @ApiDoc(
     *   section="tasks",
     *   statusCodes = {
     *     200 = "When everything worked out ok"
     *   },
     *   authentication = true,
     *   authenticationRoles = {
     *     "ROLE_MANIPULATE_REQUIREMENTS"
     *   },
     * )
     * @ApiDescription(
     *   response={
     *     "status" = {
     *       "dataType" = "string",
     *       "description" = "OK on success"
     *     }
     *   }
     * )
     */
    public function deleteTaskAction($taskId)
    {
        $list = $this->getTensideTasks();
        $task = $list->getTask($taskId);

        if (!$task) {
            throw new NotFoundHttpException('Task id ' . $taskId . ' not found');
        }

        if ($task->getStatus() === Task::STATE_RUNNING) {
            throw new NotAcceptableHttpException('Task id ' . $taskId . ' is running and can not be deleted');
        }

        $task->removeAssets();
        $list->remove($task->getId());

        return JsonResponse::create(
            [
                'status' => 'OK'
            ]
        );
    }

    /**
     * Starts the next pending task if any.
     *
     * @return JsonResponse
     *
     * @throws NotFoundHttpException      When no task could be found.
     * @throws NotAcceptableHttpException When a task is already running and holds the lock.
     *
     * @ApiDoc(
     *   section="tasks",
     *   statusCodes = {
     *     200 = "When everything worked out ok",
     *     404 = "When no pending task has been found",
     *     406 = "When another task is still running"
     *   },
     *   authentication = true,
     *   authenticationRoles = {
     *     "ROLE_MANIPULATE_REQUIREMENTS"
     *   },
     * )
     * @ApiDescription(
     *   response={
     *     "status" = {
     *       "dataType" = "string",
     *       "description" = "OK on success"
     *     },
     *     "task" = {
     *         "id" = {
     *           "dataType" = "string",
     *           "description" = "The task id."
     *         },
     *         "status" = {
     *           "dataType" = "string",
     *           "description" = "The task status."
     *         },
     *         "type" = {
     *           "dataType" = "string",
     *           "description" = "The type of the task."
     *         },
     *         "created_at" = {
     *           "dataType" = "string",
     *           "description" = "The date the task was created in ISO 8601 format."
     *         },
     *         "output" = {
     *            "dataType" = "string",
     *            "description" = "The command line output of the task."
     *         }
     *   }
     * )
     */
    public function runAction()
    {
        $lock = $this->container->get('tenside.taskrun_lock');

        if ($this->getTensideConfig()->isForkingAvailable() && !$lock->lock()) {
            throw new NotAcceptableHttpException('Task already running');
        }

        // Fetch the next queued task.
        $task = $this->getTensideTasks()->getNext();

        if (!$task) {
            throw new NotFoundHttpException('Task not found');
        }

        if ($task::STATE_PENDING !== $task->getStatus()) {
            return $this->createJsonResponse([$this->convertTaskToArray($task)]);
        }

        // Now spawn a runner.
        try {
            $this->spawn($task);
        } finally {
            if ($this->getTensideConfig()->isForkingAvailable()) {
                $lock->release();
            }
        }

        return $this->createJsonResponse([$this->convertTaskToArray($task)]);
    }

    /**
     * Spawn a detached process for a task.
     *
     * @param Task $task The task to spawn a process for.
     *
     * @return void
     *
     * @throws \RuntimeException When the task could not be started.
     */
    private function spawn(Task $task)
    {
        $config      = $this->getTensideConfig();
        $home        = $this->get('tenside.home')->homeDir();
        $commandline = PhpProcessSpawner::create($config, $home)->spawn(
            [
                $this->get('tenside.cli_script')->cliExecutable(),
                'tenside:runtask',
                $task->getId(),
                '-v',
                '--no-interaction'
            ]
        );

        $commandline->start();
        if (!$commandline->isRunning()) {
            // We might end up here when the process has been forked.
            // If exit code is neither 0 nor null, we have a problem here.
            if ($exitCode = $commandline->getExitCode()) {
                /** @var LoggerInterface $logger */
                $logger = $this->get('logger');
                $logger->error('Failed to execute "' . $commandline->getCommandLine() . '"');
                $logger->error('Exit code: ' . $commandline->getExitCode());
                $logger->error('Output: ' . $commandline->getOutput());
                $logger->error('Error output: ' . $commandline->getErrorOutput());
                throw new \RuntimeException(
                    sprintf(
                        'Spawning process task %s resulted in exit code %s',
                        $task->getId(),
                        $exitCode
                    )
                );
            }
        }
    }

    /**
     * Convert a task to an array.
     *
     * @param Task $task
     * @param null $outputOffset
     *
     * @return array
     */
    private function convertTaskToArray(Task $task, $outputOffset = null)
    {
        return [
            'id'         => $task->getId(),
            'status'     => $task->getStatus(),
            'type'       => $task->getType(),
            'created_at' => $task->getCreatedAt()->format(\DateTime::ISO8601),
            'output'     => $task->getOutput($outputOffset)
        ];
    }

    /**
     * Create a JsonResponse based on an array of tasks.
     *
     * @param Task[] $tasks
     * @param bool   $isCollection
     * @param string $status
     * @param int    $httpStatus
     *
     * @return JsonResponse
     */
    private function createJsonResponse(array $tasks, $isCollection = false, $status = 'OK', $httpStatus = JsonResponse::HTTP_OK)
    {
        $data       = ['status' => $status];
        $key        = $isCollection ? 'tasks' : 'task';
        $data[$key] = $tasks;

        return JsonResponse::create($data, $httpStatus)
            ->setEncodingOptions((JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_FORCE_OBJECT));
    }
}
