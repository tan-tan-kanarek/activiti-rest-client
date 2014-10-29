<?php
require_once(__DIR__ . '/client/ActivitiClient.php');
require_once(__DIR__ . '/client/objects/ActivitiStartProcessInstanceRequestVariable.php');

if($argc < 2)
	die('Execution ID argument required');
	
$processInstanceId = $argv[1];

$activiti = new ActivitiClient();
$activiti->setCredentials('kermit', 'kermit');
$activiti->setDebug(true);

$processInstanceVariables = array();
$processInstances = $activiti->executions->queryExecutions($processInstanceId, null, $processInstanceVariables);

$action = 'messageEventReceived';
$messageName = "entryReady";

foreach($processInstances->getData() as $processInstance)
{
	/* @var $processInstance ActivitiQueryExecutionsResponseData */
	if($processInstance->getActivityid() === 'entry-ready-event')
	{
		$activiti->executions->executeAnActionOnAnExecution($processInstance->getId(), $action, null, null, $messageName);
	}
}
