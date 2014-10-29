<?php
require_once(__DIR__ . '/client/ActivitiClient.php');
require_once(__DIR__ . '/client/objects/ActivitiStartProcessInstanceRequestVariable.php');


$activiti = new ActivitiClient();
$activiti->setCredentials('kermit', 'kermit');
$activiti->setDebug(true);

$processDefinitionId = null;
$businessKey = null;
$message = null;
$tenantId = null;

$processDefinitionKey = 'proc-add-media';
$variables = array();

$endPoint = new ActivitiStartProcessInstanceRequestVariable();
$endPoint->setName('endPoint');
$endPoint->setValue('http://www.kaltura.com');
$variables[] = $endPoint;

$partnerId = new ActivitiStartProcessInstanceRequestVariable();
$partnerId->setName('partnerId');
$partnerId->setValue(1676801);
$variables[] = $partnerId;
        
$adminSecret = new ActivitiStartProcessInstanceRequestVariable();
$adminSecret->setName('adminSecret');
$adminSecret->setValue('5f8b98c59d2f4904ab9a1c2054d79055');
$variables[] = $adminSecret;
        
$mediaUrl = new ActivitiStartProcessInstanceRequestVariable();
$mediaUrl->setName('mediaUrl');
$mediaUrl->setValue('http://www.kaltura.com/content/r71v1/entry/data/354/844/1_yap32sc6_1_2re8u4z9_11.flv');
$variables[] = $mediaUrl;
        
$entryName = new ActivitiStartProcessInstanceRequestVariable();
$entryName->setName('entryName');
$entryName->setValue('bpm test - ' . date('H:i'));
$variables[] = $entryName;

$response = $activiti->processInstances->startProcessInstance($processDefinitionId, $businessKey, $variables, $processDefinitionKey, $tenantId, $message);
var_dump($response);

