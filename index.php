<!doctype html>
<html>
	<head>
		<meta charset="utf-8"> <!-- Кодировка -->
		<meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Масштабирование страницы-->
		<title>Эффективность сотрудников</title> <!-- Название страницы -->
		<!-- Подключение CSS Bootstrap -->
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
	</head>
	<body>
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
		<table class="table table-sm table-striped table-responsive">
			<?php
			// Подключение к БД
			$settings = include '/home/bitrix/www/bitrix/.settings.php';
			$settings = $settings['connections']['value']['default'];
                        $link = mysqli_connect($settings['host'], $settings['login'], $settings['password'], $settings['database']);
                        if (mysqli_connect_errno()) {
                                die('Ошибка соединения: ' . mysqli_connect_error());
                        }

			// Запрос на выборку имён пользовательских полей
			$query = "SELECT column_name FROM information_schema.columns WHERE table_name = 'efficiency_eval' AND column_name LIKE 'object_%';";
			$result = mysqli_query($link, $query)->fetch_all();
			// Определение полей (для выборки) и заголовков таблицы
			$fields = array("last_name", "name", "second_name", "user_id", "modified", "processed", "junk", "avg_completion");
			$headers = array("#", "Фамилия", "Имя", "Отчество", "ID сотрудника", "Лидов обработано", "Лидов подписало договор", "Некачественных лидов", "Процент завершения");
			$objects = array();
			$objects_headers = array();

			foreach ($result as $field) {
				array_push($objects, $field[0]);
			}

			foreach ($objects as $object) {
				$object = (int) filter_var($object, FILTER_SANITIZE_NUMBER_INT);
				$query = sprintf("SELECT value FROM b_user_field_enum WHERE id = %d", $object);
				array_push($objects_headers, mysqli_query($link, $query)->fetch_row()[0] . ", %");
			}

			$fields = array_merge($fields, $objects);
			$headers = array_merge($headers, $objects_headers);

			// Заголовки
			echo "
			<thead class='table-dark'>
                                <tr>";
                        foreach ($headers as $field) { echo "<th class'header' scope='col'>" . $field . "</th>"; }
                          echo "</tr>
                       	</thead>
                        <tbody>";	

			// Определение номера строки
			$num = 1;

			// Подготовка запроса (защита GET-запроса от SQL-Injection)
			$efficiency = $link->prepare(sprintf("SELECT %s FROM efficiency_eval JOIN b_user ON efficiency_eval.user_id = b_user.id WHERE period = ?;", implode(', ', $fields)));

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
				// Если начальная дата больше конечной, то отклонить запрос
				if ($_GET['start_date'] > $_GET['end_date'] && !empty($_GET['end_date'])) {
					echo "
                                        <script>
                                                var error = document.getElementById('error');
                                                error.innerHTML = 'Начальная дата должна быть раньше конечной';
                                                error.style.display = 'inline';
                                        </script>
                                        ";
				// Если не заполнены обе даты
                                } elseif (empty($_GET['start_date']) && empty($_GET['end_date'])) {
                                        echo "
                                        <script>
                                                var error = document.getElementById('error');
                                                error.innerHTML = 'Выберите дату';
                                                error.style.display = 'inline';
                                        </script>
                                        ";
				// Если не заполнена только начальная дата
				} elseif (empty($_GET['start_date'])) {
					$efficiency->bind_param('s', $_GET['end_date']);
					$efficiency->execute();
					$result = $efficiency->get_result();

					echo "<tr><th class='table-dark' style='padding-left: 30px' colspan=" . count($headers) . ">" . date('d F Y', strtotime($_GET['end_date'])) . "</th></tr>";
					while($row = $result->fetch_assoc()) {
                                            echo "<tr> <th scope='row' class='table-primary'>". $num . "</th>";
                                            foreach ($fields as $field) {
                                                echo "<td>" . $row[$field] . "</td>";
                                            }
                                            echo "</tr>";
                                            $num++;
                                        }
				// Если не заполнена только конечная дата
				} elseif (empty($_GET['end_date'])) {
					$efficiency->bind_param('s', $_GET['start_date']);
                                        $efficiency->execute();
                                        $result = $efficiency->get_result();

                                        echo "<tr><th class='table-dark' style='padding-left: 30px' colspan=" . count($headers) . ">" . date('d F Y', strtotime($_GET['start_date'])) . "</th></tr>";
					while($row = $result->fetch_assoc()) {
                                            echo "<tr> <th scope='row' class='table-primary'>". $num . "</th>";
                                            foreach ($fields as $field) {
                                                echo "<td>" . $row[$field] . "</td>";
                                            }
                                            echo "</tr>";
                                            $num++;
                                        }
				// В остальных случаях:
				} else {
					$timer = $_GET['start_date'];
					$end = $_GET['end_date'];

					while ($timer <= $end) {
						// Запрос на выборку данных для выбранного промежутка времени
						$efficiency->bind_param('s', $timer);
						$efficiency->execute();
                                		$result = $efficiency->get_result();

						// Если для даты нет данных, то перейти к следующей дате
						if (mysqli_num_rows($result)==0) { $timer=date('Y-m-d', strtotime($timer . '+1 day')); continue; }
						echo "<tr><th class='table-dark' style='padding-left: 30px' colspan=" . count($headers) . ">" . date('d F Y', strtotime($timer)) . "</th></tr>";
                                		while($row = $result->fetch_assoc()) {
                                        		echo "<tr> <th scope='row' class='table-primary'>". $num . "</th>";
                                        		foreach ($fields as $field) {
                                        		        echo "<td>" . $row[$field] . "</td>";
                                        		}
                                        		echo "</tr>";
							$num++;
                                		}
						$timer=date('Y-m-d', strtotime($timer . '+1 day'));
						$num = 1;
					}
				}
			// Если форма не была подтверждена(вход без параметров; первый вход), то вывести данные с предыдущего дня по текущий
			} else {
				$timer = date('Y-m-d', strtotime("-1 day"));
				$end = date('Y-m-d');
				while ($timer <= $end) {
					$efficiency->bind_param('s', $timer);
					$efficiency->execute();
					$result = $efficiency->get_result();

					if (mysqli_num_rows($result)==0) { $timer=date('Y-m-d', strtotime($timer . '+1 day')); continue; }
					echo "<tr><th class='table-dark' style='padding-left: 30px' colspan=" . count($headers) . ">" . date('d F Y', strtotime($timer)) . "</th></tr>";
					while ($row = $result->fetch_assoc()) {
						echo "<tr><th scope='row' class='table-primary'>". $num . "</th>";
						foreach ($fields as $field) {
							echo "<td>" . $row[$field] . "</td>";
						}
						echo "</tr>";
						$num++;
					}
					$timer=date('Y-m-d', strtotime($timer . '+1 day'));
					$num = 1;
				}
			}
			?>
			</tbody>
		</table>
	</body>
</html>
