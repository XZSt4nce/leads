<!doctype html>
<html>
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>Эффективность сотрудников</title>
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
	</head>
	<body style="margin: 50px;">
		<h1 align=center>Эффективность сотрудников</h1>
		<form name="form" action="" method="get" style="margin: 20px 0px 5px 0px;">
		<label for="start_date">Выбрать данные с</div>
		<input id="start_date" name="start_date" type="date" value="<?php echo date('Y-m-d',strtotime("-1 days")); ?>">
		<label for="end_date" style="display:inline;">по</div>
		<input id="end_date" name="end_date" type="date" value="<?php echo date("Y-m-d"); ?>">
		<input type="submit" value="Найти" name="done">
		</form>
		<div id="error" style="display: none; color: #FF0000"></div>
		<br>
	
		<table class="table">
			<?php
			$host = "localhost";
                        $user = "bitrix0";
                        $pass = str_replace("\n", "", fgets(fopen('db_pass.txt', 'r')));
                        $db = "sitemanager";
                        $link = mysqli_connect($host, $user, $pass, $db);
                        if (mysqli_connect_errno()) {
                                die('Ошибка соединения: ' . mysqli_connect_error());
                        }

			$query = "SELECT column_name FROM information_schema.columns WHERE table_name = 'efficiency_eval' AND column_name LIKE 'object_%';";
			$result = mysqli_query($link, $query)->fetch_all();
			$fields = array("last_name", "name", "second_name", "user_id", "period", "modified", "processed", "junk", "avg_completion");
			$headers = array("Фамилия", "Имя", "Отчество", "ID сотрудника", "Период", "Лидов обработано", "Лидов подписало договор", "Некачественных лидов", "Процент завершения");
			$objects = array();			

			foreach ($result as $field) {
				array_push($objects, $field[0]);
			}

			$fields = array_merge($fields, $objects);
			$headers = array_merge($headers, $objects);

			echo "
			<thead>
                                <tr>";
                        foreach ($headers as $field) { echo "<th>" . $field . "</th>"; }
                          echo "</tr>
                       	</thead>
                        <tbody>";	

			if (isset($_GET['done'])) {
				echo "
				<script>
					var start = document.getElementById('start_date');
					var end = document.getElementById('end_date');
                                        start.value = '" . $_GET['start_date'] . "';
					end.value = '" . $_GET['end_date'] . "';
				</script>
				";
				if (empty($_GET['start_date']) || empty($_GET['end_date'])) {
					echo "
					<script>
						var error = document.getElementById('error');
						error.innerHTML = 'Выберите даты';
						error.style.display = 'inline';
					</script> 
					";
				} elseif ($_GET['start_date'] > $_GET['end_date']) {
					echo "
                                        <script>
                                                var error = document.getElementById('error');
                                                error.innerHTML = 'Начальная дата должна быть раньше конечной';
                                                error.style.display = 'inline';
                                        </script>
                                        ";
				} else {
					echo "
                                        <script>
                                                var x = document.getElementById('error');
                                                x.style.display = 'none';
                                        </script>
                                        ";
					$query = sprintf("SELECT %s FROM efficiency_eval JOIN b_user ON efficiency_eval.user_id = b_user.id WHERE period >= '%s' AND period <= '%s';", implode(', ', $fields), $_GET['start_date'], $_GET['end_date']);
                                	$result = mysqli_query($link, $query);

                                	while($row = $result->fetch_assoc()) {
                                        	echo "<tr>";
                                        	foreach ($fields as $field) {
                                        	        echo "<td>" . $row[$field] . "</td>";
                                        	}
                                        	echo "</tr>";
                                	}
				}
			} else {
				$query = sprintf("SELECT %s FROM efficiency_eval JOIN b_user ON efficiency_eval.user_id = b_user.id WHERE period >='%s' AND period <= '%s';", implode(', ', $fields), date('Y-m-d',strtotime("-1 days")), date('Y-m-d'));
				$result = mysqli_query($link, $query);
				while($row = $result->fetch_assoc()) {
					echo "<tr>";
					foreach ($fields as $field) {
						echo "<td>" . $row[$field] . "</td>";
					}
					echo "</tr>";
				}
			}
			?>
			</tbody>
		</table>	
	</body>
</html>
