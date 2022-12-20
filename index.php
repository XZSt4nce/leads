<!doctype html>
<html>
	<head>
		<meta charset="utf-8"> <!-- Кодировка -->
		<meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Масштабирование страницы-->
		<title>Эффективность сотрудников</title> <!-- Название страницы -->
		<!-- Подключение CSS Bootstrap -->
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
	</head>
	<body style="margin: 50px;"> <!-- По края страницы будут отступы в 50 пикселей -->
		<h1 align=center>Эффективность сотрудников</h1> <!-- Заголовок первого уровня, выровненный посередине -->
		<!-- Форма для заполнения начальной и конечной даты выборки данных -->
		<form name="form" action="" method="get" style="margin: 20px 0px 5px 0px;">
		<label for="start_date">Выбрать данные с</div>
		<input id="start_date" name="start_date" type="date" value="<?php echo date('Y-m-d',strtotime("-1 days")); ?>"> <!-- По умолчанию начальной датой является предыдущий день -->
		<label for="end_date" style="display:inline;">по</div>
		<input id="end_date" name="end_date" type="date" value="<?php echo date("Y-m-d"); ?>"> <!-- По умолчанию конечной датой является текущий день -->
		<input type="submit" value="Найти" name="done">
		</form>
		<!-- Текст для вывода ошибки -->
		<div id="error" style="display: none; color: #FF0000"></div>
		<br><br>
		<!-- Таблица -->
		<div class="table-responsive">
		<table class="table table-sm">
			<?php
			// Подключение к БД
			$host = "localhost";
                        $user = "bitrix0";
                        $pass = str_replace("\n", "", fgets(fopen('db_pass.txt', 'r')));
                        $db = "sitemanager";
                        $link = mysqli_connect($host, $user, $pass, $db);
                        if (mysqli_connect_errno()) {
                                die('Ошибка соединения: ' . mysqli_connect_error());
                        }

			// Запрос на выборку имён пользовательских полей
			$query = "SELECT column_name FROM information_schema.columns WHERE table_name = 'efficiency_eval' AND column_name LIKE 'object_%';";
			$result = mysqli_query($link, $query)->fetch_all();
			// Определение полей (для выборки) и заголовков таблицы
			$fields = array("last_name", "name", "second_name", "user_id", "period", "modified", "processed", "junk", "avg_completion");
			$headers = array("#", "Фамилия", "Имя", "Отчество", "ID сотрудника", "Период", "Лидов обработано", "Лидов подписало договор", "Некачественных лидов", "Процент завершения");
			$objects = array();			

			foreach ($result as $field) {
				array_push($objects, $field[0]);
			}

			$fields = array_merge($fields, $objects);
			$headers = array_merge($headers, $objects);

			// Заголовки
			echo "
			<thead>
                                <tr>";
                        foreach ($headers as $field) { echo "<th scope='col' class='table-dark'>" . $field . "</th>"; }
                          echo "</tr>
                       	</thead>
                        <tbody>";	

			// Определение номера строки
			$num = 1;

			// Если форма была подтверждена
			if (isset($_GET['done'])) {
				echo "
				<script>
					// Выбор даты остаётся прежним и после подтверждения
					var start = document.getElementById('start_date');
					var end = document.getElementById('end_date');
                                        start.value = '" . $_GET['start_date'] . "';
					end.value = '" . $_GET['end_date'] . "';
				</script>
				";
				// Если одна из дат не заполнена, то отклонить запрос
				if (empty($_GET['start_date']) || empty($_GET['end_date'])) {
					echo "
					<script>
						var error = document.getElementById('error');
						error.innerHTML = 'Выберите даты';
						error.style.display = 'inline';
					</script> 
					";
				// Если начальная дата больше конечной, то отклонить запрос
				} elseif ($_GET['start_date'] > $_GET['end_date']) {
					echo "
                                        <script>
                                                var error = document.getElementById('error');
                                                error.innerHTML = 'Начальная дата должна быть раньше конечной';
                                                error.style.display = 'inline';
                                        </script>
                                        ";
				// В остальных случаях:
				} else {
					$timer = $_GET['start_date'];
					$end = $_GET['end_date'];
					while ($timer < $end) {
						// Запрос на выборку данных для выбранного промежутка времени
						$query = sprintf("SELECT %s FROM efficiency_eval JOIN b_user ON efficiency_eval.user_id = b_user.id WHERE period = '%s';", implode(', ', $fields), $timer);
                                		$result = mysqli_query($link, $query);

						if (mysqli_num_rows($result)==0) { $timer=date('Y-m-d', strtotime($timer . '+1 day')); continue; }

						echo "<tr><th class='table-dark' colspan=" . count($headers) . ">" . date('d F Y', strtotime($timer)) . "</th></tr>";
                                		while($row = $result->fetch_assoc()) {
                                        		echo "<tr> <th scope='row'>". $num . "</th>";
                                        		foreach ($fields as $field) {
                                        		        echo "<td>" . $row[$field] . "</td>";
                                        		}
                                        		echo "</tr>";
							$num++;
                                		}
						$timer=date('Y-m-d', strtotime($timer . '+1 day'));
					}
				}
			// Если форма не была подтверждена(вход без параметров; первый вход), то вывести данные с предыдущего дня по текущий
			} else {
				$timer = date('Y-m-d', strtotime("-1 day"));
				$end = date('Y-m-d');
				while ($timer <= $end) {
					$query = sprintf("SELECT %s FROM efficiency_eval JOIN b_user ON efficiency_eval.user_id = b_user.id WHERE period ='%s';", implode(', ', $fields), $timer);
					$result = mysqli_query($link, $query);
					if (mysqli_num_rows($result)==0) { $timer=date('Y-m-d', strtotime($timer . '+1 day')); continue; }

					echo "<tr><th class='table-dark' colspan=" . count($headers) . ">" . date('d F Y', strtotime($timer)) . "</th></tr>";
					while ($row = $result->fetch_assoc()) {
						echo "<tr><th scope='row'>". $num . "</th>";
						foreach ($fields as $field) {
							echo "<td>" . $row[$field] . "</td>";
						}
						echo "</tr>";
						$num++;
					}
					$timer=date('Y-m-d', strtotime($timer . '+1 day'));
				}
			}
			?>
			</tbody>
		</table>
		</div>
	</body>
</html>
