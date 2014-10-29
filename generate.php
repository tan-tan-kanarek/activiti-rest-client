<?php

chdir(__DIR__);

if(!file_exists(__DIR__ . '/client'))
	mkdir(__DIR__ . '/client');
if(!file_exists(__DIR__ . '/client/services'))
	mkdir(__DIR__ . '/client/services');
if(!file_exists(__DIR__ . '/client/objects'))
	mkdir(__DIR__ . '/client/objects');
	
$sourceDir = dir(__DIR__ . '/source');
while (false !== ($entry = $sourceDir->read())) 
{
	if($entry[0] != '.')
		copy(__DIR__ . '/source/' . $entry, __DIR__ . '/client/' . $entry);
}
$sourceDir->close();

$objects = array();

$url = 'http://www.activiti.org/userguide';

$html = file_get_contents($url);
echo "Scanning URL: $url\n";

scanURL();

function scanURL()
{
	global $html;
	
	$chapterMatches = null;
	if(!preg_match_all('/<dt><span\s+class="chapter"[^>]*><a[^>]*>([^<]+)<\/a><\/span><\/dt>/sU', $html, $chapterMatches))
	{
		echo "Failed to find chapters\n";
		exit(-1);	
	}
	
	$contentTable = null;
	foreach($chapterMatches[1] as $matchIndex => $title)
	{
		$contentTableMatches = null;
		if(preg_match('/REST API$/', $title) && preg_match("/$title<\\/a><\\/span><\\/dt>\\s*<dd>(.+)<\\/dd>\\s*<dt><span\\s+class=\"chapter\"[^>]*>/sU", $html, $contentTableMatches))
		{
			echo "Content table found\n";
			$contentTable = $contentTableMatches[1];
			break;
		}
	}
	
	if(!$contentTable)
	{
		echo "Failed to find content table\n";
		exit(-1);	
	}
	
	$serviceMatches = null;
	if(!preg_match_all('/<dt><span\s+class="section"[^>]*><a[^>]*>([^<]+)<\/a><\/span><\/dt>\s*<dd>(.+)<\/dd>/sU', $contentTable, $serviceMatches))
	{
		echo "Failed to find services\n";
		exit(-1);	
	}
	echo "Services list found\n";
	
	$services = array();
	foreach($serviceMatches[1] as $serviceIndex => $serviceName)
	{
		if(!$serviceIndex)
			continue;
			
		$actionMatches = null;
		if(!preg_match_all('/<dt><span\s+class="section"[^>]*><a\s+href="#([^"]+)"[^>]*>([^<]+)<\/a><\/span><\/dt>/sU', $serviceMatches[2][$serviceIndex], $actionMatches))
		{
			echo "Failed to find actions\n";
			exit(-1);	
		}
	
		$serviceName = preg_replace('/[^\w\d]/', '', ucwords($serviceName));
		$className = 'Activiti' . preg_replace('/[^\w\d]/', '', ucwords($serviceName)) . 'Service';
		$services[$serviceName] = $className;
		$actions = array();
		foreach ($actionMatches[1] as $actionIndex => $href)
		{
			$actionName = lcfirst(preg_replace('/[^\w\d]/', '', preg_replace('/\sA\s/', '', ucwords($actionMatches[2][$actionIndex]))));
			$actions[$href] = $actionName;
		}
		
		generateActivitiService($actions, $serviceName);
	}
	
	generateActivitiClient($services);
}

function generateActivitiDoc()
{
	global $classTree;
	
	$doc = "
---
#Activiti API PHP Client
---

";

	foreach($classTree['/'] as $file => $className)
	{	
		$varName = lcfirst($className);
		$doc .= "
## Activiti$className
Could be access directly from ActivitiClient->$varName
";
		$doc .= appendActivitiServiceDoc($file, $className);
	}
	
	file_put_contents(__DIR__ . "/client.md", $doc);
}

function appendActivitiServiceDoc($file, $name)
{
	global $classTree, $objects;
	
	$classPath = str_replace(array(__DIR__, '.md', '\\v3', '\\'), array('', '', '', '/'), $file);
	echo "Generating service doc: $name [$classPath]\n";
	$content = file_get_contents($file);
'
## List your notifications

List all notifications for the current user, grouped by repository.

    GET /notifications
';
	
	preg_match_all('/## ([^\n]+)\n\n(.*)(\n\n)?    (GET|PUT|PATCH|DELETE) ([^\n]+)\n\n(([^\n]+\n)+\n)?(### (Parameters|Input)\n\n([^#]+))?### Response\n(\n<%= headers (\d+) %>)?(\n<%= json( :([^\s]+) |\(:([^\)]+)\) [^%]*)%>)?\n\n/sU', $content, $matches);

	$doc = '
### Attributes:
';

	if(isset($classTree[$classPath]))
	{
		foreach($classTree[$classPath] as $file => $className)
		{
			$varName = lcfirst(preg_replace("/^$name/", '', $className));
			$doc .= "
 - Activiti$className $varName";
		}
	
	$doc .= '

### Sub-services:
';
	
		foreach($classTree[$classPath] as $file => $className)
		{
			$varName = lcfirst(preg_replace("/^$name/", '', $className));
			$doc .= "
 - Activiti$className $varName";
		}
	}
	
	$doc .= '

### Methods:
';
	
	foreach($matches[1] as $index => $description)
	{
		$methodName = lcfirst(str_replace(array(' A ', ' '), array('', ''), ucwords(preg_replace('/[^\w]/', ' ', strtolower($description)))));
		if($methodName == 'list')
			$methodName .= $name;
			
		$httpMethod = $matches[4][$index];
		$url = str_replace(':', '$', $matches[5][$index]);
		$arguments = array();
		$dataArguments = array();
		if(preg_match_all('/(\$[^\/?.]+)/', $url, $argumentsMatches))
			$arguments = $argumentsMatches[1];
		
		$paremetersDescription = $matches[10][$index];
		$docCommentParameters = array();
		$paremetersMatches = null;
		if($paremetersDescription && preg_match_all('/([^\n]+)\n: _([^_]+)_ \*\*([^\*]+)\*\* (.+)\n\n/sU', $paremetersDescription, $paremetersMatches))
		{
			foreach($paremetersMatches[1] as $parameterIndex => $parameterName)
			{
				$parameterName = preg_replace('/[^\w]/', '', $parameterName);
				$parameterRequirement = $paremetersMatches[2][$parameterIndex];
				$parameterType = $paremetersMatches[3][$parameterIndex];
				$parameterDescription = $paremetersMatches[4][$parameterIndex];
				$parameterDescription = implode("\n	 * \t", explode("\n", $parameterDescription));
				$docCommentParameters[] = "$parameterType parameterName ($parameterRequirement) $parameterDescription";
				$argument = "\$$parameterName";
				$dataArguments[] = $parameterName;
				if($parameterRequirement == 'Optional')
					$argument .= ' = null';
					
				$arguments[] = $argument;
			}
		}
		
		$expectedStatus = 200;
		if(isset($matches[12][$index]) && is_numeric($matches[12][$index]))
			$expectedStatus = $matches[12][$index];
		
		$arguments = implode(', ', $arguments);
		$doc .= "

**$methodName:**

Expected HTTP status: $expectedStatus
*$description*


Attributes:
";
		
		foreach($docCommentParameters as $docCommentParameter)
		{
			$doc .= "
 - $docCommentParameter";
		}
						
		$responseType = null;
		$returnType = null;
		$returnArray = false;
		
		if(isset($matches[15][$index]) && strlen($matches[15][$index]))
		{
			$responseType = $matches[15][$index];
		}
		elseif(isset($matches[16][$index]) && strlen($matches[16][$index]))
		{
			$responseType = $matches[16][$index];
			$returnArray = true;
		}
	
		if($responseType)
		{
			$objects[strtolower($responseType)] = true;
			$returnType = gitHubClassName($responseType);
			
			if($returnArray)
			{
			$doc .= "

Returns array of $returnType objects";
			}
			else 
			{
			$doc .= "

Returns $returnType object";
			}
		}
	}

	if(isset($classTree[$classPath]))
	{	
		foreach($classTree[$classPath] as $file => $className)
		{
			$varName = lcfirst($className);
			$doc .= "
## Activiti$className
Could be access directly from ActivitiClient->{$name}->{$varName}
";
			$doc .= appendActivitiServiceDoc($file, $className);
		}
	}

	return $doc;
}


function generateActivitiClient($services)
{
	$requires = array();
	
	$class = "
class ActivitiClient extends ActivitiClientBase
{
";

	foreach($services as $service => $className)
	{
		$requires[$className] = "require_once(__DIR__ . '/services/$className.php');";
		$varName = lcfirst($service);
		$class .= "
	/**
	 * @var $className
	 */
	public \$$varName;
	";
	}
	
	$class .= "
	
	/**
	 * Initialize sub services
	 */
	public function __construct()
	{";
		
	foreach($services as $service => $className)
	{
		$varName = lcfirst($service);
		$class .= "
		\$this->$varName = new $className(\$this);";
	}
		
	$class .= "
	}
	";
	
	$class .= "
}
";
	
	$requires = implode("\n", $requires);
	$php = "<?php

require_once(__DIR__ . '/ActivitiClientBase.php');
$requires

$class
";

	file_put_contents(__DIR__ . "/client/ActivitiClient.php", $php);
}

function generateActivitiService($actions, $name)
{
	global $html;
	
	$requires = array();
	echo "Generating service: $name\n";
	

	$className = "Activiti{$name}Service";
	$class = "
class $className extends ActivitiService
{
	";
	
	foreach($actions as $href => $actionName)
	{
		echo "Generating [$name.$actionName] href [$href]\n";
		
//		<div class="section" title="List of Deployments">
//<div class="titlepage"><div><div><h3 class="title"><a name="N1339E"></a>List of Deployments</h3></div></div></div><p>
//          </p><pre class="prettyprint">GET repository/deployments</pre><p>
//        </p><p>
//            </p><div class="table"><a name="N133A8"></a><p class="title"><b>Table&nbsp;15.10.&nbsp;URL query parameters</b></p><div class="table-contents"><table summary="URL query parameters" border="1"><colgroup><col><col><col></colgroup><thead><tr><th>Parameter</th><th>Required</th><th>Value</th><th>Description</th></tr></thead><tbody><tr><td>name</td><td>No</td><td>String</td><td>Only return deployments with the given name.</td></tr><tr><td>nameLike</td><td>No</td><td>String</td><td>Only return deployments with a name like the given name.</td></tr><tr><td>category</td><td>No</td><td>String</td><td>Only return deployments with the given category.</td></tr><tr><td>categoryNotEquals</td><td>No</td><td>String</td><td>Only return deployments which don't have the given category.</td></tr><tr><td>tenantId</td><td>No</td><td>String</td><td>Only return deployments with the given tenantId.</td></tr><tr><td>tenantIdLike</td><td>No</td><td>String</td><td>Only return deployments with a tenantId like the given value.</td></tr><tr><td>withoutTenantId</td><td>No</td><td>Boolean</td><td>If <code class="literal">true</code>, only returns deployments without a tenantId set. If <code class="literal">false</code>, the <code class="literal">withoutTenantId</code> parameter is ignored.</td></tr><tr><td>sort</td><td>No</td><td>'id' (default), 'name', 'deploytime' or 'tenantId'</td><td>Property to sort on, to be used together with the 'order'.</td></tr><tr><td colspan="4"><p>The general <a class="link" href="#restPagingAndSort" title="Paging and sorting">paging and sorting query-parameters</a> can be used for this URL.</p></td></tr></tbody></table></div></div><p><br class="table-break">
//        </p><p>
//          </p><div class="table"><a name="N1341E"></a><p class="title"><b>Table&nbsp;15.11.&nbsp;REST Response codes</b></p><div class="table-contents"><table summary="REST Response codes" border="1"><colgroup><col><col></colgroup><thead><tr><th>Response code</th><th>Description</th></tr></thead><tbody><tr><td>200</td><td>Indicates the request was successful.</td></tr></tbody></table></div></div><p><br class="table-break">
//        </p><p>
//       <span class="bold"><strong>Success response body:</strong></span>
//            </p><pre class="prettyprint">
//{
//  "data": [
//    {
//      "id": "10",
//      "name": "activiti-examples.bar",
//      "deploymentTime": "2010-10-13T14:54:26.750+02:00",
//      "category": "examples",
//      "url": "http://localhost:8081/service/repository/deployments/10",
//      "tenantId": null
//    }
//  ],
//  "total": 1,
//  "start": 0,
//  "sort": "id",
//  "order": "asc",
//  "size": 1
//}</pre><p>

		$actionMatches = null;
		$regex = '<div[^>]+class="section"[^>]+title="([^"]+)"[^>]*>'; // 1 - title
		$regex .= "<div[^>]+class=\"titlepage\"[^>]*><div[^>]*><div[^>]*><h3[^>]+class=\"title\"[^>]*><a[^>]+name=\"$href\"[^>]*><\\/a>[^<]+<\\/h3[^>]*><\\/div[^>]*><\\/div[^>]*><\\/div[^>]*>"; // 2 - body<p>
		$regex .= '<p>.+<\/p><pre[^>]+class="prettyprint">([A-Z]+)\s+([^<]+)<\/pre[^>]*><p[^>]*>\s+<\/p>(.+)'; // 2 - HTTP action, 3 - URL, 4 - content
		$regex .= '<div[^>]+class="section"[^>][^>]*>'; // next section
		
//		$regex = '.+<table[^>]+summary="URL query parameters"[^>]*>.+<tbody>(.+)<\/tbody\s*>'; // query parameters table
//		$regex = '.+<div[^>]+class="itemizedlist"[^>]*><ul[^>]+class="itemizedlist"[^>]*>(.+)<\/ul>'; // query parameters descriptions
//		$regex = '.+<pre[^>]+class="prettyprint"[^>]*>(.+)<\/pre\s*>'; // response body
		
		if(!preg_match("/$regex/sU", $html, $actionMatches))
		{
			echo "Unable to find action [$name.$actionName] href [$href]\n";
			exit(-1);
		}
		
//		if($actionMatches[1] !== $actionMatches[11])
//		{
//			echo "action [$name.$actionName] href[$href] not found\n";
//			var_dump($actionMatches);
//			exit(-1);
//		}
		
		$description = $actionMatches[1];
		$httpMethod = $actionMatches[2];
		$url = $actionMatches[3];
		$actionContent = $actionMatches[4];
		
		$arguments = array();
		$dataArguments = array();
		if(preg_match_all('/\{([^\}]+)\}/', $url, $argumentsMatches))
			$arguments = $argumentsMatches[1];
			
		$url = preg_replace('/\{([^\}]+)\}/', '$\1', $url);
		
		
		$regex = '<p>\s+<span class="bold"><strong>Request body[^:]*:<\/strong><\/span>\s+<\/p><pre[^>]+class="prettyprint"[^>]*>(.+)<\/pre>'; // query parameters table
		if(preg_match_all("/$regex/sU", $actionContent, $requestBodyMatches))
		{
			foreach($requestBodyMatches[1] as $requestBodyMatchIndex => $requestBodyMatch)
			{
				$requestBodyJson = str_replace(array('(optional)', '...', '""'), array('', '', '"'), $requestBodyMatch);
				$requestBodyJson = preg_replace('/\s+("[^"]+"\s*:\s*[^,\n\r\s\{\[]+)[\n\r]/', '\1,', $requestBodyJson);
				$requestBodyJson = preg_replace('/,\s*([\}\]])/s', '\1', $requestBodyJson);
				$requestBodyJson = preg_replace('/\]\s*\]/s', ']', $requestBodyJson);
				$requestBody = json_decode($requestBodyJson);
				
				if(is_array($requestBody)){
					$dataClassName = 'Activiti' . ucfirst($actionName) . 'RequestData';
					generateActivitiRequestObject($dataClassName, $requestBody[0]);
					$dataArguments['data'] = "array<$dataClassName>";
					$arguments[] = "data = null";
					continue;
				}
				
				if(!is_object($requestBody))
				{
					$arguments[] = "invalid: $requestBodyJson";
					continue;
				}
				
				foreach($requestBody as $argument => $argumentValue)
				{
					if(isset($dataArguments[$argument]))
						continue;
						
					$argumentType = null;
					if(is_string($argumentValue))
					{
						$argumentType = 'string';
					}
					elseif(is_int($argumentValue))
					{
						$argumentType = 'int';
					}
					elseif(is_bool($argumentValue))
					{
						$argumentType = 'boolean';
					}
					elseif(is_array($argumentValue))
					{
						$objectClassName = 'Activiti' . ucfirst($actionName) . 'Request' . ucfirst(preg_replace(array('/ies$/', '/s$/'), array('y', ''), $argument));
						generateActivitiRequestObject($objectClassName, $argumentValue[0]);
						$argumentType = "array<$objectClassName>";
					}
				
					$dataArguments[$argument] = $argumentType;
					$arguments[] = "$argument = null";
				}
			}
		}
	
		$returnType = null;
		$isArray = false;
		$regex = '<p>\s+<span class="bold"><strong>Success response body[^:]*:<\/strong><\/span>\s+<\/p><pre[^>]+class="prettyprint"[^>]*>(.+)<\/pre>'; // query parameters table
		if(preg_match_all("/$regex/sU", $actionContent, $responseBodyMatches))
		{
			$returnType = 'Activiti' . ucfirst($actionName) . 'Response';
			$requires[$returnType] = "require_once(__DIR__ . '/../objects/$returnType.php');";
			foreach($responseBodyMatches[1] as $responseBodyMatchIndex => $responseBodyMatch)
			{
				$responseBodyJson = str_replace(array('(optional)', '...', '""'), array('', '', '"'), $responseBodyMatch);
				$responseBodyJson = preg_replace('/\[([^\}\[\]]+)\]/', '[{\1}]', $responseBodyJson);
				$responseBodyJson = preg_replace('/([,\{\[]+\s*)"([^"]+)"\s*,\s*("?[^,\n\r\s\{\["]+"?)/m', '\1"\2":\3', $responseBodyJson);
				$responseBodyJson = preg_replace('/\s+("[^"]+"\s*:\s*[^,\n\r\s\{\[]+)[\n\r]/', "\\1,\n", $responseBodyJson);
				$responseBodyJson = preg_replace('/,\s*([\}\]])/s', '\1', $responseBodyJson);
				$responseBodyJson = preg_replace('/\]\s*\]/s', ']', $responseBodyJson);
				$responseBody = json_decode($responseBodyJson);
				
				if(is_array($responseBody))
				{
					$isArray = true;
					$responseBody = reset($responseBody);
				}
				
				if(!is_object($responseBody))
				{
					$errors = array(
						JSON_ERROR_NONE => 'No error has occurred',
						JSON_ERROR_DEPTH => 'The maximum stack depth has been exceeded', 
						JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON', 
						JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded', 
						JSON_ERROR_SYNTAX => 'Syntax error', 
						JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded',
					//	JSON_ERROR_RECURSION => 'One or more recursive references in the value to be encoded',
					//	JSON_ERROR_INF_OR_NAN => 'One or more NAN or INF values in the value to be encoded',
					//	JSON_ERROR_UNSUPPORTED_TYPE => 'A value of a type that cannot be encoded was given',
					);
					
					var_dump($responseBodyMatch);
					var_dump($responseBodyJson);
					var_dump($errors[json_last_error()]);
					exit(-1);
				}
				
				generateActivitiResponseObject($returnType, $responseBody);
			}
		}
		
		$docCommentParameters = array();
//		TODO		
//		$paremetersDescription = $matches[10][$index];
//		$paremetersMatches = null;
//		if($paremetersDescription && preg_match_all('/([^\n]+)\n: _([^_]+)_ \*\*([^\*]+)\*\* (.+)\n\n/sU', $paremetersDescription, $paremetersMatches))
//		{
//			foreach($paremetersMatches[1] as $parameterIndex => $parameterName)
//			{
//				$parameterName = preg_replace('/[^\w]/', '', $parameterName);
//				$parameterRequirement = $paremetersMatches[2][$parameterIndex];
//				$parameterType = $paremetersMatches[3][$parameterIndex];
//				$parameterDescription = $paremetersMatches[4][$parameterIndex];
//				$parameterDescription = implode("\n	 * \t", explode("\n", $parameterDescription));
//				$docCommentParameters[] = "\$$parameterName $parameterType ($parameterRequirement) $parameterDescription";
//				$argument = "\$$parameterName";
//				$dataArguments[] = $parameterName;
//				if($parameterRequirement == 'Optional')
//					$argument .= ' = null';
//					
//				$arguments[] = $argument;
//			}
//		}
		
		// response codes table
		$expectedStatuses = array(200);
		$errorStatuses = array();
		$responseCodesMatches = null;
		if(preg_match('/<table summary="[^"]+ Response codes"[^>]*>.+<tbody>(.+)<\/tbody\s*>/sU', $actionContent, $responseCodesMatches))
		{
			$expectedStatuses = array();
			
			if(!preg_match_all('/<tr><td>(\d+)<\/td><td>([^<]+)<\/td><\/tr>/U', $responseCodesMatches[1], $responseCodeItemsMatches))
			{
				echo "Unable to find response codes items [$name.$actionName] href [$href]\n";
				exit(-1);
			}
			
			foreach($responseCodeItemsMatches[1] as $responseCodeIndex => $responseCode)
			{
				if($responseCode >= 200 && $responseCode < 300)
				{
					$expectedStatuses[] = $responseCode;
				}
				else 
				{
					$errorStatuses[] = $responseCode . ' => "' . $responseCodeItemsMatches[2][$responseCodeIndex] . '"';
				}
			}
		}
		else
		{
			echo "Unable to find response codes table [$name.$actionName] href [$href]\n";
		}
	
		$actionArguments = array();
		foreach($arguments as $argument)
		{
			if(isset($dataArguments[$argument]) && preg_match('/^array/', $dataArguments[$argument]))
			{
				$actionArguments[] = "array $$argument";
			}
			else 
			{
				$actionArguments[] = "$$argument";
			}
		}
		$actionArguments = implode(', ', $actionArguments);
			
		$class .= "
	/**
	 * $description
	 * ";
		
		foreach($docCommentParameters as $docCommentParameter)
		{
			$class .= "
	 * @param $docCommentParameter";
		}
		
		if($returnType)
		{
			if($isArray)
			{
				$class .= "
	 * @return array<$returnType>";
			}
			else 
			{
				$class .= "
	 * @return $returnType";
			}
		}
		
		$class .= "
	 * @see {@link http://www.activiti.org/userguide/#$href $description}
	 */
	public function $actionName($actionArguments)
	{
		\$data = array();";
		
		foreach($dataArguments as $dataArgument => $dataArgumentType)
		{
			$class .= "
		if(!is_null(\$$dataArgument))
			\$data['$dataArgument'] = \$$dataArgument;";
		}
		
		$expectedStatuses = implode(',', $expectedStatuses);
		$errorStatuses = implode(',', $errorStatuses);
		$class .= "
		
		return \$this->client->request(\"$url\", '$httpMethod', \$data, array($expectedStatuses), array($errorStatuses)" . ($returnType ? ", '$returnType'" : '') . ($isArray ? ', true' : '') . ");
	}
	";
	}
	
	$class .= "
}
";

	$requires = implode("\n", $requires);
	$php = "<?php

require_once(__DIR__ . '/../ActivitiClient.php');
require_once(__DIR__ . '/../ActivitiService.php');
$requires
	
$class
";

	file_put_contents(__DIR__ . "/client/services/$className.php", $php);
}

function generateActivitiRequestObject($className, $data)
{
	echo "Generating object: $className\n";
		
	$dataArguments = array();

	foreach($data as $argument => $argumentValue)
	{
		if(isset($dataArguments[$argument]))
			continue;
			
		$argument = lcfirst(str_replace(' ', '', ucwords(str_replace('.', ' ', $argument))));
			
		$argumentType = null;
		if(is_string($argumentValue))
		{
			$argumentType = 'string';
		}
		elseif(is_int($argumentValue))
		{
			$argumentType = 'int';
		}
		elseif(is_bool($argumentValue))
		{
			$argumentType = 'boolean';
		}
		elseif(is_array($argumentValue))
		{
			$objectClassName = $className . ucfirst(preg_replace(array('/ies$/', '/s$/'), array('y', ''), $argument));
			generateActivitiResponseObject($objectClassName, reset($argumentValue));
			$argumentType = "array<$objectClassName>";
		}
	
		$dataArguments[$argument] = $argumentType;
	}
	
	$requires = array();
	$class = "
class $className extends ActivitiRequestObject
{";
	
	foreach($dataArguments as $attributeName => $attributeType)
	{
		$matches = null;
		if(preg_match('/^(Activiti.+)$/', $attributeType, $matches) || preg_match('/^array<(Activiti.+)>$/', $attributeType, $matches))
			$requires[$attributeType] = "require_once(__DIR__ . '/" . $matches[1] . ".php');";
			
		$class .= "
	/**
	 * @var $attributeType
	 */
	public \$$attributeName;
";
	}
	
	foreach($dataArguments as $attributeName => $attributeType)
	{
		$methodName = str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($attributeName))));
		
		if(preg_match('/^Activiti/', $attributeType))
			$requires[$attributeType] = "require_once(__DIR__ . '/$attributeType.php');";
			
		$class .= "
	/**
	 * @param \$$attributeName $attributeType
	 */
	public function set{$methodName}(\$$attributeName)
	{
		\$this->$attributeName = \$$attributeName;
	}
";
	}
	
	$class .= "
}
";
	
	$requires = implode("\n", $requires);
	$php = "<?php

require_once(__DIR__ . '/../ActivitiRequestObject.php');
$requires
	
$class
";

	file_put_contents(__DIR__ . "/client/objects/$className.php", $php);
}

function generateActivitiResponseObject($className, $data)
{
	echo "Generating object: $className\n";
		
	$dataArguments = array();
	
	$requires = array();
	$class = "
class $className extends ActivitiResponseObject
{
	/* (non-PHPdoc)
	 * @see ActivitiResponseObject::getAttributes()
	 */
	protected function getAttributes()
	{
		return array_merge(parent::getAttributes(), array(";

	foreach($data as $argument => $argumentValue)
	{
		if(isset($dataArguments[$argument]))
			continue;
			
		$argument = lcfirst(str_replace(' ', '', ucwords(str_replace('.', ' ', $argument))));
			
		$argumentType = null;
		if(is_string($argumentValue))
		{
			$argumentType = 'string';
		}
		elseif(is_int($argumentValue))
		{
			$argumentType = 'int';
		}
		elseif(is_bool($argumentValue))
		{
			$argumentType = 'boolean';
		}
		elseif(is_array($argumentValue))
		{
			$objectClassName = $className . ucfirst(preg_replace(array('/ies$/', '/s$/'), array('y', ''), $argument));
			generateActivitiResponseObject($objectClassName, reset($argumentValue));
			$argumentType = "array<$objectClassName>";
		}
	
		$dataArguments[$argument] = $argumentType;
		
		$class .= "
			'$argument' => '$argumentType',";
	}
	
	$class .= "
		));
	}
	";
	
	foreach($dataArguments as $attributeName => $attributeType)
	{
		$matches = null;
		if(preg_match('/^(Activiti.+)$/', $attributeType, $matches) || preg_match('/^array<(Activiti.+)>$/', $attributeType, $matches))
			$requires[$attributeType] = "require_once(__DIR__ . '/" . $matches[1] . ".php');";
			
		$class .= "
	/**
	 * @var $attributeType
	 */
	protected \$$attributeName;
";
	}
	
	foreach($dataArguments as $attributeName => $attributeType)
	{
		$methodName = str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($attributeName))));
		
		if(preg_match('/^Activiti/', $attributeType))
			$requires[$attributeType] = "require_once(__DIR__ . '/$attributeType.php');";
			
		$class .= "
	/**
	 * @return $attributeType
	 */
	public function get{$methodName}()
	{
		return \$this->$attributeName;
	}
";
	}
	
	$class .= "
}
";
	
	$requires = implode("\n", $requires);
	$php = "<?php

require_once(__DIR__ . '/../ActivitiResponseObject.php');
$requires
	
$class
";

	file_put_contents(__DIR__ . "/client/objects/$className.php", $php);
}

