<?php
	$pgsql_host = '10.9.8.22';
	$pgsql_user = 'sistemascead';
	$pgsql_pass = 'oF$`760q:7n3N24&^s]..2{Ck'; 
	$pgsql_db = 'sistemascead';

	function get_ultimo_fi_datafrequencia_id() {
		if (empty($GLOBALS['link'])) return null;

		$query = "SELECT fi_datafrequencia_id FROM sistemascead.fi_frequencia ORDER BY id DESC LIMIT 1";
		$result = pg_query($GLOBALS['link'], $query);

		if (pg_num_rows($result) == 1) return pg_fetch_result($result, 0, 'fi_datafrequencia_id');
		else return null;
	}

	function get_data_fim_frequencia($ultimo_id_frequencia_id) {
		if (empty($GLOBALS['link'])) return null;

		$query = "SELECT data_fim FROM sistemascead.fi_datafrequencia WHERE id = ".intval($ultimo_id_frequencia_id);
		$result = pg_query($GLOBALS['link'], $query);

		if (pg_num_rows($result) == 1) return pg_fetch_result($result, 0, 'data_fim');
		else return null;
	}

	$link = pg_connect("host=$pgsql_host dbname=$pgsql_db user=$pgsql_user password=$pgsql_pass");
	if (!$link) exit('Erro de conexão com PostgreSQL: ' .pg_last_error(). '\n');

	$data_fim_frequencia = get_data_fim_frequencia(get_ultimo_fi_datafrequencia_id());

	if ($data_fim_frequencia == date('Y-m-d',strtotime("-1 days")))
		echo date_format(date_create($data_fim_frequencia), 'm/Y');
	else
		echo 0;

	pg_close($link);
?>
