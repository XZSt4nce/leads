#!/usr/bin/php
<?php
declare(strict_types=1);
require_once __DIR__ . '/vendor/autoload.php';

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\HttpClient\HttpClient;

$log = new Logger('name');
$log->pushHandler(new StreamHandler('/var/log/b24-api-client-debug.log', Logger::DEBUG));

$client = HttpClient::create(["verify_peer"=>false, "verify_host"=>false]);
$key = str_replace("\n", "", fgets(fopen('/var/rest/rest_api_key.txt', 'r')));
$credentials = new \Bitrix24\SDK\Core\Credentials\Credentials(
    new \Bitrix24\SDK\Core\Credentials\WebhookUrl($key),
    null,
    null,
    null
);

$apiClient = new \Bitrix24\SDK\Core\ApiClient($credentials, $client, $log);

do {
	// Запрос информации о лидах
	$result = $apiClient->getResponse('crm.lead.list', ['start'=>0]);
	$result = json_decode($result->getContent(), true);
	// Определение переменной для хранения количества лидов
	$total = $result['total'];
	// Цикл для прохождения по всем лидам
	foreach($result['result'] as $record) {
		// Запрос на удаление лида
		$apiClient->getResponse('crm.lead.delete', ['id'=>$record['ID']]);
	}
} while ($total != 0); // Выполнять цикл до тех пор, пока количество лидов не достигнет нуля
