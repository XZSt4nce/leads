#!/usr/bin/php
<?php
declare(strict_types=1);
require_once __DIR__ . '/vendor/autoload.php';

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\HttpClient\HttpClient;

$log = new Logger('name');
$log->pushHandler(new StreamHandler('b24-api-client-debug.log', Logger::DEBUG));

$client = HttpClient::create(["verify_peer"=>false, "verify_host"=>false]);
$key = str_replace("\n", "", fgets(fopen('rest_api_key.txt', 'r')));
$credentials = new \Bitrix24\SDK\Core\Credentials\Credentials(
    new \Bitrix24\SDK\Core\Credentials\WebhookUrl($key),
    null,
    null,
    null
);

$apiClient = new \Bitrix24\SDK\Core\ApiClient($credentials, $client, $log);

$leads = array();
$tmp = $apiClient->getResponse('crm.lead.list', ['start'=>$next, 'select'=>["ID", "STATUS_ID"], 'filter'=>["!STATUS_ID"=>"PROCESSED", "!STATUS_ID"=>"JUNK", "!STATUS_ID"=>"CONVERTED"]]);
$tmp = json_decode($tmp->getContent(), true)['total'];
$k = (int)($tmp/50-1);
$k = rand(0, $k);

$result = $apiClient->getResponse('crm.lead.list', ['start'=>50*$k, 'select'=>["ID", "STATUS_ID"], 'filter'=>["!STATUS_ID"=>"PROCESSED", "!STATUS_ID"=>"JUNK", "!STATUS_ID"=>"CONVERTED"]]);
$result = json_decode($result->getContent(), true);
if ($result['total'] < 50) { 
	echo "Недостаточно лидов"; exit(); 
} else {
	foreach ($result['result'] as $record) {
		array_push($leads, $record);
	}
}

foreach ($leads as $lead) {
	switch ($lead['STATUS_ID']) {
		case 'NEW':
			$users = $apiClient->getResponse('user.get', ['select'=>'ID']);
			$users = json_decode($users->getContent(), true)['result'];
			$assign = $users[array_rand($users, 1)]['ID'];
			$opportunity = rand(1000, 100000);
			$fields = array("ASSIGNED_BY_ID"=>$assign, "OPPORTUNITY"=>$opportunity, "STATUS_ID"=>"IN_PROCESS", "UF_CRM_1671268270567"=>rand(1, 75));
			$apiClient->getResponse('crm.lead.update', ['id'=>$lead['ID'], 'fields'=>$fields]);
			break;
		case 'IN_PROCESS':
			$statuses = array("UC_8R263T", "UC_JEL0AG", "UC_XYLS6I");
			$status = $statuses[array_rand($statuses, 1)];
			$fields = array("STATUS_ID"=>$status, "UF_CRM_1671268270567"=>rand(76, 99));
			$apiClient->getResponse('crm.lead.update', ['id'=>$lead['ID'], 'fields'=>$fields]);
                        break;
		default:
			if (rand(0, 1)) {
				$fields = array("STATUS_ID"=>"PROCESSED", "UF_CRM_1671268270567"=>100);
			} else {
				$fields = array("STATUS_ID"=>"JUNK");
			}
			$apiClient->getResponse('crm.lead.update', ['id'=>$lead['ID'], 'fields'=>$fields]);
	}
}
