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

// Запрос на получение информации о сотрудниках
$result = $apiClient->getResponse('user.get');
$result = json_decode($result->getContent(), true)['result'];
// Создание пустого массива сотрудников
$users = array();
// Цикл для прохождения по результатам запроса, идентификатор сотрудника заносится в массив $users
foreach ($result as $record) { array_push($users, $record['ID']); }

// Получение идентификаторов объектов интереса (для проверки на появление новых объектов)
$products = $apiClient->getResponse('crm.lead.fields');
$products = json_decode($products->getContent(), true)['result'];
$products = $products["UF_CRM_1671268152606"]['items'];
$prod_ids = array();
foreach ($products as $product) { array_push($prod_ids, $product['ID']); }

// Установление параметров для подключения к базе данных
$host = 'localhost';
$username = 'bitrix0';
$password = str_replace("\n", "", fgets(fopen('db_pass.txt', 'r')));
$db = 'sitemanager';

// Подключение
$link = mysqli_connect($host, $username, $password, $db);
// Проверка успешности подключения
if (mysqli_connect_errno()) {
    die('Ошибка соединения: ' . mysqli_connect_error());
}

// Проверка на появление новых объектов интереса
foreach ($prod_ids as $product) {
        $query = sprintf("SELECT SUM('object_%d' IN (column_name)) AS res FROM information_schema.columns WHERE table_name = 'efficiency_eval';", $product);
        $result = mysqli_query($link, $query)->fetch_assoc()['res'];
        if(!$result) {
		$query = sprintf("ALTER TABLE efficiency_eval ADD COLUMN object_%d DECIMAL(5, 2)", $product);
		mysqli_query($link, $query);
	}
}

// Цикл для прохождения по пользователям
foreach ($users as $user) {
	// Запрос для вставки данных в таблицу efficiency_eval
	$query = sprintf("
		INSERT INTO efficiency_eval(user_id, period, modified, processed, junk, avg_completion)
		SELECT 
			%d, -- Идентификатор пользователя
			CURDATE(), -- Период (день подсчёта)
			SUM(STATUS_ID <> 'NEW' AND STATUS_ID <> 'IN_PROCESS' OR OPPORTUNITY <> 0 
				OR UF_CRM_1671268152606 IS NOT NULL OR STATUS_SEMANTIC_ID = 'F'), -- Количество обработанных лидов
			SUM(STATUS_ID='PROCESSED'), -- Количество лидов со статусом 'Подписан договор'
			SUM(STATUS_ID='JUNK'), -- Количество лидов со статусом 'Некачественный лид'
			ROUND(AVG(UF_CRM_1671268270567), 2) -- Средний процент завершения
		FROM 
			b_crm_lead 
			LEFT JOIN b_uts_crm_lead ON b_uts_crm_lead.value_id = b_crm_lead.id 
		WHERE assigned_by_id = %d;", 
		$user, $user);
	mysqli_query($link, $query);
	
	// Заполнение полей распределения по объектам интереса
	foreach ($prod_ids as $product) {
		$query = sprintf("
			UPDATE efficiency_eval 
			SET object_%d = (
				SELECT ROUND(SUM(UF_CRM_1671268152606=%d)/COUNT(*)*100,2) 
				FROM 
					b_crm_lead 
					LEFT JOIN b_uts_crm_lead ON b_crm_lead.id = b_uts_crm_lead.value_id 
				WHERE assigned_by_id=%d) 
			WHERE 
				user_id=%d 
				AND period=CURDATE();",
		$product, $product, $user, $user);
		mysqli_query($link, $query);
	}
}

mysqli_close($link);
