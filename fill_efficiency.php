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

// Запрос на получение информации о сотрудниках
$result = $apiClient->getResponse('user.get');
$result = json_decode($result->getContent(), true)['result'];
// Создание пустого массива сотрудников
$users = array();
// Цикл для прохождения по результатам запроса, идентификатор сотрудника заносится в массив $users
foreach ($result as $record) { array_push($users, $record['ID']); }
// Цикл для прохождения по пользователям
foreach ($users as $user) {
	// Запрос для вставки данных в таблицу efficiency_eval
	$query = sprintf("
		INSERT INTO efficiency_eval(user_id, period, modified, processed, junk, distribution_percentage, avg_completion)
		SELECT 
			%d, -- Идентификатор пользователя
			subdate(NOW(), 1), -- Период (день подсчёта)
			SUM(STATUS_ID <> 'NEW' AND STATUS_ID <> 'IN_PROCESS' OR OPPORTUNITY <> 0 
				OR UF_CRM_1671268152606 IS NOT NULL OR STATUS_SEMANTIC_ID = 'F'), -- Количество обработанных лидов
			SUM(STATUS_ID='PROCESSED'), -- Количество лидов со статусом 'Подписан договор'
			SUM(STATUS_ID='JUNK'), -- Количество лидов со статусом 'Некачественный лид'
			ROUND(SUM(UF_CRM_1671268152606 IS NULL)/COUNT(*) * 100, 2),
			ROUND(AVG(UF_CRM_1671268270567), 2) -- Средний процент завершения
		FROM 
			b_crm_lead 
			LEFT JOIN b_uts_crm_lead ON b_uts_crm_lead.value_id = b_crm_lead.id 
		WHERE assigned_by_id = %d;", 
		$user, $user);
	mysqli_query($link, $query);
}

mysqli_close($link);
