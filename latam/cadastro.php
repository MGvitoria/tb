<?php
	header('Content-Type: application/json');
	
	// Verifica a existência dos parâmetros de busca
	if ( !isset($_REQUEST['origem_iata']) || !isset($_REQUEST['destino_iata']) || !isset($_REQUEST['data']) ) {
		// Retorna erro se os parâmetros estiverem incompletos
		echo json_encode(['error' => 'Parâmetros de busca incompletos.'], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$origem_iata = $_REQUEST['origem_iata'];
	$destino_iata = $_REQUEST['destino_iata'];
	// Usa LIKE para permitir busca por data (ex: '2023-07-01%')
	$data_like = $_REQUEST['data'] . '%'; 

	try {
		$conexao = new PDO ('sqlite:database');
		$conexao->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		
		// Consulta na view vvoo para obter os detalhes e na tabela voo para obter o preço
		$sql = "
			SELECT 
				v.id, 
				v.voo, 
				v.aviao, 
				v.origem, 
				v.destino, 
				v.datahora,
				t.preco
			FROM 
				vvoo v
			JOIN 
				voo t ON t.id = v.id
			WHERE 
				v.origem = :origem_iata 
				AND v.destino = :destino_iata 
				AND v.datahora LIKE :data_like
			ORDER BY v.datahora
		";

		$stmt = $conexao->prepare($sql);
		$stmt->bindParam(':origem_iata', $origem_iata);
		$stmt->bindParam(':destino_iata', $destino_iata);
		$stmt->bindParam(':data_like', $data_like);
		$stmt->execute();
		
		$resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);

		echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
	} catch (PDOException $e) {
		echo json_encode(['error' => 'Erro ao buscar voos: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
	}
?>