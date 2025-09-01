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

	//$mdl_token = '36f84b36e6225bfab10f87e88b4aa455';
	$mdl_token = '6abd76ba8c1d190601f43b60d1745e73';
	//$mdl_domainname = 'http://teste.ead.ufjf.br';
	$mdl_domainname = 'https://ead.ufjf.br';
	$mdl_functionname = 'core_user_get_users_by_field';
	$mdl_restformat = 'json';

	$mysql_host = '10.9.8.28';
	$mysql_user = 'cead';
	$mysql_pass = 'F4ktrd8CyevjkBR6BFy4Cjyh';
	$mysql_db = 'cead';

	date_default_timezone_set("America/Sao_Paulo");

	function is_moodle_online() {
		try {
			file_get_contents($GLOBALS['mdl_domainname']);
			return true;	
		} catch(Exception $e) {
			return false;
		}
	}

	//Consulta ao webservice do Modle
	function get_moodle_lastaccess($field, $id) {
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

	//Busca o ultimo id_frequencia lancado na tabela fi_frequencia
	function get_last_id_frequencia() {
		if (empty($GLOBALS['link'])) return null;

		$query = "SELECT id_data_frequencia FROM fi_frequencia ORDER BY id_data_frequencia DESC LIMIT 1";
		$result = mysqli_query($GLOBALS['link'], $query);

		if (mysqli_num_rows($result) == 1) return mysqli_fetch_array($result, MYSQLI_NUM)[0];
		else return null;
	}

	//Retorna os bolsistas autorizados num array(cpf, id_bolsista)
	function get_bolsistas_autorizados($id_data_frequencia) {
		if (empty($id_data_frequencia)) return null;
		$rows = array();

		$query = "SELECT cm_pessoa.cpf, fi_frequencia.id_bolsista FROM fi_frequencia
				INNER JOIN cm_pessoa ON fi_frequencia.id_bolsista = cm_pessoa.id_pessoa
				WHERE id_data_frequencia = ".$id_data_frequencia.
				" AND pagamento_autorizado = 1 GROUP BY fi_frequencia.id_bolsista";
		$result = mysqli_query($GLOBALS['link'], $query);

		if (mysqli_num_rows($result) > 0) {
			while ($row = mysqli_fetch_array($result, MYSQLI_NUM)) {
				$rows[] = array($row[0], $row[1]);
			}
			return $rows;
		} else return null;
	}

	//Pega as informacoes em qual edital o bolsista foi aprovado
	function get_edital_bolsista($id_bolsista) {
		if (empty($id_bolsista)) return null;
		$rows = array();

		$query = "SELECT cm_pessoa_oferta.id_pessoa_curso, pr_edital.edital, pr_edital.descricao ";
		$query .= "FROM cm_pessoa_oferta ";
		$query .= "INNER JOIN pr_edital ON cm_pessoa_oferta.id_edital = pr_edital.id_edital ";
		$query .= "WHERE cm_pessoa_oferta.id_pessoa = ";
		$query .= $id_bolsista;
		$query .= " AND cm_pessoa_oferta.id_status = 1 ";
		$query .= "AND cm_pessoa_oferta.data_fim IS NULL ";
		$query .= "ORDER BY cm_pessoa_oferta.id_pessoa_curso DESC LIMIT 1";

		$result = mysqli_query($GLOBALS['link'], $query);

		if (mysqli_num_rows($result) == 1) {
			$row = mysqli_fetch_array($result, MYSQLI_NUM);
			return array($row[1], $row[2]);
		}
		else return null;
	}

	//Pega informacoes para imprimir relatorio
	function get_bolsistas_autorizados_report($id_data_frequencia) {
		if (empty($id_data_frequencia)) return null;
		$rows = array();

		$query = "SELECT fi_frequencia.id_bolsista, cm_curso.nome, cm_pessoa.cpf, cm_pessoa.nome, cm_funcao.nome ";
		$query .= "FROM fi_frequencia ";
		$query .= "INNER JOIN cm_funcao ON fi_frequencia.id_funcao = cm_funcao.id_funcao ";
		$query .= "INNER JOIN cm_curso ON fi_frequencia.id_curso = cm_curso.id_curso ";
		$query .= "INNER JOIN cm_pessoa ON fi_frequencia.id_bolsista = cm_pessoa.id_pessoa ";
		$query .= "INNER JOIN fi_data_frequencia ON fi_frequencia.id_data_frequencia = fi_data_frequencia.id_data_frequencia ";
		$query .= "WHERE fi_frequencia.id_data_frequencia = ";
		$query .= $id_data_frequencia;
		$query .= " AND fi_frequencia.pagamento_autorizado = 1 ";
		$query .= "GROUP BY fi_frequencia.id_bolsista ";
		$query .= "ORDER BY cm_curso.nome, cm_pessoa.nome";

		$result = mysqli_query($GLOBALS['link'], $query);

		if (mysqli_num_rows($result) > 0) {
			while ($row = mysqli_fetch_array($result, MYSQLI_NUM)) {
				/*
					* Busca informacoes do edital em que a pessoa esta' cadastrada
					* Nao fiz na mesma query porque a cm_pessoa_oferta e' uma zona
					* Fazer subquery como join nao vale a pena
					* Vai retornar um monte de NULL porque nao ta cadastrado certo e
					* eu nem sei se vai ser necessaria a informacao, entao se chamar
					* o parametro, mostra
				*/
				$info_edital = array();
				if ($_SERVER['argc'] > 1)
					if ($_SERVER['argv'][1] == 'mostraedital') 
						$info_edital = get_edital_bolsista($row[0]);
				/*
					* id bolsista, nome do curso, cpf, nome da pessoa, funcao da pessoa,
					* numero do edital, descricao do edital
				*/
				if (!empty($info_edital))
					$rows[] = array($row[0], $row[1], $row[2], $row[3], $row[4], $info_edital[0], $info_edital[1]);
				else
					$rows[] = array($row[0], $row[1], $row[2], $row[3], $row[4], NULL, NULL);
			}
			return $rows;
		} else return null;
	}

	function insert_into_fi_frequencia_moodle($users, $x) {
		if (empty($users)) return null;
		if (empty($x)) return null;

		$json = json_decode($users);
		foreach ($json as $z) {
			$query = "INSERT INTO fi_frequencia_moodle (id_data_frequencia, id_pessoa, id_moodle";

			if ($z->lastaccess > 0) {
				$query .= ", ultimo_acesso) VALUES (";
				$query .= $GLOBALS['last_id_frequencia'].", ".$x.", '".$z->username."', FROM_UNIXTIME(".$z->lastaccess."))";
			} else {
				$query .= ") VALUES (";
				$query .= $GLOBALS['last_id_frequencia'].", ".$x.", '".$z->username."')";
			}
			if (!mysqli_query($GLOBALS['link'], $query)) echo mysqli_error($GLOBALS['link'])."\n";
		}
	}

	function get_fifrequenciamodle_lastaccess($x, $id_data_frequencia) {
		if (empty($id_data_frequencia)) return null;

		$query = "SELECT id_moodle, MAX(ultimo_acesso) FROM fi_frequencia_moodle WHERE id_pessoa = ";
		$query .= $x[0];
		$query .= " AND id_data_frequencia = ";
		$query .= $id_data_frequencia;
		$query .= " GROUP BY id_moodle";

		$result = mysqli_query($GLOBALS['link'], $query);

		if (mysqli_num_rows($result) > 0) {
			while ($row = mysqli_fetch_array($result, MYSQLI_NUM)) {
				$rows[] = array($row[0], $row[1]);
			}
			return $rows;
		} else return null;
	}

	function generate_html_report($bolsistas) {
		$html_out = "";
		$print_barra_div = false;

		for ($i = 0; $i < count($bolsistas); $i++) {
			$bolsista = $bolsistas[$i];

			if ($i > 0 && $bolsistas[$i][0] != $bolsistas[$i - 1][0]) {
				if ($print_barra_div) {
					$html_out .= "</div>";
				} else {
					$print_barra_div = true;	
				}
			}

			if ($i > 0 && $bolsistas[$i][0] != $bolsistas[$i - 1][0])
				$html_out .= "<div class=\"pagebreak\">";

			if ($i == 0 || $bolsistas[$i][0] != $bolsistas[$i - 1][0])
				$html_out .= "<p><font size=\"6\">".$bolsista[0]."</font></p>";

			$html_out .= "<strong>".$bolsista[1]." | ";
			$html_out .= $bolsista[2]." | ";
			$html_out .= $bolsista[3];
			if (!empty($bolsista[4]) && !empty($bolsista[5])) {
				$html_out .= " | Edital ".substr_replace($bolsista[4], "/", -4).substr($bolsista[4], -4)." -- ";
				$html_out .= $bolsista[5];
			}
			$html_out .= "</strong><br />";

			for ($j = 0; $j < count(array($bolsista[6])); $j++) {
				if (empty($bolsista[6])) {
					$html_out .= "<i>&emsp;<font color=\"red\">Login n&atilde;o encontrado</font><br /></i>\n";
					continue;
				}

				$infomoodle = $bolsista[6];
				foreach($bolsista[6] as $infomoodle) {
					if (!empty($infomoodle)) {
						$data = strtotime($infomoodle[1]);

						if($data < strtotime("-30 day"))
							$html_out .= "<i>&emsp;<font color=\"red\">Login Moodle: ".$infomoodle[0]." | &Uacute;ltimo acesso: ".date("d/m/y\, H\hi", $data)."</font><br /></i>";
						else
							$html_out .= "<i>&emsp;Login Moodle: ".$infomoodle[0]." | &Uacute;ltimo acesso: ".date("d/m/y\, H\hi", $data)."<br /></i>";
					} else {
						$html_out .= "<i>&emsp;<font color=\"red\">Login Moodle: ".$y[0]." | nunca acessou</font><br /></i>\n";
					}
				}
			}
		}
		return $html_out;
	}

	//Variavel para salvar o relatorio
	$html = "<!DOCTYPE html><head><meta charset=\"iso-8859-1\"><style>@media print {.pagebreak {page-break-before: always;}}</style></head><body>";

	if (!is_moodle_online()) exit('Moodle offline');

	$link = mysqli_connect($mysql_host, $mysql_user, $mysql_pass, $mysql_db);
	if (!$link) exit('Erro de conexao ('.mysqli_connect_errno().') '.mysqli_connect_error().'\n');

	$last_id_frequencia = get_last_id_frequencia();
	$bolsistas_autorizados[] = get_bolsistas_autorizados($last_id_frequencia);

	foreach ($bolsistas_autorizados as $row => $bolsista_autorizado) {
		foreach ($bolsista_autorizado as $x) {
			//x[0] = cm_pessoa.cpf
			//x[1] = fi_frequencia.id_bolsista

			//Busca pelo cpf no campo 'username' do Moodle, insere na tabela
			insert_into_fi_frequencia_moodle(get_moodle_lastaccess('username', $x[0]), $x[1]);
			//Busca pelo idnumber, "numero de identificacao" na tela do Moodle, insere na tabela
			insert_into_fi_frequencia_moodle(get_moodle_lastaccess('idnumber', $x[0]), $x[1]);
		}
	}

	//Prepara para montar os dados do relatorio
	$bolsistas_autorizados = array();
	$bolsistas_autorizados[] = get_bolsistas_autorizados_report($last_id_frequencia);

	$bolsistas = array();
	$bolsistas_infomoodle = array();
	$count_bolsistas = 0;

	//Salva no array bolsistas[] todas as informacoes para gerar o relatorio
	foreach ($bolsistas_autorizados as $bolsista_autorizado) {
	 	foreach ($bolsista_autorizado as $x) {
	 		$count_bolsistas++;
	 		$fifrequenciamodle_lastaccess = get_fifrequenciamodle_lastaccess($x, $last_id_frequencia);
	 		$bolsistas_infomoodle = array();
	 		foreach ((array)$fifrequenciamodle_lastaccess as $y) {
 		 		if (!empty($y[1])) {
 		 			//idmoodle, lastaccess
 		 			array_push($bolsistas_infomoodle, array($y[0], $y[1]));
 		 		}
	 		}
	 		//curso, cpf, nome, funcao, numero edital, descricao edital, array acima
	 		array_push($bolsistas, array($x[1], $x[2], $x[3], $x[4], $x[5], $x[6], $bolsistas_infomoodle));
	 	}
	}

	$html .= generate_html_report($bolsistas);
	/*
	$html .= "<br /><br /><br />";
	$html .= "<center><p>N&uacute;mero de bolsistas: ";
	$html .= $count_bolsistas;
	$html .= "</p></center>";
	$html .= "<center><p>Gerado em ".date("d/m/y\, H\hi");
	*/
	//$html .= "</p></center></body></html>\n";
	$html .= "</body></html>\n";
	echo $html;

	mysqli_close($link);
?>
