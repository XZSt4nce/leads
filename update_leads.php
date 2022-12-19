#!/usr/bin/php
<?php
declare(strict_types=1);
require_once __DIR__ . '/vendor/autoload.php';

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\HttpClient\HttpClient;

$log = new Logger('name');
$log->pushHandler(new StreamHandler('b24-api-client-debug.log', Logger::DEBUG));

$client = HttpClient::create(['verify_peer'=>false, 'verify_host'=>false]);
$key = str_replace("\n", "", fgets(fopen('rest_api_key.txt', 'r')));
$credentials = new \Bitrix24\SDK\Core\Credentials\Credentials(
    new \Bitrix24\SDK\Core\Credentials\WebhookUrl($key),
    null,
    null,
    null
);

$apiClient = new \Bitrix24\SDK\Core\ApiClient($credentials, $client, $log);

// Создание пустого массива лидов
$leads = array();
// Запрос идентификатора и статуса лидов, статус которых не "PROCESSED", не "JUNK" и не "CONVERTED"
$tmp = $apiClient->getResponse('crm.lead.list', 
							   ['start'=>$next, 'select'=>['ID', 'STATUS_ID'], 
							   'filter'=>['!STATUS_ID'=>'PROCESSED', '!STATUS_ID'=>'JUNK', '!STATUS_ID'=>'CONVERTED']]);
// Сохранить количество лидов из выборки								
$tmp = json_decode($tmp->getContent(), true)['total'];
// Определение случайной страницы лидов
$k = (int)($tmp/50-1);
$k = rand(0, $k);

// Запрос идентификатора, статуса, семантического статуса, объекта интереса, предполагаемой суммы и процента завершения лидов,
// статус которых не "PROCESSED", не "JUNK" и не "CONVERTED"
$result = $apiClient->getResponse('crm.lead.list', ['start'=>50*$k, 
								  'select'=>['ID', 'STATUS_ID', 'STATUS_SEMANTIC_ID', 
										   // UF_CRM_1671268152606 – пользовательское поле, которое означает объект интереса
											 'UF_CRM_1671268152606', 'OPPORTUNITY', "UF_CRM_1671268270567"], 
								  'filter'=>["!STATUS_ID"=>"PROCESSED", "!STATUS_ID"=>"JUNK", "!STATUS_ID"=>"CONVERTED"]]);
$result = json_decode($result->getContent(), true);

// По ТЗ программа должна изменять 50 случайных лидов. Если их меньше, то прервать выполнение программы
if ($result['total'] < 50) { 
	echo "Недостаточно лидов"; 
	exit(); 
} else {
	// Иначе занести всю выбранную информацию по лидам в массив $leads
	foreach ($result['result'] as $record) {
		array_push($leads, $record);
	}
}

foreach ($leads as $lead) {
	// Случайный выбор объекта интереса, если он не установлен
	if (rand(0, 1) && is_null($lead['UF_CRM_1671268152606'])) {
		$products = $apiClient->getResponse('crm.lead.fields');
		$products = json_decode($products->getContent(), true)['result'];
		$products = $products["UF_CRM_1671268152606"]['items'];
		$prod_ids = array();
		foreach ($products as $product) { array_push($prod_ids, $product['ID']); }
		$product = $prod_ids[array_rand($prod_ids, 1)];
    }
    else { $product = null; }
	// Разные сценарии в зависимости от текущего статуса лида
	switch ($lead['STATUS_ID']) {
		// Если не обработан
		case 'NEW':
			// Присваивание ответственности за лида случайному сотруднику
			$users = $apiClient->getResponse('user.get', ['select'=>'ID']);
			$users = json_decode($users->getContent(), true)['result'];
			$assign = $users[array_rand($users, 1)]['ID'];
			// Либо устанавливается случайная предполагаемая сумма, либо нет
			if (rand(0, 1)) {
				$opportunity = rand(1000, 100000);
				$completion = rand(1, 50);
			} else {
				$opportunity = null;
				$completion = rand(1, 30);
			}

			$fields = array("ASSIGNED_BY_ID"=>$assign, "OPPORTUNITY"=>$opportunity, "STATUS_ID"=>"IN_PROCESS", 
							"UF_CRM_1671268270567"=>$completion, 'UF_CRM_1671268152606'=>$product);
			// Запрос на изменение лида
			$apiClient->getResponse('crm.lead.update', ['id'=>$lead['ID'], 'fields'=>$fields]);
			break;
		// Если в работе
		case 'IN_PROCESS':
			$semantic = 'p';
			// Если предполагаемая цена не установлена, то установить
			if (is_null($lead['OPPORTUNITY'])) {
				$status = "IN_PROCESS";
					$completion = rand(31, 50);
					$opportunity = rand(1000, 100000);
			} else {
				$opportunity = $lead['OPPORTUNITY'];
				// Либо статус лида переходит в "Думает", "Назначена встреча" или "Ожидание решения",
				// либо отмечается неуспешная обработка лида
				if (rand(0, 1)) {
					$statuses = array("UC_8R263T", "UC_JEL0AG", "UC_XYLS6I");
					$status = $statuses[array_rand($statuses, 1)];
					$completion = rand(51, 99);
				} else {
					$status = "IN_PROCESS";
					$completion = $lead["UF_CRM_1671268270567"];
					$semantic = 'f';
				}
			}

			$fields = array('STATUS_ID'=>$status, 'UF_CRM_1671268270567'=>$completion, 'STATUS_SEMANTIC_ID'=>$semantic, 
							'UF_CRM_1671268152606'=>$product, 'OPPORTUNITY'=>$opportunity);
			$apiClient->getResponse('crm.lead.update', ['id'=>$lead['ID'], 'fields'=>$fields]);
            break;
		// Если "Думает", "Назначена встреча" или "Ожидание решения"
		default:
			// Либо статус лида переходит в "Подписание договора", либо в "Некачественный лид"
			// В случае подписания договора, установить процент завершения равным 100%
			if (rand(0, 1)) {
				$fields = array("STATUS_ID"=>"PROCESSED", "UF_CRM_1671268270567"=>100, 'UF_CRM_1671268152606'=>$product);
			} else {
				$fields = array("STATUS_ID"=>"JUNK", 'UF_CRM_1671268152606'=>$product);
			}
			$apiClient->getResponse('crm.lead.update', ['id'=>$lead['ID'], 'fields'=>$fields]);
	}
}
