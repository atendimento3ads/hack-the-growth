<?php
declare(strict_types=1);

// ============================================================
// E-MAIL QUE RECEBE OS LEADS — ALTERAR SOMENTE ESTA LINHA
// ============================================================
const LEAD_DESTINATION_EMAIL = 'jpsalgadomelo@gmail.com';

// Remetente do próprio domínio melhora SPF/DKIM e a entrega no Gmail.
const LEAD_FROM_EMAIL = 'leads@3ads.com.br';
const LEAD_FROM_NAME = 'Hack The Growth';

// Configuração privada fora do repositório e da pasta pública do site.
$privateConfigPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'secure' . DIRECTORY_SEPARATOR . 'lead-config.php';
$privateConfig = [];
if (is_file($privateConfigPath)) {
    $loadedConfig = require $privateConfigPath;
    if (is_array($loadedConfig)) {
        $privateConfig = $loadedConfig;
    }
}

$webhookUrl = $privateConfig['google_sheets_webhook_url'] ?? getenv('GOOGLE_SHEETS_WEBHOOK_URL');
$webhookSecret = $privateConfig['google_sheets_webhook_secret'] ?? getenv('GOOGLE_SHEETS_WEBHOOK_SECRET');
define('GOOGLE_SHEETS_WEBHOOK_URL', is_string($webhookUrl) ? trim($webhookUrl) : '');
define('GOOGLE_SHEETS_WEBHOOK_SECRET', is_string($webhookSecret) ? trim($webhookSecret) : '');
unset($privateConfigPath, $privateConfig, $loadedConfig, $webhookUrl, $webhookSecret);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

function finish(int $status, bool $success, string $message): void
{
    http_response_code($status);
    echo json_encode(
        ['success' => $success, 'message' => $message],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function clean_text(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? '');
    return function_exists('mb_substr')
        ? mb_substr($value, 0, $maxLength, 'UTF-8')
        : substr($value, 0, $maxLength);
}

function send_lead_to_google_sheets(array $lead): void
{
    if (GOOGLE_SHEETS_WEBHOOK_URL === '') {
        throw new RuntimeException('URL do Google Apps Script ainda não configurada.');
    }
    if (GOOGLE_SHEETS_WEBHOOK_SECRET === '') {
        throw new RuntimeException('Chave do Google Apps Script ainda não configurada.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('A extensão cURL é necessária.');
    }

    $webhookHost = parse_url(GOOGLE_SHEETS_WEBHOOK_URL, PHP_URL_HOST);
    $webhookPath = parse_url(GOOGLE_SHEETS_WEBHOOK_URL, PHP_URL_PATH);
    if ($webhookHost !== 'script.google.com' || !is_string($webhookPath) || substr($webhookPath, -5) !== '/exec') {
        throw new RuntimeException('A URL do Google Apps Script é inválida.');
    }

    $payload = json_encode(
        array_merge(['secret' => GOOGLE_SHEETS_WEBHOOK_SECRET], $lead),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($payload === false) {
        throw new RuntimeException('Não foi possível preparar o lead para o Apps Script.');
    }

    $curl = curl_init(GOOGLE_SHEETS_WEBHOOK_URL);
    if ($curl === false) {
        throw new RuntimeException('Não foi possível iniciar o envio ao Apps Script.');
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json; charset=UTF-8',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => $payload,
    ]);

    $response = curl_exec($curl);
    $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if (!is_string($response) || $httpStatus < 200 || $httpStatus >= 300) {
        throw new RuntimeException('O Apps Script recusou a inclusão do lead.');
    }

    $result = json_decode($response, true);
    if (!is_array($result) || ($result['success'] ?? false) !== true) {
        throw new RuntimeException('O Apps Script não confirmou a inclusão do lead.');
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    finish(405, false, 'Método não permitido.');
}

if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 32768) {
    finish(413, false, 'Formulário muito grande.');
}

// Honeypot: responde normalmente para não ensinar o bloqueio aos robôs.
if (trim((string) ($_POST['_honey'] ?? '')) !== '') {
    finish(200, true, 'Cadastro recebido.');
}

$name = clean_text((string) ($_POST['name'] ?? ''), 120);
$email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$company = clean_text((string) ($_POST['empresa'] ?? ''), 160);
$role = clean_text((string) ($_POST['cargo'] ?? ''), 80);
$whatsapp = clean_text((string) ($_POST['whatsapp'] ?? ''), 40);
$whatsappDigits = preg_replace('/\D+/', '', $whatsapp) ?? '';
$howFound = clean_text((string) ($_POST['como_conheceu'] ?? ''), 500);
$material = clean_text((string) ($_POST['material'] ?? ''), 160);
$pageUrl = filter_var(trim((string) ($_POST['page_url'] ?? '')), FILTER_VALIDATE_URL);
$consent = isset($_POST['consent']);

$allowedRoles = [
    'CEO',
    'Fundador(a)',
    'Sócio(a)',
    'Diretor(a)',
    'Gestor(a)',
    'Coordenador(a)',
    'Analista',
    'Especialista ou Consultor(a)',
    'Empreendedor(a) ou Autônomo(a)',
    'Estudante',
    'Outro',
];

if ($name === '' || $email === false || $company === '' || !in_array($role, $allowedRoles, true) || strlen($whatsappDigits) < 10 || strlen($whatsappDigits) > 13 || $material === '' || !$consent) {
    finish(422, false, 'Confira os campos obrigatórios e tente novamente.');
}

$submittedAt = gmdate('Y-m-d H:i:s') . ' UTC';
$leadIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$leadIpHash = $leadIp !== '' ? hash('sha256', $leadIp) : '';
$sourceUrl = $pageUrl !== false ? $pageUrl : 'Não informada';

// Backup local: nenhum lead é perdido caso o serviço de e-mail do servidor oscile.
$storageDir = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
if (!is_dir($storageDir) && !mkdir($storageDir, 0750, true) && !is_dir($storageDir)) {
    error_log('Hack The Growth: não foi possível criar a pasta de leads.');
    finish(500, false, 'Não foi possível registrar o cadastro. Tente novamente.');
}

$csvHeaders = ['data_utc', 'nome', 'email', 'empresa', 'cargo', 'whatsapp', 'como_conheceu', 'material', 'pagina', 'ip_hash'];
$csvPath = $storageDir . DIRECTORY_SEPARATOR . 'leads.csv';

// Preserva o CSV antigo caso a hospedagem já tenha recebido leads antes dos novos campos.
if (is_file($csvPath) && filesize($csvPath) > 0) {
    $existingCsv = fopen($csvPath, 'rb');
    $existingHeaders = $existingCsv !== false ? fgetcsv($existingCsv) : false;
    if ($existingCsv !== false) {
        fclose($existingCsv);
    }
    if ($existingHeaders !== $csvHeaders) {
        $legacyPath = $storageDir . DIRECTORY_SEPARATOR . 'leads-anteriores-' . gmdate('Ymd-His') . '.csv';
        if (!@rename($csvPath, $legacyPath)) {
            $csvPath = $storageDir . DIRECTORY_SEPARATOR . 'leads-atualizados.csv';
        }
    }
}

$isNewFile = !is_file($csvPath) || filesize($csvPath) === 0;
$csv = fopen($csvPath, 'ab');
if ($csv === false) {
    error_log('Hack The Growth: não foi possível abrir o arquivo de leads.');
    finish(500, false, 'Não foi possível registrar o cadastro. Tente novamente.');
}

if (!flock($csv, LOCK_EX)) {
    fclose($csv);
    finish(500, false, 'Não foi possível registrar o cadastro. Tente novamente.');
}

if ($isNewFile) {
    fputcsv($csv, $csvHeaders);
}
fputcsv($csv, [$submittedAt, $name, $email, $company, $role, $whatsapp, $howFound, $material, $sourceUrl, $leadIpHash]);
fflush($csv);
flock($csv, LOCK_UN);
fclose($csv);
@chmod($csvPath, 0640);

// A planilha é uma cópia operacional. CSV e e-mail continuam funcionando
// mesmo que o Apps Script esteja temporariamente indisponível.
try {
    send_lead_to_google_sheets([
        'data_utc' => $submittedAt,
        'nome' => $name,
        'email' => (string) $email,
        'empresa' => $company,
        'cargo' => $role,
        'whatsapp' => $whatsapp,
        'como_conheceu' => $howFound,
        'material' => $material,
        'pagina' => $sourceUrl,
        'consentimento' => 'Sim',
        'status' => 'Novo',
        'responsavel' => '',
        'ultimo_contato' => '',
    ]);
} catch (Throwable $error) {
    error_log('Hack The Growth: falha ao salvar lead no Google Sheets — ' . $error->getMessage());
}

$subject = 'Novo lead — ' . $material;
$encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
$body = implode("\r\n", [
    'Novo lead recebido pela landing page Hack The Growth',
    '',
    'Nome: ' . $name,
    'E-mail: ' . $email,
    'Empresa: ' . $company,
    'Cargo: ' . $role,
    'WhatsApp: ' . $whatsapp,
    'Como conheceu a 3ADS: ' . ($howFound !== '' ? $howFound : 'Não informado'),
    'Material: ' . $material,
    'Consentimento: Sim',
    'Página: ' . $sourceUrl,
    'Data: ' . $submittedAt,
]);

$headers = implode("\r\n", [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'From: ' . LEAD_FROM_NAME . ' <' . LEAD_FROM_EMAIL . '>',
    'Reply-To: ' . $name . ' <' . $email . '>',
    'X-Mailer: PHP/' . PHP_VERSION,
]);

$mailAccepted = @mail(
    LEAD_DESTINATION_EMAIL,
    $encodedSubject,
    $body,
    $headers,
    '-f' . LEAD_FROM_EMAIL
);

if (!$mailAccepted) {
    // Alguns provedores bloqueiam o envelope sender (-f); tenta novamente sem ele.
    $mailAccepted = @mail(LEAD_DESTINATION_EMAIL, $encodedSubject, $body, $headers);
}

if (!$mailAccepted) {
    error_log('Hack The Growth: lead salvo, mas o servidor recusou o envio por e-mail.');
    finish(503, false, 'Seu cadastro foi salvo, mas o servidor de e-mail não confirmou o envio. Tente novamente em instantes.');
}

finish(200, true, 'Cadastro recebido e e-mail encaminhado.');
