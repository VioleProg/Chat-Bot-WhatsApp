<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $message = $_POST['message'] ?? '';

    if ($type && $message) {

        $file = 'messages.json';
        $messages = [];
        if (file_exists($file)) {
            $messages = json_decode(file_get_contents($file), true) ?: [];
        }

        $messages[$type] = $message;

        if (file_put_contents($file, json_encode($messages, JSON_PRETTY_PRINT))) {
            echo 'Modelo de mensagem salvo com sucesso!';
        } else {
            echo 'Erro ao salvar o arquivo.';
        }
    } else {
        echo 'Erro: Dados inválidos.';
    }
}
?>