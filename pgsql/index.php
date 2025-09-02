<?php
	/**
		Seguir http://www.spanidis.eu/?p=27 para tutorial
		Tem tambem documentacao do Moodle online
	*/
	/*
		Argumento: mostraedital
		Se passar index.php mostraedital imprime a qual
		edital o bolsista pertence
	*/

	function is_moodle_online() {
		try {
			file_get_contents($GLOBALS['mdl_domainname']);
			return true;	
		} catch(Exception $e) {
			return false;
		}
	}

	function get_ultimo_fi_datafrequencia_id() {
		if (empty($GLOBALS['link'])) return null;

		$query = "SELECT fi_datafrequencia_id FROM sistemascead.fi_frequencia ORDER BY id DESC LIMIT 1";
		$result = pg_query($GLOBALS['link'], $query);

		if (pg_num_rows($result) == 1) return pg_fetch_result($result, 0, 'fi_datafrequencia_id');
		else return null;
	}

	// Consulta ao webservice do Moodle
	function get_ultimo_acesso_moodle($field, $id) {
		try {
			$serverurl = $GLOBALS['mdl_domainname'].'/webservice/rest/server.php?wstoken='.
				$GLOBALS['mdl_token'].'&wsfunction='.$GLOBALS['mdl_functionname'].
				'&field='.$field.'&values[0]='.$id.
				'&moodlewsrestformat='.$GLOBALS['mdl_restformat'];

			return file_get_contents($serverurl);
		} catch (Exception $e) {
			return $e;
		}
	}

	function insert_into_fi_frequencia_moodle($users, $cm_pessoa_id) {
		if (empty($users)) return null;
		if (empty($cm_pessoa_id)) return null;
	
		$json = json_decode($users);
		foreach ($json as $usuario_moodle) {
			$query = "INSERT INTO fi_frequencia_moodle (fi_datafrequencia_id, cm_pessoa_id, moodle_id";

			if ($usuario_moodle->lastaccess > 0) {
				$query .= ", ultimo_acesso) VALUES (";
				$query .= $GLOBALS['ultimo_fi_datafrequencia_id'].", ".$cm_pessoa_id.", '".$usuario_moodle->username."', TO_TIMESTAMP(".$usuario_moodle->lastaccess."))";
			} else {
				$query .= ") VALUES (";
				$query .= $GLOBALS['ultimo_fi_datafrequencia_id'].", ".$cm_pessoa_id.", '".$usuario_moodle->username."')";
			}

			if (!pg_query($GLOBALS['link'], $query)) echo pg_last_error($GLOBALS['link'])."\n";
		}
	}

	// Retorna os bolsistas autorizados num array(cpf, cm_pessoa_id)
	function get_bolsistas_autorizados($id_data_frequencia) {
		if (empty($id_data_frequencia)) return null;
		$rows = array();

		$query = "SELECT cm_pessoa.cpf, fi_frequencia.cm_pessoa_id FROM sistemascead.fi_frequencia ";
		$query .= "INNER JOIN sistemascead.cm_pessoa ON fi_frequencia.cm_pessoa_id = cm_pessoa.id ";
		$query .= "WHERE fi_frequencia.fi_datafrequencia_id = ";
		$query .= intval($id_data_frequencia);

		$result = pg_query($GLOBALS['link'], $query);

		if (pg_num_rows($result) == 0) return null;
		
		while ($row = pg_fetch_row($result)) {
			$rows[] = array($row[0], $row[1]);
		}
		return $rows;
	}

	// So e' chamada se passar argumento mostraedital na chamada do arquivo
	function get_edital_bolsista($id_bolsista) {
		if (empty($id_bolsista)) return null;

		$query = "SELECT sistemascead.ed_edital.numero, sistemascead.ed_edital.ano, sistemascead.ed_edital.descricao ";
		$query .= "FROM sistemascead.fi_pessoa_ficha ";
		$query .= "INNER JOIN sistemascead.ed_edital ON sistemascead.fi_pessoa_ficha.ed_edital_id = sistemascead.ed_edital.id ";
		$query .= "WHERE sistemascead.fi_pessoa_ficha.cm_pessoa_id = ";
		$query .= intval($id_bolsista);
		$query .= " AND sistemascead.fi_pessoa_ficha.data_fim_vinculacao IS NULL ";
		$query .= "ORDER BY sistemascead.fi_pessoa_ficha.id DESC LIMIT 1";

		$result = pg_query($GLOBALS['link'], $query);

		if (pg_num_rows($result) == 1) return pg_fetch_row($result);
		else return null;
	}

	function get_bolsistas_autorizados_relatorio($id_data_frequencia) {
		if (empty($id_data_frequencia)) return null;

		$query = "SELECT id, curso_nome, cpf, pessoa_nome, funcao ";
		$query .= "FROM (";
		$query .= "    SELECT DISTINCT ON (sistemascead.cm_pessoa.id) ";
		$query .= "           sistemascead.cm_pessoa.id, ";
		$query .= "           sistemascead.cm_pessoa.cpf, ";
		$query .= "           sistemascead.cm_pessoa.nome AS pessoa_nome, ";
		$query .= "           sistemascead.ac_curso.nome AS curso_nome, ";
		$query .= "           sistemascead.fi_funcao_bolsista.funcao ";
		$query .= "    FROM sistemascead.fi_frequencia ";
		$query .= "    INNER JOIN sistemascead.fi_pessoa_ficha ";
		$query .= "        ON sistemascead.fi_frequencia.cm_pessoa_id = sistemascead.fi_pessoa_ficha.cm_pessoa_id ";
		$query .= "    INNER JOIN sistemascead.ac_curso_oferta ";
		$query .= "        ON sistemascead.fi_pessoa_ficha.ac_curso_oferta_id = sistemascead.ac_curso_oferta.id ";
		$query .= "    INNER JOIN sistemascead.ac_curso ";
		$query .= "        ON sistemascead.ac_curso_oferta.ac_curso_id = sistemascead.ac_curso.id ";
		$query .= "    LEFT JOIN sistemascead.fi_funcao_bolsista ";
		$query .= "        ON sistemascead.fi_pessoa_ficha.fi_funcao_bolsista_id = sistemascead.fi_funcao_bolsista.id ";
		$query .= "    INNER JOIN sistemascead.cm_pessoa ";
		$query .= "        ON sistemascead.fi_frequencia.cm_pessoa_id = sistemascead.cm_pessoa.id ";
		$query .= "    WHERE sistemascead.fi_frequencia.fi_datafrequencia_id = " .intval($id_data_frequencia). " ";
		$query .= "    ORDER BY sistemascead.cm_pessoa.id, sistemascead.fi_pessoa_ficha.id DESC ";
		$query .= ") AS subquery ";
		$query .= "ORDER BY curso_nome, pessoa_nome";

		$result = pg_query($GLOBALS['link'], $query);

		if (pg_num_rows($result) == 0) return null;

		$rows = array();
		while ($row = pg_fetch_row($result)) {
			$info_edital = array();

			if ($_SERVER['argc'] > 1 && $_SERVER['argv'][1] == 'mostraedital')
				$info_edital = get_edital_bolsista($row[0]);

			if (!empty($info_edital))
				/*
					* id, nome do curso, cpf, nome da pessoa, funcao da pessoa,
					* numero/ano do edital, descricao do edital
					*
					* Preciso do id aqui para fazer a busca em get_fi_frequencia_moodle_ultimo_acesso
					* Se eu procurar por CPF pode ser que volte vazio porque nem sempre o login e' CPF
				*/
				$rows[] = array($row[0], $row[1], $row[2], $row[3], $row[4], $info_edital[0].'/'.$info_edital[1], $info_edital[2]);
			else
				$rows[] = array($row[0], $row[1], $row[2], $row[3], $row[4], NULL, NULL);
		}
		return $rows;
	}

	function get_fi_frequencia_moodle_ultimo_acesso($cm_pessoa_id, $fi_datafrequencia_id) {
		if (empty($cm_pessoa_id)) return null;
		if (empty($fi_datafrequencia_id)) return null;

		$query = "SELECT moodle_id, MAX(ultimo_acesso) FROM sistemascead.fi_frequencia_moodle ";
		$query .= "WHERE sistemascead.fi_frequencia_moodle.cm_pessoa_id = ";
		$query .= intval($cm_pessoa_id);
		$query .= " AND fi_datafrequencia_id = ";
		$query .= intval($fi_datafrequencia_id);
		$query .= " GROUP BY moodle_id";

		$result = pg_query($GLOBALS['link'], $query);

		if (pg_num_rows($result) == 0) return null;

		$rows = array();
		while ($row = pg_fetch_row($result)) {
			$ultimo_acesso = is_null($row[1]) ? "" : $row[1];
			$rows[] = array($row[0], $ultimo_acesso);
		}
		return $rows;	
	}

	function generate_html_report($bolsistas) {
		$html = "<!DOCTYPE html><head><meta charset=\"UTF-8\"><style>@media print {.pagebreak {page-break-before: always;}}</style></head><body>";

		$html = "";
		$print_barra_div = false;

		for ($i = 0; $i < count($bolsistas); $i++) {
			$bolsista = $bolsistas[$i];

			if ($i > 0 && $bolsistas[$i][0] != $bolsistas[$i - 1][0]) {
				if ($print_barra_div) {
					$html .= "</div>";
				} else {
					$print_barra_div = true;
				}
			}

			if ($i > 0 && $bolsistas[$i][0] != $bolsistas[$i - 1][0])
				$html .= "<div class=\"pagebreak\">";

			if ($i == 0 || $bolsistas[$i][0] != $bolsistas[$i - 1][0])
				$html .= "<p><font size=\"6\">".$bolsista[0]."</font></p>";

			$html .= "<strong>".$bolsista[1]." | ";
			$html .= $bolsista[2]." | ";
			$html .= $bolsista[3];
			if (!empty($bolsista[4]) && !empty($bolsista[5])) {
				$html .= " | Edital ".$bolsista[4]." -- ";
				$html .= $bolsista[5];
			}
			$html .= "</strong><br />";

			for ($j = 0; $j < count(array($bolsista[6])); $j++) {
				if (empty($bolsista[6])) {
					$html .= "<i>&emsp;<font color=\"red\">Login n&atilde;o encontrado</font><br /></i>\n";
					continue;
				}

				foreach($bolsista[6] as $infomoodle) {
					if (!empty($infomoodle)) {
						if ($infomoodle[1] !== null) {
							$data = strtotime($infomoodle[1]);

							if ($data < strtotime("-30 day"))
								$html .= "<i>&emsp;<font color=\"red\">Login Moodle: ".$infomoodle[0]." | &Uacute;ltimo acesso: ".date("d/m/y\, H\hi", $data)."</font><br /></i>";
							else
								$html .= "<i>&emsp;Login Moodle: ".$infomoodle[0]." | &Uacute;ltimo acesso: ".date("d/m/y\, H\hi", $data)."<br /></i>";
						}
						else
							$html .= "<i>&emsp;<font color=\"red\">Login Moodle: ".$infomoodle[0]." | nunca acessou</font><br /></i>\n";
					}
				}
			}
		}

		$html .= "</body></html>\n";
		return $html;
	}

	$mdl_token = '6abd76ba8c1d190601f43b60d1745e73';
	$mdl_domainname = 'https://ead.ufjf.br';
	$mdl_functionname = 'core_user_get_users_by_field';
	$mdl_restformat = 'json';

	$pgsql_host = '10.9.8.22';
	$pgsql_user = 'sistemascead';
	$pgsql_pass = 'oF$`760q:7n3N24&^s]..2{Ck'; 
	$pgsql_db = 'sistemascead';

	date_default_timezone_set("America/Sao_Paulo");

	if (!is_moodle_online()) exit('Moodle offline');

	$link = pg_connect("host=$pgsql_host dbname=$pgsql_db user=$pgsql_user password=$pgsql_pass");
	if (!$link) exit('Erro de conexão com PostgreSQL: ' .pg_last_error(). '\n');

	$ultimo_fi_datafrequencia_id = get_ultimo_fi_datafrequencia_id();
	$bolsistas_autorizados[] = get_bolsistas_autorizados($ultimo_fi_datafrequencia_id);

	foreach ($bolsistas_autorizados as $row => $bolsista_autorizado) {
		foreach ($bolsista_autorizado as $dados_bolsista_autorizado) {
			/*
				Preciso buscar o CPF da pessoa em dois campos no Moodle: username e idnumber
				Algumas tem login CPF, outras o SIAPE, por exemplo, e possuem o CPF em outro campo

				Entao eu associo o nome de usuario ao cm_pessoa.id na base sistemascead

				Os parametros passados nas linhas abaixo sao dois de busca e 1 de insercao, respectivamente
				1 - Campo a buscar no Moodle
				2 - CPF da pessoa, cm_pessoa.cpf, que quero buscar via WS no campo 1 no Moodle (dados_bolsista_autorizado[0])
				3 - Insere na base associando fi_frequencia.cm_pessoa_id (dados_bolsista_autorizado[1])
			*/
			insert_into_fi_frequencia_moodle(get_ultimo_acesso_moodle('username', $dados_bolsista_autorizado[0]), $dados_bolsista_autorizado[1]);
			insert_into_fi_frequencia_moodle(get_ultimo_acesso_moodle('idnumber', $dados_bolsista_autorizado[0]), $dados_bolsista_autorizado[1]);
		}
	}

	// Acabou de inserir na base sistemascead os dados que preciso, daqui pra baixo e' so consulta, comeca a preparar dados para relatorio
	/*
	$bolsistas_autorizados = array();
	$bolsistas_autorizados[] = get_bolsistas_autorizados_relatorio($ultimo_fi_datafrequencia_id);

	$bolsistas = array();
	$bolsistas_infomoodle = array();
	$count_bolsistas = 0;

	// Coloca em $bolsistas[] as informacoes adicionais
	// Ate' entao eu tenho em $bolsistas_autorizados[] somente cpf, nome, curso, funcao, edital (opctional)
	// Preciso saber, para cada uma das posicoes do array de array, quais os dados de acesso ao Moodle p/ gerar relatorio
	foreach ($bolsistas_autorizados as $bolsista_autorizado) {
		foreach ($bolsista_autorizado as $dados_bolsista_autorizado) {
			$count_bolsistas++;
			$fi_frequencia_moodle_ultimo_acesso = get_fi_frequencia_moodle_ultimo_acesso($dados_bolsista_autorizado[0], $ultimo_fi_datafrequencia_id);
			$bolsistas_infomoodle = array();
	
			foreach ((array)$fi_frequencia_moodle_ultimo_acesso as $dados_fi_frequencia_moodle_ultimo_acesso) {
				$ultimo_acesso = !empty($dados_fi_frequencia_moodle_ultimo_acesso[1]) ? $dados_fi_frequencia_moodle_ultimo_acesso[1] : null;
				// $dados_fi_frequencia_moodle_ultimo_acesso[0] = moodle_id
				array_push($bolsistas_infomoodle, array($dados_fi_frequencia_moodle_ultimo_acesso[0], $ultimo_acesso));
			}
	
			// Adiciona todos os dados do bolsista e do Moodle no array final
			array_push(
				$bolsistas, 
				array(
					$dados_bolsista_autorizado[1], // nome do curso
					$dados_bolsista_autorizado[2], // cpf
					$dados_bolsista_autorizado[3], // nome da pessoa
					$dados_bolsista_autorizado[4], // funcao da pessoa
					$dados_bolsista_autorizado[5], // numero/ano edital
					$dados_bolsista_autorizado[6], // descricao edital
					$bolsistas_infomoodle // Array de Moodle para esse bolsista
				)
			);
			
		}
	}

	echo(generate_html_report($bolsistas));
	*/

	pg_close($link);
?>
