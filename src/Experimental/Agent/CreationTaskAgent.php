<?php

namespace LLPhant\Experimental\Agent;

use LLPhant\Chat\FunctionInfo\FunctionInfo;
use LLPhant\Chat\FunctionInfo\Parameter;
use LLPhant\Chat\OpenAIChat;
use LLPhant\Utils\CLIOutputUtils;

class CreationTaskAgent extends AgentBase
{
    public function __construct(private readonly TaskManager $taskManager, private readonly OpenAIChat $openAIChat, bool $verbose = false)
    {
        parent::__construct($verbose);
        $nameTask = new Parameter('name', 'string', 'name of the task');
        $descriptionTask = new Parameter('description', 'string', 'description of the task');
        $param = new Parameter('tasks', 'array', 'tasks to be added to the list of tasks to be completed', [], null, [$nameTask, $descriptionTask]);
        $addTasksFunction = new FunctionInfo('addTasks', $this->taskManager, 'add tasks to the list of tasks to be completed', [$param], [$param]);
        $this->openAIChat->addFunction($addTasksFunction);
        $this->openAIChat->requiredFunction = $addTasksFunction;
    }

    /**
     * Generates new tasks using OpenAI API based on previous tasks' results.
     *
     * @param  FunctionInfo[]  $tools
     */
    public function createTasks(string $objective, array $tools): void
    {
        if (empty($this->taskManager->getAchievedTasks())) {
            $prompt = 'You are a task creation AI. '
                ."The objective is: {$objective}."
                .'You have the following tools available: '.$this->getToolsDescription($tools)
                .'You need to create tasks to do the objective.';

        } else {
            // Join the task list into a string for the prompt
            $unachievedTasks = implode(', ', array_column($this->taskManager->getUnachievedTasks(), 'name'));
            $achievedTasks = $this->taskManager->getAchievedTasksNameAndResult();
            $prompt = 'You are a task creation AI that uses the result of an execution agent'
                ."Your objective is: {$objective},"
                ." The previous tasks are: {$achievedTasks}."
                ." These are incomplete tasks: {$unachievedTasks}."
                .'You have the following tools available: '.$this->getToolsDescription($tools)
                .' Based on the result of previous tasks, create new tasks to do the objective but ONLY if needed.'
                .' You MUST avoid create duplicated tasks.';
        }
        CLIOutputUtils::renderTitleAndMessageGreen('🤖 CreationTaskAgent.', 'Prompt: '.$prompt, $this->verbose);

        // We don't handle the response because the function will be executed
        $this->openAIChat->generateText($prompt);
    }

    /**
     * @param  FunctionInfo[]  $tools
     */
    private function getToolsDescription(array $tools): string
    {
        $toolsDescription = '';
        foreach ($tools as $tool) {
            $toolsDescription .= "The tool {$tool->name} is used to {$tool->description}.";
        }

        return $toolsDescription;
    }
}
