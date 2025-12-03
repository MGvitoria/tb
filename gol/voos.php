<?php
header('Content-Type: application/json');

if ( !isset($_REQUEST['origem']) || !isset($_REQUEST['destino']) || !isset($_REQUEST['data']) ) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros de busca (origem, destino, data) ausentes.']);
    exit;
}

$origem_iata = strtoupper($_REQUEST['origem']);
$destino_iata = strtoupper($_REQUEST['destino']);
$data_busca = $_REQUEST['data'];

try {
    $conexao = new PDO ('sqlite:database');
    $conexao->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt_origem = $conexao->prepare("SELECT id FROM destino WHERE iata = ?");
    $stmt_origem->execute([$origem_iata]);
    $origem_id = $stmt_origem->fetchColumn();

    $stmt_destino = $conexao->prepare("SELECT id FROM destino WHERE iata = ?");
    $stmt_destino->execute([$destino_iata]);
    $destino_id = $stmt_destino->fetchColumn();

    if (!$origem_id || !$destino_id) {
        echo json_encode([]);
        exit;
    }

    $sql = "
        SELECT v.id, o.iata as origem, d.iata as destino, v.datahora, v.preco, a.matricula || a.fabricante || a.modelo as aviao
        FROM voo v
        JOIN destino o ON v.origem = o.id
        JOIN destino d ON v.destino = d.id
        JOIN aviao a ON v.aviao = a.id
        WHERE o.id = :origem_id AND d.id = :destino_id 
        AND STRFTIME('%Y-%m-%d', v.datahora) = :data_busca
    ";

    $stmt = $conexao->prepare($sql);
    $stmt->bindParam(':origem_id', $origem_id);
    $stmt->bindParam(':destino_id', $destino_id);
    $stmt->bindParam(':data_busca', $data_busca);
    $stmt->execute();

    $voos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($voos);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

?>