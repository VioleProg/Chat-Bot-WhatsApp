<?php
// Habilitar exibição de erros para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Carregar mensagens do arquivo JSON
$file = 'messages.json';
$editableMessages = [
    'reminder' => "*BOT Marcio* Olá, {nome} 😊\n\n⚠️**Lembrete:** sua assinatura de Revenda de IPTV vence em 5 dias ({data}).\nPor favor, regularize o pagamento para continuar aproveitando nossos serviços.",
    'due' => "*BOT Marcio* Olá, {nome} ⚠️\n\nHoje é o dia de vencimento da sua assinatura de Revenda de IPTV ({data}).\nPor favor, efetue o pagamento para evitar interrupções.",
    'overdue' => "*BOT Marcio* Olá, {nome} ⚠️\n\nNotamos que sua assinatura de Revenda de IPTV está em atraso desde {data}.\nRegularize sua situação o quanto antes para evitar a suspensão do serviço."
];
if (file_exists($file)) {
    $savedMessages = json_decode(file_get_contents($file), true) ?: [];
    $editableMessages = array_merge($editableMessages, $savedMessages);
}

$host = 'localhost';
$user = 'root';
$password = '46984698';
$database = 'whatsapp_bot';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco de dados: " . $e->getMessage());
}

$success = '';
$error = '';
$messages = [];

// Resetar status pago no início do mês
$today = new DateTime();
if ($today->format('d') === '01') {
    try {
        $pdo->exec("UPDATE clientes SET pago = 0");
    } catch (PDOException $e) {
        $error = 'Erro ao resetar status de pagamento: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_client'])) {
    $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_STRING);
    $sobrenome = filter_input(INPUT_POST, 'sobrenome', FILTER_SANITIZE_STRING);
    $cpf = filter_input(INPUT_POST, 'cpf', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $telefone = filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_STRING);
    $data_inicio = filter_input(INPUT_POST, 'data_inicio', FILTER_SANITIZE_STRING);
    $data_expiracao = filter_input(INPUT_POST, 'data_expiracao', FILTER_SANITIZE_STRING);
    $pago = 0;

    if ($nome && $sobrenome && $cpf && $email && $telefone && $data_inicio && $data_expiracao) {
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => json_encode([
                        'nome' => $nome,
                        'sobrenome' => $sobrenome,
                        'cpf' => $cpf,
                        'email' => $email,
                        'telefone' => $telefone,
                        'data_inicio' => $data_inicio,
                        'data_expiracao' => $data_expiracao,
                        'pago' => $pago
                    ]),
                    'timeout' => 10
                ]
            ]);
            $response = @file_get_contents('http://localhost:3000/api/clients', false, $context);
            if ($response === false) {
                throw new Exception('Falha na conexão com a API ao adicionar cliente.');
            }
            $result = json_decode($response, true);
            if (isset($result['success']) && $result['success']) {
                $success = 'Cliente adicionado com sucesso!';
            } else {
                $error = 'Erro ao adicionar cliente: ' . ($result['error'] ?? 'Resposta inválida da API');
            }
        } catch (Exception $e) {
            $error = 'Erro ao adicionar cliente: ' . $e->getMessage();
        }
    } else {
        $error = 'Preencha todos os campos obrigatórios.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_messages'])) {
    $client_ids = isset($_POST['client_ids']) ? $_POST['client_ids'] : [];
    $message_type = isset($_POST['message_type']) ? $_POST['message_type'] : 'reminder';
    if (!empty($client_ids)) {
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => json_encode(['clientIds' => $client_ids, 'messageType' => $message_type]),
                    'timeout' => 10
                ]
            ]);
            $response = @file_get_contents('http://localhost:3000/api/send-messages', false, $context);
            if ($response === false) {
                throw new Exception('Falha na conexão com a API ao enviar mensagens.');
            }
            $result = json_decode($response, true);
            if (isset($result['success']) && $result['success']) {
                $success = 'Mensagens enviadas com sucesso!';
                $clients = [];
                $response = @file_get_contents('http://localhost:3000/api/clients');
                if ($response !== false) {
                    $clients = json_decode($response, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($clients)) {
                        foreach ($client_ids as $id) {
                            $client = null;
                            foreach ($clients as $c) {
                                if (isset($c['id']) && $c['id'] == $id) {
                                    $client = $c;
                                    break;
                                }
                            }
                            if ($client && isset($client['nome'])) {
                                $formattedDate = date('d/m/Y', strtotime($client['data_expiracao']));
                                $messageText = str_replace(['{nome', '{data}'], [$client['nome'], $formattedDate], $editableMessages[$message_type]);
                                $messages[] = [
                                    'to' => $client['nome'],
                                    'type' => $message_type === 'reminder' ? 'Lembrete' : ($message_type === 'due' ? 'Vencimento' : 'Atraso'),
                                    'date' => date('d/m/Y H:i'),
                                    'message' => $messageText,
                                    'editable' => true
                                ];
                            }
                        }
                    } else {
                        $error = 'Erro ao decodificar a lista de clientes: ' . json_last_error_msg();
                    }
                } else {
                    $error = 'Falha ao carregar a lista de clientes após enviar mensagens.';
                }
            } else {
                $error = 'Erro ao enviar mensagens: ' . ($result['error'] ?? 'Resposta inválida da API');
            }
        } catch (Exception $e) {
            $error = 'Erro ao enviar mensagens: ' . $e->getMessage();
        }
    } else {
        $error = 'Selecione pelo menos um cliente.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_clients'])) {
    $client_ids = isset($_POST['client_ids']) ? $_POST['client_ids'] : [];
    if (!empty($client_ids)) {
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => json_encode(['clientIds' => $client_ids]),
                    'timeout' => 10
                ]
            ]);
            $response = @file_get_contents('http://localhost:3000/api/delete-clients', false, $context);
            if ($response === false) {
                throw new Exception('Falha na conexão com a API ao deletar clientes.');
            }
            $result = json_decode($response, true);
            if (isset($result['success']) && $result['success']) {
                $success = 'Clientes deletados com sucesso!';
            } else {
                $error = 'Erro ao deletar clientes: ' . ($result['error'] ?? 'Resposta inválida da API');
            }
        } catch (Exception $e) {
            $error = 'Erro ao deletar clientes: ' . $e->getMessage();
        }
    } else {
        $error = 'Selecione pelo menos um cliente para deletar.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_client'])) {
    $id = $_POST['id'] ?? '';
    $telefone = filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_STRING);
    $data_expiracao = filter_input(INPUT_POST, 'data_expiracao', FILTER_SANITIZE_STRING);

    if ($id && $telefone && $data_expiracao) {
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => json_encode([
                        'id' => $id,
                        'telefone' => $telefone,
                        'data_expiracao' => $data_expiracao
                    ]),
                    'timeout' => 10
                ]
            ]);
            $response = @file_get_contents('http://localhost:3000/api/edit-client', false, $context);
            if ($response === false) {
                throw new Exception('Falha na conexão com a API ao editar cliente.');
            }
            $result = json_decode($response, true);
            if (isset($result['success']) && $result['success']) {
                $success = 'Cliente editado com sucesso!';
            } else {
                $error = 'Erro ao editar cliente: ' . ($result['error'] ?? 'Resposta inválida da API');
            }
        } catch (Exception $e) {
            $error = 'Erro ao editar cliente: ' . $e->getMessage();
        }
    } else {
        $error = 'Preencha todos os campos obrigatórios.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_as_paid'])) {
    $client_ids = isset($_POST['client_ids']) ? $_POST['client_ids'] : [];
    if (!empty($client_ids)) {
        try {
            $new_expiration = (new DateTime())->modify('+30 days')->format('Y-m-d');
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => json_encode(['clientIds' => $client_ids, 'newExpiration' => $new_expiration]),
                    'timeout' => 10
                ]
            ]);
            $response = @file_get_contents('http://localhost:3000/api/mark-as-paid', false, $context);
            if ($response === false) {
                throw new Exception('Falha na conexão com a API ao marcar como pago.');
            }
            $result = json_decode($response, true);
            if (isset($result['success']) && $result['success']) {
                $success = 'Clientes marcados como pagos e datas prorrogadas com sucesso!';
            } else {
                $error = 'Erro ao marcar como pago: ' . ($result['error'] ?? 'Resposta inválida da API');
            }
        } catch (Exception $e) {
            $error = 'Erro ao marcar como pago: ' . $e->getMessage();
        }
    } else {
        $error = 'Selecione pelo menos um cliente para marcar como pago.';
    }
}

$clients = [];
$search = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';
try {
    $response = @file_get_contents('http://localhost:3000/api/clients');
    if ($response === false) {
        $error = 'Falha ao conectar à API do Node.js. Verifique se o servidor está rodando em http://localhost:3000.';
    } else {
        $clients = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = 'Erro ao decodificar a resposta da API: ' . json_last_error_msg();
        } elseif (is_array($clients)) {
            if ($search) {
                $filtered_clients = [];
                foreach ($clients as $c) {
                    $fullName = strtolower($c['nome'] . ' ' . ($c['sobrenome'] ?? ''));
                    $cpf = strtolower($c['cpf'] ?? '');
                    if (strpos($fullName, $search) !== false || strpos($cpf, $search) !== false) {
                        $filtered_clients[] = $c;
                    }
                }
                $clients = $filtered_clients;
            }
        } else {
            $clients = [];
        }
    }
} catch (Exception $e) {
    $error = 'Erro ao carregar clientes: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BOT Marcio - Painel de Controle</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        
    </style>
</head>
<body>
    <div class="container">
        <h1>BOT Marcio - Painel de Controle</h1>

        <?php if ($success): ?>
            <p class="success"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <div class="section">
            <button id="toggleAddClient" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">Adicionar Novo Cliente</button>
            <div id="addClientSection" class="hidden">
                <select id="clientTypeCombobox" class="combobox" onchange="handleComboboxChange()">
                    <option value="">Selecione um tipo de cliente</option>
                    <option value="standard">Cliente Padrão</option>
                </select>
                <div id="addModalOverlay" class="modal-overlay"></div>
                <div id="addModal" class="modal">
                    <h3 id="addModalTitle">Adicionar Cliente</h3>
                    <form id="addClientForm" method="POST" action="">
                        <input type="hidden" name="id" id="add-edit-id">
                        <div id="addClientFields">
                            <input type="text" name="nome" id="add-edit-nome" placeholder="Nome" required>
                            <input type="text" name="sobrenome" id="add-edit-sobrenome" placeholder="Sobrenome" required>
                            <input type="text" name="cpf" id="add-edit-cpf" placeholder="CPF (ex: 123.456.789-00)" required pattern="\d{3}\.\d{3}\.\d{3}-\d{2}">
                            <input type="email" name="email" id="add-edit-email" placeholder="Email" required>
                            <input type="text" name="telefone" id="add-edit-telefone" placeholder="Telefone (ex: 11999999999)" required pattern="\d{11,12}">
                            <input type="date" name="data_inicio" id="add-edit-data_inicio" placeholder="Data Início" required>
                            <input type="date" name="data_expiracao" id="add-edit-data_expiracao" placeholder="Data Expiração" required>
                            <label><input type="checkbox" name="pago" id="add-edit-pago"> Pago</label>
                        </div>
                        <button type="submit" name="add_client" id="add-client-btn">Adicionar</button>
                        <button type="button" id="closeAddModal" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded">Fechar</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>Gerenciar Clientes</h2>
            <div class="mb-4">
                <input type="text" id="search" placeholder="Pesquisar por nome ou CPF..." class="border p-2 w-full" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <button type="button" id="toggleAll" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded mt-2">Marcar/Desmarcar Todos</button>
            </div>
            <form method="POST" action="">
                <select name="message_type" class="border p-2 mb-2" required>
                    <option value="reminder">Lembrete (5 dias antes)</option>
                    <option value="due">Vencimento (dia do pagamento)</option>
                    <option value="overdue">Atraso</option>
                </select>
                <button type="submit" name="send_messages" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">Enviar Mensagem</button>
                <button type="submit" name="delete_clients" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded" onclick="return confirm('Tem certeza que deseja deletar os clientes selecionados?');">Deletar Selecionados</button>
                <button type="submit" name="mark_as_paid" class="pay-btn" onclick="return confirm('Tem certeza que deseja marcar os clientes selecionados como pagos?');">Marcar como Pago</button>
                <?php if (!empty($clients)): ?>
                    <table class="client-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all"></th>
                                <th>Nome</th>
                                <th>Telefone</th>
                                <th>CPF</th>
                                <th>Vencimento</th>
                                <th>Status</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                                <tr>
                                    <td><input type="checkbox" name="client_ids[]" value="<?php echo htmlspecialchars($client['id']); ?>"></td>
                                    <td><?php echo htmlspecialchars($client['nome'] . ' ' . $client['sobrenome']); ?></td>
                                    <td><?php echo htmlspecialchars($client['telefone']); ?></td>
                                    <td><?php echo htmlspecialchars($client['cpf']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($client['data_expiracao'])); ?></td>
                                    <td><?php echo $client['pago'] ? 'Pago' : 'Pendente'; ?></td>
                                    <td><button type="button" class="edit-btn" onclick='editClient(<?php echo json_encode($client, JSON_HEX_APOS | JSON_HEX_QUOT); ?>, event)'>Editar</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Nenhum cliente encontrado.</p>
                <?php endif; ?>
            </form>
        </div>

        <?php if (!empty($messages)): ?>
            <div class="section messages">
                <h2>Mensagens Enviadas</h2>
                <?php foreach ($messages as $index => $msg): ?>
                    <div class="message-item">
                        Enviado para <?php echo htmlspecialchars($msg['to']); ?> - 
                        Tipo: <?php echo htmlspecialchars($msg['type']); ?> - 
                        Data: <?php echo htmlspecialchars($msg['date']); ?>
                        <br>
                        <textarea class="edit-message" data-index="<?php echo $index; ?>"><?php echo htmlspecialchars($msg['message']); ?></textarea>
                        <button class="save-btn" data-index="<?php echo $index; ?>">Salvar</button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="section">
            <h2>Modelos de Mensagens (Editáveis)</h2>
            <?php foreach ($editableMessages as $type => $message): ?>
                <div class="message-item">
                    <strong><?php echo ucfirst(str_replace('_', ' ', $type)); ?>:</strong>
                    <br>
                    <textarea class="edit-message" data-type="<?php echo $type; ?>"><?php echo htmlspecialchars($message); ?></textarea>
                    <button class="save-btn" data-type="<?php echo $type; ?>">Salvar</button>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="editModalOverlay" class="modal-overlay"></div>
        <div id="editModal" class="modal">
            <h3 id="editModalTitle">Editar Cliente</h3>
            <form id="editClientForm" method="POST" action="">
                <input type="hidden" name="id" id="edit-client-id">
                <div id="editClientFields">
                    <input type="text" name="telefone" id="edit-telefone-edit" placeholder="Telefone (ex: 11999999999)" required pattern="\d{11,12}">
                    <input type="date" name="data_expiracao" id="edit-data_expiracao-edit" placeholder="Data Expiração" required>
                </div>
                <button type="submit" name="edit_client" id="edit-submit">Salvar Edição</button>
                <button type="button" id="closeEditModal" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded">Fechar</button>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('toggleAddClient').addEventListener('click', () => {
            console.log('Abrindo seção de adição de cliente com combobox');
            const addClientSection = document.getElementById('addClientSection');
            if (addClientSection) {
                addClientSection.classList.remove('hidden');
                document.getElementById('clientTypeCombobox').focus();
            } else {
                console.error('Seção de adição de cliente não encontrada');
            }
        });

        document.getElementById('closeAddModal').addEventListener('click', () => {
            console.log('Fechando modal de adição');
            const addClientSection = document.getElementById('addClientSection');
            if (addClientSection) {
                addClientSection.classList.add('hidden');
                document.getElementById('addModal').style.display = 'none';
                document.getElementById('addModalOverlay').style.display = 'none';
                resetAddCombobox();
            }
        });

        document.getElementById('addModalOverlay').addEventListener('click', () => {
            console.log('Fechando modal de adição via overlay');
            const addClientSection = document.getElementById('addClientSection');
            if (addClientSection) {
                addClientSection.classList.add('hidden');
                document.getElementById('addModal').style.display = 'none';
                document.getElementById('addModalOverlay').style.display = 'none';
                resetAddCombobox();
            }
        });

        function handleComboboxChange() {
            const select = document.getElementById('clientTypeCombobox');
            const value = select.value;
            const modal = document.getElementById('addModal');
            const overlay = document.getElementById('addModalOverlay');

            if (value) {
                const today = new Date().toISOString().split('T')[0];
                let defaultValues = {
                    'standard': { nome: 'Cliente', sobrenome: 'Padrão', cpf: '123.456.789-00', email: 'cliente@exemplo.com', telefone: '11999999999', data_inicio: today, data_expiracao: new Date(new Date().setMonth(new Date().getMonth() + 1)).toISOString().split('T')[0], pago: false },
                    'vip': { nome: 'Revendedor', sobrenome: 'VIP', cpf: '987.654.321-00', email: 'vip@exemplo.com', telefone: '11988888888', data_inicio: today, data_expiracao: new Date(new Date().setMonth(new Date().getMonth() + 3)).toISOString().split('T')[0], pago: true }
                };

                const values = defaultValues[value] || {};
                document.getElementById('add-edit-nome').value = values.nome || '';
                document.getElementById('add-edit-sobrenome').value = values.sobrenome || '';
                document.getElementById('add-edit-cpf').value = values.cpf || '';
                document.getElementById('add-edit-email').value = values.email || '';
                document.getElementById('add-edit-telefone').value = values.telefone || '';
                document.getElementById('add-edit-data_inicio').value = values.data_inicio || '';
                document.getElementById('add-edit-data_expiracao').value = values.data_expiracao || '';
                document.getElementById('add-edit-pago').checked = values.pago || false;

                modal.style.display = 'block';
                overlay.style.display = 'block';
                console.log('Modal de adição aberto com valores padrão para:', value);
            }
        }

        function resetAddCombobox() {
            const select = document.getElementById('clientTypeCombobox');
            if (select) {
                select.value = '';
                document.getElementById('addClientFields').querySelectorAll('input').forEach(input => input.value = '');
                document.getElementById('add-edit-pago').checked = false;
            }
        }

        document.getElementById('toggleAll').addEventListener('click', () => {
            const checkboxes = document.querySelectorAll('input[name="client_ids[]"]');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !allChecked);
        });

        document.getElementById('select-all').addEventListener('click', (e) => {
            const checkboxes = document.querySelectorAll('input[name="client_ids[]"]');
            checkboxes.forEach(cb => cb.checked = e.target.checked);
        });

        document.getElementById('search').addEventListener('input', (e) => {
            const searchTerm = e.target.value;
            window.location.href = `?search=${encodeURIComponent(searchTerm)}`;
        });

        document.querySelectorAll('.save-btn').forEach(button => {
            button.addEventListener('click', () => {
                const index = button.getAttribute('data-index');
                const type = button.getAttribute('data-type');
                const textarea = button.previousElementSibling;
                const newMessage = textarea.value;

                if (index !== null) {
                    messages[index].message = newMessage;
                    textarea.value = newMessage;
                    alert('Mensagem atualizada com sucesso!');
                } else if (type) {
                    fetch('update_message.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `type=${encodeURIComponent(type)}&message=${encodeURIComponent(newMessage)}`
                    })
                    .then(response => response.text())
                    .then(data => alert(data))
                    .catch(error => alert('Erro ao salvar: ' + error));
                }
            });
        });

        function editClient(client, event) {
            event.preventDefault();
            console.log('Editando cliente:', client);
            if (!client || typeof client !== 'object' || !client.id) {
                console.error('Cliente inválido:', client);
                alert('Erro: Dados do cliente inválidos.');
                return;
            }
            const modal = document.getElementById('editModal');
            const overlay = document.getElementById('editModalOverlay');
            if (!modal || !overlay) {
                console.error('Modal ou overlay de edição não encontrado');
                alert('Erro: Não foi possível abrir o formulário de edição.');
                return;
            }
            document.getElementById('edit-client-id').value = client.id || '';
            document.getElementById('edit-telefone-edit').value = client.telefone || '';
            document.getElementById('edit-data_expiracao-edit').value = client.data_expiracao || '';
            modal.style.display = 'block';
            overlay.style.display = 'block';
            console.log('Modal de edição aberto com dados:', client);
        }

        document.getElementById('closeEditModal').addEventListener('click', () => {
            console.log('Fechando modal de edição');
            document.getElementById('editModal').style.display = 'none';
            document.getElementById('editModalOverlay').style.display = 'none';
        });

        document.getElementById('editModalOverlay').addEventListener('click', () => {
            console.log('Fechando modal de edição via overlay');
            document.getElementById('editModal').style.display = 'none';
            document.getElementById('editModalOverlay').style.display = 'none';
        });

        let messages = <?php echo json_encode($messages, JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>
</body>
</html>