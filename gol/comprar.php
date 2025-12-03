<?php
// File: latam/api_comprar.php (e gol/api_comprar.php)
header('Content-Type: application/json');

if ( !isset($_REQUEST['voo_id']) || !isset($_REQUEST['cpf']) || !isset($_REQUEST['nome']) ) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetros (voo_id, cpf, nome) ausentes.']);
    exit;
}

$voo_id = $_REQUEST['voo_id'];
$cpf = $_REQUEST['cpf'];
$nome = $_REQUEST['nome'];

try {
    $conexao = new PDO ('sqlite:database');
    $conexao->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexao->exec("pragma foreign_keys = ON;");

    // 1. Buscar cliente por CPF
    $stmt_cliente = $conexao->prepare("SELECT id FROM cliente WHERE cpf = ?");
    $stmt_cliente->execute([$cpf]);
    $cliente_id = $stmt_cliente->fetchColumn();

    // 2. Se o cliente não existir, inserir novo cliente
    if (!$cliente_id) {
        $stmt_insert = $conexao->prepare("INSERT INTO cliente (cpf, nome) VALUES (?, ?)");
        $success = $stmt_insert->execute([$cpf, $nome]);
        
        if (!$success) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao cadastrar novo cliente.']);
            exit;
        }
        
        // Obter o ID do cliente recém-inserido
        $cliente_id = $conexao->lastInsertId();
    }
    
    // 3. Inserir passageiro (registrar a compra)
    $stmt_passageiro = $conexao->prepare("INSERT INTO passageiro (voo, cliente) VALUES (?, ?)");
    $success = $stmt_passageiro->execute([$voo_id, $cliente_id]);

    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Passagem comprada com sucesso.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao registrar a passagem.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

?>