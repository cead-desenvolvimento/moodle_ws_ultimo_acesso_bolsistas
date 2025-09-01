<?php
	$mysql_host = '10.9.8.28';
	$mysql_user = 'cead';
	$mysql_pass = 'F4ktrd8CyevjkBR6BFy4Cjyh';
	$mysql_db = 'cead';

	function get_ultimo_id_frequencia_preenchido() {
		if (empty($GLOBALS['link'])) return null;

		$query = "SELECT id_data_frequencia FROM fi_frequencia ORDER BY id_data_frequencia DESC LIMIT 1";
		$result = mysqli_query($GLOBALS['link'], $query);

		if (mysqli_num_rows($result) == 1) return mysqli_fetch_array($result, MYSQLI_NUM)[0];
		else return null;
	}

	function get_data_fim_frequencia($ultimo_id_frequencia_preenchido) {
		if (empty($GLOBALS['link'])) return null;

		$query = "SELECT fim FROM fi_data_frequencia WHERE id_data_frequencia = ".$ultimo_id_frequencia_preenchido;
		$result = mysqli_query($GLOBALS['link'], $query);

		if (mysqli_num_rows($result) == 1) return mysqli_fetch_array($result, MYSQLI_NUM)[0];
		else return null;
	}

	$link = mysqli_connect($mysql_host, $mysql_user, $mysql_pass, $mysql_db);
	if (!$link) exit('Erro de conexao ('.mysqli_connect_errno().') '.mysqli_connect_error().'\n');

	$data_fim_frequencia = get_data_fim_frequencia(get_ultimo_id_frequencia_preenchido());

	if ($data_fim_frequencia == date('Y-m-d',strtotime("-1 days")))
		echo date_format(date_create($data_fim_frequencia), 'm/Y');
	else
		echo 0;

	mysqli_close($link);
?>
